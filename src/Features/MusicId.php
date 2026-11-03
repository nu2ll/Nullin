<?php

final class MusicId
{
    private const DAILY  = 10;
    private const WAIT   = 20;
    private const TMP    = '/tmp/nullini-music';
    private const SLOTS  = 2;
    private const MAX_IN = 20 * 1024 * 1024;

    public static function media(array $msg): ?array
    {
        foreach ([$msg, $msg['reply_to_message'] ?? []] as $m) {
            if (!is_array($m)) {
                continue;
            }
            foreach (['voice', 'audio', 'video', 'video_note', 'animation'] as $k) {
                if (is_array($m[$k] ?? null) && !empty($m[$k]['file_id'])) {
                    return ['id' => (string) $m[$k]['file_id'], 'size' => (int) ($m[$k]['file_size'] ?? 0), 'kind' => $k];
                }
            }
            $d = $m['document'] ?? null;
            if (is_array($d) && !empty($d['file_id']) && preg_match('#^(audio|video)/#', (string) ($d['mime_type'] ?? '')) === 1) {
                return ['id' => (string) $d['file_id'], 'size' => (int) ($d['file_size'] ?? 0), 'kind' => 'document'];
            }
        }
        return null;
    }

    public static function start(array $msg): void
    {
        $tg  = App::tg();
        $uid = (int) ($msg['from']['id'] ?? 0);
        $m   = self::media($msg);
        if ($m === null) {
            $tg->reply($msg, "🎧 <b>موزیک‌یاب نالینی</b>\n\nیه ویس، آهنگ یا ویدیوی صدادار بفرست و زیرش /music بزن؛\nیا روی همون پیام ریپلای کن و /music رو بنویس 😉");
            return;
        }
        if ($m['size'] > self::MAX_IN) {
            $tg->reply($msg, "😕 فایل بزرگه؛ فقط تا ۲۰ مگابایت می‌تونم بگیرم.\nیه تیکه‌ی کوتاه‌ترش رو بفرست.");
            return;
        }
        $admin = App::isAdmin($uid);
        if (!$admin && self::usedToday($uid) >= self::DAILY) {
            $tg->reply($msg, "فردا دوباره بیا 🙂\n✋ امروز سهمیه‌ی موزیک‌یابت تموم شده؛");
            return;
        }
        if (!$admin && Store::update('rl', 'music_' . $uid, static fn($at) => (is_int($at) && time() - $at < self::WAIT) ? null : time()) === null) {
            $tg->reply($msg, "یه‌کم دیگه بیا 😅\n⏳ هر ۲۰ ثانیه فقط یه موزیک‌یاب!");
            return;
        }
        $r     = $tg->reply($msg, '🎧 دارم گوش می‌دم...');
        $token = bin2hex(random_bytes(6));
        Store::set('music', $token, [
            'chat'    => $msg['chat']['id'],
            'uid'     => $uid,
            'reply'   => (int) ($msg['message_id'] ?? 0),
            'card'    => (int) ($r['result']['message_id'] ?? 0),
            'file_id' => $m['id'],
        ]);
        if (!self::spawn($token)) {
            self::work($token);
        }
    }

    private static function spawn(string $token): bool
    {
        if (!function_exists('exec') || in_array('exec', array_map('trim', explode(',', (string) ini_get('disable_functions'))), true)) {
            return false;
        }
        $cmd = 'timeout -k 10 400 ' . escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(NULLINI_ROOT . '/bot.php')
            . ' --music ' . escapeshellarg($token) . ' ' . App::botIndex() . ' > /dev/null 2>&1 &';
        exec($cmd, $o, $code);
        return $code === 0;
    }

