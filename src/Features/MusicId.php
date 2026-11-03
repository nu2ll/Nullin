<?php

final class MusicId
{
    private const WAIT   = 20;
    private const TMP    = '/tmp/nullini-music';
    private const PAGE = 10;

    public static function start(array $msg, string $query = ''): void
    {
        if (trim($query) !== '') {
            self::searchText($msg, trim($query));
            return;
        }
        App::tg()->reply($msg, "🎧 <b>موزیک‌یاب نالینی</b>\n\nاسم آهنگ یا خواننده رو بنویس:\n<code>/music geek</code>");
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
        self::workSong($job, $token);
    }

    private static function lead(array $s): string
    {
        $a = trim((string) (preg_split('/\s*(?:,|&|;|\sfeat\.?\s|\sft\.?\s)\s*/iu', (string) $s['artist']) ?: [''])[0]);
        return $a !== '' ? $a : (string) $s['title'];
    }

    private static function audioKeyboard(array $s, bool $lyrics = true): array
    {
        $row = [];
        if ($lyrics) {
            $tk = bin2hex(random_bytes(6));
            Store::set('mulyr', $tk, ['title' => (string) $s['title'], 'artist' => (string) $s['artist'], 'dur' => (int) ($s['dur'] ?? 0)]);
            $row[] = btn('📝 متن ترانه', 'mu:l:' . $tk, 'primary');
        }
        $row[] = self::searchBtn();
        return ikb([$row]);
    }

    private static function searchBtn(): array
    {
        return ['text' => '🔍', 'switch_inline_query_current_chat' => 'mu '];
    }

