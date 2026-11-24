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
        if ($kind === 'file') {
            return self::start($msg, $q);
        }
        // The AI always writes the file-type and version slots ("audio | name | |"); they only matter
        // for "file", so for video/audio/image keep just the search text and drop the stray pipes.
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
            return self::start($msg, $q);
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
     * No pick list: video/audio use the first match, images send one photo, files go to the user's private chat.
     */
    public static function guest(array $from, string $arg, string $imid): bool
    {
        $uid = (int) ($from['id'] ?? 0);
        if (!Switches::on('ai')) {
            return false;
        }
        $parts = preg_split('/\s*[|｜]\s*/u', $arg) ?: [$arg];
        $kind  = strtolower(trim((string) ($parts[0] ?? '')));
        if (!in_array($kind, ['video', 'audio', 'image', 'file'], true)) {
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
                return true;
            }
            $kind = 'file';
        }
        if ($kind === 'file') {
            return self::guestFile($uid, implode(' | ', $parts), $imid);
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
        $store = self::imgStore($uid);
        if ($store === null) {
            Rich::editInline($imid, L('⚠️ اول ربات رو تو چت خصوصی استارت کن 🙂', '⚠️ Start the bot in a private chat first 🙂'));
            return true;
        }
        foreach (self::grab(array_slice(WebSearch::images($q), 0, 12)) as [$bytes, $from]) {
            $id = App::tg()->photoFileId($store, $bytes);
            if ($id === null) {
                continue;
            }
            Rich::editInline($imid, '🖼 ' . h(mb_substr($q, 0, 100)) . "\n" . self::src($from), null, $id);
            Downloader::charge($uid);
            return true;
        }
        Rich::editInline($imid, self::nothing());
        return true;
    }

    private static function guestFile(int $uid, string $arg, string $imid): bool
    {
        $req = self::parseArg($arg);
        if (!App::isAdmin($uid) && (int) Store::get('gf_busy', (string) $uid, 0) > time() - 1800) {
            Rich::editInline($imid, L('⏳ یه دانلود دیگه‌ت در جریانه؛', '⏳ You already have another download in progress;') . "\n" . L('صبر کن اون تموم شه.', 'wait for it to finish.'));
            return true;
        }
        $store = Downloader::storeChat($uid);
        if ($store === null) {
            Rich::editInline($imid, L('⚠️ اول ربات رو تو چت خصوصی استارت کن 🙂', '⚠️ Start the bot in a private chat first 🙂'));
            return true;
        }
        $token = bin2hex(random_bytes(6));
        $job   = ['owner' => $uid, 'chat' => $store, 'mid' => 0, 'imid' => $imid, 'reply_to' => 0,
                  'q' => $req['q'], 'hint' => $req['hint'], 'ver' => $req['ver'], 'cands' => []];
        Store::set('gf', $token, $job);
        Store::set('gf_busy', (string) $uid, time());
        Rich::editInline($imid, L('🔎 دارم دنبال فایل می‌گردم...', '🔎 Looking for the file...'));
        if (!self::spawn($token, -1)) {
            Store::delete('gf_busy', (string) $uid);
            self::fail($job, L('😮‍💨 سرور الان خیلی شلوغه؛', '😮‍💨 The server is very busy right now;') . "\n" . L('چند دقیقه دیگه دوباره امتحان کن.', 'try again in a few minutes.'));
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
            $rows = [];
            $hint = $kind === 'audio' ? 'mp3' : 'mp4';
            $tail = $kind === 'audio' ? [' song music', ' mp3 download', ' listen online', ' audio', ' official'] : [' video', ' watch online', ' mp4 download', ' full video', ' official'];
            $hits = WebSearch::files($q . $tail[$round % count($tail)]);
            $page = [];
            foreach ((array) ($hits['results'] ?? []) as $r) {
                $url = (string) ($r['url'] ?? '');
                if (!preg_match('#^https?://#i', $url)) {
                    continue;
                }
                $known = Downloader::match($url);
                $link  = $known ?? ['url' => $url, 'site' => 'web', 'name' => self::host($url)];
                if ($link['site'] === 'youtube' || isset($seen[$link['url']]) || isset($page[$link['url']])) {
                    continue;
                }
                $page[$link['url']] = true;
                $rows[] = ['site' => $link['site'], 'url' => $link['url'], 'name' => $link['name'], 'check' => $known === null,
                           'title' => (string) (($r['title'] ?? '') !== '' ? $r['title'] : $link['name'])];
            }
            // pages the downloader doesn't know: keep only those that really hold a direct file
            $good  = [];
            $pages = [];
            foreach ($rows as $row) {
                if (!$row['check']) {
                    continue;
                }
                if (self::looksFile($row['url'], self::mediaRe($hint), $hint)) {
                    $good[$row['url']] = true;
                } else {
                    $pages[] = $row['url'];
                }
            }
            if ($pages) {
                [$htmls, $direct] = self::readPages(array_slice($pages, 0, 10));
                foreach ($direct as $du) {
                    $good[$du] = true;
                }
                foreach ($htmls as $pu => $html) {
                    if (self::pageMedia((string) $pu, $html, $hint)) {
                        $good[$pu] = true;
                    }
                }
            }
            foreach ($rows as $row) {
                if ($row['check'] && !isset($good[$row['url']])) {
                    continue;
                }
                $web[] = ['url' => $row['url'], 'site' => $row['site'], 'name' => $row['name'], 'title' => $row['title'], 'd' => 0];
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
            $kindArg = $job['kind'] === 'audio' ? 'mp3' : 'mp4';
            if (($e['site'] ?? '') === 'web' && isset($e['url'])) {
                self::guestFile($uid, (string) $e['url'] . ' | ' . $kindArg, $imid);
                return;
            }
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
        if (($e['site'] ?? '') === 'web' && isset($e['url'])) {
            self::start($msg, (string) $e['url'] . ' | ' . ($job['kind'] === 'audio' ? 'mp3' : 'mp4'));
            return;
        }
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

    // ---------- files, apps and GitHub (background process) ----------
    // Fetch::run → start() → (background) work() → findLinks() → pullFile() → send()

    private const TMP   = '/tmp/nullini-gf';
    private const FILE_UA    = 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36';

    private const AUDIO_EXT = 'mp3|m4a|m4b|aac|ogg|oga|opus|flac|wav|wma|aiff|aif|amr|mka|ac3|weba';
    private const VIDEO_EXT = 'mp4|m4v|mkv|webm|mov|avi|wmv|flv|mpg|mpeg|3gp|ogv|mts|m2ts|vob';
    private const FILE_EXT = 'pdf|zip|rar|7z|tar|gz|tgz|xz|apk|xapk|apks|aab|exe|msi|dmg|pkg|deb|rpm|appimage|ipa|iso|mp3|m4a|m4b|aac|ogg|oga|opus|flac|wav|wma|aiff|aif|amr|mka|ac3|weba|mp4|m4v|mkv|webm|mov|avi|wmv|flv|mpg|mpeg|3gp|ogv|mts|m2ts|vob|epub|mobi|docx?|xlsx?|pptx?|csv|jpe?g|png|gif|webp|bmp|tiff?|heic|heif|avif|psd|ttf|otf|torrent|rtf|odt|ods|odp|xlsm|xlsb|docm|srt|vtt|azw3|fb2|djvu|cbz|cbr|bz2|zst|cab|jar|whl';
    // Only matched with a real ".ext" at the end of the URL path (never as a bare last path segment such as /go or /py).
    // Web-page assets (js, css, html, php, svg, ico, json, xml) are deliberately NOT here: pages are full of them,
    // so they would flood link discovery. They still work with a direct link or an explicit type: "name | json".
    private const DOT_EXT = 'txt|md|log|sql|ass|ssa|ipynb|yml|yaml|toml|ini|cfg|conf|py|java|cpp|cc|hpp|cs|go|rs|rb|swift|kts?|sh|bat|ps1|lua|pl|dart|scala|vue|jsx|tsx';

    private const SOFT_HINTS = ['', 'apk', 'win', 'mac', 'linux', 'ios', 'source', 'zip', 'exe', 'msi', 'dmg', 'deb', 'rpm', 'appimage', 'ipa', 'tar', 'gz'];

    private static string $err = '';

    private const MIME_EXT = [
        'application/pdf' => 'pdf', 'application/zip' => 'zip', 'application/vnd.android.package-archive' => 'apk',
        'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif', 'image/bmp' => 'bmp',
        'image/svg+xml' => 'svg', 'image/tiff' => 'tiff', 'image/heic' => 'heic', 'image/heif' => 'heif', 'image/avif' => 'avif',
        'image/x-icon' => 'ico', 'image/vnd.microsoft.icon' => 'ico', 'image/vnd.adobe.photoshop' => 'psd',
        'audio/mpeg' => 'mp3', 'audio/mp4' => 'm4a', 'audio/aac' => 'aac', 'audio/ogg' => 'ogg', 'audio/opus' => 'opus', 'audio/flac' => 'flac',
        'audio/wav' => 'wav', 'audio/x-wav' => 'wav', 'audio/webm' => 'weba',
        'video/mp4' => 'mp4', 'video/webm' => 'webm', 'video/quicktime' => 'mov', 'video/x-matroska' => 'mkv', 'video/x-msvideo' => 'avi',
        'video/mpeg' => 'mpg', 'video/3gpp' => '3gp',
        'application/x-msdownload' => 'exe', 'application/x-7z-compressed' => '7z',
        'application/gzip' => 'gz', 'application/x-gzip' => 'gz', 'application/x-bzip2' => 'bz2', 'application/zstd' => 'zst',
        'application/x-tar' => 'tar', 'application/x-xz' => 'xz', 'application/vnd.rar' => 'rar', 'application/x-rar-compressed' => 'rar',
        'application/java-archive' => 'jar', 'application/vnd.ms-cab-compressed' => 'cab',
        'application/epub+zip' => 'epub', 'application/x-mobipocket-ebook' => 'mobi', 'image/vnd.djvu' => 'djvu', 'application/x-fictionbook+xml' => 'fb2',
        'application/msword' => 'doc', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'application/vnd.ms-excel' => 'xls', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
        'application/vnd.ms-powerpoint' => 'ppt', 'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
        'application/vnd.oasis.opendocument.text' => 'odt', 'application/vnd.oasis.opendocument.spreadsheet' => 'ods',
        'application/vnd.oasis.opendocument.presentation' => 'odp', 'application/rtf' => 'rtf', 'text/rtf' => 'rtf',
        'text/csv' => 'csv', 'text/plain' => 'txt', 'text/markdown' => 'md', 'application/json' => 'json', 'application/xml' => 'xml', 'text/xml' => 'xml',
        'application/sql' => 'sql', 'application/x-yaml' => 'yaml', 'application/yaml' => 'yaml', 'text/yaml' => 'yaml', 'application/toml' => 'toml',
        'application/x-subrip' => 'srt', 'text/vtt' => 'vtt', 'application/x-bittorrent' => 'torrent',
        'text/javascript' => 'js', 'application/javascript' => 'js', 'text/css' => 'css', 'application/x-httpd-php' => 'php',
        'text/x-python' => 'py', 'application/x-python-code' => 'pyc', 'text/x-java-source' => 'java', 'text/x-c' => 'c', 'text/x-c++src' => 'cpp',
        'text/x-csharp' => 'cs', 'text/x-go' => 'go', 'text/x-rustsrc' => 'rs', 'text/x-ruby' => 'rb', 'text/x-shellscript' => 'sh', 'application/x-sh' => 'sh',
        'application/x-ipynb+json' => 'ipynb', 'application/typescript' => 'ts',
        'font/ttf' => 'ttf', 'font/otf' => 'otf', 'font/woff' => 'woff', 'font/woff2' => 'woff2', 'application/font-woff' => 'woff',
    ];

    // ───────────────────────── ورودی ─────────────────────────

    public static function start(array $msg, string $arg): bool
    {
        if (!Switches::on('ai') || (($msg['chat']['type'] ?? '') !== 'private' && !Downloader::groupsEnabled())) {
            return true;
        }
        $uid  = (int) ($msg['from']['id'] ?? 0);
        $chat = $msg['chat']['id'];
        $req  = self::parseArg($arg);
        $isAdmin = App::isAdmin($uid);

        if ($req['q'] === '') {
            Rich::notice($msg, L('چه فایلی می‌خوای؟ اسم برنامه یا لینکش رو بگو 🙂', 'Which file do you want? Tell me the app name or the link 🙂'));
            return true;
        }
        if (!Downloader::quotaLeft($uid)) {
            Rich::notice($msg, Downloader::quotaText());
            return true;
        }
        if (!$isAdmin && (int) Store::get('gf_busy', (string) $uid, 0) > time() - 1800) {
            Rich::notice($msg, L('⏳ یه دانلود دیگه‌ت در جریانه؛', '⏳ You already have another download in progress;') . "\n" . L('صبر کن اون تموم شه.', 'wait for it to finish.'));
            return true;
        }

        $sent = Rich::sendHtml($chat, L('🔎 دارم دنبال فایل می‌گردم...', '🔎 Looking for the file...'), (int) $msg['message_id']);
        $mid  = (int) ($sent['result']['message_id'] ?? 0);
        if ($mid <= 0) {
            return true;
        }
        $token = bin2hex(random_bytes(6));
        $job   = [
            'owner'    => $uid,
            'chat'     => $chat,
            'mid'      => $mid,
            'reply_to' => (int) $msg['message_id'],
            'q'        => $req['q'],
            'hint'     => $req['hint'],
            'ver'      => $req['ver'],
            'cands'    => [],
        ];
        Store::set('gf', $token, $job);
        Store::set('gf_busy', (string) $uid, time());
        if (!self::spawn($token, -1)) {
            Store::delete('gf_busy', (string) $uid);
            self::fail($job, L('😮‍💨 سرور الان خیلی شلوغه؛', '😮‍💨 The server is very busy right now;') . "\n" . L('چند دقیقه دیگه دوباره امتحان کن.', 'try again in a few minutes.'));
        }
        return true;
    }

    public static function onFileCallback(array $cq, string $data): void
    {
        $tg = App::tg();
        if (!preg_match('/^gf:([a-f0-9]{12}):(x|\d{1,2})$/', $data, $m)) {
            $tg->answer($cq);
            return;
        }
        $token = $m[1];
        $uid   = (int) ($cq['from']['id'] ?? 0);
        $job   = Store::get('gf', $token);
        if (!is_array($job)) {
            $tg->alert($cq, L('⌛️ منقضی شده؛', '⌛️ Expired;') . "\n" . L('دوباره درخواست بده.', 'ask again.'));
            return;
        }
        if ($uid !== (int) $job['owner'] && !App::isAdmin($uid)) {
            $tg->alert($cq, L("🙅 این دکمه مال کسیه که\nفایل رو خواسته؛", "🙅 This button belongs to whoever\nasked for the file;") . "\n" . L('خودت درخواست بده 🙂', 'ask for your own 🙂'));
            return;
        }
        if ($m[2] === 'x') {
            !empty($job['imid']) ? Rich::editInline((string) $job['imid'], L('✖️ بیخیال شدی.', '✖️ You cancelled.')) : $tg->call('deleteMessage', ['chat_id' => $job['chat'], 'message_id' => (int) $job['mid']], 8);
            Store::delete('gf', $token);
            $tg->answer($cq, L('باشه 👌', 'OK 👌'));
            return;
        }
        $i = (int) $m[2];
        if (empty($job['cands'][$i]) || !empty($job['picked'])) {
            $tg->answer($cq);
            return;
        }
        if (!Downloader::quotaLeft((int) $job['owner'])) {
            $tg->alert($cq, Downloader::quotaText());
            return;
        }
        $job['picked'] = true;
        Store::set('gf', $token, $job);
        Store::set('gf_busy', (string) $job['owner'], time());
        $tg->answer($cq, L('باشه 👌', 'OK 👌'));
        self::show($job, L('⏳ آماده‌ی دانلود...', '⏳ Getting ready to download...'));
        if (!self::spawn($token, $i)) {
            Store::delete('gf_busy', (string) $job['owner']);
            self::fail($job, L('😮‍💨 سرور الان خیلی شلوغه؛', '😮‍💨 The server is very busy right now;') . "\n" . L('چند دقیقه دیگه دوباره امتحان کن.', 'try again in a few minutes.'));
        }
    }

    // ───────────────────────── پروسه‌ی پس‌زمینه ─────────────────────────

    public static function work(string $token, int $pick): void
    {
        $job = Store::get('gf', $token);
        if (!is_array($job)) {
            return;
        }
        Lang::useFrom(['id' => (int) ($job['owner'] ?? 0)]);
        $dir = self::TMP . '/' . $token;
        @mkdir($dir, 0700, true);
        try {
            $res = $pick >= 0 ? ['urls' => [$job['cands'][$pick] ?? []]] : self::findLinks($job);
            if (!empty($res['error'])) {
                self::fail($job, (string) $res['error']);
                return;
            }
            if (!empty($res['choose'])) {
                $job['cands'] = $res['choose'];
                Store::set('gf', $token, $job);
                self::askChoice($job, $token, $res['choose']);
                return;
            }
            self::download($job, $token, $dir, (array) ($res['urls'] ?? []));
        } catch (\Throwable $e) {
            logError('gf', $e->getMessage());
            self::fail($job, L('❌ یه مشکلی پیش اومد 😕', '❌ Something went wrong 😕') . "\n" . L('یه‌کم دیگه دوباره امتحان کن.', 'please try again in a bit.'));
        } finally {
            Store::delete('gf_busy', (string) ($job['owner'] ?? 0));
            self::rmrf($dir);
        }
    }

    private static function download(array $job, string $token, string $dir, array $urls): void
    {
        $max  = self::maxBytes();
        $last = 'notfound';
        $hint = (string) $job['hint'];
        foreach (array_slice($urls, 0, 4) as $c) {
            if (empty($c['url'])) {
                continue;
            }
            $file  = $dir . '/download.bin';
            @unlink($file);
            $label = (string) ($c['name'] ?? '') !== '' ? (string) $c['name'] : self::hostOf((string) $c['url']);
            $next  = 0.0;
            $tick  = function (int $now, int $total) use ($job, $label, &$next): void {
                if (microtime(true) < $next) {
                    return;
                }
                $next = microtime(true) + 3.5;
                $pct  = $total > 0 ? (int) floor($now / $total * 100) : 0;
                $line = $total > 0
                    ? Downloader::bar($pct) . ' ' . fa((string) $pct) . L('٪', '%') . "\n📦 " . Downloader::mb((float) $now) . L(' از ', ' of ') . Downloader::mb((float) $total)
                    : '📦 ' . Downloader::mb((float) $now);
                self::show($job, '⬇️ <b>' . h(mb_substr($label, 0, 80)) . "</b>\n" . $line);
            };
            self::show($job, '⬇️ <b>' . h(mb_substr($label, 0, 80)) . "</b>\n" . L('⏳ دارم دانلودش می‌کنم...', '⏳ Downloading it...') . (!empty($c['size']) ? ' (' . Downloader::mb((float) $c['size']) . ')' : ''));

            $r = self::pullFile((string) $c['url'], $file, $max, $tick, $hint);
            if (empty($r['ok'])) {
                $last = (string) ($r['err'] ?? 'net');
                logWarn('gf', "fetch failed ({$last}): " . mb_substr((string) $c['url'], 0, 200));
                continue;
            }

            $name = self::fileName((string) ($c['name'] ?? ''), (string) $r['disp'], (string) $r['final'], (string) $r['mime']);
            $path = $dir . '/' . $name;
            if (!@rename($file, $path)) {
                $path = $file;
            }
            $size = (int) @filesize($path);
            $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            $cap  = self::caption($c, $name, $size);

            if (!self::send($job, $path, $name, $cap, $size, (string) $r['mime'])) {
                $why = App::isAdmin((int) $job['owner']) && self::$err !== '' ? "\n<code>" . h(mb_substr(self::$err, 0, 400)) . '</code>' : '';
                self::fail($job, L('❌ دانلود شد ولی نتونستم بفرستمش 😕', '❌ It downloaded but I couldn’t send it 😕') . "\n" . L('یه‌کم دیگه دوباره امتحان کن.', 'please try again in a bit.') . $why);
                return;
            }
            if (!empty($job['imid'])) {
                self::show($job, L('✅ فایل رو تو چت خصوصی‌ت با ربات فرستادم 📥', '✅ I sent the file to your private chat with the bot 📥'));
            } else {
                App::tg()->call('deleteMessage', ['chat_id' => $job['chat'], 'message_id' => (int) $job['mid']], 8);
            }
            Downloader::charge((int) $job['owner']);
            Store::delete('gf', $token);
            logInfo('gf', 'sent ' . $name . ' (' . $size . ' bytes) to ' . $job['owner']);
            return;
        }
        self::fail($job, self::errText($last));
    }

    // ───────────────────────── پیدا کردن لینک ─────────────────────────

    private static function findLinks(array $job): array
    {
        $q    = (string) $job['q'];
        $hint = (string) $job['hint'];
        $ver  = (string) $job['ver'];

        if (preg_match('#^https?://#i', $q)) {
            if (preg_match('#^https?://(?:www\.)?github\.com/([\w.-]+)/([\w.-]+)/blob/([^?\#]+)#i', $q, $bm)) {
                return ['urls' => [['url' => 'https://raw.githubusercontent.com/' . $bm[1] . '/' . $bm[2] . '/' . $bm[3], 'name' => '', 'size' => 0, 'src' => 'GitHub: ' . $bm[1] . '/' . $bm[2], 'srcUrl' => $q]]];
            }
            if (preg_match('#^https?://(?:www\.)?github\.com/([\w.-]+)/([\w.-]+?)(?:\.git)?/tree/([^?\#]+)#i', $q, $tm2)) {
                $br = trim($tm2[3], '/');
                return ['urls' => [['url' => 'https://codeload.github.com/' . $tm2[1] . '/' . $tm2[2] . '/zip/refs/heads/' . $br, 'name' => $tm2[2] . '-' . str_replace('/', '-', $br) . '.zip', 'size' => 0, 'src' => 'GitHub: ' . $tm2[1] . '/' . $tm2[2] . ' · ' . $br, 'srcUrl' => $q]]];
            }
            if (preg_match('#^https?://(?:www\.)?github\.com/([\w.-]+)/([\w.-]+?)(?:\.git)?(?:/(.*))?$#i', $q, $m)
                && !preg_match('#^(releases/download|raw|archive|blob|raw\.githubusercontent)#i', (string) ($m[3] ?? ''))) {
                if (preg_match('#^releases/tag/(.+)$#i', (string) ($m[3] ?? ''), $tm) && $ver === '') {
                    $ver = rawurldecode($tm[1]);
                }
                return self::fromGithub($m[1], $m[2], $hint, $ver);
            }
            if (in_array($hint, ['mp3', 'mp4'], true) && !self::looksFile($q, null, '')) {
                [$htmls, $direct] = self::readPages([$q]);
                $c    = [];
                $seen = [];
                $add  = static function (string $u) use (&$c, &$seen, $q): void {
                    if (isset($seen[$u]) || count($c) >= 4) {
                        return;
                    }
                    $seen[$u] = true;
                    $c[]      = ['url' => $u, 'name' => '', 'size' => 0, 'src' => self::hostOf($q), 'srcUrl' => $q];
                };
                foreach ($direct as $du) {
                    $add($du);
                }
                foreach ($htmls as $pu => $html) {
                    foreach (self::pageMedia((string) $pu, $html, $hint) as $lnk) {
                        $add($lnk);
                    }
                }
                if ($c) {
                    return ['urls' => $c];
                }
                return ['error' => L('🤷 توی این صفحه لینک مستقیم فایل پیدا نکردم 😕', '🤷 I couldn’t find a direct file link on that page 😕') . "\n" . L('یه نتیجه‌ی دیگه رو امتحان کن.', 'try another result.')];
            }
            return ['urls' => [['url' => $q, 'name' => '', 'size' => 0, 'src' => self::hostOf($q), 'srcUrl' => $q]]];
        }

        if (in_array($hint, self::SOFT_HINTS, true) && preg_match('/[A-Za-z0-9]/', $q) && !preg_match('/\s{3,}/', $q)) {
            $repo = self::findRepo($q);
            if ($repo !== null) {
                return self::fromGithub($repo[0], $repo[1], $hint, $ver);
            }
        }
        return self::fromWeb($job);
    }

    private static function gh(string $path): ?array
    {
        $h = [];
        if (($t = trim((string) env('GITHUB_TOKEN', ''))) !== '') {
            $h[] = 'Authorization: Bearer ' . $t;
        }
        return Http::getJson('https://api.github.com' . $path, $h, 12);
    }

    private static function norm(string $s): string
    {
        return (string) preg_replace('/[^a-z0-9]/', '', strtolower($s));
    }

    private static function findRepo(string $q): ?array
    {
        $nq = self::norm($q);
        if (strlen($nq) < 3) {
            return null;
        }
        $r = self::gh('/search/repositories?q=' . rawurlencode($q) . '&sort=stars&order=desc&per_page=6');
        foreach ((array) ($r['items'] ?? []) as $it) {
            $nr = self::norm((string) ($it['name'] ?? ''));
            if ($nr === '') {
                continue;
            }
            if ($nr === $nq || (strlen($nr) >= 3 && str_contains($nq, $nr))) {
                return [(string) $it['owner']['login'], (string) $it['name']];
            }
        }
        return null;
    }

    private static function fromGithub(string $o, string $r, string $hint, string $ver): array
    {
        $src    = 'GitHub: ' . $o . '/' . $r;
        $srcUrl = 'https://github.com/' . $o . '/' . $r;

        if ($hint === 'source') {
            return self::sourceZip($o, $r, $src, $srcUrl);
        }
        $rel = self::release($o, $r, $ver);
        if ($rel === null || empty($rel['assets'])) {
            if ($hint === '' && $ver === '') {
                return self::sourceZip($o, $r, $src, $srcUrl);
            }
            return ['error' => L('🤷 برای این پروژه فایل آماده‌ای پیدا نکردم 😕', '🤷 I couldn’t find a ready-made file for this project 😕')];
        }
        $tag = (string) ($rel['tag_name'] ?? '');
        $re  = self::hintRegex($hint);
        $all = [];
        foreach ((array) $rel['assets'] as $a) {
            $n = (string) ($a['name'] ?? '');
            if ($n === '' || empty($a['browser_download_url']) || preg_match('/\.(sha\d+|md5|sig|asc|minisig|pem|sbom|blockmap|ya?ml|txt|json)$/i', $n)) {
                continue;
            }
            if ($re !== null && !preg_match($re, $n)) {
                continue;
            }
            $all[] = ['url' => (string) $a['browser_download_url'], 'name' => $n, 'size' => (int) ($a['size'] ?? 0),
                      'src' => $src . ($tag !== '' ? ' · ' . $tag : ''), 'srcUrl' => 'https://github.com/' . $o . '/' . $r . '/releases' . ($tag !== '' ? '/tag/' . rawurlencode($tag) : ''), 'score' => self::score($n, $hint)];
        }
        if (!$all) {
            return ['error' => L('🤷 فایلی با این نوع تو آخرین نسخه‌ی این پروژه نبود 😕', '🤷 This project’s release has no file of that type 😕')];
        }
        usort($all, static fn($a, $b) => [$b['score'], $a['size']] <=> [$a['score'], $b['size']]);
        if (count($all) === 1) {
            return ['urls' => $all];
        }
        if (in_array($hint, ['apk', 'win'], true) && $all[0]['score'] > $all[1]['score']) {
            return ['urls' => $all];
        }
        return ['choose' => array_slice($all, 0, 8)];
    }

    private static function sourceZip(string $o, string $r, string $src, string $srcUrl): array
    {
        $info = self::gh("/repos/{$o}/{$r}");
        if (!is_array($info) || empty($info['name'])) {
            return ['error' => L('🤷 این ریپو رو پیدا نکردم 😕', '🤷 I couldn’t find this repository 😕')];
        }
        $bytes = (int) ($info['size'] ?? 0) * 1024;
        if ($bytes > self::maxBytes()) {
            return ['error' => L('📦 حجم این ریپو از سقف ', '📦 This repository’s size is over the ') . self::limitText() . L(' بیشتره 😕', ' limit 😕')];
        }
        $branch = (string) ($info['default_branch'] ?? 'main');
        return ['urls' => [['url' => "https://api.github.com/repos/{$o}/{$r}/zipball", 'name' => "{$r}-{$branch}.zip", 'size' => $bytes, 'src' => $src . L(' (سورس)', ' (source)'), 'srcUrl' => $srcUrl]]];
    }

    private static function release(string $o, string $r, string $ver): ?array
    {
        if ($ver === '') {
            $x = self::gh("/repos/{$o}/{$r}/releases/latest");
            if (is_array($x) && !empty($x['assets'])) {
                return $x;
            }
            $list = self::gh("/repos/{$o}/{$r}/releases?per_page=8");
        } else {
            $list = self::gh("/repos/{$o}/{$r}/releases?per_page=50");
        }
        $list = array_values(array_filter((array) $list, static fn($x) => is_array($x) && empty($x['draft'])));
        if ($ver === '') {
            foreach ($list as $rel) {
                if (!empty($rel['assets'])) {
                    return $rel;
                }
            }
            return null;
        }
        $v = ltrim(strtolower($ver), 'v');
        foreach ($list as $rel) {
            if (ltrim(strtolower((string) ($rel['tag_name'] ?? '')), 'v') === $v) {
                return $rel;
            }
        }
        foreach ($list as $rel) {
            if (str_contains(strtolower((string) ($rel['tag_name'] ?? '')), $v)) {
                return $rel;
            }
        }
        return null;
    }

    private static function hintRegex(string $h): ?string
    {
        return match ($h) {
            '' => null,
            'apk'    => '/\.(apk|xapk|apks)$/i',
            'win'    => '/(\.(exe|msi)$|win(dows)?[-_. ]|[-_. ]win)/i',
            'mac'    => '/(\.(dmg|pkg)$|mac|darwin|osx)/i',
            'linux'  => '/(\.(appimage|deb|rpm)$|linux|\.tar\.(gz|xz)$)/i',
            'ios'    => '/\.ipa$/i',
            'image'  => '/\.(jpe?g|png|webp|gif|bmp)$/i',
            'source' => null,
            default  => preg_match('/^[a-z0-9]{2,6}$/', $h) ? '/\.' . $h . '$/i' : null,
        };
    }

    private static function score(string $name, string $hint): int
    {
        $n = strtolower($name);
        $s = 0;
        if ($hint === 'apk') {
            if (preg_match('/arm64/', $n)) {
                $s += 4;
            } elseif (str_contains($n, 'universal')) {
                $s += 3;
            } elseif (preg_match('/x86|armeabi/', $n)) {
                $s -= 1;
            }
        } else {
            if (preg_match('/x64|amd64|x86[_-]64/', $n)) {
                $s += 2;
            }
            if (preg_match('/arm64|aarch64/', $n)) {
                $s += 1;
            }
        }
        return $s;
    }

    private static function fromWeb(array $job): array
    {
        $q      = (string) $job['q'];
        $hint   = (string) $job['hint'];
        $images = $hint === 'image';
        $re     = self::hintRegex($hint);
        $words  = $hint !== '' && !$images && $hint !== 'source' ? ' ' . $hint : '';
        $res    = WebSearch::files($q . $words . ($images ? '' : ' download'), $images);
        if ($res === null) {
            return ['error' => L('⚠️ جستجو الان کار نمی‌کنه؛', '⚠️ Search isn’t working right now;') . "\n" . L('لینک مستقیم فایل رو بفرست یا یه‌کم دیگه امتحان کن.', 'send a direct link or try again in a bit.')];
        }

        $cands = [];
        $seen  = [];
        $add   = static function (string $u, string $title) use (&$cands, &$seen): void {
            $k = md5($u);
            if (isset($seen[$k]) || count($cands) >= 12) {
                return;
            }
            $seen[$k] = true;
            $cands[]  = ['url' => $u, 'name' => '', 'size' => 0, 'src' => self::hostOf($u), 'srcUrl' => $u, 'title' => $title];
        };

        if ($images) {
            foreach (array_slice((array) ($res['images'] ?? []), 0, 8) as $im) {
                $u = is_array($im) ? (string) ($im['url'] ?? '') : (string) $im;
                if (preg_match('#^https?://#i', $u)) {
                    $add($u, $q);
                }
            }
        } else {
            $pages = [];
            foreach ((array) ($res['results'] ?? []) as $it) {
                $u = (string) ($it['url'] ?? '');
                if (!preg_match('#^https?://#i', $u)) {
                    continue;
                }
                if (self::looksFile($u, $re, $hint)) {
                    $add($u, (string) ($it['title'] ?? ''));
                } else {
                    $pages[] = [$u, (string) ($it['title'] ?? '')];
                }
            }
            $titles = [];
            $purls  = [];
            foreach (array_slice($pages, 0, 10) as [$pu, $pt]) {
                $titles[$pu] = $pt;
                $purls[]     = $pu;
            }
            [$htmls, $direct] = self::readPages($purls);
            foreach ($direct as $du) {
                $add($du, (string) ($titles[$du] ?? ''));
            }
            foreach ($htmls as $pu => $html) {
                if (!preg_match_all('/(?:href|src)\s*=\s*["\']([^"\'#<>\s]+)["\']/i', $html, $mm)) {
                    continue;
                }
                foreach ($mm[1] as $lnk) {
                    $abs = self::absUrl((string) $pu, html_entity_decode($lnk));
                    if ($abs !== null && self::looksFile($abs, $re, $hint)) {
                        $add($abs, (string) ($titles[$pu] ?? ''));
                    }
                }
            }
        }

        if (!$cands) {
            return ['error' => L('🤷 لینک مستقیم فایل رو پیدا نکردم 😕', '🤷 I couldn’t find a direct file link 😕') . "\n" . L('اسم دقیق‌تر یا لینک مستقیمش رو بفرست.', 'send a more exact name or the direct link.')];
        }
        if (count($cands) > 1 && !$images) {
            $lines = [];
            foreach ($cands as $i => $c) {
                $lines[] = ($i + 1) . '. ' . mb_substr($c['url'], 0, 160) . ' | ' . mb_substr($c['title'], 0, 60);
            }
            $pick = AI::chat([
                ['role' => 'system', 'content' => 'Pick the one direct file link that best matches what the user asked for: the right file, from an official or trustworthy-looking source, not an ad or a dubious mirror. Reply with its number only.'],
                ['role' => 'user', 'content' => 'Request: ' . $q . ($hint !== '' ? " ({$hint})" : '') . "\n" . implode("\n", $lines)],
            ], ['tier' => 'lite', 'temperature' => 0, 'thinking' => null, 'deadline' => microtime(true) + 10]);
            $n = $pick !== null && preg_match('/\d+/', $pick, $pm) ? (int) $pm[0] - 1 : -1;
            if (isset($cands[$n]) && $n > 0) {
                array_unshift($cands, ...array_splice($cands, $n, 1));
            }
        }
        return ['urls' => $cands];
    }

    /** @return array{0: array<string,string>, 1: string[]} [html pages by url, links that are files themselves] */
    private static function readPages(array $urls): array
    {
        $mh   = curl_multi_init();
        $hs   = [];
        $bufs = [];
        $cts  = [];
        foreach (array_values($urls) as $i => $u) {
            $sh = self::safeHost((string) $u);
            if ($sh === null) {
                continue;
            }
            $bufs[$i] = '';
            $cts[$i]  = '';
            $ch = curl_init($u);
            curl_setopt_array($ch, [
                CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 12, CURLOPT_CONNECTTIMEOUT => 6,
                CURLOPT_USERAGENT      => self::FILE_UA, CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
                CURLOPT_RESOLVE        => ["{$sh['host']}:{$sh['port']}:{$sh['ip']}"], CURLOPT_ENCODING => '',
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
            $hs[$i] = [$ch, (string) $u];
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

    private static function mediaRe(string $hint): string
    {
        return '/\.(' . ($hint === 'mp3' ? self::AUDIO_EXT : self::VIDEO_EXT) . ')$/i';
    }

    /** @return string[] direct audio/video file links found in a page's HTML */
    private static function pageMedia(string $pageUrl, string $html, string $hint): array
    {
        $out = [];
        if (preg_match_all('/(?:href|src)\s*=\s*["\']([^"\'#<>\s]+)["\']/i', $html, $mm)) {
            foreach ($mm[1] as $lnk) {
                $abs = self::absUrl($pageUrl, html_entity_decode($lnk));
                if ($abs !== null && self::looksFile($abs, self::mediaRe($hint), $hint)) {
                    $out[$abs] = $abs;
                }
            }
        }
        return array_values($out);
    }

    private static function looksFile(string $url, ?string $re, string $hint = ''): bool
    {
        $path = rawurldecode((string) parse_url($url, PHP_URL_PATH));
        if (preg_match('/\.(' . self::FILE_EXT . '|' . self::DOT_EXT . ')$/i', $path)) {
            return $re === null || preg_match($re, $path) === 1;
        }
        // پسوند دلخواه: اگه کاربر خودش نوع فایل رو گفته (مثلاً txt، psd، sql)، همون پسوند هم قبول می‌شه
        if (preg_match('/^[a-z0-9]{2,6}$/', $hint) && !in_array($hint, ['apk', 'win', 'mac', 'linux', 'ios', 'image', 'source'], true)
            && preg_match('/\.' . $hint . '$/i', $path)) {
            return true;
        }
        // لینک‌های دانلود بدون پسوند، مثل telegram.org/dl/android/apk: آخرین بخش آدرس خودش نوع فایله
        $last = strtolower(basename($path));
        if ($last !== '' && preg_match('/^(' . self::FILE_EXT . ')$/i', $last)) {
            return $hint === '' || $last === $hint || self::canonHint($last) === $hint;
        }
        return false;
    }

    private static function absUrl(string $base, string $href): ?string
    {
        if (preg_match('#^https?://#i', $href)) {
            return $href;
        }
        $p = parse_url($base);
        if (!is_array($p) || empty($p['host']) || empty($p['scheme'])) {
            return null;
        }
        $origin = $p['scheme'] . '://' . $p['host'] . (!empty($p['port']) ? ':' . $p['port'] : '');
        if (str_starts_with($href, '//')) {
            return $p['scheme'] . ':' . $href;
        }
        if (str_starts_with($href, '/')) {
            return $origin . $href;
        }
        if (preg_match('#^[a-z][a-z0-9+.-]*:#i', $href)) {
            return null;
        }
        $dir = preg_replace('#/[^/]*$#', '/', (string) ($p['path'] ?? '/'));
        return $origin . $dir . $href;
    }

    // ───────────────────────── دانلود امن ─────────────────────────

    /** @return array{ip:string,host:string,port:int}|null */
    private static function safeHost(string $url): ?array
    {
        $p = parse_url($url);
        if (!is_array($p) || empty($p['host']) || !in_array(strtolower((string) ($p['scheme'] ?? '')), ['http', 'https'], true) || isset($p['user']) || isset($p['pass'])) {
            return null;
        }
        $host = strtolower((string) $p['host']);
        $port = (int) ($p['port'] ?? (strtolower($p['scheme']) === 'https' ? 443 : 80));
        if (!in_array($port, [80, 443, 8080, 8443], true)) {
            return null;
        }
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $ips = [$host];
        } else {
            if (!str_contains($host, '.') || str_ends_with($host, '.local') || str_ends_with($host, '.internal') || str_ends_with($host, '.localhost')) {
                return null;
            }
            $ips = gethostbynamel($host) ?: [];
        }
        if (!$ips) {
            return null;
        }
        foreach ($ips as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                return null;
            }
            $l = ip2long($ip);
            if ($l !== false && ($l & 0xFFC00000) === 0x64400000) { // 100.64.0.0/10
                return null;
            }
        }
        return ['ip' => $ips[0], 'host' => $host, 'port' => $port];
    }

    private static function pullFile(string $url, string $dest, int $max, callable $tick, string $hint): array
    {
        $allowHtml = in_array($hint, ['html', 'htm'], true);
        for ($hop = 0; $hop < 6; $hop++) {
            $sh = self::safeHost($url);
            if ($sh === null) {
                return ['ok' => false, 'err' => 'blocked'];
            }
            $fh = @fopen($dest, 'wb');
            if (!$fh) {
                return ['ok' => false, 'err' => 'net'];
            }
            $status = 0;
            $loc    = '';
            $mime   = '';
            $len    = -1;
            $disp   = '';
            $got    = 0;
            $abort  = '';
            $ch     = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_FOLLOWLOCATION  => false,
                CURLOPT_RESOLVE         => ["{$sh['host']}:{$sh['port']}:{$sh['ip']}"],
                CURLOPT_USERAGENT       => self::FILE_UA,
                CURLOPT_HTTPHEADER      => ['Accept: */*'],
                CURLOPT_CONNECTTIMEOUT  => 10,
                CURLOPT_TIMEOUT         => 1500,
                CURLOPT_LOW_SPEED_LIMIT => 2048,
                CURLOPT_LOW_SPEED_TIME  => 45,
                CURLOPT_SSL_VERIFYPEER  => true,
                CURLOPT_PROTOCOLS       => CURLPROTO_HTTP | CURLPROTO_HTTPS,
                CURLOPT_NOPROGRESS      => false,
                CURLOPT_PROGRESSFUNCTION => static function ($c, $dlT, $dlN) use ($tick, &$status): int {
                    if ($status === 200 || $status === 206) {
                        $tick((int) $dlN, (int) $dlT);
                    }
                    return 0;
                },
                CURLOPT_HEADERFUNCTION  => static function ($c, string $line) use (&$status, &$loc, &$mime, &$len, &$disp): int {
                    $t = trim($line);
                    if (preg_match('#^HTTP/\S+\s+(\d{3})#i', $t, $m)) {
                        $status = (int) $m[1];
                        [$loc, $mime, $len, $disp] = ['', '', -1, ''];
                    } elseif (stripos($t, 'location:') === 0) {
                        $loc = trim(substr($t, 9));
                    } elseif (stripos($t, 'content-type:') === 0) {
                        $mime = strtolower(trim(explode(';', substr($t, 13))[0]));
                    } elseif (stripos($t, 'content-length:') === 0) {
                        $len = (int) trim(substr($t, 15));
                    } elseif (stripos($t, 'content-disposition:') === 0) {
                        $disp = trim(substr($t, 20));
                    }
                    return strlen($line);
                },
                CURLOPT_WRITEFUNCTION   => static function ($c, string $data) use ($fh, &$status, &$mime, &$len, &$got, &$abort, $max, $allowHtml): int {
                    if ($status < 200 || $status >= 300) {
                        return strlen($data);
                    }
                    if ($got === 0) {
                        if ($len > $max) {
                            $abort = 'big';
                            return 0;
                        }
                        if (!$allowHtml && ($mime === 'text/html' || $mime === 'application/xhtml+xml' || preg_match('/^\s*<(!doctype\s+html|html|head|body)/i', substr($data, 0, 512)) === 1)) {
                            $abort = 'html';
                            return 0;
                        }
                    }
                    $got += strlen($data);
                    if ($got > $max) {
                        $abort = 'big';
                        return 0;
                    }
                    return (int) fwrite($fh, $data);
                },
            ]);
            curl_exec($ch);
            $errno = curl_errno($ch);
            curl_close($ch);
            fclose($fh);

            if ($abort !== '') {
                return ['ok' => false, 'err' => $abort];
            }
            if ($status >= 300 && $status < 400 && $loc !== '') {
                $next = self::absUrl($url, $loc);
                if ($next === null) {
                    return ['ok' => false, 'err' => 'net'];
                }
                $url = $next;
                continue;
            }
            if ($errno !== 0) {
                return ['ok' => false, 'err' => 'net'];
            }
            if ($status < 200 || $status >= 300) {
                return ['ok' => false, 'err' => 'http'];
            }
            if ($got <= 0) {
                return ['ok' => false, 'err' => 'net'];
            }
            return ['ok' => true, 'mime' => $mime, 'disp' => $disp, 'final' => $url, 'size' => $got];
        }
        return ['ok' => false, 'err' => 'net'];
    }

    private static function fileName(string $given, string $disp, string $finalUrl, string $mime): string
    {
        $name = $given;
        if ($name === '' && $disp !== '') {
            if (preg_match("/filename\\*\\s*=\\s*(?:UTF-8|utf-8)''([^;]+)/", $disp, $m)) {
                $name = rawurldecode(trim($m[1], " \"'"));
            } elseif (preg_match('/filename\s*=\s*"?([^";]+)"?/i', $disp, $m)) {
                $name = trim($m[1]);
            }
        }
        if ($name === '') {
            $name = rawurldecode(basename((string) parse_url($finalUrl, PHP_URL_PATH)));
        }
        $name = (string) preg_replace('/[\x00-\x1F\x7F\\\\\/:*?"<>|]+/u', '_', $name);
        $name = mb_substr(trim($name, " ._"), 0, 100);
        if ($name === '') {
            $name = 'file';
        }
        if (pathinfo($name, PATHINFO_EXTENSION) === '' && isset(self::MIME_EXT[$mime])) {
            $name .= '.' . self::MIME_EXT[$mime];
        }
        return $name;
    }

    // ───────────────────────── ارسال ─────────────────────────

    private static function mediaKind(string $ext, string $mime): string
    {
        if (str_starts_with($mime, 'video/') || preg_match('/^(' . self::VIDEO_EXT . ')$/', $ext) === 1) {
            return 'video';
        }
        if (str_starts_with($mime, 'audio/') || preg_match('/^(' . self::AUDIO_EXT . ')$/', $ext) === 1) {
            return 'audio';
        }
        return 'document';
    }

    private static function send(array $job, string $file, string $name, string $caption, int $size, string $mime = ''): bool
    {
        $tg    = App::tg();
        $reply = !empty($job['reply_to']) ? ['message_id' => (int) $job['reply_to'], 'allow_sending_without_reply' => true] : null;
        $ext   = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $kind  = self::mediaKind($ext, $mime);

        if ($size <= Downloader::BOT_API_BYTES) {
            self::show($job, L('📤 دارم می‌فرستمش...', '📤 Sending it...'));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true) && $size <= 10 * 1048576) {
                $mime = $ext === 'png' ? 'image/png' : ($ext === 'webp' ? 'image/webp' : 'image/jpeg');
                $r = $tg->call('sendPhoto', [
                    'chat_id'          => $job['chat'],
                    'photo'            => new CURLFile($file, $mime, $name),
                    'caption'          => $caption,
                    'parse_mode'       => 'HTML',
                    'reply_parameters' => $reply,
                ], 180);
                if (!empty($r['ok'])) {
                    return true;
                }
            }
            if ($kind !== 'document') {
                $r = $tg->call($kind === 'video' ? 'sendVideo' : 'sendAudio', [
                    'chat_id'          => $job['chat'],
                    $kind              => new CURLFile($file, $mime !== '' ? $mime : 'application/octet-stream', $name),
                    'caption'          => $caption,
                    'parse_mode'       => 'HTML',
                    'reply_parameters' => $reply,
                ] + ($kind === 'video' ? ['supports_streaming' => true] : []), 900);
                if (!empty($r['ok'])) {
                    return true;
                }
            }
            $r = $tg->call('sendDocument', [
                'chat_id'          => $job['chat'],
                'document'         => new CURLFile($file, 'application/octet-stream', $name),
                'caption'          => $caption,
                'parse_mode'       => 'HTML',
                'reply_parameters' => $reply,
            ], 900);
            if (empty($r['ok'])) {
                logWarn('gf', 'sendDocument failed: ' . mb_substr((string) ($r['description'] ?? ''), 0, 200));
            }
            return !empty($r['ok']);
        }
        return self::sendMt($job, $file, $name, $caption, $kind, $mime)
            || ($kind !== 'document' && self::sendMt($job, $file, $name, $caption, 'document', $mime));
    }

    private static function sendMt(array $job, string $file, string $name, string $caption, string $kind = 'document', string $mime = ''): bool
    {
        if (!Downloader::mtproto()) {
            self::$err = 'MTProto is off: API_ID / API_HASH are not set';
            return false;
        }
        $tg   = App::tg();
        $job2 = [
            'session_file' => NULLINI_DATA . '/mtproto' . (App::botIndex() === 0 ? '' : (string) App::botIndex()) . '.session',
            'api_id'       => (int) (env('API_ID') ?: env('TG_API_ID', 0)),
            'api_hash'     => trim((string) (env('API_HASH') ?: env('TG_API_HASH', ''))),
            'bot_token'    => $tg->token(),
            'test'         => App::botIndex() === 2 ? ['dc' => (int) env('TEST_DC_ID', 2), 'ip' => (string) env('TEST_DC_IP', '149.154.167.40'), 'port' => (int) env('TEST_DC_PORT', 443)] : null,
            'chat'         => (int) $job['chat'],
            'reply_to'     => (int) $job['reply_to'],
            'file'         => $file,
            'name'         => $name,
            'kind'         => $kind,
            'mime'         => $mime !== '' ? $mime : null,
            'thumb'        => '',
            'caption'      => $caption,
            'duration'     => 0,
            'width'        => 0,
            'height'       => 0,
            'title'        => '',
            'performer'    => '',
        ];
        $out  = null;
        $last = 0.0;
        $res  = self::runMt($job2, self::jobTime() - 120, static function (string $line) use ($job, &$out, &$last): void {
            $j = json_decode($line, true);
            if (!is_array($j)) {
                return;
            }
            if (isset($j['ok'])) {
                $out = $j;
                return;
            }
            if (isset($j['p'], $j['t']) && microtime(true) - $last >= 3.5) {
                $last = microtime(true);
                $pct  = $j['t'] > 0 ? (int) floor($j['p'] / $j['t'] * 100) : 0;
                self::show($job, '📤 ' . Downloader::bar($pct) . ' ' . fa((string) $pct) . L('٪', '%') . "\n📦 " . Downloader::mb((float) $j['p']) . L(' از ', ' of ') . Downloader::mb((float) $j['t']));
            }
        });
        if (empty($out['ok']) || empty($out['msg_id'])) {
            self::$err = 'exit ' . $res['code'] . ': ' . trim((string) ($out['error'] ?? mb_substr(trim($res['err']), -300)));
            logError('gf', 'mtproto upload failed (' . self::$err . ')');
            return false;
        }
        return true;
    }

    private static function runMt(array $job2, int $timeout, callable $onLine): array
    {
        $cmd  = ['timeout', '-k', '5', (string) $timeout, 'python3', NULLINI_ROOT . '/bin/mtup.py'];
        $env  = ['NL_JOB' => json_encode($job2, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'HOME' => '/tmp', 'PATH' => '/usr/local/bin:/usr/bin:/bin', 'LANG' => 'C.UTF-8'];
        $proc = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, $env);
        if (!is_resource($proc)) {
            return ['code' => -1, 'out' => '', 'err' => 'proc_open failed'];
        }
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
        $err = $buf = '';
        $end = time() + $timeout + 15;
        while (true) {
            $r = [$pipes[1], $pipes[2]];
            $w = $e = null;
            if (@stream_select($r, $w, $e, 1) === false) {
                break;
            }
            foreach ($r as $p) {
                $chunk = (string) fread($p, 65536);
                if ($p === $pipes[1]) {
                    $buf .= $chunk;
                    while (($nl = strpos($buf, "\n")) !== false) {
                        $onLine(rtrim(substr($buf, 0, $nl), "\r"));
                        $buf = substr($buf, $nl + 1);
                    }
                } else {
                    $err = substr($err . $chunk, -65536);
                }
            }
            if (feof($pipes[1]) && feof($pipes[2])) {
                break;
            }
            if (time() > $end) {
                proc_terminate($proc, 9);
                break;
            }
        }
        fclose($pipes[1]);
        fclose($pipes[2]);
        return ['code' => proc_close($proc), 'out' => '', 'err' => $err];
    }

    // ───────────────────────── رابط ─────────────────────────

    private static function askChoice(array $job, string $token, array $cands): void
    {
        $rows = [];
        foreach ($cands as $i => $c) {
            $label = '📦 ' . mb_substr((string) $c['name'], 0, 40) . (!empty($c['size']) ? ' · ' . Downloader::mb((float) $c['size']) : '');
            $rows[] = [btn($label, "gf:{$token}:{$i}", $i === 0 ? 'success' : 'primary')];
        }
        $rows[] = [btn(L('✖️ بیخیال', '✖️ Cancel'), "gf:{$token}:x", null)];
        self::show($job, L("📥 چند تا فایل پیدا کردم؛\nکدوم رو بفرستم؟ 👇\n🔗 ", "📥 I found several files;\nwhich one should I send? 👇\n🔗 ") . h((string) ($cands[0]['src'] ?? '')), ikb($rows));
    }

    private static function show(array $job, string $html, ?array $kb = null): void
    {
        if (!empty($job['imid'])) {
            Rich::editInline((string) $job['imid'], $html, $kb);
            return;
        }
        Rich::editHtml($job['chat'], (int) $job['mid'], $html, $kb);
    }

    private static function fail(array $job, string $html): void
    {
        self::show($job, $html);
        if (!empty($job['owner'])) {
            Store::delete('gf_busy', (string) $job['owner']);
        }
    }

    private static function errText(string $err): string
    {
        return match ($err) {
            'html'    => L('🚫 این لینک یه صفحه‌ی وبه، نه خود فایل 😕', '🚫 That link is a web page, not the file itself 😕') . "\n" . L('لینک مستقیم فایل رو بفرست.', 'send the direct file link.'),
            'big'     => L('📦 حجمش از سقف ', '📦 Its size is over the ') . self::limitText() . L(' بیشتره 😕', ' limit 😕'),
            'blocked' => L('🚫 این آدرس مجاز نیست.', '🚫 This address isn’t allowed.'),
            'http'    => L('🚫 سرور فایل اجازه‌ی دانلود نداد یا فایل پیدا نشد 😕', '🚫 The file server refused the download or the file wasn’t found 😕'),
            default   => L('❌ نتونستم دانلودش کنم 😕', '❌ Couldn’t download it 😕') . "\n" . L('سرور فایل جواب نداد.', 'the file server didn’t respond.'),
        };
    }

    private static function caption(array $c, string $name, int $size): string
    {
        $bot   = App::botUsername();
        $ext   = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $kind  = self::mediaKind($ext, '');
        $icon  = $kind === 'audio' ? '🎵' : ($kind === 'video' ? '🎬' : '📥');
        $title = trim((string) ($c['title'] ?? ''));
        if ($title === '') {
            $title = trim((string) preg_replace('/[._]+/', ' ', pathinfo($name, PATHINFO_FILENAME)));
        }
        $d = dirMark($title);
        $t = $d . $icon . ' <b>' . h(mb_substr($title !== '' ? $title : $name, 0, 800)) . "</b>\n"
            . $d . '📦 ' . strtoupper($ext !== '' ? $ext : 'file') . ' · ' . Downloader::mb((float) $size) . "\n";
        $url = (string) ($c['srcUrl'] ?? '');
        $t  .= "\n";
        if ($url !== '' && strlen($url) <= 400) {
            $t .= $d . '🔗 <a href="' . h($url) . '">' . h(preg_replace('/^www\\./', '', self::hostOf($url))) . "</a>\n";
        }
        return $t . $d . "📥 <a href=\"https://t.me/{$bot}\">@{$bot}</a>";
    }

    // ───────────────────────── کمکی‌ها ─────────────────────────

    private static function parseArg(string $arg): array
    {
        $p    = array_map('trim', explode('|', $arg));
        $ver  = ltrim(trim((string) ($p[2] ?? '')), 'vV');
        $ver  = preg_match('/^[A-Za-z0-9._-]{1,30}$/', $ver) ? $ver : '';
        return ['q' => (string) ($p[0] ?? ''), 'hint' => self::canonHint((string) ($p[1] ?? '')), 'ver' => $ver];
    }

    private static function canonHint(string $h): string
    {
        $h = strtolower(trim($h, " .\t"));
        return match (true) {
            in_array($h, ['apk', 'android', 'اندروید', 'آندروید'], true)                  => 'apk',
            in_array($h, ['windows', 'win', 'ویندوز'], true)                              => 'win',
            in_array($h, ['mac', 'macos', 'osx', 'مک'], true)                             => 'mac',
            in_array($h, ['linux', 'لینوکس'], true)                                       => 'linux',
            in_array($h, ['ios', 'iphone', 'آیفون', 'ایفون'], true)                      => 'ios',
            in_array($h, ['image', 'photo', 'picture', 'img', 'عکس', 'تصویر'], true)     => 'image',
            in_array($h, ['source', 'src', 'repo', 'repository', 'code', 'سورس', 'ریپو'], true) => 'source',
            preg_match('/^[a-z0-9]{2,10}$/', $h) === 1                                    => $h,
            default                                                                       => '',
        };
    }

    private static function hostOf(string $url): string
    {
        return (string) (parse_url($url, PHP_URL_HOST) ?: $url);
    }

    private static function maxBytes(): int
    {
        return Downloader::maxBytes();
    }

    private static function limitText(): string
    {
        return Downloader::mb((float) self::maxBytes());
    }

    private static function jobTime(): int
    {
        return Downloader::mtproto() ? 3600 : 1200;
    }

    private static function spawn(string $token, int $pick): bool
    {
        if (!function_exists('exec') || in_array('exec', array_map('trim', explode(',', (string) ini_get('disable_functions'))), true)) {
            return false;
        }
        $cmd = 'timeout -k 10 ' . self::jobTime() . ' ' . escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(NULLINI_ROOT . '/bot.php')
            . ' --getfile ' . escapeshellarg($token) . ' ' . (int) $pick . ' ' . App::botIndex() . ' > /dev/null 2>&1 &';
        exec($cmd, $o, $code);
        return $code === 0;
    }

    private static function rmrf(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach ((array) glob($dir . '/{,.}[!.]*', GLOB_BRACE) as $f) {
            is_dir($f) ? self::rmrf($f) : @unlink($f);
        }
        @rmdir($dir);
    }

    public static function cleanupTmp(): void
    {
        foreach ((array) glob(self::TMP . '/*') as $f) {
            if (is_dir($f) && time() - (int) @filemtime($f) > self::jobTime() + 60) {
                self::rmrf($f);
            }
        }
    }
}
