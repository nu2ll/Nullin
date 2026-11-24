<?php

final class Fetch
{
    private const PICK    = 5;
    private const IMAGES  = 10;
    private const IMG_MAX = 9000000;
    private const BOT_MAX = 49000000;
    private const UA      = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36';

    public static function run(array $msg, string $arg): bool
    {
        $chat    = $msg['chat']['id'];
        $uid     = (int) ($msg['from']['id'] ?? 0);
        $private = ($msg['chat']['type'] ?? '') === 'private';
        if (!Switches::on('download') || (!$private && !Downloader::groupsEnabled())) {
            return true;
        }
        $parts = preg_split('/\s*[|｜]\s*/u', $arg, 2) ?: [$arg];
        $kind  = strtolower(trim((string) ($parts[0] ?? '')));
        $q     = mbTrim((string) ($parts[1] ?? ''));
        if (!in_array($kind, ['video', 'audio', 'image', 'file'], true)) {
            $kind = 'video';
            $q    = mbTrim($arg);
        }
        if ($q === '') {
            Rich::notice($msg, L('🤔 چی رو دنبالش بگردم؟ اسمش رو بگو.', '🤔 What should I look for? Tell me its name.'));
            return true;
        }
        if (preg_match('#^https?://\S+$#i', $q)) {
            return self::direct($msg, $q);
        }
        if (!Downloader::quotaLeft($uid)) {
            Rich::notice($msg, L('فردا دوباره بیا 🙂', 'Come back tomorrow 🙂') . "\n" . L('✋ امروز سهمیه‌ی دانلودت تموم شده.', '✋ You’ve used up today’s download quota.'));
            return true;
        }
        if ($kind === 'image') {
            return self::images($msg, $q);
        }
        if ($kind === 'file') {
            return self::files($msg, $q);
        }
        App::tg()->action($chat, 'typing');
        $items = self::search($q, 0);
        if (!$items) {
            Rich::notice($msg, self::nothing());
            return true;
        }
        $token = bin2hex(random_bytes(6));
        Store::set('fetch', $token, [
            'owner' => $uid, 'chat' => $chat, 'ctype' => (string) ($msg['chat']['type'] ?? 'private'),
            'reply' => (int) $msg['message_id'], 'kind' => $kind, 'q' => $q, 'off' => 0, 'items' => $items,
        ]);
        Rich::sendHtml($chat, self::listText($q, $kind), (int) $msg['message_id'], self::listKeyboard($token, $items));
        return true;
    }

    private static function nothing(): string
    {
        return L('🤷 چیزی پیدا نکردم؛ یه‌جور دیگه اسمش رو بگو.', '🤷 I found nothing; try describing it differently.');
    }

    private static function listText(string $q, string $kind): string
    {
        return ($kind === 'audio' ? '🎵 ' : ($kind === 'file' ? '📦 ' : '🎬 ')) . L('نتیجه‌ها برای «', 'Results for “') . h($q) . L('»؛ یکی رو انتخاب کن:', '”; pick one:');
    }

    private static function listKeyboard(string $token, array $items): array
    {
        $rows = [];
        foreach ($items as $i => $e) {
            $d      = (int) $e['d'];
            $time   = $d > 0 ? ($d >= 3600 ? sprintf('%d:%02d:%02d', intdiv($d, 3600), intdiv($d % 3600, 60), $d % 60) : sprintf('%d:%02d', intdiv($d, 60), $d % 60)) : '';
            $label  = mb_substr(trim($e['title']), 0, 48) . ($time !== '' ? ' · ' . $time : '');
            $rows[] = [btn(($i + 1) . '. ' . $label, "fe:{$token}:{$i}", null)];
        }
        $rows[] = [btn(L('🔄 نتایج بیشتر', '🔄 More results'), "fe:{$token}:more", null), btn(L('✖️ بیخیال', '✖️ Cancel'), "fe:{$token}:x", null)];
        return ikb($rows);
    }

    private static function search(string $q, int $off): array
    {
        $n   = $off + self::PICK;
        $res = Downloader::exec([Downloader::bin(), '--ignore-config', '--no-color', '-J', '--flat-playlist', '--no-warnings', '--socket-timeout', '15', 'ytsearch' . $n . ':' . $q], 45);
        $j   = json_decode((string) $res['out'], true);
        $out = [];
        foreach (array_values((array) ($j['entries'] ?? [])) as $e) {
            $id = (string) ($e['id'] ?? '');
            if (preg_match('/^[\w-]{11}$/', $id) && !in_array($e['live_status'] ?? '', ['is_live', 'is_upcoming'], true)) {
                $out[] = ['id' => $id, 'title' => (string) ($e['title'] ?? $id), 'd' => (int) ($e['duration'] ?? 0)];
            }
        }
        return array_slice($out, $off, self::PICK);
    }

