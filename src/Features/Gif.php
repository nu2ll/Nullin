<?php

final class Gif
{
    private const API  = 'https://api.giphy.com/v1/gifs/';
    private const PAGE = 25;

    public static function enabled(): bool
    {
        return (string) App::cfg('giphy_key', '') !== '';
    }

    private static function clean(string $q): string
    {
        return mbTrim(mb_substr(preg_replace('/\s+/u', ' ', $q) ?? $q, 0, 100));
    }

    private static function pick(array $imgs, array $order, int $max): ?array
    {
        foreach ($order as $k) {
            $v   = $imgs[$k] ?? null;
            $url = is_array($v) ? (string) ($v['mp4'] ?? '') : '';
            if ($url === '' || !str_starts_with($url, 'https://')) {
                continue;
            }
            $size = (int) ($v['mp4_size'] ?? 0);
            if ($size === 0 || $size <= $max) {
                return ['url' => $url, 'w' => (int) ($v['width'] ?? 0), 'h' => (int) ($v['height'] ?? 0)];
            }
        }
        return null;
    }

    private static function normalize(array $g): ?array
    {
        $id   = (string) ($g['id'] ?? '');
        $imgs = (array) ($g['images'] ?? []);
        $chat = self::pick($imgs, ['original', 'fixed_height', 'fixed_width', 'downsized_small'], 8000000);
        $inl  = self::pick($imgs, ['fixed_height', 'fixed_width', 'fixed_height_small', 'fixed_width_small', 'downsized_small'], 1000000);
        if ($id === '' || ($chat === null && $inl === null)) {
            return null;
        }
        $thumb = '';
        foreach (['fixed_width_still', 'fixed_height_still', 'downsized_still', 'preview_gif'] as $k) {
            $u = (string) (((array) ($imgs[$k] ?? []))['url'] ?? '');
            if ($u !== '' && str_starts_with($u, 'https://')) {
                $thumb = $u;
                break;
            }
        }
        return [
            'id'     => $id,
            'title'  => mb_substr(trim((string) ($g['title'] ?? '')), 0, 100),
            'page'   => (string) ($g['url'] ?? ''),
            'chat'   => $chat ?? $inl,
            'inline' => $inl,
            'thumb'  => $thumb,
        ];
    }

    public static function fetch(string $q, int $offset = 0): ?array
    {
        $key = (string) App::cfg('giphy_key', '');
        if ($key === '') {
            return null;
        }
        $term = self::clean($q);
        $lang = preg_match('/[\x{0600}-\x{06FF}]/u', $term) === 1 ? 'fa' : 'en';
        $ck   = 'gif:' . md5($term . '|' . $lang . '|' . $offset);
        return Store::remember('cache', $ck, $term === '' ? 600 : 1800, static function () use ($key, $term, $lang, $offset) {
            $params = ['api_key' => $key, 'limit' => self::PAGE, 'offset' => $offset];
            if ($term !== '') {
                $params += ['q' => $term, 'lang' => $lang];
            }
            $j = Http::getJson(self::API . ($term === '' ? 'trending' : 'search') . '?' . http_build_query($params), [], 8);
            if (!is_array($j) || !is_array($j['data'] ?? null)) {
                return null;
            }
            $items = [];
            foreach ($j['data'] as $g) {
                $it = self::normalize((array) $g);
                if ($it !== null) {
                    $items[] = $it;
                }
            }
            return ['items' => $items, 'total' => (int) ($j['pagination']['total_count'] ?? 0)];
        });
    }

    private static function variants(array $g): array
    {
        $out = [];
        foreach ([$g['chat'] ?? null, $g['inline'] ?? null] as $v) {
            if (is_array($v) && !isset($out[$v['url']])) {
                $out[$v['url']] = $v;
            }
        }
        return array_values($out);
    }

    private static function caption(array $g): string
    {
        if (!App::cfg('giphy_caption', true)) {
            return '';
        }
        $p = (string) ($g['page'] ?? '');
        return str_starts_with($p, 'https://') ? '🎞 <a href="' . h($p) . '">GIPHY</a>' : '🎞 GIPHY';
    }

    private static function notice(string $kind, string $q = ''): string
    {
        $q = h(mb_substr($q, 0, 60));
        return match ($kind) {
            'off'   => L('🎞 گیف‌ها هنوز راه‌اندازی نشدن.', '🎞 GIFs aren’t set up yet.'),
            'none'  => L('🎞 برای «' . $q . '» گیفی پیدا نکردم.', '🎞 I couldn’t find a GIF for “' . $q . '”.'),
            'send'  => L('😕 گیف پیدا شد ولی ارسالش به مشکل خورد؛ دوباره امتحان کن.', '😕 I found a GIF but sending it failed; try again.'),
            default => L('😕 الان به سرویس گیف دسترسی ندارم؛ چند لحظه‌ی دیگه دوباره امتحان کن.', '😕 I can’t reach the GIF service right now; try again in a moment.'),
        };
    }

    private static function track(string $q, int $uid, int $i): string
    {
        $hash = substr(md5($q . '|' . $uid . '|' . microtime(true)), 0, 12);
        Store::set('gif_state', $hash, ['q' => $q, 'u' => $uid, 'i' => $i, 't' => time()]);
        return $hash;
    }

    public static function keyboard(string $hash, string $q): array
    {
        return ikb([[
            btn(L('🔄 یکی دیگه', '🔄 Another'), 'gif:n:' . $hash, null),
            ['text' => L('🔎 گیف‌های بیشتر', '🔎 More GIFs'), 'switch_inline_query_current_chat' => 'gif ' . mb_substr($q, 0, 60), 'style' => 'primary'],
        ]]);
    }

