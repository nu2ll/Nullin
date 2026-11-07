<?php

final class Wiki
{
    public static function lang(string $q): string
    {
        return preg_match('/\p{Arabic}/u', $q) ? 'fa' : 'en';
    }

    public static function search(string $q, int $limit = 6, ?string $lang = null): array
    {
        $q = mbTrim(mb_substr($q, 0, 200));
        if (mb_strlen($q) < 2) {
            return [];
        }
        $lang ??= self::lang($q);
        $list = Store::remember('cache', 'wiki:' . $lang . ':' . md5(mb_strtolower($q) . '|' . $limit), 6 * 3600, static function () use ($q, $limit, $lang) {
            $j = Http::getJson("https://{$lang}.wikipedia.org/w/api.php?" . http_build_query([
                'action' => 'query', 'format' => 'json', 'formatversion' => 2, 'redirects' => 1, 'generator' => 'search', 'gsrsearch' => $q, 'gsrlimit' => $limit,
                'prop' => 'extracts|pageimages|info|description', 'exintro' => 1, 'explaintext' => 1, 'exchars' => 1200, 'exlimit' => 'max',
                'piprop' => 'thumbnail', 'pithumbsize' => 500, 'inprop' => 'url',
            ]), ['User-Agent: NulliniBot/3.0 (https://t.me/NullinBot)'], 8);
            if (!is_array($j)) {
                return null;
            }
            $pages = (array) ($j['query']['pages'] ?? []);
            usort($pages, static fn($a, $b) => ($a['index'] ?? 99) <=> ($b['index'] ?? 99));
            $out = [];
            foreach ($pages as $p) {
                $ext = mbTrim((string) ($p['extract'] ?? ''));
                if ($ext === '' || !empty($p['missing'])) {
                    continue;
                }
                $out[] = [
                    'title' => (string) ($p['title'] ?? ''),
                    'desc'  => mb_substr((string) ($p['description'] ?? ''), 0, 120),
                    'text'  => mb_substr($ext, 0, 3500),
                    'thumb' => (string) ($p['thumbnail']['source'] ?? ''),
                    'url'   => (string) ($p['fullurl'] ?? "https://{$lang}.wikipedia.org/wiki/" . rawurlencode(str_replace(' ', '_', (string) ($p['title'] ?? '')))),
                    'lang'  => $lang,
                ];
            }
            return $out;
        });
        return is_array($list) ? $list : [];
    }

    public static function first(string $q): ?array
    {
        $l = self::search($q, 3);
        if (!$l && self::lang($q) === 'fa') {
            $l = self::search($q, 3, 'en');
        }
        return $l[0] ?? null;
    }

    public static function text(array $p): string
    {
        return '📖 <b>' . h($p['title']) . '</b>' . ($p['desc'] !== '' ? "\n<i>" . h($p['desc']) . '</i>' : '') . "\n\n"
            . h($p['text']) . "\n\n🔗 <a href=\"" . h($p['url']) . '">ادامه در ویکی‌پدیا</a>';
    }

    public static function keyboard(array $p): array
    {
        return ikb([[urlBtn('📖 ادامه در ویکی‌پدیا', $p['url'], null)]]);
    }

    public static function inline(string $id, array $p): array
    {
        // تو نتیجه‌ی اینلاین عکسِ URL جا نمی‌گیره؛ متن می‌ره و بعد از انتخاب، onChosen عکس (file_id) رو اضافه می‌کنه
        Store::set('wiki_pending', $id, $p);
        $a = Ui::article($id, 'wiki', '📖 ' . $p['title'], $p['desc'] !== '' ? $p['desc'] : mb_substr($p['text'], 0, 90), self::text($p), self::keyboard($p), true, null);
        if ($p['thumb'] !== '') {
            $a['thumbnail_url'] = $p['thumb'];
        }
        return $a;
    }

    public static function onChosen(string $id, string $imid, int $uid): void
    {
        $p = Store::get('wiki_pending', $id);
        if (!is_array($p)) {
            return;
        }
        $fid = null;
        if (($img = self::image($p)) !== null) {
            $fid = self::photoId($img);
            if ($fid === null) {
                logWarn('wiki', 'photo upload failed for ' . $p['title']);
            }
        }
        // با عکس: پیام rich با عکس؛ بدون عکس: همون متن به‌صورت پیام rich ادیت می‌شه
        $r = $fid !== null
            ? Rich::editInlinePhoto($imid, self::text($p), $fid, self::keyboard($p))
            : Rich::editInline($imid, self::text($p), self::keyboard($p));
        if (empty($r['ok'])) {
            logWarn('wiki', 'edit failed: ' . (string) ($r['description'] ?? ''));
        }
    }

    public static function photoId(string $url, int $timeout = 12): ?string
    {
        $chat = (string) App::cfg('storage_chat', '');
        $chat = $chat !== '' ? $chat : (string) (App::cfg('admins', [])[0] ?? '');
        if ($chat === '') {
            logWarn('wiki', 'photo: no storage_chat/admin configured (set STORAGE_CHAT_ID)');
            return null;
        }
        $bytes = '';
        foreach (array_unique([$url, preg_replace('#/thumb/(.+?)/\d+px-[^/]+$#', '/$1', $url)]) as $u) {
            if (!is_string($u) || $u === '') {
                continue;
            }
            $r = Http::request('GET', $u, ['timeout' => $timeout, 'max_bytes' => 5000000, 'headers' => [
                'User-Agent: NulliniBot/3.0 (https://t.me/NullinBot; bot@nullini.app) curl',
                'Accept: image/*',
            ] + (str_contains($u, 'wikimedia.org') ? [3 => 'Referer: https://fa.wikipedia.org/'] : [])]);
            $bytes = (string) ($r['body'] ?? '');
            if (($r['code'] ?? 0) === 200 && $bytes !== '' && @getimagesizefromstring($bytes) !== false) {
                break;
            }
            logWarn('wiki', 'photo download http ' . (int) ($r['code'] ?? 0) . ' ' . substr($u, 0, 120));
            $bytes = '';
        }
        if ($bytes === '') {
            return null;
        }
        $id = App::tg()->photoFileId($chat, $bytes);
        if ($id === null) {
            logWarn('wiki', 'photo sendPhoto to storage chat failed (bot must be able to message ' . $chat . ')');
        }
        return $id;
    }

    public static function image(array $p): ?string
    {
        $t = (string) ($p['thumb'] ?? '');
        return ($t !== '' && str_starts_with($t, 'https://') && !str_contains($t, 'raw.githubusercontent.com')) ? $t : null;
    }

    public static function reply(array $msg, string $q): void
    {
        $tg = App::tg();
        $tg->action($msg['chat']['id'], 'typing');
        $p = self::first($q);
        if ($p === null) {
            $tg->reply($msg, '📖 تو ویکی‌پدیا چیزی درباره‌ی «' . h(mb_substr($q, 0, 80)) . '» پیدا نکردم.');
            return;
        }
        Users::hit('wiki');
        Rich::replyTo($msg, self::text($p), self::keyboard($p), self::image($p), ['link_preview_options' => ['url' => $p['url'], 'prefer_small_media' => true]]);
    }
}