    public static function onCallback(array $cq, string $data): void
    {
        $tg = App::tg();
        if (!preg_match('/^fe:([a-f0-9]{12}):(\d|more|x)$/', $data, $m)) {
            $tg->answer($cq);
            return;
        }
        $uid = (int) $cq['from']['id'];
        $job = Store::get('fetch', $m[1]);
        if (!is_array($job)) {
            $tg->alert($cq, L('⌛️ این لیست منقضی شده؛ دوباره درخواست بده.', '⌛️ This list has expired; ask again.'));
            return;
        }
        if ($uid !== (int) $job['owner'] && !App::isAdmin($uid)) {
            $tg->alert($cq, L('🙅 این دکمه مال کسیه که درخواست داده.', '🙅 This button belongs to whoever asked.'));
            return;
        }
        $chat = $job['chat'];
        $mid  = (int) ($cq['message']['message_id'] ?? 0);
        if ($m[2] === 'x') {
            $tg->call('deleteMessage', ['chat_id' => $chat, 'message_id' => $mid], 8);
            Store::delete('fetch', $m[1]);
            $tg->answer($cq, L('باشه 👌', 'OK 👌'));
            return;
        }
        if ($m[2] === 'more') {
            $off   = (int) $job['off'] + self::PICK;
            $items = $job['kind'] === 'file' ? array_slice((array) ($job['all'] ?? []), $off, self::PICK) : self::search((string) $job['q'], $off);
            if (!$items) {
                $tg->alert($cq, L('نتیجه‌ی بیشتری نیست.', 'No more results.'));
                return;
            }
            $job['off'] = $off;
            $job['items'] = $items;
            Store::set('fetch', $m[1], $job);
            Rich::editHtml($chat, $mid, self::listText((string) $job['q'], (string) $job['kind']), self::listKeyboard($m[1], $items), null);
            $tg->answer($cq);
            return;
        }
        $e = $job['items'][(int) $m[2]] ?? null;
        if (!is_array($e)) {
            $tg->answer($cq);
            return;
        }
        $tg->answer($cq, L('⏳ شروع شد', '⏳ Started'));
        $tg->call('deleteMessage', ['chat_id' => $chat, 'message_id' => $mid], 8);
        Store::delete('fetch', $m[1]);
        $msg = ['chat' => ['id' => $chat, 'type' => $job['ctype']], 'from' => ['id' => (int) $job['owner']], 'message_id' => (int) $job['reply']];
        if ($job['kind'] === 'file') {
            self::direct($msg, (string) ($e['url'] ?? ''));
            return;
        }
        Downloader::$preset = $job['kind'] === 'audio' ? 'mp3' : 'video';
        try {
            Downloader::offer($msg, ['url' => 'https://www.youtube.com/watch?v=' . $e['id'], 'site' => 'youtube', 'name' => 'YouTube']);
        } finally {
            Downloader::$preset = null;
        }
    }

    // ---------- any file via open web search ----------


    private static function files(array $msg, string $q): bool
    {
        $chat = $msg['chat']['id'];
        App::tg()->action($chat, 'typing');
        $cands = [];
        $pages = [];
        foreach (WebSearch::links($q) as $u) {
            $gz = self::rewrite($u);
            if ($gz !== $u && str_contains($gz, 'codeload.github.com')) {
                $cands[$gz] = true;
                continue;
            }
            if (self::isFile($u)) {
                $cands[$u] = true;
            } else {
                $pages[] = $u;
            }
        }
        [$htmls, $direct] = self::pages(array_slice($pages, 0, 10));
        foreach ($direct as $u) {
            $cands[$u] = true;
        }
        foreach ($htmls as $base => $html) {
            foreach (self::extract($html, $base) as $u) {
                $cands[$u] = true;
            }
        }
        $all = [];
        foreach (array_keys($cands) as $u) {
            if (preg_match('#^https://codeload\.github\.com/([^/]+/[^/]+)/zip/#', $u, $gm)) {
                $all[] = ['url' => $u, 'title' => '🐙 ' . mb_substr($gm[1], 0, 44) . ' · GitHub', 'd' => 0];
                continue;
            }
            $name  = rawurldecode(basename((string) parse_url($u, PHP_URL_PATH)));
            $host  = preg_replace('/^www\./', '', (string) parse_url($u, PHP_URL_HOST));
            $all[] = ['url' => $u, 'title' => mb_substr($name, 0, 40) . ' · ' . $host, 'd' => 0];
            if (count($all) >= 15) {
                break;
            }
        }
        if (!$all) {
            Rich::notice($msg, self::nothing());
            return true;
        }
        $token = bin2hex(random_bytes(6));
        $first = array_slice($all, 0, self::PICK);
        Store::set('fetch', $token, [
            'owner' => (int) ($msg['from']['id'] ?? 0), 'chat' => $chat, 'ctype' => (string) ($msg['chat']['type'] ?? 'private'),
            'reply' => (int) $msg['message_id'], 'kind' => 'file', 'q' => $q, 'off' => 0, 'items' => $first, 'all' => $all,
        ]);
        Rich::sendHtml($chat, self::listText($q, 'file'), (int) $msg['message_id'], self::listKeyboard($token, $first));
        return true;
    }

