<?php

final class Fetch
{
    private const PICK    = 5;
    private const IMAGES  = 10;
    private const IMG_MAX = 9000000;
    private const UA      = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36';

    public static function run(array $msg, string $arg): bool
    {
        $chat    = $msg['chat']['id'];
        $uid     = (int) ($msg['from']['id'] ?? 0);
        $private = ($msg['chat']['type'] ?? '') === 'private';
        $parts = preg_split('/\s*[|｜]\s*/u', $arg, 2) ?: [$arg];
        $kind  = strtolower(trim((string) ($parts[0] ?? '')));
        $q     = mbTrim((string) ($parts[1] ?? ''));
        // The AI may add stray pipe slots ("audio | name | |"); keep just the search text.
        if (in_array($kind, ['video', 'audio', 'image'], true)) {
            $q = mbTrim((string) (preg_split('/\s*[|｜]\s*/u', $q)[0] ?? ''));
        }
        if (!Switches::on('ai') || (!$private && !Downloader::groupsEnabled())) {
            return true;
        }
        if (!in_array($kind, ['video', 'audio', 'image'], true)) {
            $kind = 'video';
            $q    = mbTrim($arg);
        }
        if ($q === '') {
            Rich::notice($msg, L('🤔 چی رو دنبالش بگردم؟ اسمش رو بگو 🙂', '🤔 What should I look for? Tell me its name 🙂'));
            return true;
        }
        if (preg_match('#^https?://\S+$#i', $q)) {
            if (($link = Downloader::match($q)) !== null) {
                Downloader::offer($msg, $link);
            } else {
                Downloader::dislike($msg);
            }
            return true;
        }
        if (!Downloader::quotaLeft($uid)) {
            Rich::notice($msg, Downloader::quotaText());
            return true;
        }
        if ($kind === 'image') {
            return self::images($msg, $q);
        }
        App::tg()->action($chat, 'typing');
        $items = self::search($q, 0, $kind, []);
        if (!$items) {
            Rich::notice($msg, self::nothing());
            return true;
        }
        $token = bin2hex(random_bytes(6));
        $job   = [
            'owner' => $uid, 'chat' => $chat, 'ctype' => (string) ($msg['chat']['type'] ?? 'private'),
            'reply' => (int) $msg['message_id'], 'kind' => $kind, 'q' => $q, 'off' => 0, 'items' => $items, 'seen' => self::keys($items),
            'b'     => App::botIndex(),
        ];
        Store::set('fetch', $token, $job);
        $sent = Rich::sendHtml($chat, self::listText($q, $kind, 0, count($items)), (int) $msg['message_id'], self::listKeyboard($token, $items, $kind));
        $mid  = (int) ($sent['result']['message_id'] ?? 0);
        if ($mid > 0) {
            $job['mid'] = $mid;
            Store::set('fetch', $token, $job);
        }
        return true;
    }

    /**
     * Guest / inline version of run(): the answer lives in an inline message ($imid).
     * No pick list: video/audio use the first match, images become a slideshow, files replace the guest message itself.
     */
    public static function guest(array $from, string $arg, string $imid): bool
    {
        $uid = (int) ($from['id'] ?? 0);
        if (!Switches::on('ai')) {
            return false;
        }
        $parts = preg_split('/\s*[|｜]\s*/u', $arg) ?: [$arg];
        $kind  = strtolower(trim((string) ($parts[0] ?? '')));
        if (!in_array($kind, ['video', 'audio', 'image'], true)) {
            $kind  = 'video';
            $parts = [$arg];
        } else {
            array_shift($parts);
        }
        $q = mbTrim((string) ($parts[0] ?? ''));
        if ($q === '') {
            Rich::editInline($imid, L('🤔 چی رو دنبالش بگردم؟ اسمش رو بگو 🙂', '🤔 What should I look for? Tell me its name 🙂'));
            return true;
        }
        if (!Downloader::quotaLeft($uid)) {
            Rich::editInline($imid, Downloader::quotaText());
            return true;
        }
        if (preg_match('#^https?://\S+$#i', $q)) {
            if (($link = Downloader::match($q)) !== null) {
                Downloader::guest($from, $link, $imid);
            } else {
                Rich::editInline($imid, Downloader::genericError());
            }
            return true;
        }
        if ($kind === 'image') {
            return self::guestImage($uid, $q, $imid);
        }
        $items = self::search($q, 0, $kind, []);
        if (!$items) {
            Rich::editInline($imid, self::nothing());
            return true;
        }
        $token = bin2hex(random_bytes(6));
        Store::set('fetch', $token, [
            'owner' => $uid, 'chat' => null, 'ctype' => 'inline', 'reply' => 0, 'kind' => $kind, 'q' => $q, 'off' => 0,
            'items' => $items, 'seen' => self::keys($items), 'b' => App::botIndex(), 'imid' => $imid, 'from' => $from,
        ]);
        Rich::editInline($imid, self::listText($q, $kind, 0, count($items)), self::listKeyboard($token, $items, $kind));
        return true;
    }

