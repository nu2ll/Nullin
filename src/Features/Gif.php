<?php

final class Gif
{
    private const API  = 'https://api.giphy.com/v1/gifs/';
    private const PAGE = 25;

    private static function clean(string $q): string
    {
        return mbTrim(mb_substr(preg_replace('/\s+/u', ' ', $q) ?? $q, 0, 50));
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
        $chat = self::pick($imgs, ['original', 'fixed_height', 'fixed_width', 'downsized_small', 'fixed_height_small', 'fixed_width_small'], 8000000);
        if ($id === '' || $chat === null) {
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
            'id'    => $id,
            'title' => mb_substr(trim((string) ($g['title'] ?? '')), 0, 100),
            'page'  => (string) ($g['url'] ?? ''),
            'chat'  => $chat,
            'thumb' => $thumb,
        ];
    }

    public static function keys(): array
    {
        $keys = array_map('trim', (array) App::cfg('giphy_keys', []));
        return array_values(array_unique(array_filter($keys, static fn($k) => $k !== '' && !preg_match('/^(PUT|YOUR)_/i', $k))));
    }

    // Same scheme as the AI keys: random start, skip keys that are cooling down,
    // remember the last HTTP code per key and rest a key after a limit or an outage.
    private static function call(string $path, array $params): ?array
    {
        $keys = self::keys();
        $n    = count($keys);
        if ($n === 0) {
            logError('gif', 'no GIPHY key configured (GIPHY_API_KEYS)');
            return null;
        }
        $deadline = microtime(true) + 12;
        $start    = random_int(0, $n - 1);
        for ($i = 0; $i < $n; $i++) {
            $key = $keys[($start + $i) % $n];
            if (AI::isCooling($key, 'giphy')) {
                continue;
            }
            $left = $deadline - microtime(true);
            if ($left < 2) {
                return null;
            }
            $r = Http::request('GET', self::API . $path . '?' . http_build_query($params + ['api_key' => $key]), [
                'headers' => ['Accept: application/json'],
                'timeout' => (int) max(3, min($left, 8)),
            ]);
            $code = (int) $r['code'];
            AI::noteKey('giphy', $key, $code);
            if ($code === 200 && $r['body'] !== null) {
                $j = json_decode((string) $r['body'], true);
                if (is_array($j)) {
                    return $j;
                }
                continue;
            }
            logWarn('gif', 'giphy key' . (($start + $i) % $n) . " HTTP {$code}");
            if ($code === 429) {
                AI::cool($key, 'giphy', time() + 3600, 'سقف ساعتی تموم شد');
            } elseif (in_array($code, [401, 403], true)) {
                AI::cool($key, 'giphy', time() + 3600, 'کلید نامعتبر یا بدون دسترسی');
            } elseif (in_array($code, [0, 408, 500, 502, 503, 504], true)) {
                AI::cool($key, 'giphy', time() + 120, 'شلوغه / جواب نداد');
            } else {
                return null;
            }
        }
        return null;
    }

    private static function byId(string $id): ?array
    {
        if (preg_match('/^[A-Za-z0-9]{3,40}$/', $id) !== 1) {
            return null;
        }
        $j = self::call($id, []);
        return is_array($j) && is_array($j['data'] ?? null) ? self::normalize((array) $j['data']) : null;
    }

    public static function fetch(string $q, int $offset = 0): ?array
    {
        $term   = self::clean($q);
        $params = ['limit' => self::PAGE, 'offset' => $offset];
        if ($term !== '') {
            $params += ['q' => $term];
        }
        $j = self::call($term === '' ? 'trending' : 'search', $params);
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
    }

    private static function fileId(string $url): ?string
    {
        $ck  = 'gif_fid:' . md5($url . '|' . App::botIndex());
        $hit = Store::getToday('cache', $ck);
        if (is_string($hit) && $hit !== '') {
            return $hit;
        }
        $fid = (static function () use ($url) {
            $chat = (string) App::cfg('storage_chat', '');
            $chat = $chat !== '' ? $chat : (string) (App::cfg('admins', [])[0] ?? '');
            if ($chat === '') {
                logWarn('gif', 'no storage_chat/admin configured (set STORAGE_CHAT_ID)');
                return null;
            }
            $tg  = App::tg();
            $r   = $tg->call('sendVideo', ['chat_id' => $chat, 'video' => $url, 'disable_notification' => true], 30);
            $id  = $r['result']['video']['file_id'] ?? $r['result']['animation']['file_id'] ?? null;
            $mid = (int) ($r['result']['message_id'] ?? 0);
            if ($mid > 0) {
                $tg->call('deleteMessage', ['chat_id' => $chat, 'message_id' => $mid], 8);
            }
            if (!is_string($id) || $id === '') {
                $why = $r === null ? 'no response' : ((string) ($r['description'] ?? '') ?: 'ok=' . (int) !empty($r['ok']) . ' result=[' . implode(',', array_keys((array) ($r['result'] ?? []))) . ']');
                logWarn('gif', 'file_id upload failed: ' . $why);
                return null;
            }
            return $id;
        })();
        if ($fid !== null) {
            Store::set('cache', $ck, $fid);
        }
        return $fid;
    }