    private static function isFile(string $u): bool
    {
        return preg_match('~\.([a-z0-9]{2,6})$~i', (string) parse_url($u, PHP_URL_PATH), $m) === 1
            && !in_array(strtolower($m[1]), ['html', 'htm', 'xhtml', 'shtml', 'php', 'asp', 'aspx', 'jsp', 'cfm', 'cgi', 'js', 'css', 'xml', 'rss', 'atom', 'map', 'ico', 'woff', 'woff2', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'], true);
    }

    private static function pages(array $urls): array
    {
        $mh   = curl_multi_init();
        $hs   = [];
        $bufs = [];
        $cts  = [];
        foreach (array_values($urls) as $i => $u) {
            $r = self::resolve((string) $u);
            if ($r === null) {
                continue;
            }
            $bufs[$i] = '';
            $cts[$i]  = '';
            $ch = curl_init($u);
            curl_setopt_array($ch, [
                CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 12, CURLOPT_CONNECTTIMEOUT => 6,
                CURLOPT_USERAGENT      => self::UA, CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
                CURLOPT_RESOLVE        => ["{$r[0]}:{$r[1]}:{$r[2]}"], CURLOPT_ENCODING => '',
                CURLOPT_HEADERFUNCTION => static function ($c, string $line) use (&$cts, $i): int {
                    if (stripos($line, 'content-type:') === 0) {
                        $cts[$i] = strtolower(trim(substr($line, 13)));
                        if ($cts[$i] !== '' && !preg_match('#html|xml|text/#', $cts[$i])) {
                            return 0;
                        }
                    }
                    return strlen($line);
                },
                CURLOPT_WRITEFUNCTION  => static function ($c, string $d) use (&$bufs, $i): int {
                    $bufs[$i] .= $d;
                    return strlen($bufs[$i]) > 1500000 ? 0 : strlen($d);
                },
            ]);
            curl_multi_add_handle($mh, $ch);
            $hs[$i] = [$ch, $u];
        }
        do {
            $st = curl_multi_exec($mh, $active);
            if ($active) {
                curl_multi_select($mh, 1);
            }
        } while ($active && $st === CURLM_OK);
        $html   = [];
        $direct = [];
        foreach ($hs as $i => [$ch, $u]) {
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);
            if ($code !== 200) {
                continue;
            }
            if ($cts[$i] !== '' && !preg_match('#html|xml|text/#', $cts[$i])) {
                $direct[] = $u;
            } elseif ($bufs[$i] !== '') {
                $html[$u] = $bufs[$i];
            }
        }
        curl_multi_close($mh);
        return [$html, $direct];
    }

    private static function extract(string $html, string $base): array
    {
        $p = parse_url($base);
        if (!$p || empty($p['host'])) {
            return [];
        }
        $origin = $p['scheme'] . '://' . $p['host'] . (isset($p['port']) ? ':' . $p['port'] : '');
        $dir    = rtrim(dirname((string) ($p['path'] ?? '/') . 'x'), '/');
        $out    = [];
        if (preg_match_all('/href\s*=\s*["\']([^"\'#]+)["\']/i', $html, $m)) {
            foreach ($m[1] as $h) {
                $h = html_entity_decode(trim($h));
                if (str_starts_with($h, '//')) {
                    $h = $p['scheme'] . ':' . $h;
                } elseif (str_starts_with($h, '/')) {
                    $h = $origin . $h;
                } elseif (!preg_match('#^https?://#i', $h)) {
                    if (preg_match('#^[a-z][a-z0-9+.-]*:#i', $h)) {
                        continue;
                    }
                    $h = $origin . $dir . '/' . $h;
                }
                if (self::isFile($h)) {
                    $out[$h] = true;
                }
                if (count($out) >= 4) {
                    break;
                }
            }
        }
        return array_keys($out);
    }

    private static function rewrite(string $url): string
    {
        if (preg_match('#^https?://(?:www\.)?github\.com/([\w.-]+)/([\w.-]+?)(?:\.git)?(?:/tree/([^?\#]+))?/?(?:[?\#].*)?$#i', $url, $m)
            && !in_array(strtolower($m[1]), ['orgs', 'settings', 'topics', 'marketplace', 'sponsors', 'features'], true)) {
            return 'https://codeload.github.com/' . $m[1] . '/' . $m[2] . '/zip/' . (($m[3] ?? '') !== '' ? 'refs/heads/' . $m[3] : 'HEAD');
        }
        if (preg_match('#^https?://github\.com/([\w.-]+)/([\w.-]+)/blob/(.+)$#i', $url, $m)) {
            return 'https://raw.githubusercontent.com/' . $m[1] . '/' . $m[2] . '/' . $m[3];
        }
        return $url;
    }

    // ---------- images ----------

    private static function images(array $msg, string $q): bool
    {
        $chat = $msg['chat']['id'];
        $tg   = App::tg();
        $tg->action($chat, 'upload_photo');
        $urls  = WebSearch::images($q);
        $files = [];
        foreach (self::grab(array_slice($urls, 0, 24)) as $bytes) {
            $tmp = tempnam(sys_get_temp_dir(), 'nl_fe_');
            if ($tmp !== false && file_put_contents($tmp, $bytes) !== false) {
                $files[] = $tmp;
            }
            if (count($files) >= self::IMAGES) {
                break;
            }
        }
        if (!$files) {
            Rich::notice($msg, self::nothing());
            return true;
        }
        $cap = '🖼 ' . h(mb_substr($q, 0, 100));
        $ok  = false;
        try {
            if (count($files) === 1) {
                $ok = $tg->sendPhotoBytes($chat, (string) file_get_contents($files[0]), $cap, (int) $msg['message_id']);
            } else {
                $media  = [];
                $params = ['chat_id' => $chat, 'reply_parameters' => ['message_id' => (int) $msg['message_id'], 'allow_sending_without_reply' => true]];
                foreach ($files as $i => $f) {
                    $media[]         = ['type' => 'photo', 'media' => "attach://p{$i}"] + ($i === 0 ? ['caption' => $cap, 'parse_mode' => 'HTML'] : []);
                    $params["p{$i}"] = new CURLFile($f, (string) (getimagesize($f)['mime'] ?? 'image/jpeg'), "p{$i}.jpg");
                }
                $params['media'] = $media;
                $r  = $tg->call('sendMediaGroup', $params, 180);
                $ok = !empty($r['ok']);
                if (!$ok) {
                    foreach ($files as $i => $f) {
                        $ok = $tg->sendPhotoBytes($chat, (string) file_get_contents($f), $i === 0 ? $cap : '', (int) $msg['message_id']) || $ok;
                    }
                }
            }
        } finally {
            foreach ($files as $f) {
                @unlink($f);
            }
        }
        if ($ok) {
            Downloader::charge((int) $msg['from']['id']);
        } else {
            Rich::notice($msg, Downloader::genericError());
        }
        return true;
    }

    private static function grab(array $urls): array
    {
        $mh   = curl_multi_init();
        $hs   = [];
        $bufs = [];
        foreach (array_values($urls) as $i => $u) {
            $r = self::resolve((string) $u);
            if ($r === null) {
                continue;
            }
            $bufs[$i] = '';
            $ch = curl_init($u);
            curl_setopt_array($ch, [
                CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 20, CURLOPT_CONNECTTIMEOUT => 6,
                CURLOPT_USERAGENT      => self::UA, CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
                CURLOPT_RESOLVE        => ["{$r[0]}:{$r[1]}:{$r[2]}"],
                CURLOPT_HTTPHEADER     => ['Accept: image/jpeg,image/png,image/webp,image/*;q=0.8'],
                CURLOPT_WRITEFUNCTION  => static function ($c, string $d) use (&$bufs, $i): int {
                    $bufs[$i] .= $d;
                    return strlen($bufs[$i]) > self::IMG_MAX ? 0 : strlen($d);
                },
            ]);
            curl_multi_add_handle($mh, $ch);
            $hs[$i] = $ch;
        }
        do {
            $st = curl_multi_exec($mh, $active);
            if ($active) {
                curl_multi_select($mh, 1);
            }
        } while ($active && $st === CURLM_OK);
        $out = [];
        foreach ($hs as $i => $ch) {
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);
            $b = $bufs[$i];
            if ($code !== 200 || strlen($b) < 5000 || strlen($b) > self::IMG_MAX) {
                continue;
            }
            $info = @getimagesizefromstring($b);
            if ($info === false || !in_array($info['mime'], ['image/jpeg', 'image/png', 'image/webp'], true) || $info[0] < 300 || $info[1] < 200 || $info[0] + $info[1] > 9500) {
                continue;
            }
            $out[$i] = $b;
        }
        curl_multi_close($mh);
        ksort($out);
        return array_values($out);
    }

