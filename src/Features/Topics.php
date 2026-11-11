<?php

/**
 * حالت تاپیک‌ها (Threaded Mode) تو پی‌وی ربات؛ Bot API 9.3+
 *
 * هر تاپیک یه گفتگوی جدا با نالینه (حافظه‌ی مکالمه‌اش از بقیه‌ی تاپیک‌ها جداست):
 *   • کلاینت تلگرام وقتی کاربر تو «تاپیک جدید» پیام می‌نویسه، خودش تاپیک می‌سازه و پیام با message_thread_id میاد
 *   • اسم تاپیکِ تازه بعد از اولین جواب، خودکار با یه عنوان کوتاه + ایموجی عوض می‌شه (editForumTopic)
 *
 * چطور کار می‌کنه: Kernel::handle برای هر آپدیتِ پی‌وی، تاپیک جاری رو تو App ثبت می‌کنه (App::setThread).
 * Telegram::call خودش message_thread_id رو به متدهای ارسال اضافه می‌کنه و Assistant::memKey حافظه رو
 * به‌ازای هر تاپیک جدا نگه می‌داره؛ پس بقیه‌ی کد ربات لازم نیست چیزی درباره‌ی تاپیک بدونه.
 *
 * فعال‌سازی: @BotFather ← Bot Settings ← Threaded Mode (تو Mini App بات‌فادر)
 */
final class Topics
{
    public static function on(): bool
    {
        return (bool) App::cfg('topics', true);
    }

    /** اول هر آپدیت صدا زده می‌شه: تاپیک جاری (اگه تو پی‌وی داخل تاپیکیم) رو تو App ثبت می‌کنه */
    public static function enter(array $update): void
    {
        App::setThread();
        if (!self::on()) {
            return;
        }
        $m = $update['message'] ?? $update['callback_query']['message'] ?? null;
        if (!is_array($m) || ($m['chat']['type'] ?? '') !== 'private') {
            return;
        }
        $thread = (int) ($m['message_thread_id'] ?? 0);
        if ($thread > 0) {
            App::setThread($m['chat']['id'], $thread);
        }
    }

    /** پیام سرویسی «تاپیک ساخته شد»؛ اسمِ خالی یا is_name_implicit (اسمی که تلگرام خودش گذاشته) یعنی بعد از اولین جواب اسم خودکار بذار */
    public static function created(array $msg): bool
    {
        $ft = $msg['forum_topic_created'] ?? null;
        if (!is_array($ft) || ($msg['chat']['type'] ?? '') !== 'private') {
            return false;
        }
        $thread = (int) ($msg['message_thread_id'] ?? $msg['reply_to_message']['message_id'] ?? 0);
        if ($thread > 0) {
            $name = trim((string) ($ft['name'] ?? ''));
            Store::set('topics', $msg['chat']['id'] . '_' . $thread, [
                'name' => $name,
                'auto' => $name === '' || !empty($ft['is_name_implicit']),
                'at'   => time(),
            ]);
        }
        return true;
    }

    /**
     * کاربر تو General (بدون تاپیک) پیام داده ولی ربات Threaded Mode داره: ربات خودش با اسمِ ساخته‌شده توسط مدل
     * تاپیک می‌سازه و بقیه‌ی این آپدیت (جواب، حافظه، حباب) تو همون تاپیک انجام می‌شه. پیام کاربر تو تاپیک تکرار نمی‌شه.
     */
    public static function adopt(array $msg, string $question): bool
    {
        if (!App::cfg('topics_auto_create', true) || App::thread() > 0 || trim($question) === '' || !self::on()) {
            return false;
        }
        $chat   = (int) $msg['chat']['id'];
        $r      = App::tg()->call('createForumTopic', ['chat_id' => $chat, 'name' => self::title($question)], 10);
        $thread = (int) ($r['result']['message_thread_id'] ?? 0);
        if (empty($r['ok']) || $thread <= 0) {
            return false;
        }
        App::setThread($chat, $thread, true);
        Store::set('topics', $chat . '_' . $thread, ['name' => (string) ($r['result']['name'] ?? ''), 'auto' => false, 'named' => true, 'own' => true, 'at' => time()]);
        return true;
    }

    /** بعد از اولین جواب تو یه تاپیکِ تازه، اسمش رو با یه عنوان کوتاه + ایموجی عوض می‌کنه */
    public static function nameAfter(array $msg, string $question): void
    {
        $thread = App::thread();
        if ($thread <= 0 || !App::cfg('topics_autoname', true) || trim($question) === '') {
            return;
        }
        $chat = (int) $msg['chat']['id'];
        $key  = $chat . '_' . $thread;
        $rec  = Store::get('topics', $key);
        if (is_array($rec)) {
            if (empty($rec['auto']) || !empty($rec['named'])) {
                return;
            }
        } elseif ((int) ($msg['message_id'] ?? 0) - $thread > 3) {
            return;
        }
        $claimed = Store::update('topics', $key, static function ($r) {
            $r = is_array($r) ? $r : [];
            if (!empty($r['named']) || (isset($r['auto']) && !$r['auto'])) {
                return null;
            }
            return ['named' => true, 'auto' => false, 'at' => time()] + $r;
        });
        if ($claimed === null) {
            return;
        }
        $name = self::title($question);
        App::tg()->call('editForumTopic', ['chat_id' => $chat, 'message_thread_id' => $thread, 'name' => $name], 10);
    }

    private static function fallbackName(string $question): string
    {
        return '💬';
    }

    /** جواب خامِ مدل رو به یه اسمِ تمیز تبدیل می‌کنه: فقط یه خط، بدون برچسب، مارک‌داون، نقل‌قول و علامتِ آخر جمله */
    private static function cleanTitle(string $text): string
    {
        $text = preg_replace('~<(think|thinking|reasoning)>.*?</\1>~isu', '', $text) ?? $text;
        $text = preg_replace('/```[a-z]*/iu', '', $text) ?? $text;
        $line = '';
        foreach (preg_split('/\R/u', $text) ?: [] as $l) {
            $l = trim($l);
            if ($l !== '') {
                $line = $l;
                break;
            }
        }
        $quote = '"\'«»“”„‟‘’‚‹›`´';
        $line  = preg_replace('/^(?:title|topic|name|chat\s*title|chat\s*name|عنوان|موضوع|اسم)\s*[:：]\s*/iu', '', $line) ?? $line;
        $line  = preg_replace('/^(?:[#>\-+•·▪◦]+|\d+[.)])\s+/u', '', $line) ?? $line;
        $line  = str_replace(['**', '__', '~~', '`'], '', $line);
        $line  = preg_replace('/^[\s' . $quote . '*_#~|\[\]<>]+|[\s' . $quote . '*_#~|\[\]<>.!?،؛؟:;,…。！？—–-]+$/u', '', $line) ?? $line;
        return trim($line);
    }

    private static function title(string $question): string
    {
        $text = AI::chat([
            ['role' => 'system', 'content' => implode("\n", [
                'You name a chat topic. Reply with ONE line only: a single fitting emoji, a space, then a very short title (2-4 words).',
                "Write the title in the same language as the user's message.",
                'At most 36 characters in total.',
            ])],
            ['role' => 'user', 'content' => "User: " . mb_substr($question, 0, 400)],
        ], ['tier' => 'lite', 'temperature' => 0.3, 'thinking' => null, 'section' => 'topic', 'deadline' => microtime(true) + 60]);

        $line = self::cleanTitle((string) $text);
        if ($line === '') {
            return self::fallbackName($question);
        }
        return mb_substr($line, 0, 40);
    }
}
