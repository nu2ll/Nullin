<?php

final class Screenshot
{
    private const API = 'https://pro.microlink.io/';

    private const SITE_ERRORS = [
        'EINVALURL'        => 'url',
        'EINVALURLCLIENT'  => 'url',
        'EFORBIDDENURL'    => 'url',
        'EMAXREDIRECTS'    => 'host',
        'EFATAL'           => 'host',
        'EUNREACHABLE'     => 'site',
        'EBRWSRTIMEOUT'    => 'timeout',
        'ETIMEOUT'         => 'timeout',
        'EPROXYNEEDED'     => 'antibot',
        'EINTEGRATION'     => 'antibot',
    ];

    private static string $why = 'fail';

    public static function keys(): array
    {
        $keys = array_map('trim', (array) App::cfg('screenshot_keys', []));
        return array_values(array_unique(array_filter($keys, static fn($k) => $k !== '' && !preg_match('/^(PUT|YOUR)_/i', $k))));
    }

    private static function publicHost(string $host): bool
    {
        if ($host === '' || $host === 'localhost' || preg_match('/\.(?:local|localhost|internal|lan|home|corp|intranet)$/', $host) === 1) {
            return false;
        }
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
        }
        return str_contains($host, '.') && preg_match('/^[\d.]+$/', $host) !== 1;
    }

    public static function parse(string $q): ?array
    {
        $q = trim($q);
        if ($q === '') {
            return null;
        }
        if (preg_match('~https?://[^\s<>"«»]+~iu', $q, $m) === 1) {
            $u = $m[0];
        } elseif (preg_match('~(?<![\p{L}\p{N}@./-])(?:[\p{L}\p{N}](?:[\p{L}\p{N}-]{0,61}[\p{L}\p{N}])?\.)+\p{L}{2,24}(?::\d{2,5})?(?:[/?#][^\s<>"«»]*)?~iu', $q, $m) === 1) {
            $u = 'https://' . $m[0];
        } else {
            return null;
        }
        $u = (string) preg_replace('~[.,;:!?،؛؟»"\'<>]+$~u', '', $u);
        if (substr_count($u, ')') > substr_count($u, '(')) {
            $u = rtrim($u, ')');
        }
        if ($u === '' || mb_strlen($u) > 1000) {
            return null;
        }
        $parts = parse_url($u);
        if (!is_array($parts) || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])) {
            return null;
        }
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        if (!in_array($scheme, ['http', 'https'], true)) {
            return null;
        }
        $host = mb_strtolower(trim((string) $parts['host'], '[]'));
        if (preg_match('/[^\x20-\x7e]/', $host) === 1) {
            $ascii = function_exists('idn_to_ascii') ? idn_to_ascii($host, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTF46) : false;
            if (!is_string($ascii) || $ascii === '') {
                return null;
            }
            $host = $ascii;
            $u    = $scheme . '://' . $host . (isset($parts['port']) ? ':' . $parts['port'] : '') . ($parts['path'] ?? '')
                . (isset($parts['query']) ? '?' . $parts['query'] : '') . (isset($parts['fragment']) ? '#' . $parts['fragment'] : '');
        }
        if (!self::publicHost($host)) {
            return null;
        }
        return ['url' => $u, 'host' => $host];
    }

    private static function request(string $url): ?string
    {
        self::$why = 'fail';
        $keys      = self::keys();
        $n         = count($keys);
        if ($n === 0) {
            logError('screenshot', 'no Microlink key configured (MICROLINK_API_KEYS)');
            self::$why = 'nokey';
            return null;
        }
        $query    = http_build_query(['url' => $url, 'screenshot' => 'true', 'meta' => 'false'], '', '&', PHP_QUERY_RFC3986);
        $deadline = microtime(true) + 60;
        $start    = random_int(0, $n - 1);
        $limited  = false;
        for ($i = 0; $i < $n; $i++) {
            $idx = ($start + $i) % $n;
            $key = $keys[$idx];
            if (AI::isCooling($key, 'screenshot')) {
                $limited = true;
                continue;
            }
            $left = $deadline - microtime(true);
            if ($left < 10) {
                self::$why = 'timeout';
                return null;
            }
            $r    = Http::request('GET', self::API . '?' . $query, [
                'headers'      => ['x-api-key: ' . $key, 'Accept: application/json'],
                'timeout'      => (int) min($left, 50),
                'want_headers' => true,
            ]);
            $code = (int) $r['code'];
            $body = (string) ($r['body'] ?? '');
            $j    = $body !== '' && $body[0] === '{' ? json_decode($body, true) : null;
            $shot = is_array($j) ? (string) ($j['data']['screenshot']['url'] ?? '') : '';
            if ($code === 200 && $shot !== '') {
                $img = Http::request('GET', $shot, ['timeout' => (int) max(10, min($deadline - microtime(true), 30))]);
                $raw = (string) ($img['body'] ?? '');
                if ((int) $img['code'] === 200 && $raw !== '' && @getimagesizefromstring($raw) !== false) {
                    AI::noteKey('screenshot', $key, 200);
                    return $raw;
                }
                logWarn('screenshot', 'image download failed HTTP ' . (int) $img['code']);
                AI::noteKey('screenshot', $key, 200);
                self::$why = 'send';
                return null;
            }
            $err = is_array($j) ? (string) ($j['code'] ?? '') : '';
            $msg = is_array($j) ? (string) ($j['message'] ?? '') : (string) ($r['error'] ?? '');
            if (isset(self::SITE_ERRORS[$err])) {
                AI::noteKey('screenshot', $key, 200);
                self::$why = self::SITE_ERRORS[$err];
                logWarn('screenshot', $err . ': ' . mb_substr($msg, 0, 120));
                return null;
            }
            AI::noteKey('screenshot', $key, $code, $err);
            logWarn('screenshot', 'key' . $idx . " HTTP {$code} " . ($err !== '' ? $err : mb_substr($msg, 0, 80)));
            if ($err === 'ERATE') {
                $reset = (int) ($r['headers']['x-rate-limit-reset'] ?? 0);
                $until = $reset > time() && $reset < time() + 90000 ? $reset + 5 : time() + 10800;
                AI::cool($key, 'screenshot', $until, 'سهمیه‌ی روزانه تموم شد');
                $limited = true;
            } elseif ($err === 'ECONCURRENCY') {
                AI::cool($key, 'screenshot', time() + 3, 'محدودیت دقیقه‌ای');
                $limited = true;
            } elseif ($err === 'EAUTH' || $err === 'EPRO' || in_array($code, [401, 403], true)) {
                AI::cool($key, 'screenshot', time() + 3600, 'کلید نامعتبر یا بدون دسترسی');
            } elseif ($err === 'EFATALCLIENT' || in_array($code, [0, 408, 500, 502, 503, 504], true)) {
                AI::cool($key, 'screenshot', time() + 120, 'شلوغه / جواب نداد');
            } elseif ($code === 429) {
                $reset = (int) ($r['headers']['x-rate-limit-reset'] ?? 0);
                $until = $reset > time() && $reset < time() + 90000 ? $reset + 5 : time() + 10800;
                AI::cool($key, 'screenshot', $until, 'سهمیه‌ی روزانه تموم شد');
                $limited = true;
            } else {
                return null;
            }
        }
        self::$why = $limited ? 'busy' : 'fail';
        return null;
    }

    private static function shot(string $url): ?string
    {
        $bytes = self::request($url);
        if ($bytes === null) {
            return null;
        }
        $fid = ImageGen::fileId($bytes);
        if ($fid === null) {
            logWarn('screenshot', 'file_id upload failed (check STORAGE_CHAT_ID)');
            self::$why = 'send';
        }
        return $fid;
    }

    public static function wait(): string
    {
        return L('📸 دارم عکس می‌گیرم…', '📸 Taking the screenshot…');
    }

    private static function caption(array $p): string
    {
        return '📸 <a href="' . h($p['url']) . '">' . h($p['host']) . '</a>';
    }

    public static function notice(string $kind, string $host = ''): string
    {
        $h = h(mb_substr($host, 0, 60));
        return match ($kind) {
            'url'     => L("📸 آدرس سایت رو درست نفهمیدم؛\nآدرس سایت رو بنویس، مثلاً <code>/screenshot example.com</code>", "📸 I couldn’t make out the website address;\nwrite the site address, for example <code>/screenshot example.com</code>"),
            'host'    => L("😕 سایت «{$h}» باز نشد؛\nشاید پایینه یا صفحه وجود نداره.", "😕 The site “{$h}” didn’t open;\nit may be down or the page may not exist."),
            'site'    => L("😕 «{$h}» جواب نداد؛\nشاید آدرسش اشتباهه یا موقتاً پایینه.", "😕 “{$h}” didn’t respond;\nthe address may be wrong or it may be down for a moment."),
            'timeout' => L("😕 «{$h}» دیر جواب داد؛\nیه‌کم دیگه دوباره امتحان کن.", "😕 “{$h}” took too long to respond;\ntry again in a bit."),
            'antibot' => L("😕 «{$h}» جلوی ربات‌ها رو گرفته؛\nنمی‌تونم ازش عکس بگیرم.", "😕 “{$h}” blocks bots;\nI can’t take a screenshot of it."),
            'busy'    => L("😕 الان سرویس اسکرین‌شات شلوغه یا سهمیه‌اش پر شده؛\nیه‌کم دیگه امتحان کن.", "😕 The screenshot service is busy or out of quota right now;\ntry again in a bit."),
            'nokey'   => L("😕 اسکرین‌شات هنوز راه‌اندازی نشده؛\nبه ادمین خبر بده.", "😕 Screenshots aren’t set up yet;\nlet the admin know."),
            'send'    => L("😕 عکس گرفته شد ولی ارسالش به مشکل خورد؛\nدوباره امتحان کن.", "😕 The screenshot was taken but sending it failed;\ntry again."),
            'expired' => L("😕 این درخواست قدیمی شده؛\nدوباره ازش بخواه.", "😕 This request has expired;\nask for it again."),
            default   => L("😕 الان نتونستم از این سایت عکس بگیرم؛\nیه‌کم دیگه امتحان کن.", "😕 I couldn’t take a screenshot of this site right now;\ntry again in a bit."),
        };
    }

    private static function say(array $msg, int $statusId, string $text): void
    {
        $tg = App::tg();
        if ($statusId > 0) {
            $r = $tg->editMessage($msg['chat']['id'], $statusId, $text);
            if (!empty($r['ok'])) {
                return;
            }
        }
        $tg->reply($msg, $text);
    }

    public static function send(array $msg, string $q): bool
    {
        $tg   = App::tg();
        $chat = $msg['chat']['id'];
        $rt   = (string) ($msg['reply_to_message']['text'] ?? $msg['reply_to_message']['caption'] ?? '');
        $p    = self::parse($q) ?? self::parse($rt);
        if ($p === null) {
            $tg->reply($msg, trim($q) === '' ? Ui::guide('screenshot') : self::notice('url'));
            return true;
        }
        $tg->action($chat, 'upload_photo');
        $st  = $tg->reply($msg, self::wait());
        $sid = (int) ($st['result']['message_id'] ?? 0);
        $fid = self::shot($p['url']);
        if ($fid === null) {
            self::say($msg, $sid, self::notice(self::$why, $p['host']));
            return true;
        }
        $res = Rich::send($chat, self::caption($p), null, (int) $msg['message_id'], $fid);
        if (!empty($res['ok'])) {
            if ($sid > 0) {
                $tg->call('deleteMessage', ['chat_id' => $chat, 'message_id' => $sid], 8);
            }
            return true;
        }
        logWarn('screenshot', 'rich photo send failed: ' . (string) ($res['description'] ?? ''));
        self::say($msg, $sid, self::notice('send'));
        return true;
    }

    public static function output(string $q, ?string $imid = null): ?array
    {
        $p = self::parse($q);
        if ($p === null) {
            return ['text' => self::notice('url')];
        }
        if ($imid !== null) {
            Rich::editInlineRich($imid, self::wait());
        }
        $fid = self::shot($p['url']);
        if ($fid === null) {
            return ['text' => self::notice(self::$why, $p['host'])];
        }
        return ['photo' => $fid, 'text' => self::caption($p)];
    }

    public static function inline(array $p): array
    {
        $token = substr(md5($p['url'] . '|' . microtime(true)), 0, 12);
        Store::set('ss_pending', $token, ['url' => $p['url']]);
        $wait = self::wait();
        $item = Ui::article(
            'ss_' . $token,
            'screenshot',
            L('📸 اسکرین‌شات ', '📸 Screenshot of ') . $p['host'],
            L('بزن تا عکسش رو بگیرم', 'Tap to take it'),
            $wait,
            ikb([[btn('Nullin', 'noop', null)]])
        );
        $item['input_message_content'] = Rich::inlineContent($wait, null);
        return $item;
    }

    public static function onChosen(string $token, string $imid): void
    {
        $job = Store::get('ss_pending', $token);
        $p   = is_array($job) ? self::parse((string) ($job['url'] ?? '')) : null;
        if ($p === null) {
            Rich::editInlineRich($imid, self::notice('expired'));
            return;
        }
        Store::delete('ss_pending', $token);
        $fid = self::shot($p['url']);
        if ($fid === null) {
            Rich::editInlineRich($imid, self::notice(self::$why, $p['host']));
            return;
        }
        $res = Rich::editInlinePhoto($imid, self::caption($p), $fid, null);
        if (empty($res['ok'])) {
            logWarn('screenshot', 'inline photo edit failed: ' . (string) ($res['description'] ?? ''));
            Rich::editInlineRich($imid, self::notice('send'));
        }
    }
}