    // expired data: no message to the user, just drop the callback buttons (URL / inline-search buttons stay)
    private static function dropButtons(array $cq): void
    {
        $tg   = App::tg();
        $tg->answer($cq);
        $imid = (string) ($cq['inline_message_id'] ?? '');
        $m    = $cq['message'] ?? null;
        if ($imid !== '') {
            $rows   = [[self::searchBtn()]];
            $target = ['inline_message_id' => $imid];
        } elseif (is_array($m) && isset($m['message_id'], $m['chat']['id'])) {
            $rows = [];
            foreach ((array) ($m['reply_markup']['inline_keyboard'] ?? []) as $row) {
                $keep = array_values(array_filter((array) $row, static fn($b) => is_array($b) && !isset($b['callback_data'])));
                if ($keep) {
                    $rows[] = $keep;
                }
            }
            $target = ['chat_id' => $m['chat']['id'], 'message_id' => (int) $m['message_id']];
        } else {
            return;
        }
        $tg->call('editMessageReplyMarkup', ['reply_markup' => ikb($rows)] + $target);
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
        $s  = Store::getToday('mulyr', $tk);
        if (!is_array($s)) {
            self::dropButtons($cq);
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
            $tg->answer($cq, '🤷 متن این آهنگ رو پیدا نکردم 😕');
            $tg->call('editMessageReplyMarkup', ['reply_markup' => self::audioKeyboard($s, false)] + ($imid !== '' ? ['inline_message_id' => $imid] : ['chat_id' => $m['chat']['id'], 'message_id' => (int) $m['message_id']]));
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

    public static function inline(string $qt, int $i, string $imid, array $from): void
    {
        $tg  = App::tg();
        $uid = (int) ($from['id'] ?? 0);
        $set = Store::getToday('imusic', $qt);
        $t   = is_array($set) ? ($set['list'][$i] ?? null) : null;
        if (!is_array($t)) {
            $tg->editInline($imid, '🎧', ikb([[self::searchBtn()]]));
            return;
        }
        if (Downloader::storeChat($uid) === null) {
            return;
        }
        $token = bin2hex(random_bytes(6));
        Store::set('music', $token, ['chat' => 0, 'uid' => $uid, 'reply' => 0, 'card' => 0, 'imid' => $imid, 'song' => $t]);
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
        $fail = static function (string $text) use ($tg, $imid, $job, $head): void {
            if ($imid !== '') {
                $tg->editInline($imid, $head . "\n\n" . $text);
            } else {
                self::card($job, $head . "\n\n" . $text);
            }
        };
        try {
            self::rmrf($dir);
            @mkdir($dir, 0775, true);
            $yt   = !empty($t['yt']) ? 'https://www.youtube.com/watch?v=' . $t['yt'] : Downloader::musicMatch($song['title'], $song['artist'], $dur);
            $file = $yt === null ? null : Downloader::musicFile($song['title'], $song['artist'], $song['cover'], $dir, $yt);
            if ($file === null) {
                $fail('😕 نتونستم این آهنگ رو بگیرم؛' . "\n" . 'یه‌کم دیگه دوباره امتحان کن.');
                return;
            }
            $target = $imid !== '' ? Downloader::storeChat($uid) : $job['chat'];
            if ($target === null) {
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
                $fail('😕 نتونستم این آهنگ رو بگیرم؛' . "\n" . 'یه‌کم دیگه دوباره امتحان کن.');
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
                    $fail('😕 نتونستم این آهنگ رو بگیرم؛' . "\n" . 'یه‌کم دیگه دوباره امتحان کن.');
                    return;
                }
            } elseif (!empty($job['pick'])) {
                $tg->call('deleteMessage', ['chat_id' => $job['chat'], 'message_id' => (int) $job['pick']], 8);
            }
            Users::hit($imid !== '' ? 'music:inline' : 'music');
        } catch (\Throwable $e) {
            logError('music', $e->getMessage() . ' @ ' . basename($e->getFile()) . ':' . $e->getLine());
            $fail('😕 نتونستم این آهنگ رو بگیرم؛' . "\n" . 'یه‌کم دیگه دوباره امتحان کن.');
        } finally {
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
        if (preg_match('/^mu:m:([a-f0-9]{12}):(\d{1,3})$/', $data, $x) === 1) {
            self::more($cq, $m, $uid, $x[1], (int) $x[2]);
            return;
        }
        if (preg_match('/^mu:p:([a-f0-9]{12}):(\d{1,2})$/', $data, $x) === 1) {
            self::pick($cq, $m, $uid, $x[1], (int) $x[2]);
            return;
        }
        $tg->answer($cq);
    }

    private static function listPage($chat, int $reply, string $qt, array $list, int $off): void
    {
        $total = count($list);
        $songs = [];
        for ($i = $off; $i < $total && count($songs) < self::PAGE; $i++) {
            $t       = $list[$i];
            $d       = (int) $t['dur'];
            $label   = ($d > 0 ? intdiv($d, 60) . ':' . sprintf('%02d', $d % 60) . ' • ' : '') . $t['title'] . ($t['artist'] !== '' ? ' — ' . $t['artist'] : '');
            $songs[] = [btn(mb_substr($label, 0, 60), "mu:p:{$qt}:{$i}", null)];
        }
        if (!$songs) {
            return;
        }
        $rows = $songs;
        $next = $off + count($songs);
        if ($next < $total) {
            $rows[] = [btn('آهنگ‌های بیشتر ➕', "mu:m:{$qt}:{$next}", null)];
        }
        $rows[] = [self::searchBtn()];
        App::tg()->send($chat, '🔍', ['reply_markup' => ikb($rows)] + ($reply ? ['reply_parameters' => ['message_id' => $reply, 'allow_sending_without_reply' => true]] : []));
    }

    private static function more(array $cq, array $m, int $uid, string $qt, int $off): void
    {
        $tg  = App::tg();
        $set = Store::getToday('imusic', $qt);
        if (!is_array($set)) {
            self::dropButtons($cq);
            return;
        }
        $list = (array) ($set['list'] ?? []);
        if (($uid !== (int) ($set['uid'] ?? 0) && !App::isAdmin($uid)) || $off < 1 || $off >= count($list)) {
            $tg->answer($cq);
            return;
        }
        $tg->answer($cq);
        $rows = [];
        foreach ((array) ($m['reply_markup']['inline_keyboard'] ?? []) as $row) {
            $keep = array_values(array_filter((array) $row, static fn($b) => is_array($b) && ($b['callback_data'] ?? '') !== ($cq['data'] ?? '')));
            if ($keep) {
                $rows[] = $keep;
            }
        }
        $tg->call('editMessageReplyMarkup', ['chat_id' => $m['chat']['id'], 'message_id' => (int) $m['message_id'], 'reply_markup' => ikb($rows)]);
        self::listPage($m['chat']['id'], (int) ($set['reply'] ?? 0) ?: (int) $m['message_id'], $qt, $list, $off);
    }

    private static function searchText(array $msg, string $q): void
    {
        $tg  = App::tg();
        $uid = (int) ($msg['from']['id'] ?? 0);
        if (!App::isAdmin($uid) && Store::update('rl', 'musics_' . $uid, static fn($at) => (is_int($at) && time() - $at < self::WAIT) ? null : time()) === null) {
            $tg->reply($msg, "یه‌کم دیگه بیا 😅\n⏳ هر ۲۰ ثانیه فقط یه موزیک‌یاب!");
            return;
        }
        $list = MusicSearch::find(mb_substr($q, 0, 100));
        if (!$list) {
            $tg->reply($msg, "🤷 آهنگی با این اسم پیدا نکردم 😕\nاسمش رو دقیق‌تر بنویس.");
            return;
        }
        $qt    = bin2hex(random_bytes(6));
        $reply = (int) ($msg['message_id'] ?? 0);
        Store::set('imusic', $qt, ['list' => $list, 'uid' => $uid, 'chat' => $msg['chat']['id'], 'reply' => $reply]);
        self::listPage($msg['chat']['id'], $reply, $qt, $list, 0);
    }

    private static function pick(array $cq, array $m, int $uid, string $qt, int $i): void
    {
        $tg  = App::tg();
        $set = Store::getToday('imusic', $qt);
        $t   = is_array($set) ? ($set['list'][$i] ?? null) : null;
        if (!is_array($t)) {
            self::dropButtons($cq);
            return;
        }
        $admin = App::isAdmin($uid);
        if ($uid !== (int) ($set['uid'] ?? 0) && !$admin) {
            $tg->answer($cq);
            return;
        }
        $tg->answer($cq, '⏳ دارم موزیک رو می‌گیرم...');
        $rows = [];
        foreach ((array) ($m['reply_markup']['inline_keyboard'] ?? []) as $row) {
            $keep = array_values(array_filter((array) $row, static fn($b) => is_array($b) && ($b['callback_data'] ?? '') !== ($cq['data'] ?? '')));
            if ($keep) {
                $rows[] = $keep;
            }
        }
        $tg->call('editMessageReplyMarkup', ['chat_id' => $m['chat']['id'], 'message_id' => (int) $m['message_id'], 'reply_markup' => ikb($rows)]);
        $token = bin2hex(random_bytes(6));
        Store::set('music', $token, ['chat' => $set['chat'], 'uid' => $uid, 'reply' => (int) ($set['reply'] ?? 0), 'card' => 0, 'song' => $t, 'pick' => (int) $m['message_id']]);
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
