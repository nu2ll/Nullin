<?php

final class Ban
{
    // ضد اسپم: اگه کسی تو SPAM_WINDOW ثانیه SPAM_LIMIT تا پیام پشت سر هم بده، SPAM_BAN ثانیه ساکت بلاک می‌شه
    private const SPAM_LIMIT  = 5;
    private const SPAM_WINDOW = 10;
    private const SPAM_BAN    = 1800;
    private const SPAM_TEXT   = "⏳ نیم ساعت استراحت؛ بعدش دوباره بیا.\n🚫 اینقدر سریع پیام دادی که نالین قاطی کرد 😅";
    private const SPAM_TEXT_EN = "⏳ Take a 30-minute break, then come back.\n🚫 You sent messages so fast that Nullin got confused 😅";

    public static function is(int $uid): bool
    {
        if ($uid <= 0 || App::isAdmin($uid)) {
            return false;
        }
        if (Store::get('ban', (string) $uid) !== null) {
            return true;
        }
        $until = (int) (Store::get('tban', (string) $uid)['until'] ?? 0);
        if ($until > time()) {
            return true;
        }
        if ($until > 0) {
            Store::delete('tban', (string) $uid);
        }
        return false;
    }

    // پیام خطاب به ربات تو گروه: کامند، منشن، شروع با اسم نالین یا ریپلای به ربات
    private static function toBot(array $m): bool
    {
        $text = trim((string) ($m['text'] ?? $m['caption'] ?? ''));
        if ($text !== '' && $text[0] === '/') {
            return true;
        }
        $bot = App::botUsername();
        if ($bot !== '' && preg_match('/@' . preg_quote($bot, '/') . '\\b/iu', $text) === 1) {
            return true;
        }
        if (preg_match('/^(?:نالینی|نالین|nullini|nullin)(?:[\\s،,:!؟?.]|$)/iu', $text) === 1) {
            return true;
        }
        $r = $m['reply_to_message']['from'] ?? null;
        return $r && !empty($r['is_bot']) && strcasecmp((string) ($r['username'] ?? ''), $bot) === 0;
    }

    private static function spam(array $update): bool
    {
        // پی‌وی و گروه: پیام عادی. تو گروه فقط پیام‌هایی که خطاب به رباتن شمرده می‌شن
        // مهمان: هر پیام مهمان
        $m     = $update['message'] ?? null;
        $guest = $update['guest_message'] ?? null;
        $chat  = 0;
        if (is_array($m)) {
            $type = (string) ($m['chat']['type'] ?? '');
            if ($type === 'private') {
                $chat = 0;
            } elseif (in_array($type, ['group', 'supergroup'], true) && self::toBot($m)) {
                $chat = (int) ($m['chat']['id'] ?? 0);
            } else {
                return false;
            }
        } elseif (!is_array($guest)) {
            return false;
        }
        $uid = self::senderId($update);
        if ($uid <= 0 || App::isAdmin($uid)) {
            return false;
        }
        $now  = time();
        $hits = Store::update('spam', (string) $uid, static function ($old) use ($now) {
            $a = is_array($old) ? $old : [];
            $a = array_values(array_filter($a, static fn($t) => $now - (int) $t < self::SPAM_WINDOW));
            $a[] = $now;
            return $a;
        });
        if (is_array($hits) && count($hits) >= self::SPAM_LIMIT) {
            Store::set('tban', (string) $uid, ['until' => $now + self::SPAM_BAN]);
            Store::delete('spam', (string) $uid);
            $text = Lang::is('en') ? self::SPAM_TEXT_EN : self::SPAM_TEXT;
            try {
                if (is_array($guest)) {
                    GuestHandler::spamNotice($guest, $text);
                } else {
                    Rich::sendHtml($chat !== 0 ? $chat : $uid, $text);
                }
            } catch (\Throwable $e) {
            }
            return true;
        }
        return false;
    }

    public static function add(int $uid, int $by): bool
    {
        if ($uid <= 0 || App::isAdmin($uid)) {
            return false;
        }
        Store::set('ban', (string) $uid, ['at' => time(), 'by' => $by]);
        return true;
    }

    public static function remove(int $uid): bool
    {
        if (Store::get('ban', (string) $uid) === null) {
            return false;
        }
        Store::delete('ban', (string) $uid);
        return true;
    }

    public static function byUsername(string $username): int
    {
        $u = strtolower(ltrim(trim($username), '@'));
        if ($u === '' || !preg_match('/^[a-z0-9_]{3,64}$/', $u)) {
            return 0;
        }
        $id = (int) Db::value('SELECT id FROM users WHERE LOWER(username) = ?', [$u]);
        return $id > 0 ? $id : (int) Store::get('uname', $u, 0);
    }

    public static function all(): array
    {
        return array_map('intval', Db::run("SELECT k FROM kv WHERE ns = 'ban' ORDER BY updated_at DESC")->fetchAll(PDO::FETCH_COLUMN));
    }

    public static function senderId(array $update): int
    {
        foreach (['message', 'inline_query', 'chosen_inline_result', 'callback_query', 'business_message', 'edited_business_message'] as $k) {
            if (isset($update[$k])) {
                return (int) ($update[$k]['from']['id'] ?? 0);
            }
        }
        if (isset($update['guest_message'])) {
            $g = $update['guest_message'];
            return (int) ($g['guest_bot_caller_user']['id'] ?? $g['from']['id'] ?? 0);
        }
        return 0;
    }

    public static function block(array $update): bool
    {
        $uid = self::senderId($update);
        if (!self::is($uid) && !self::spam($update)) {
            return false;
        }
        $tg = App::tg();
        if (isset($update['callback_query'])) {
            $tg->answer($update['callback_query']);
        } elseif (isset($update['inline_query']['id'])) {
            $tg->call('answerInlineQuery', ['inline_query_id' => $update['inline_query']['id'], 'results' => [], 'cache_time' => 0, 'is_personal' => true]);
        }
        return true;
    }
}