    private static function guestImage(int $uid, string $q, string $imid): bool
    {
        $store = Downloader::storeChat($uid);
        if ($store === null) {
            Rich::editInline($imid, Downloader::genericError());
            return true;
        }
        $files = [];
        $srcs  = [];
        foreach (self::grab(array_slice(WebSearch::images($q), 0, 24)) as [$bytes, $from]) {
            $tmp = tempnam(sys_get_temp_dir(), 'nl_fe_');
            if ($tmp !== false && file_put_contents($tmp, $bytes) !== false) {
                $files[] = $tmp;
                $srcs[]  = $from;
            }
            if (count($files) >= self::IMAGES) {
                break;
            }
        }
        if (!$files) {
            Rich::editInline($imid, self::nothing());
            return true;
        }
        try {
            $items = array_map(static fn(string $f): array => ['photo', $f], $files);
            $cap   = '🖼 ' . h(mb_substr($q, 0, 100)) . "\n" . self::sources($srcs);
            $ok    = Downloader::slideshowInline($imid, $store, $items, $cap);
        } finally {
            foreach ($files as $f) {
                @unlink($f);
            }
        }
        if ($ok) {
            Downloader::charge($uid);
        } else {
            Rich::editInline($imid, Downloader::genericError());
        }
        return true;
    }

    private static function host(string $u): string
    {
        return preg_replace('/^www\./', '', (string) parse_url($u, PHP_URL_HOST)) ?: $u;
    }

    private static function src(string $u): string
    {
        return '🔗 <a href="' . h($u) . '">' . h(self::host($u)) . '</a>';
    }

    private static function sources(array $urls): string
    {
        if (count($urls) === 1) {
            return self::src($urls[0]);
        }
        $parts = [];
        foreach (array_values($urls) as $i => $u) {
            $parts[] = ($i + 1) . ' · <a href="' . h($u) . '">' . h(self::host($u)) . '</a>';
        }
        return '🔗 ' . implode('   ', $parts);
    }

    private static function nothing(): string
    {
        return L('🤷 چیزی پیدا نکردم 😕', '🤷 I found nothing 😕') . "\n" . L('یه‌جور دیگه اسمش رو بگو.', 'try describing it differently.');
    }

    private static function listText(string $q, string $kind, int $off = 0, int $count = 0): string
    {
        $meta = ['🔎 ' . fa((string) $count) . L(' نتیجه', ' results')];
        if ($off > 0) {
            $meta[] = '📄 ' . L('صفحه‌ی ', 'page ') . fa((string) (intdiv($off, self::PICK) + 1));
        }
        $meta[] = '📍 ' . L('یوتیوب و وب', 'YouTube & web');
        return ($kind === 'audio' ? '🎵 ' : '🎬 ') . '<b>' . h(mb_substr($q, 0, 200)) . "</b>\n"
            . implode(' · ', $meta) . "\n\n"
            . ($kind === 'audio' ? L('کدوم آهنگ؟ 👇', 'Which song? 👇') : L('کدوم ویدیو؟ 👇', 'Which video? 👇'));
    }

    private static function listKeyboard(string $token, array $items, string $kind = 'video'): array
    {
        $rows = [];
        foreach ($items as $i => $e) {
            $d      = (int) $e['d'];
            $time   = $d > 0 ? ($d >= 3600 ? sprintf('%d:%02d:%02d', intdiv($d, 3600), intdiv($d % 3600, 60), $d % 60) : sprintf('%d:%02d', intdiv($d, 60), $d % 60)) : '';
            $label  = ($kind === 'audio' ? '🎵 ' : '🎬 ') . mb_substr(trim($e['title']), 0, 40)
                . ($time !== '' ? ' · ' . $time : '')
                . (isset($e['name']) ? ' · ' . $e['name'] : ' · YouTube');
            $rows[] = [btn($label, "fe:{$token}:{$i}", $i === 0 ? 'success' : 'primary')];
        }
        $rows[] = [btn(L('🔄 نتایج بیشتر', '🔄 More results'), "fe:{$token}:more", null), btn(L('✖️ بیخیال', '✖️ Cancel'), "fe:{$token}:x", null)];
        return ikb($rows);
    }

