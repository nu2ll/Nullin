<?php

/**
 * آپدیت فایل کوکی دانلودر (فقط ادمین، فقط پیوی).
 *
 * /cookies  ->  یه پیام فقط با 🍪 و یه دکمه‌ی اینلاین می‌آد.
 * تا وقتی فایلِ واقعاً cookies.txt (با فرمت Netscape) نفرستی، دکمه می‌مونه،
 * هوش مصنوعی به هیچ‌چیزی که می‌فرستی جواب نمی‌ده و فقط یه 👎 روش می‌خوره.
 */
final class Cookies
{
    private const NS   = 'ck_wait';
    private const TTL  = 1800;
    private const NAME = 'cookies.txt';
    private const MAX  = 2 * 1048576;

    private static function keyboard(): array
    {
        return ikb([[btn('❌ انصراف', 'ck:x', null)]]);
    }

    public static function waiting(int $uid): ?array
    {
        $w = Store::get(self::NS, (string) $uid);
        if (!is_array($w) || time() - (int) ($w['at'] ?? 0) > self::TTL) {
            return null;
        }
        return $w;
    }

    private static function del(int|string $chat, ?int $mid): void
    {
        if ($mid) {
            App::tg()->call('deleteMessage', ['chat_id' => $chat, 'message_id' => $mid], 8);
        }
    }

    private static function nope(int|string $chat, ?int $mid): void
    {
        if ($mid) {
            App::tg()->react($chat, $mid, '👎');
        }
    }

    private static function prompt(int|string $chat, int $uid): void
    {
        $old = self::waiting($uid);
        if ($old && !empty($old['m'])) {
            self::del($chat, (int) $old['m']);
        }
        $r   = App::tg()->send($chat, '🍪', ['reply_markup' => self::keyboard()]);
        $mid = (int) ($r['result']['message_id'] ?? 0);
        Store::set(self::NS, (string) $uid, ['m' => $mid, 'at' => time()]);
    }

    /**
     * فایل رو چک می‌کنه و اگه درست بود ذخیره می‌کنه.
     * فقط وقتی true برمی‌گردونه که فایل واقعاً cookies.txt با فرمت درست بوده و ذخیره شده.
     */
    private static function accept(int|string $chat, int $uid, array $doc, ?int $fileMid, ?int $promptMid): bool
    {
        $tg   = App::tg();
        $name = strtolower(trim((string) ($doc['file_name'] ?? '')));
        $size = (int) ($doc['file_size'] ?? 0);
        if ($name !== self::NAME || $size <= 0 || $size > self::MAX || empty($doc['file_id'])) {
            self::nope($chat, $fileMid);
            return false;
        }
        $raw = $tg->download((string) $doc['file_id']);
        if ($raw === null || $raw === '') {
            logWarn('cookies', 'download of cookies.txt failed');
            self::nope($chat, $fileMid);
            return false;
        }
        $res = Downloader::saveCookies($raw);
        if (isset($res['error'])) {
            logWarn('cookies', 'rejected: ' . str_replace("\n", ' ', (string) $res['error']));
            self::nope($chat, $fileMid);
            return false;
        }

        Store::delete(self::NS, (string) $uid);
        self::del($chat, $fileMid);
        $text = '🍪✅ ' . fa($res['count']) . ' تا کوکی ذخیره شد.';
        if (!$res['insta']) {
            $text .= "\n⚠️ کوکیِ لاگینِ اینستاگرام (sessionid) توش نیست؛ استوری و پیج خصوصی باز نمی‌شن.";
        }
        if ($promptMid) {
            $r = $tg->editMessage($chat, $promptMid, $text, ['inline_keyboard' => []]);
            if (empty($r['ok'])) {
                $tg->send($chat, $text);
            }
        } else {
            $tg->send($chat, $text);
        }
        Backup::push('cookies');
        return true;
    }

    /** دستور /cookies */
    public static function command(array $msg): void
    {
        $chat  = $msg['chat']['id'];
        $uid   = (int) $msg['from']['id'];
        $cmdId = !empty($msg['message_id']) ? (int) $msg['message_id'] : null;

        $doc     = null;
        $fileMid = null;
        if (!empty($msg['document'])) {
            $doc     = $msg['document'];
            $fileMid = $cmdId;
        } elseif (!empty($msg['reply_to_message']['document'])) {
            $doc     = $msg['reply_to_message']['document'];
            $fileMid = !empty($msg['reply_to_message']['message_id']) ? (int) $msg['reply_to_message']['message_id'] : null;
        }

        if (is_array($doc)) {
            $w = self::waiting($uid);
            if (self::accept($chat, $uid, $doc, $fileMid, $w ? (int) ($w['m'] ?? 0) : null)) {
                if ($fileMid !== $cmdId) {
                    self::del($chat, $cmdId);
                }
                return;
            }
        }
        self::del($chat, $cmdId !== $fileMid ? $cmdId : null);
        self::prompt($chat, $uid);
    }

    /**
     * هر پیام ادمین تا وقتی منتظر فایل کوکی هستیم.
     * true یعنی پیام مصرف شد و هوش مصنوعی نباید جوابش رو بده.
     */
    public static function input(array $msg): bool
    {
        $uid = (int) ($msg['from']['id'] ?? 0);
        if (!$uid || !App::isAdmin($uid)) {
            return false;
        }
        $w = self::waiting($uid);
        if ($w === null) {
            return false;
        }
        $chat = $msg['chat']['id'];
        $mid  = !empty($msg['message_id']) ? (int) $msg['message_id'] : null;
        $doc  = $msg['document'] ?? null;
        if (!is_array($doc)) {
            self::nope($chat, $mid);
            return true;
        }
        self::accept($chat, $uid, $doc, $mid, (int) ($w['m'] ?? 0));
        return true;
    }

    public static function onCallback(array $cq, string $data): void
    {
        $tg  = App::tg();
        $uid = (int) ($cq['from']['id'] ?? 0);
        if (!App::isAdmin($uid)) {
            $tg->answer($cq);
            return;
        }
        if ($data === 'ck:x') {
            Store::delete(self::NS, (string) $uid);
            self::del($cq['message']['chat']['id'] ?? $uid, (int) ($cq['message']['message_id'] ?? 0));
        }
        $tg->answer($cq);
    }
}
