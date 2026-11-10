<?php

/**
 * استاپ جنریشن (Bot API 10.3)
 *
 * وقتی ربات حباب پیش‌نویس (sendRichMessageDraft) رو با can_stop=true می‌فرسته، تلگرام یه دکمه‌ی توقف
 * کنار حباب نشون می‌ده. با زدنش آپدیت `stopped_message_generation` میاد. چون هر آپدیت تو یه پروسه‌ی جدا
 * پردازش می‌شه، پروسه‌ی «تولید جواب» باید از یه جای مشترک (دیتابیس) بفهمه که کاربر استاپ زده.
 *
 *   poller → Kernel::handle → GenStop::onStopped()   ← فلگ رو می‌نویسه
 *   LiveReply (پروسه‌ی تولید) → GenStop::requested() ← هر ~۰.۳ ثانیه فلگ رو چک می‌کنه و curl رو قطع می‌کنه
 */
final class GenStop
{
    private static float $last = 0.0;
    private static bool $hit   = false;

    public static function enabled(): bool
    {
        return (bool) App::cfg('gen_stop', true);
    }

    private static function key(int|string $chatId, int $draftId): string
    {
        return $chatId . '_' . $draftId;
    }

    /** آپدیت stopped_message_generation: { chat, draft_id, message_thread_id? } */
    public static function onStopped(array $u): void
    {
        $chatId  = (int) ($u['chat']['id'] ?? 0);
        $draftId = (int) ($u['draft_id'] ?? 0);
        if ($chatId === 0 || $draftId === 0) {
            return;
        }
        Store::set('gen_stop', self::key($chatId, $draftId), ['at' => time(), 'thread' => (int) ($u['message_thread_id'] ?? 0)]);
    }

    /** آیا کاربر برای این پیش‌نویس استاپ زده؟ (برای نرخ کم‌تر دیتابیس، سقف ~۳ بار در ثانیه) */
    public static function requested(int|string $chatId, int $draftId): bool
    {
        if (self::$hit) {
            return true;
        }
        $now = microtime(true);
        if ($now - self::$last < 0.3) {
            return false;
        }
        self::$last = $now;
        try {
            if (Store::get('gen_stop', self::key($chatId, $draftId)) !== null) {
                self::$hit = true;
                return true;
            }
        } catch (Throwable) {
        }
        return false;
    }

    public static function reset(int|string $chatId, int $draftId): void
    {
        self::$hit  = false;
        self::$last = 0.0;
        try {
            Store::delete('gen_stop', self::key($chatId, $draftId));
        } catch (Throwable) {
        }
    }
}
