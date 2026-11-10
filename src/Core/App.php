<?php

final class App
{
    private static array $cfg = [];
    private static array $tgs = [];
    private static int $bot = 0;
    private static int|string $threadChat = 0;
    private static int $thread = 0;
    private static bool $threadOwn = false;

    public static function boot(): void
    {
        self::$cfg = require NULLINI_ROOT . '/config.php';

        if (!is_dir(NULLINI_DATA)) {
            @mkdir(NULLINI_DATA, 0775, true);
        }
        ini_set('log_errors', '1');
        if (!env('LOG_TO_STDERR') && !env('RAILWAY_ENVIRONMENT')) {
            ini_set('error_log', NULLINI_DATA . '/error.log');
        }
    }

    public static function cfg(string $key, $default = null)
    {
        return self::$cfg[$key] ?? $default;
    }

    public static function tokens(): array
    {
        $out = [];
        foreach ((array) self::cfg('bot_tokens', []) as $i => $token) {
            if (trim((string) $token) !== '') {
                $out[(int) $i] = trim((string) $token);
            }
        }
        return $out;
    }

    public static function useBot(int $index): void
    {
        self::$bot = isset(self::tokens()[$index]) ? $index : 0;
    }

    public static function botIndex(): int
    {
        return self::$bot;
    }

    // تاپیکِ جاریِ پی‌وی (Threaded Mode). بدون آرگومان = بیرون از تاپیک.
    // $own یعنی خود ربات تاپیک رو ساخته (پیام کاربر تو General مونده، پس ریپلای به اون ممنوعه)
    public static function setThread(int|string $chatId = 0, int $thread = 0, bool $own = false): void
    {
        self::$threadChat = $thread > 0 ? $chatId : 0;
        self::$thread     = max(0, $thread);
        self::$threadOwn  = $thread > 0 && $own;
    }

    public static function thread(): int
    {
        return self::$thread;
    }

    public static function threadChat(): int|string
    {
        return self::$threadChat;
    }

    public static function threadOwn(): bool
    {
        return self::$threadOwn;
    }

    public static function tgFor(int $index): Telegram
    {
        return self::$tgs[$index] ??= new Telegram(self::tokens()[$index] ?? (string) self::cfg('bot_token', ''), $index === 1);
    }

    public static function tg(): Telegram
    {
        return self::tgFor(self::$bot);
    }

    public static function resetConnections(): void
    {
        self::$tgs = [];
        Db::disconnect();
    }

    public static function botUsername(): string
    {
        $u   = (string) (((array) self::cfg('bot_usernames', []))[self::$bot] ?? '');
        $key = self::$bot === 0 ? 'me' : 'me' . self::$bot;
        if ($u !== '') {
            return ltrim($u, '@');
        }
        $cached = Store::get('meta', $key);
        if (!empty($cached['username']) && time() - (int) ($cached['_at'] ?? 0) < 86400) {
            return $cached['username'];
        }
        $me = self::tg()->call('getMe');
        if (!empty($me['result']['username'])) {
            Store::set('meta', $key, $me['result'] + ['_at' => time()]);
            return $me['result']['username'];
        }
        return !empty($cached['username']) ? $cached['username'] : 'NullinBot';
    }

    public static function ngrokUrl(): string
    {
        return rtrim((string) (Store::get('meta', 'ngrok_url') ?? ''), '/');
    }

    public static function isAdmin(int $userId): bool
    {
        return in_array($userId, array_map('intval', (array) self::cfg('admins', [])), true);
    }
}