    /** Identity of a result in the pick list, so "more results" never repeats one. */
    private static function keys(array $items): array
    {
        $out = [];
        foreach ($items as $e) {
            $out[] = isset($e['url']) ? (string) $e['url'] : 'yt:' . (string) ($e['id'] ?? '');
        }
        return $out;
    }

    /**
     * YouTube plus the whole web (no site list), interleaved. Every call (the first and each
     * "more results") searches both again; $seen holds what's already been shown, so only new
     * results come back. Each round asks Tavily with a different wording to get different pages.
     */
    private static function search(string $q, int $round, string $kind, array $seen): array
    {
        $seen = array_flip($seen);
        $n   = self::PICK * ($round + 1) + count($seen);
        $res = Downloader::exec([Downloader::bin(), '--ignore-config', '--no-color', '-J', '--flat-playlist', '--no-warnings', '--socket-timeout', '15', 'ytsearch' . min($n, 50) . ':' . $q], 45);
        $j   = json_decode((string) $res['out'], true);
        $yt  = [];
        foreach (array_values((array) ($j['entries'] ?? [])) as $e) {
            $id = (string) ($e['id'] ?? '');
            if (preg_match('/^[\w-]{11}$/', $id) && !isset($seen['yt:' . $id]) && !in_array($e['live_status'] ?? '', ['is_live', 'is_upcoming'], true)) {
                $yt[] = ['id' => $id, 'title' => (string) ($e['title'] ?? $id), 'd' => (int) ($e['duration'] ?? 0)];
            }
        }
        $web = [];
        if (WebSearch::available()) {
            $tail = $kind === 'audio' ? [' song music', ' listen online', ' audio', ' official'] : [' video', ' watch online', ' full video', ' official'];
            $hits = WebSearch::files($q . $tail[$round % count($tail)]);
            $page = [];
            foreach ((array) ($hits['results'] ?? []) as $r) {
                $url = (string) ($r['url'] ?? '');
                if (!preg_match('#^https?://#i', $url) || ($link = Downloader::match($url)) === null) {
                    continue;
                }
                if (in_array($link['site'], ['youtube', 'github'], true) || isset($seen[$link['url']]) || isset($page[$link['url']])) {
                    continue;
                }
                $page[$link['url']] = true;
                $web[] = ['url' => $link['url'], 'site' => $link['site'], 'name' => $link['name'],
                          'title' => (string) (($r['title'] ?? '') !== '' ? $r['title'] : $link['name']), 'd' => 0];
            }
        }
        $out = [];
        for ($i = 0, $m = max(count($yt), count($web)); $i < $m; $i++) {
            if (isset($yt[$i])) {
                $out[] = $yt[$i];
            }
            if (isset($web[$i])) {
                $out[] = $web[$i];
            }
        }
        return array_slice($out, 0, self::PICK);
    }

    /** At 00:00 (Tehran) every pick list from a previous day loses its buttons, then its record is deleted. */
    public static function sweep(int $budget = 100): int
    {
        $end  = microtime(true) + $budget;
        $done = 0;
        $keys = Db::run('SELECT k FROM kv WHERE ns = ? AND updated_at < ? LIMIT 3000', ['fetch', Store::dayStart()])->fetchAll(PDO::FETCH_COLUMN);
        foreach ($keys as $k) {
            if (microtime(true) > $end) {
                break;
            }
            $job = Store::get('fetch', (string) $k);
            $r   = null;
            if (is_array($job) && !empty($job['chat']) && !empty($job['mid'])) {
                $r = App::tgFor((int) ($job['b'] ?? 0))->call('editMessageReplyMarkup', [
                    'chat_id'      => $job['chat'],
                    'message_id'   => (int) $job['mid'],
                    'reply_markup' => ['inline_keyboard' => []],
                ], 5);
            }
            if ((int) ($r['error_code'] ?? 0) === 429) {
                break;
            }
            Store::delete('fetch', (string) $k);
            $done++;
            usleep(60000);
        }
        return $done;
    }