    public static function work(string $token): void
    {
        $job = Store::get('music', $token);
        if (!is_array($job)) {
            return;
        }
        $tg  = App::tg();
        $dir = self::TMP . '/' . $token;
        $slot = null;
        try {
            $slot = self::acquireSlot();
            if ($slot === null) {
                self::card($job, "😮‍💨 سرور الان خیلی شلوغه؛\nچند دقیقه دیگه دوباره امتحان کن.");
                return;
            }
            self::rmrf($dir);
            @mkdir($dir, 0775, true);

            $bytes = $tg->download((string) $job['file_id']);
            if ($bytes === null || $bytes === '') {
                self::card($job, "😕 نتونستم فایل رو بگیرم؛\nمطمئن شو بیشتر از ۲۰ مگابایت نیست.");
                return;
            }
            file_put_contents($dir . '/in.bin', $bytes);
            unset($bytes);

            $song = self::identify($dir);
            if ($song === null) {
                self::card($job, "🔇 صدایی توی این فایل پیدا نکردم.");
                return;
            }
            if (empty($song['ok'])) {
                self::card($job, "😕 نتونستم آهنگ رو بشناسم؛\nیه قسمت واضح‌تر و بدون حرف و سروصدا بفرست.");
                return;
            }

            self::card($job, '✅ ' . h($song['title']) . ' — ' . h($song['artist']) . "\n⏳ دارم موزیک رو می‌گیرم...");
            self::sendInfo($job, $song);

            $file = Downloader::musicFile((string) $song['title'], (string) $song['artist'], (string) $song['cover'], $dir);
            if ($file === null) {
                self::card($job, '✅ ' . h($song['title']) . ' — ' . h($song['artist']) . "\n😕 اسمش رو پیدا کردم ولی نتونستم خود موزیک رو بگیرم.");
                self::charge((int) $job['uid']);
                return;
            }
            $p = [
                'chat_id'          => $job['chat'],
                'audio'            => new CURLFile($file['file'], 'audio/mpeg', 'track.mp3'),
                'title'            => mb_substr((string) $song['title'], 0, 64),
                'performer'        => mb_substr((string) $song['artist'], 0, 64) ?: null,
                'reply_parameters' => $job['reply'] ? ['message_id' => (int) $job['reply'], 'allow_sending_without_reply' => true] : null,
            ];
            if (!empty($file['thumb']) && is_file($file['thumb'])) {
                $p['thumbnail'] = new CURLFile($file['thumb'], 'image/jpeg', 'cover.jpg');
            }
            $r = $tg->call('sendAudio', $p, 300);
            if (empty($r['ok'])) {
                logWarn('music', 'sendAudio failed: ' . (string) ($r['description'] ?? ''));
                self::card($job, '✅ ' . h($song['title']) . ' — ' . h($song['artist']) . "\n😕 فرستادن موزیک نشد، یه‌کم دیگه دوباره امتحان کن.");
                return;
            }
            self::charge((int) $job['uid']);
            Users::hit('music');
            if (!empty($job['card'])) {
                $tg->call('deleteMessage', ['chat_id' => $job['chat'], 'message_id' => (int) $job['card']]);
            }
        } catch (\Throwable $e) {
            logError('music', $e->getMessage() . ' @ ' . basename($e->getFile()) . ':' . $e->getLine());
            self::card($job, '😕 یه مشکلی پیش اومد، دوباره امتحان کن.');
        } finally {
            if (is_resource($slot)) {
                flock($slot, LOCK_UN);
                fclose($slot);
            }
            self::rmrf($dir);
            Store::delete('music', $token);
        }
    }

    // null = فایل صدا نداره؛ ['ok' => false] = نشناخت؛ وگرنه مشخصات آهنگ
    private static function identify(string $dir): ?array
    {
        $in  = $dir . '/in.bin';
        $dur = (float) trim((string) Downloader::exec(['ffprobe', '-v', 'error', '-show_entries', 'format=duration', '-of', 'csv=p=0', $in], 20)['out']);
        $starts = [0];
        if ($dur > 75) {
            $starts[] = (int) ($dur / 3);
        }
        $heard = false;
        foreach ($starts as $i => $st) {
            $wav = $dir . "/s{$i}.wav";
            Downloader::exec(['ffmpeg', '-y', '-v', 'error', '-ss', (string) $st, '-i', $in, '-vn', '-ac', '1', '-ar', '44100', '-t', '20', $wav], 60);
            if (!is_file($wav) || filesize($wav) < 20000) {
                continue;
            }
            $heard = true;
            $res = Downloader::exec(['python3', NULLINI_ROOT . '/bin/musicid.py', $wav], 60);
            $line = trim((string) strrchr("\n" . trim($res['out']), "\n"));
            $j    = json_decode($line, true);
            if (is_array($j) && !empty($j['ok']) && ($j['title'] ?? '') !== '') {
                return $j + ['title' => '', 'artist' => '', 'album' => '', 'label' => '', 'year' => '', 'genre' => '', 'cover' => ''];
            }
            if (is_array($j) && isset($j['error'])) {
                logWarn('music', 'identify: ' . $j['error']);
            }
        }
        return $heard ? ['ok' => false] : null;
    }