    private static function caption(array $g): string
    {
        if (!App::cfg('giphy_caption', true)) {
            return '';
        }
        $p = (string) ($g['page'] ?? '');
        $src = L('🔗 منبع: ', '🔗 Source: ');
        return str_starts_with($p, 'https://') ? $src . '<a href="' . h($p) . '">GIPHY</a>' : $src . 'GIPHY';
    }

    public static function notice(string $kind, string $q = ''): string
    {
        $q = h(mb_substr($q, 0, 60));
        return match ($kind) {
            'none'  => L("🎞 تو GIPHY چیزی درباره‌ی «" . $q . "» پیدا نکردم؛\nیه جور دیگه بنویس.", "🎞 I found nothing on GIPHY for “" . $q . "”;\ntry wording it differently."),
            'send'  => L("😕 گیف پیدا شد ولی ارسالش به مشکل خورد؛\nدوباره امتحان کن.", "😕 The GIF was found but sending it failed;\ntry again."),
            default => L("😕 الان نتونستم به GIPHY وصل بشم؛\nیه‌کم دیگه امتحان کن.", "😕 I couldn’t reach GIPHY right now;\ntry again in a bit."),
        };
    }

    private static function track(string $q, int $uid, int $i, array $target = []): string
    {
        $hash = substr(md5($q . '|' . $uid . '|' . microtime(true)), 0, 12);
        Store::set('gif_state', $hash, ['q' => $q, 'u' => $uid, 'i' => $i, 't' => time(), 'b' => App::botIndex()] + $target);
        return $hash;
    }

    public static function bind(string $hash, array $target): void
    {
        $st = Store::get('gif_state', $hash);
        if (is_array($st)) {
            Store::set('gif_state', $hash, $target + $st);
        }
    }

    public static function sweep(int $budget = 100): int
    {
        $end  = microtime(true) + $budget;
        $done = 0;
        $keys = Db::run('SELECT k FROM kv WHERE ns = ? AND updated_at < ? LIMIT 3000', ['gif_state', Store::dayStart()])->fetchAll(PDO::FETCH_COLUMN);
        foreach ($keys as $k) {
            if (microtime(true) > $end) {
                break;
            }
            $st = Store::get('gif_state', (string) $k);
            $r  = null;
            if (is_array($st)) {
                $tgx = App::tgFor((int) ($st['b'] ?? 0));
                $where = !empty($st['im']) ? ['inline_message_id' => (string) $st['im']]
                    : (!empty($st['c']) && !empty($st['m']) ? ['chat_id' => $st['c'], 'message_id' => (int) $st['m']] : null);
                if ($where !== null && !empty($st['f'])) {
                    $r = $tgx->call('editMessageText', $where + ['rich_message' => Rich::videoPayload((string) ($st['cap'] ?? ''), (string) $st['f'], null)], 15);
                } elseif ($where !== null) {
                    $r = $tgx->call('editMessageReplyMarkup', $where + ['reply_markup' => ['inline_keyboard' => []]], 5);
                }
            }
            if ((int) ($r['error_code'] ?? 0) === 429) {
                break;
            }
            Store::delete('gif_state', (string) $k);
            $done++;
            usleep(60000);
        }
        return $done;
    }