    public static function send(array $msg, string $q): bool
    {
        $tg   = App::tg();
        $chat = $msg['chat']['id'];
        $uid  = (int) ($msg['from']['id'] ?? 0);
        $q    = self::clean($q);
        if ($q === '') {
            $tg->reply($msg, Ui::guide('gif'));
            return true;
        }
        if (!self::enabled()) {
            $tg->reply($msg, self::notice('off'));
            return true;
        }
        $tg->action($chat, 'upload_video');
        $r = self::fetch($q);
        if ($r === null) {
            $tg->reply($msg, self::notice('fail'));
            return true;
        }
        $n = count($r['items']);
        if ($n === 0) {
            $tg->reply($msg, self::notice('none', $q));
            return true;
        }
        $i    = mt_rand(0, $n - 1);
        $g    = $r['items'][$i];
        $hash = self::track($q, $uid, $i);
        $params = [
            'chat_id'          => $chat,
            'caption'          => self::caption($g),
            'parse_mode'       => 'HTML',
            'reply_markup'     => self::keyboard($hash, $q),
            'reply_parameters' => ['message_id' => $msg['message_id'], 'allow_sending_without_reply' => true],
        ];
        foreach (self::variants($g) as $v) {
            $res = $tg->call('sendAnimation', $params + ['animation' => $v['url']], 30);
            if (!empty($res['ok'])) {
                Users::hit('gif:sent');
                return true;
            }
        }
        logWarn('gif', 'sendAnimation failed for ' . $g['id']);
        $tg->reply($msg, self::notice('send'));
        return true;
    }

    public static function output(string $q, int $uid): ?array
    {
        $q = self::clean($q);
        if ($q === '') {
            return null;
        }
        if (!self::enabled()) {
            return ['text' => self::notice('off')];
        }
        $r = self::fetch($q);
        if ($r === null) {
            return ['text' => self::notice('fail')];
        }
        if (!$r['items']) {
            return ['text' => self::notice('none', $q)];
        }
        $i = mt_rand(0, count($r['items']) - 1);
        $g = $r['items'][$i];
        $v = self::variants($g)[0];
        return ['anim' => $v['url'], 'text' => self::caption($g), 'kb' => self::keyboard(self::track($q, $uid, $i), $q)];
    }

    public static function inline(string $q, int $offset): ?array
    {
        $r = self::fetch($q, $offset);
        if ($r === null) {
            return null;
        }
        $results = [];
        foreach ($r['items'] as $g) {
            $v = $g['inline'];
            if ($v === null || $g['thumb'] === '') {
                continue;
            }
            $a = [
                'type'                => 'mpeg4_gif',
                'id'                  => 'gif_' . $g['id'],
                'mpeg4_url'           => $v['url'],
                'thumbnail_url'       => $g['thumb'],
                'thumbnail_mime_type' => 'image/gif',
            ];
            if ($v['w'] > 0 && $v['h'] > 0) {
                $a['mpeg4_width']  = $v['w'];
                $a['mpeg4_height'] = $v['h'];
            }
            if ($g['title'] !== '') {
                $a['title'] = $g['title'];
            }
            if (($cap = self::caption($g)) !== '') {
                $a['caption']    = $cap;
                $a['parse_mode'] = 'HTML';
            }
            $results[] = $a;
        }
        $next = $offset + self::PAGE;
        return ['results' => $results, 'next' => count($r['items']) >= self::PAGE && $next < min((int) $r['total'], 500) ? (string) $next : ''];
    }

    public static function onCallback(array $cq, string $data): void
    {
        $tg   = App::tg();
        $hash = substr($data, 6);
        $st   = Store::get('gif_state', $hash);
        $uid  = (int) ($cq['from']['id'] ?? 0);
        $imid = (string) ($cq['inline_message_id'] ?? '');
        $chat = $cq['message']['chat']['id'] ?? null;
        $mid  = (int) ($cq['message']['message_id'] ?? 0);
        $target = $imid !== '' ? ['inline_message_id' => $imid] : ($chat !== null && $mid > 0 ? ['chat_id' => $chat, 'message_id' => $mid] : null);
        if (!is_array($st) || $target === null) {
            $tg->answer($cq, L('⌛ این گیف قدیمی شده؛ دوباره بخواهش.', '⌛ This GIF is too old; ask for it again.'));
            return;
        }
        if ((int) ($st['u'] ?? 0) !== $uid) {
            $tg->alert($cq, L("این گیف مال تو نیست؛\nخودت بخواهش 🙂", "This GIF isn’t yours;\nask for your own 🙂"));
            return;
        }
        $q = (string) ($st['q'] ?? '');
        $i = (int) ($st['i'] ?? 0) + 1;
        $r = self::fetch($q, intdiv($i, self::PAGE) * self::PAGE);
        $g = $r !== null ? ($r['items'][$i % self::PAGE] ?? null) : null;
        if ($g === null) {
            $i = 0;
            $r = self::fetch($q);
            $g = $r !== null ? ($r['items'][0] ?? null) : null;
        }
        if ($g === null) {
            $tg->answer($cq, L('😕 گیف دیگه‌ای پیدا نشد.', '😕 No more GIFs found.'));
            return;
        }
        $st['i'] = $i;
        Store::set('gif_state', $hash, $st);
        $ok = false;
        foreach (self::variants($g) as $v) {
            $res = $tg->call('editMessageMedia', $target + [
                'media'        => ['type' => 'animation', 'media' => $v['url'], 'caption' => self::caption($g), 'parse_mode' => 'HTML'],
                'reply_markup' => self::keyboard($hash, $q),
            ], 30);
            if (!empty($res['ok'])) {
                $ok = true;
                break;
            }
        }
        $tg->answer($cq, $ok ? null : L('😕 نشد؛ دوباره بزن.', '😕 Didn’t work; tap again.'));
    }
}