    private static function sendInfo(array $job, array $s): void
    {
        $tg   = App::tg();
        $text = '🎵 <b>' . h(mb_substr((string) $s['title'], 0, 200)) . '</b>';
        if ($s['artist'] !== '') {
            $text .= "\n👤 " . h(mb_substr((string) $s['artist'], 0, 100));
        }
        if ($s['album'] !== '') {
            $text .= "\n💿 آلبوم: " . h(mb_substr((string) $s['album'], 0, 100));
        }
        if ($s['year'] !== '') {
            $text .= "\n📅 سال: " . fa(h((string) $s['year']));
        }
        if ($s['genre'] !== '') {
            $text .= "\n🎼 ژانر: " . h((string) $s['genre']);
        }
        if ($s['label'] !== '') {
            $text .= "\n🏷 لیبل: " . h(mb_substr((string) $s['label'], 0, 100));
        }
        $img = '';
        if (preg_match('#^https://#', (string) $s['cover']) === 1) {
            $r   = Http::request('GET', (string) $s['cover'], ['timeout' => 15, 'max_bytes' => 8 * 1048576]);
            $img = (string) ($r['body'] ?? '');
        }
        $reply = $job['reply'] ? (int) $job['reply'] : null;
        if ($img !== '' && @getimagesizefromstring($img) !== false && $tg->sendPhotoBytes($job['chat'], $img, $text, $reply)) {
            return;
        }
        $tg->send($job['chat'], $text, $reply ? ['reply_parameters' => ['message_id' => $reply, 'allow_sending_without_reply' => true]] : []);
    }

    private static function card(array $job, string $text): void
    {
        $tg = App::tg();
        if (!empty($job['card'])) {
            $tg->editMessage($job['chat'], (int) $job['card'], $text);
        } else {
            $tg->send($job['chat'], $text);
        }
    }

    private static function usedToday(int $uid): int
    {
        $q = Store::get('mquota', (string) $uid);
        return is_array($q) && (int) ($q['d'] ?? 0) === Store::dayStart() ? (int) ($q['n'] ?? 0) : 0;
    }

    private static function charge(int $uid): void
    {
        Store::update('mquota', (string) $uid, static function ($q) {
            $d = Store::dayStart();
            $n = is_array($q) && (int) ($q['d'] ?? 0) === $d ? (int) ($q['n'] ?? 0) : 0;
            return ['d' => $d, 'n' => $n + 1];
        });
    }

    private static function acquireSlot()
    {
        @mkdir(self::TMP, 0775, true);
        for ($wait = 0; $wait < 90; $wait += 2) {
            for ($i = 0; $i < self::SLOTS; $i++) {
                $fh = @fopen(self::TMP . "/slot{$i}.lock", 'c');
                if ($fh && flock($fh, LOCK_EX | LOCK_NB)) {
                    return $fh;
                }
                if ($fh) {
                    fclose($fh);
                }
            }
            sleep(2);
        }
        return null;
    }

    private static function rmrf(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) ?: [] as $f) {
            if ($f === '.' || $f === '..') {
                continue;
            }
            $p = $dir . '/' . $f;
            is_dir($p) && !is_link($p) ? self::rmrf($p) : @unlink($p);
        }
        @rmdir($dir);
    }
}