    public static function keyboard(string $hash, string $q): array
    {
        return ikb([[
            btn('🔄', 'gif:n:' . $hash, null),
            ['text' => '🔎', 'switch_inline_query_current_chat' => 'gif ' . mb_substr($q, 0, 60), 'style' => 'primary'],
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
        $fid = self::fileId($g['chat']['url']);
        $res = $fid !== null ? Rich::sendVideo($chat, self::caption($g), $fid, (int) $msg['message_id'], self::keyboard($hash, $q)) : null;
        if (!empty($res['ok'])) {
            $sentId = (int) ($res['result']['message_id'] ?? 0);
            if ($sentId > 0) {
                self::bind($hash, ['c' => $chat, 'm' => $sentId, 'f' => $fid, 'cap' => self::caption($g)]);
            }
            Users::hit('gif:sent');
            return true;
        }
        logWarn('gif', 'rich video send failed for ' . $g['id'] . ': ' . (string) ($res['description'] ?? ''));
        $tg->reply($msg, self::notice('send'));
        return true;
    }

    public static function output(string $q, int $uid): ?array
    {
        $q = self::clean($q);
        if ($q === '') {
            return null;
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
        $fid = self::fileId($g['chat']['url']);
        if ($fid === null) {
            return ['text' => self::notice('send')];
        }
        $hash = self::track($q, $uid, $i, ['f' => $fid, 'cap' => self::caption($g)]);
        return ['video' => $fid, 'text' => self::caption($g), 'kb' => self::keyboard($hash, $q), 'gif' => $hash];
    }

    public static function inline(string $q, int $offset): ?array
    {
        $r = self::fetch($q, $offset);
        if ($r === null) {
            return null;
        }
        $results = [];
        foreach ($r['items'] as $n => $g) {
            if ($g['thumb'] === '') {
                continue;
            }
            $wait = L('🎞 دارم گیف میارم…', '🎞 Getting the GIF…');
            $item = Ui::article(
                'gif_' . $g['id'] . '_' . ($offset + $n),
                $g['thumb'],
                $g['title'] !== '' ? $g['title'] : L('گیف', 'GIF'),
                'GIPHY',
                $wait,
                ikb([[btn('Nullin', 'noop', null)]])
            );
            // The reply_markup placeholder above is what makes the message editable;
            // keep the rich body free of a second, useless Nullin button.
            $item['input_message_content'] = Rich::inlineContent($wait, null);
            $results[] = $item;
        }
        $next = $offset + self::PAGE;
        return ['results' => $results, 'next' => count($r['items']) >= self::PAGE && $next < min((int) $r['total'], 500) ? (string) $next : ''];
    }

    public static function onChosen(string $id, string $imid, int $uid, string $query): void
    {
        [$gid, $pos] = explode('_', $id, 2) + ['', '0'];
        $g   = self::byId($gid);
        $fid = $g !== null ? self::fileId($g['chat']['url']) : null;
        if ($g === null || $fid === null) {
            Rich::editInlineRich($imid, self::notice('send'));
            return;
        }
        $q   = self::clean(preg_replace('/^\S+\s*/u', '', $query) ?? '');
        $res = Rich::editInlineVideo($imid, self::caption($g), $fid, self::keyboard(self::track($q, $uid, max(0, (int) $pos), ['im' => $imid, 'f' => $fid, 'cap' => self::caption($g)]), $q));
        if (empty($res['ok'])) {
            logWarn('gif', 'chosen rich video edit failed: ' . (string) ($res['description'] ?? ''));
            Rich::editInlineRich($imid, self::notice('send'));
        }
    }

    public static function onCallback(array $cq, string $data): void
    {
        $tg   = App::tg();
        $hash = substr($data, 6);
        $st   = Store::getToday('gif_state', $hash);
        $uid  = (int) ($cq['from']['id'] ?? 0);
        $imid = (string) ($cq['inline_message_id'] ?? '');
        $chat = $cq['message']['chat']['id'] ?? null;
        $mid  = (int) ($cq['message']['message_id'] ?? 0);
        $target = $imid !== '' ? ['inline_message_id' => $imid] : ($chat !== null && $mid > 0 ? ['chat_id' => $chat, 'message_id' => $mid] : null);
        if (!is_array($st) || $target === null) {
            $tg->answer($cq, L("⌛ این گیف قدیمی شده؛\nدوباره ازش بخواه.", "⌛ This GIF has expired;\nask for it again."));
            return;
        }
        if ((int) ($st['u'] ?? 0) !== $uid) {
            $tg->alert($cq, L("⚠️ این گیف مال تو نیست؛\nخودت یکی بگیر 🙂", "⚠️ This GIF isn’t yours;\nget your own 🙂"));
            return;
        }
        $q   = (string) ($st['q'] ?? '');
        $i   = (int) ($st['i'] ?? 0);
        $o   = intdiv($i, self::PAGE) * self::PAGE;
        $k   = ($i % self::PAGE) + 1;
        $g   = null;
        $end = false;
        for ($n = 0; $n < 40; $n++) {
            $r = self::fetch($q, $o);
            if ($r === null) {
                break;
            }
            $items = $r['items'];
            if ($k >= count($items)) {
                $o += self::PAGE;
                if ($o >= min((int) $r['total'], 500)) {
                    $end = true;
                    break;
                }
                $k = 0;
                continue;
            }
            $g = $items[$k];
            $i = $o + $k;
            break;
        }
        if ($g === null) {
            $tg->answer($cq, $end
                ? L('🏁 همه‌ی گیف‌های این جستجو رو دیدی؛' . "\n" . 'یه کلمه‌ی دیگه امتحان کن.', '🏁 You’ve seen every GIF for this search;' . "\n" . 'try another word.')
                : L('🤷 گیف دیگه‌ای پیدا نکردم 😕', '🤷 I didn’t find any more GIFs 😕'));
            return;
        }
        $st['i']     = $i;
        if ($imid !== '') {
            $st['im'] = $imid;
        } elseif ($chat !== null && $mid > 0) {
            $st['c'] = $chat;
            $st['m'] = $mid;
        }
        $st['b'] = App::botIndex();
        $fid = self::fileId($g['chat']['url']);
        if ($fid !== null) {
            $st['f']   = $fid;
            $st['cap'] = self::caption($g);
        }
        Store::set('gif_state', $hash, $st);
        $kb  = self::keyboard($hash, $q);
        $res = $fid === null ? null : ($imid !== ''
            ? Rich::editInlineVideo($imid, self::caption($g), $fid, $kb)
            : Rich::editVideoMsg($chat, $mid, self::caption($g), $fid, $kb));
        $ok = !empty($res['ok']);
        if (!$ok) {
            logWarn('gif', 'rich video edit failed: ' . (string) ($res['description'] ?? ''));
        }
        $tg->answer($cq, $ok ? null : L("😕 نتونستم گیف بعدی رو بذارم؛\nدوباره بزن.", "😕 I couldn’t load the next GIF;\ntap again."));
    }
}
