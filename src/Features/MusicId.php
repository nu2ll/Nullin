<?php

final class MusicId
{
    private const DAILY  = PHP_INT_MAX;
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
        $token = bin2hex(random_bytes(6));
        Store::set('music', $token, [
            'chat'    => $msg['chat']['id'],
            'uid'     => $uid,
            'reply'   => (int) ($msg['message_id'] ?? 0),
            'card'    => 0,
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
        if (!empty($job['imid']) || !empty($job['song'])) {
            self::workSong($job, $token);
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

            $yt = Downloader::musicMatch((string) $song['title'], (string) $song['artist']);
            self::sendInfo($job, $song, $yt, $token);
            Store::set('mures', $token, ['uid' => (int) $job['uid'], 'chat' => $job['chat'], 'reply' => (int) $job['reply'], 'song' => ['title' => (string) $song['title'], 'artist' => (string) $song['artist']]]);

            $file = $yt === null ? null : Downloader::musicFile((string) $song['title'], (string) $song['artist'], (string) $song['cover'], $dir, $yt);
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
                'reply_markup'     => self::audioKeyboard($song),
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

    private static function query(array $s): string
    {
        return trim(($s['artist'] !== '' ? $s['artist'] . ' - ' : '') . $s['title']);
    }

    private static function lead(array $s): string
    {
        $a = trim((string) (preg_split('/\s*(?:,|&|;|\sfeat\.?\s|\sft\.?\s)\s*/iu', (string) $s['artist']) ?: [''])[0]);
        return $a !== '' ? $a : (string) $s['title'];
    }

    private static function infoKeyboard(array $s, string $token): array
    {
        $q  = self::query($s);
        $by = self::lead($s);
        return ikb([
            [
                urlBtn('گوگل', Search::googleUrl($q), null),
                urlBtn('یوتیوب موزیک', 'https://music.youtube.com/search?q=' . rawurlencode($q), null),
                urlBtn('اسپاتیفای', 'https://open.spotify.com/search/' . rawurlencode($q), null),
            ],
            [['text' => '🔍 جستجوی آهنگ‌های خواننده', 'switch_inline_query_current_chat' => 'mu ' . mb_substr($by, 0, 100), 'style' => 'primary']],
            [btn('❌ آهنگ اشتباهه ❌', 'mu:x:' . $token, 'danger')],
        ]);
    }

    private static function audioKeyboard(array $s, bool $lyrics = true): array
    {
        $row = [];
        if ($lyrics) {
            $tk = bin2hex(random_bytes(6));
            Store::set('mulyr', $tk, ['title' => (string) $s['title'], 'artist' => (string) $s['artist'], 'dur' => (int) ($s['dur'] ?? 0)]);
            $row[] = btn('📝 متن ترانه', 'mu:l:' . $tk, 'primary');
        }
        $row[] = ['text' => '🔍', 'switch_inline_query_current_chat' => 'mu ' . mb_substr(self::lead($s), 0, 100)];
        return ikb([$row]);
    }

    private static function clip(string $t, int $max = 950): string
    {
        if (mb_strlen($t) <= $max) {
            return $t;
        }
        $cut = mb_substr($t, 0, $max);
        $pos = mb_strrpos($cut, "\n");
        if ($pos !== false && $pos > $max / 2) {
            $cut = mb_substr($cut, 0, $pos);
        }
        return rtrim($cut) . "\n…";
    }

    private static function lyrics(array $cq, string $tk): void
    {
        $tg = App::tg();
        $s  = Store::get('mulyr', $tk);
        if (!is_array($s)) {
            $tg->answer($cq, '⌛️ منقضی شده');
            return;
        }
        $imid = (string) ($cq['inline_message_id'] ?? '');
        $m    = $cq['message'] ?? null;
        if ($imid === '' && !is_array($m)) {
            $tg->answer($cq);
            return;
        }
        $text = Lyrics::find(self::lead($s), (string) $s['title'], (int) ($s['dur'] ?? 0));
        if ($text === null) {
            $tg->answer($cq, '😕 متن این ترانه رو پیدا نکردم');
            return;
        }
        $tg->answer($cq);
        $params = [
            'caption'      => '<blockquote expandable>' . h(self::clip($text)) . '</blockquote>',
            'parse_mode'   => 'HTML',
            'reply_markup' => self::audioKeyboard($s, false),
        ];
        $params += $imid !== '' ? ['inline_message_id' => $imid] : ['chat_id' => $m['chat']['id'], 'message_id' => (int) $m['message_id']];
        $tg->call('editMessageCaption', $params);
    }

    private static function infoCover(?string $yt, string $fallback): string
    {
        if (preg_match('#^https://#', $fallback) === 1) {
            $r = Http::request('GET', $fallback, ['timeout' => 15, 'max_bytes' => 8 * 1048576]);
            if ((string) $r['body'] !== '' && @getimagesizefromstring((string) $r['body']) !== false) {
                return (string) $r['body'];
            }
        }
        return '';
    }

    private static function sendInfo(array $job, array $s, ?string $yt, string $token): ?int
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
        $kb    = self::infoKeyboard($s, $token);
        $reply = $job['reply'] ? (int) $job['reply'] : null;
        $img   = self::infoCover($yt, (string) $s['cover']);
        if ($img !== '') {
            $id = $tg->sendPhotoCard($job['chat'], $img, $text, $reply, $kb);
            if ($id !== null) {
                return $id;
            }
        }
        $r = $tg->send($job['chat'], $text, ['reply_markup' => $kb] + ($reply ? ['reply_parameters' => ['message_id' => $reply, 'allow_sending_without_reply' => true]] : []));
        return !empty($r['ok']) ? (int) ($r['result']['message_id'] ?? 0) : null;
    }

    public static function inline(string $qt, int $i, string $imid, array $from): void
    {
        $tg  = App::tg();
        $uid = (int) ($from['id'] ?? 0);
        $set = Store::get('imusic', $qt);
        $t   = is_array($set) ? ($set['list'][$i] ?? null) : null;
        if (!is_array($t)) {
            $tg->editInline($imid, '⌛️ منقضی شده؛ دوباره جستجو کن.');
            return;
        }
        if (!App::isAdmin($uid) && self::usedToday($uid) >= self::DAILY) {
            $tg->editInline($imid, "فردا دوباره بیا 🙂\n✋ امروز سهمیه‌ی موزیکت تموم شده؛");
            return;
        }
        if (Downloader::storeChat($uid) === null) {
            $tg->editInline($imid, "🤖 برای گرفتن موزیک این‌جا،\nاول یه بار ربات رو تو پیوی استارت کن.", ikb([[urlBtn('🤖 استارت ربات', 'https://t.me/' . App::botUsername() . '?start=dl', 'success')]]));
            return;
        }
        $token = bin2hex(random_bytes(6));
        Store::set('music', $token, ['chat' => 0, 'uid' => $uid, 'reply' => 0, 'card' => 0, 'file_id' => '', 'imid' => $imid, 'song' => $t]);
        if (!self::spawn($token)) {
            self::work($token);
        }
    }

    private static function workSong(array $job, string $token): void
    {
        $tg   = App::tg();
        $t    = (array) $job['song'];
        $imid = (string) ($job['imid'] ?? '');
        $uid  = (int) $job['uid'];
        $song = ['title' => (string) $t['title'], 'artist' => (string) $t['artist'], 'cover' => (string) ($t['cover'] ?? ''), 'album' => (string) ($t['album'] ?? '')];
        $dur  = (int) ($t['dur'] ?? 0);
        $song['dur'] = $dur;
        $head = '🎵 <b>' . h(mb_substr($song['title'], 0, 200)) . '</b>' . ($song['artist'] !== '' ? "\n👤 " . h(mb_substr($song['artist'], 0, 100)) : '');
        $dir  = self::TMP . '/' . $token;
        $slot = null;
        $fail = static function (string $text) use ($tg, $imid, $job, $head): void {
            if ($imid !== '') {
                $tg->editInline($imid, $head . "\n\n" . $text);
            } else {
                self::card($job, $head . "\n\n" . $text);
            }
        };
        try {
            $slot = self::acquireSlot();
            if ($slot === null) {
                $fail('😮‍💨 سرور الان خیلی شلوغه؛ چند دقیقه دیگه دوباره امتحان کن.');
                return;
            }
            self::rmrf($dir);
            @mkdir($dir, 0775, true);
            $yt   = !empty($t['yt']) ? 'https://www.youtube.com/watch?v=' . $t['yt'] : Downloader::musicMatch($song['title'], $song['artist'], $dur);
            $file = $yt === null ? null : Downloader::musicFile($song['title'], $song['artist'], $song['cover'], $dir, $yt);
            if ($file === null) {
                $fail('😕 نتونستم موزیک رو بگیرم؛ یه بار دیگه امتحان کن.');
                return;
            }
            $target = $imid !== '' ? Downloader::storeChat($uid) : $job['chat'];
            if ($target === null) {
                $fail('🤖 اول یه بار ربات رو تو پیوی استارت کن.');
                return;
            }
            $p = [
                'chat_id'   => $target,
                'audio'     => new CURLFile($file['file'], 'audio/mpeg', 'track.mp3'),
                'title'     => mb_substr($song['title'], 0, 64),
                'performer' => mb_substr($song['artist'], 0, 64) ?: null,
                'duration'  => $dur > 0 ? $dur : null,
            ];
            if ($imid === '') {
                $p['reply_markup']     = self::audioKeyboard($song);
                $p['reply_parameters'] = !empty($job['reply']) ? ['message_id' => (int) $job['reply'], 'allow_sending_without_reply' => true] : null;
            }
            if (!empty($file['thumb']) && is_file($file['thumb'])) {
                $p['thumbnail'] = new CURLFile($file['thumb'], 'image/jpeg', 'cover.jpg');
            }
            $r   = $tg->call('sendAudio', $p, 300);
            $fid = $r['result']['audio']['file_id'] ?? null;
            if (!$fid) {
                logWarn('music', 'sendAudio failed: ' . (string) ($r['description'] ?? ''));
                $fail('😕 فرستادن موزیک نشد؛ یه بار دیگه امتحان کن.');
                return;
            }
            if ($imid !== '') {
                $vid  = preg_match('/v=([\w-]{11})/', (string) $yt, $vm) === 1 ? $vm[1] : '';
                $cap  = '@' . App::botUsername() . ($vid !== '' ? ' | <a href="https://song.link/y/' . $vid . '">info</a>' : '');
                $e = $tg->call('editMessageMedia', [
                    'inline_message_id' => $imid,
                    'media'             => ['type' => 'audio', 'media' => $fid, 'caption' => $cap, 'parse_mode' => 'HTML'],
                    'reply_markup'      => self::audioKeyboard($song),
                ], 30);
                if (env('DL_STORE_CHAT', '') === '' && !empty($r['result']['message_id'])) {
                    $tg->call('deleteMessage', ['chat_id' => $target, 'message_id' => (int) $r['result']['message_id']], 8);
                }
                if (empty($e['ok'])) {
                    logWarn('music', 'inline editMessageMedia failed: ' . (string) ($e['description'] ?? ''));
                    $fail('😕 فرستادن موزیک نشد؛ یه بار دیگه امتحان کن.');
                    return;
                }
            } elseif (!empty($job['pick'])) {
                $tg->call('deleteMessage', ['chat_id' => $job['chat'], 'message_id' => (int) $job['pick']], 8);
            }
            self::charge($uid);
            Users::hit($imid !== '' ? 'music:inline' : 'music');
        } catch (\Throwable $e) {
            logError('music', $e->getMessage() . ' @ ' . basename($e->getFile()) . ':' . $e->getLine());
            $fail('😕 یه مشکلی پیش اومد، دوباره امتحان کن.');
        } finally {
            if (is_resource($slot)) {
                flock($slot, LOCK_UN);
                fclose($slot);
            }
            self::rmrf($dir);
            Store::delete('music', $token);
        }
    }

    public static function onCallback(array $cq, string $data): void
    {
        $tg  = App::tg();
        $m   = $cq['message'] ?? null;
        $uid = (int) ($cq['from']['id'] ?? 0);
        if (preg_match('/^mu:l:([a-f0-9]{12})$/', $data, $x) === 1) {
            self::lyrics($cq, $x[1]);
            return;
        }
        if (!is_array($m) || !isset($m['message_id'], $m['chat']['id'])) {
            $tg->answer($cq);
            return;
        }
        if (preg_match('/^mu:x:([a-f0-9]{12})$/', $data, $x) === 1) {
            self::alternatives($cq, $m, $uid, $x[1]);
            return;
        }
        if (preg_match('/^mu:p:([a-f0-9]{12}):(\d{1,2})$/', $data, $x) === 1) {
            self::pick($cq, $m, $uid, $x[1], (int) $x[2]);
            return;
        }
        $tg->answer($cq);
    }

    private static function alternatives(array $cq, array $m, int $uid, string $token): void
    {
        $tg  = App::tg();
        $res = Store::get('mures', $token);
        if (!is_array($res)) {
            $tg->answer($cq, '⌛️ منقضی شده');
            return;
        }
        if ($uid !== (int) $res['uid'] && !App::isAdmin($uid)) {
            $tg->answer($cq, '🙅 این دکمه مال کسیه که آهنگ رو فرستاده');
            return;
        }
        if (Store::update('rl', 'mualt_' . $token, static fn($at) => (is_int($at) && time() - $at < 10) ? null : time()) === null) {
            $tg->answer($cq, '⏳ نتایج دیگه همین الان فرستاده شد');
            return;
        }
        $s    = (array) $res['song'];
        $lt   = mb_strtolower((string) $s['title']);
        $la   = mb_strtolower(self::lead($s));
        $list = array_values(array_filter(
            MusicSearch::find(trim($s['artist'] . ' ' . $s['title'])),
            static fn($t) => !(mb_strtolower($t['title']) === $lt && str_contains(mb_strtolower($t['artist']), $la))
        ));
        $list = array_slice($list, 0, 100);
        if (!$list) {
            $tg->answer($cq, '😕 آهنگ دیگه‌ای پیدا نکردم');
            return;
        }
        $tg->answer($cq);
        $qt = bin2hex(random_bytes(6));
        Store::set('imusic', $qt, ['list' => $list, 'uid' => (int) $res['uid'], 'chat' => $res['chat'], 'reply' => (int) ($res['reply'] ?? 0)]);
        $rows = [];
        foreach ($list as $i => $t) {
            $d      = (int) $t['dur'];
            $label  = ($d > 0 ? intdiv($d, 60) . ':' . sprintf('%02d', $d % 60) . ' • ' : '') . $t['title'] . ($t['artist'] !== '' ? ' — ' . $t['artist'] : '');
            $rows[] = [btn(mb_substr($label, 0, 60), "mu:p:{$qt}:{$i}", null)];
        }
        $tg->send($m['chat']['id'], "🎧 همه‌ی چیزهایی که پیدا کردم؛\nآهنگ درست رو انتخاب کن 👇", [
            'reply_markup'     => ikb($rows),
            'reply_parameters' => ['message_id' => (int) $m['message_id'], 'allow_sending_without_reply' => true],
        ]);
    }

    private static function pick(array $cq, array $m, int $uid, string $qt, int $i): void
    {
        $tg  = App::tg();
        $set = Store::get('imusic', $qt);
        $t   = is_array($set) ? ($set['list'][$i] ?? null) : null;
        if (!is_array($t)) {
            $tg->answer($cq, '⌛️ منقضی شده');
            return;
        }
        $admin = App::isAdmin($uid);
        if ($uid !== (int) ($set['uid'] ?? 0) && !$admin) {
            $tg->answer($cq, '🙅 این لیست مال کسیه که آهنگ رو فرستاده');
            return;
        }
        if (!$admin && self::usedToday($uid) >= self::DAILY) {
            $tg->answer($cq, '✋ امروز سهمیه‌ی موزیکت تموم شده؛ فردا دوباره بیا');
            return;
        }
        if (!$admin && Store::update('rl', 'mupick_' . $uid, static fn($at) => (is_int($at) && time() - $at < 10) ? null : time()) === null) {
            $tg->answer($cq, '⏳ یه موزیک دیگه‌ت در جریانه؛ چند لحظه صبر کن');
            return;
        }
        $tg->answer($cq, '⏳ دارم موزیک رو می‌گیرم...');
        $token = bin2hex(random_bytes(6));
        Store::set('music', $token, ['chat' => $set['chat'], 'uid' => $uid, 'reply' => (int) ($set['reply'] ?? 0), 'card' => 0, 'file_id' => '', 'song' => $t, 'pick' => (int) $m['message_id']]);
        if (!self::spawn($token)) {
            self::work($token);
        }
    }

    private static function card(array $job, string $text): void
    {
        $tg = App::tg();
        if (!empty($job['card'])) {
            $tg->editMessage($job['chat'], (int) $job['card'], $text);
        } else {
            $tg->send($job['chat'], $text, !empty($job['reply']) ? ['reply_parameters' => ['message_id' => (int) $job['reply'], 'allow_sending_without_reply' => true]] : []);
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