    // ---------- direct links ----------

    private static function resolve(string $url): ?array
    {
        $p = parse_url($url);
        if (!$p || !in_array(strtolower((string) ($p['scheme'] ?? '')), ['http', 'https'], true) || empty($p['host'])) {
            return null;
        }
        $port = (int) ($p['port'] ?? (strtolower($p['scheme']) === 'https' ? 443 : 80));
        if (!in_array($port, [80, 443, 8080, 8443], true)) {
            return null;
        }
        $host = (string) $p['host'];
        $ips  = [];
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $ips[] = $host;
        } else {
            foreach ((array) @dns_get_record($host, DNS_A) as $r) {
                $ips[] = (string) ($r['ip'] ?? '');
            }
            if (!$ips) {
                $ip = gethostbyname($host);
                if ($ip !== $host) {
                    $ips[] = $ip;
                }
            }
        }
        $ips = array_values(array_filter($ips));
        if (!$ips) {
            return null;
        }
        foreach ($ips as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                return null;
            }
        }
        return [$host, $port, $ips[0]];
    }

    private static function pull(string $url, int $max, string $dest, int $timeout): ?array
    {
        for ($hop = 0; $hop < 4; $hop++) {
            $r = self::resolve($url);
            if ($r === null) {
                return null;
            }
            $fh  = fopen($dest, 'wb');
            $hdr = [];
            $len = 0;
            $ch  = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => $timeout, CURLOPT_CONNECTTIMEOUT => 8,
                CURLOPT_USERAGENT      => self::UA, CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
                CURLOPT_RESOLVE        => ["{$r[0]}:{$r[1]}:{$r[2]}"],
                CURLOPT_HEADERFUNCTION => static function ($c, string $line) use (&$hdr, $max): int {
                    if (!str_contains($line, ':')) {
                        return strlen($line);
                    }
                    [$k, $v] = explode(':', $line, 2);
                    $k = strtolower(trim($k));
                    $hdr[$k] = trim($v);
                    return $k === 'content-length' && (int) trim($v) > $max ? 0 : strlen($line);
                },
                CURLOPT_WRITEFUNCTION  => static function ($c, string $d) use ($fh, &$len, $max): int {
                    $len += strlen($d);
                    return $len > $max ? 0 : (int) fwrite($fh, $d);
                },
            ]);
            curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            fclose($fh);
            if ($code >= 300 && $code < 400 && !empty($hdr['location'])) {
                $loc = $hdr['location'];
                if (!preg_match('#^https?://#i', $loc)) {
                    $p   = parse_url($url);
                    $loc = $p['scheme'] . '://' . $p['host'] . (isset($p['port']) ? ':' . $p['port'] : '') . ($loc[0] === '/' ? '' : '/') . $loc;
                }
                $url = $loc;
                continue;
            }
            if ($code !== 200 || $len < 1 || $len > $max) {
                return null;
            }
            $name = '';
            if (preg_match('/filename\*?=(?:UTF-8\'\')?"?([^";]+)/i', $hdr['content-disposition'] ?? '', $mm)) {
                $name = rawurldecode($mm[1]);
            }
            if ($name === '') {
                $name = rawurldecode(basename((string) parse_url($url, PHP_URL_PATH)));
            }
            $name = preg_replace('/[^\p{L}\p{N}._ -]+/u', '_', $name) ?: 'file';
            return ['type' => strtolower(trim(explode(';', $hdr['content-type'] ?? '')[0])), 'size' => $len, 'name' => mb_substr($name, 0, 80)];
        }
        return null;
    }

    public static function direct(array $msg, string $url): bool
    {
        $uid  = (int) ($msg['from']['id'] ?? 0);
        $chat = $msg['chat']['id'];
        $tg   = App::tg();
        if (!Switches::on('download')) {
            return true;
        }
        if (!Downloader::quotaLeft($uid)) {
            Rich::notice($msg, L('فردا دوباره بیا 🙂', 'Come back tomorrow 🙂') . "\n" . L('✋ امروز سهمیه‌ی دانلودت تموم شده.', '✋ You’ve used up today’s download quota.'));
            return true;
        }
        $max  = Downloader::maxBytes();
        $url  = self::rewrite($url);
        $tg->react($chat, (int) $msg['message_id'], '👍');
        $got = Store::update('dl_busy', (string) $uid, static fn($at) => (is_int($at) && time() - $at < 5100) ? null : time());
        if ($got === null) {
            Rich::notice($msg, L('⏳ یه دانلود دیگه‌ت در جریانه؛ صبر کن اون تموم شه.', '⏳ You already have another download in progress; wait for it to finish.'));
            return true;
        }
        $sent = Rich::sendHtml($chat, L('⏳ دارم فایل رو می‌گیرم...', '⏳ Downloading the file...'), (int) $msg['message_id']);
        $card = (int) ($sent['result']['message_id'] ?? 0);
        $dest = tempnam(sys_get_temp_dir(), 'nl_dl_');
        $ok   = false;
        try {
            $f = $dest !== false ? self::pull($url, $max, $dest, Downloader::mtproto() ? 5000 : 600) : null;
            if ($f === null) {
                $txt = L('😕 نتونستم این لینک رو دانلود کنم یا فایلش بیشتر از ', '😕 I couldn’t download this link, or the file is over ') . fa((string) round($max / 1048576)) . L(' مگه.', ' MB.');
                $card ? Rich::editHtml($chat, $card, $txt, null, null) : Rich::notice($msg, $txt);
                return true;
            }
            $t    = $f['type'];
            $ext  = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
            $kind = str_starts_with($t, 'video/') || in_array($ext, ['mp4', 'mov', 'mkv', 'webm'], true) ? 'video'
                : (str_starts_with($t, 'audio/') || in_array($ext, ['mp3', 'm4a', 'ogg', 'flac', 'wav'], true) ? 'audio'
                : (in_array($t, ['image/jpeg', 'image/png', 'image/webp'], true) && $f['size'] < 9000000 ? 'photo' : 'document'));
            $tg->action($chat, $kind === 'photo' ? 'upload_photo' : 'upload_document');
            $file   = new CURLFile($dest, $t !== '' ? $t : 'application/octet-stream', $f['name']);
            $params = ['chat_id' => $chat, 'reply_parameters' => ['message_id' => (int) $msg['message_id'], 'allow_sending_without_reply' => true]];
            $r      = null;
            if ($f['size'] > self::BOT_MAX) {
                $mime = $t !== '' ? $t : 'application/octet-stream';
                $big  = Downloader::mtSendFile($chat, (int) $msg['message_id'], $dest, $f['name'], $kind === 'photo' ? 'document' : $kind, $mime)
                    || ($kind !== 'document' && Downloader::mtSendFile($chat, (int) $msg['message_id'], $dest, $f['name'], 'document', $mime));
                $r = ['ok' => $big];
            } elseif ($kind !== 'document') {
                $r = $tg->call('send' . ucfirst($kind), $params + [$kind => $file] + ($kind === 'video' ? ['supports_streaming' => true] : []), 600);
            }
            if (empty($r['ok']) && $f['size'] <= self::BOT_MAX) {
                $r = $tg->call('sendDocument', $params + ['document' => new CURLFile($dest, $t !== '' ? $t : 'application/octet-stream', $f['name'])], 600);
            }
            $ok = !empty($r['ok']);
            if ($ok) {
                Downloader::charge($uid);
                Users::hit('dl:direct');
                $card && $tg->call('deleteMessage', ['chat_id' => $chat, 'message_id' => $card], 8);
            } else {
                $card ? Rich::editHtml($chat, $card, Downloader::genericError(), null, null) : Rich::notice($msg, Downloader::genericError());
            }
        } finally {
            Store::delete('dl_busy', (string) $uid);
            if ($dest !== false) {
                @unlink($dest);
            }
        }
        return true;
    }
}