    public static function onCallback(array $cq, string $data): void
    {
        $tg = App::tg();
        if (!preg_match('/^fe:([a-f0-9]{12}):(\d|more|x)$/', $data, $m)) {
            $tg->answer($cq);
            return;
        }
        $uid = (int) $cq['from']['id'];
        $job = Store::getToday('fetch', $m[1]);
        if (!is_array($job)) {
            $tg->alert($cq, L('⌛️ منقضی شده؛', '⌛️ Expired;') . "\n" . L('دوباره درخواست بده.', 'ask again.'));
            return;
        }
        if ($uid !== (int) $job['owner'] && !App::isAdmin($uid)) {
            $tg->alert($cq, L("🙅 این دکمه مال کسیه که\nدرخواست داده؛", "🙅 This button belongs to whoever\nasked;") . "\n" . L('خودت درخواست بده 🙂', 'ask for your own 🙂'));
            return;
        }
        $chat = $job['chat'];
        $imid = (string) ($job['imid'] ?? '');
        $mid  = (int) ($cq['message']['message_id'] ?? 0);
        if ($m[2] === 'x') {
            $imid !== '' ? Rich::editInline($imid, L('✖️ بیخیال شدی.', '✖️ You cancelled.')) : $tg->call('deleteMessage', ['chat_id' => $chat, 'message_id' => $mid], 8);
            Store::delete('fetch', $m[1]);
            $tg->answer($cq, L('باشه 👌', 'OK 👌'));
            return;
        }
        if ($m[2] === 'more') {
            $off   = (int) $job['off'] + self::PICK;
            $seen  = (array) ($job['seen'] ?? []);
            $items = self::search((string) $job['q'], intdiv($off, self::PICK), (string) $job['kind'], $seen);
            if (!$items) {
                $tg->alert($cq, L('🏁 نتیجه‌ی بیشتری نیست؛', '🏁 There are no more results;') . "\n" . L('یه کلمه‌ی دیگه امتحان کن.', 'try another word.'));
                return;
            }
            $job['off'] = $off;
            $job['items'] = $items;
            $job['seen'] = array_values(array_unique(array_merge($seen, self::keys($items))));
            Store::set('fetch', $m[1], $job);
            $lt = self::listText((string) $job['q'], (string) $job['kind'], $off, count($items));
            $lk = self::listKeyboard($m[1], $items, (string) $job['kind']);
            $imid !== '' ? Rich::editInline($imid, $lt, $lk) : Rich::editHtml($chat, $mid, $lt, $lk, null);
            $tg->answer($cq);
            return;
        }
        $e = $job['items'][(int) $m[2]] ?? null;
        if (!is_array($e)) {
            $tg->answer($cq);
            return;
        }
        $tg->answer($cq, L('⏳ شروع شد', '⏳ Started'));
        if ($imid === '') {
            $tg->call('deleteMessage', ['chat_id' => $chat, 'message_id' => $mid], 8);
        }
        Store::delete('fetch', $m[1]);
        if ($imid !== '') {
            $link = isset($e['url'])
                ? ['url' => (string) $e['url'], 'site' => (string) $e['site'], 'name' => (string) $e['name']]
                : ['url' => 'https://www.youtube.com/watch?v=' . $e['id'], 'site' => 'youtube', 'name' => 'YouTube'];
            Downloader::$preset = $job['kind'] === 'audio' ? 'mp3' : 'video';
            try {
                Downloader::guest((array) ($job['from'] ?? ['id' => $uid]), $link, $imid);
            } finally {
                Downloader::$preset = null;
            }
            return;
        }
        $msg = ['chat' => ['id' => $chat, 'type' => $job['ctype']], 'from' => ['id' => (int) $job['owner']], 'message_id' => (int) $job['reply']];
        Downloader::$preset = $job['kind'] === 'audio' ? 'mp3' : 'video';
        try {
            Downloader::offer($msg, isset($e['url'])
                ? ['url' => (string) $e['url'], 'site' => (string) $e['site'], 'name' => (string) $e['name']]
                : ['url' => 'https://www.youtube.com/watch?v=' . $e['id'], 'site' => 'youtube', 'name' => 'YouTube']);
        } finally {
            Downloader::$preset = null;
        }
    }

    // ---------- images ----------

    private static function images(array $msg, string $q): bool
    {
        $chat = $msg['chat']['id'];
        $tg   = App::tg();
        $tg->action($chat, 'upload_photo');
        $urls  = WebSearch::images($q);
        $files = [];
        $srcs  = [];
        foreach (self::grab(array_slice($urls, 0, 24)) as [$bytes, $from]) {
            $tmp = tempnam(sys_get_temp_dir(), 'nl_fe_');
            if ($tmp !== false && file_put_contents($tmp, $bytes) !== false) {
                $files[] = $tmp;
                $srcs[]  = $from;
            }
            if (count($files) >= self::IMAGES) {
                break;
            }
        }
        if (!$files) {
            Rich::notice($msg, self::nothing());
            return true;
        }
        $cap = '🖼 ' . h(mb_substr($q, 0, 100)) . "\n" . self::sources($srcs);
        $ok  = false;
        try {
            if (count($files) === 1) {
                $ok = $tg->sendPhotoBytes($chat, (string) file_get_contents($files[0]), $cap, (int) $msg['message_id']);
            } else {
                $ok = self::slideshow($msg, $q, $files, $srcs);
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

    /** One rich message holding all photos in Telegram's own swipeable slideshow block. */
    private static function slideshow(array $msg, string $q, array $files, array $srcs): bool
    {
        $tg    = App::tg();
        if (Downloader::slidesDirect()) {
            $imgs   = '';
            $slides = [];
            foreach (array_values($files) as $i => $f) {
                $imgs    .= '<img src="tg://photo?id=p' . $i . '"/>';
                $slides[] = ['id' => 'p' . $i, 'kind' => 'photo', 'file' => $f, 'name' => 'img' . $i . '.jpg'];
            }
            $html = '<tg-slideshow>' . $imgs . '<figcaption>🖼 ' . h(mb_substr($q, 0, 100)) . '</figcaption></tg-slideshow>'
                  . '<p>' . str_replace("\n", '<br>', self::sources(array_values($srcs))) . '</p>';
            return Downloader::mtSlideshow((int) $msg['chat']['id'], (int) $msg['message_id'], $slides, $html);
        }
        $store = self::imgStore((int) ($msg['from']['id'] ?? 0));
        if ($store === null) {
            logWarn('fetch', 'slideshow: no storage chat to upload photos to');
            return false;
        }
        $imgs  = '';
        $media = [];
        foreach ($files as $i => $f) {
            $id = $tg->photoFileId($store, (string) file_get_contents($f));
            if ($id === null) {
                logWarn('fetch', "slideshow: uploading image {$i} to the storage chat failed");
                continue;
            }
            $imgs   .= '<img src="tg://photo?id=p' . $i . '"/>';
            $media[] = ['id' => 'p' . $i, 'media' => ['type' => 'photo', 'media' => $id]];
        }
        if (!$media) {
            return false;
        }
        $n    = count($media);
        $html = '<tg-slideshow>' . $imgs . '<figcaption>🖼 ' . h(mb_substr($q, 0, 100)) . '</figcaption></tg-slideshow>'
              . '<p>' . str_replace("\n", '<br>', self::sources(array_values($srcs))) . '</p>';
        $r = $tg->call('sendRichMessage', [
            'chat_id'          => $msg['chat']['id'],
            'rich_message'     => ['html' => $html, 'media' => $media] + (Lang::is('fa') ? ['is_rtl' => true] : []),
            'reply_parameters' => ['message_id' => (int) $msg['message_id'], 'allow_sending_without_reply' => true],
        ], 30);
        if (empty($r['ok'])) {
            logWarn('fetch', "slideshow ({$n} photos) failed: " . mb_substr((string) ($r['description'] ?? 'no response'), 0, 200));
            return false;
        }
        if (!empty($r['result']['message_id'])) {
            Rich::mark((int) ($r['result']['chat']['id'] ?? $msg['chat']['id']), (int) $r['result']['message_id']);
        }
        return true;
    }

    public static function imgStore(int $uid): int|string|null
    {
        $fixed = (string) App::cfg('storage_chat', '');
        if ($fixed !== '') {
            return preg_match('/^-?\d+$/', $fixed) ? (int) $fixed : $fixed;
        }
        $s = Downloader::storeChat($uid);
        if ($s !== null) {
            return $s;
        }
        $a = (array) App::cfg('admins', []);
        return isset($a[0]) ? (int) $a[0] : null;
    }

    private static function grab(array $urls): array
    {
        $mh   = curl_multi_init();
        $hs   = [];
        $bufs = [];
        $list = array_values($urls);
        foreach ($list as $i => $u) {
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
                Http::pulse();
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
            $out[$i] = [$b, (string) $list[$i]];
        }
        curl_multi_close($mh);
        ksort($out);
        return array_values($out);
    }

    // ---------- safe address check (images) ----------

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
}
