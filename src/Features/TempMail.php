<?php

final class TempMail
{
    private const NS      = 'tempmail';
    private const DOMAINS = ['1secmail.com', '1secmail.org', '1secmail.net'];
    private const API     = 'https://www.1secmail.com/api/v1/';

    private static function random(int $len = 10): string
    {
        $chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
        $out   = '';
        for ($i = 0; $i < $len; $i++) {
            $out .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $out;
    }

    public static function current(int $userId): ?array
    {
        return Store::get(self::NS, (string) $userId);
    }

    public static function create(int $userId): array
    {
        $login  = self::random();
        $domain = self::DOMAINS[array_rand(self::DOMAINS)];
        $addr   = ['login' => $login, 'domain' => $domain, 'address' => $login . '@' . $domain, 'created' => time()];
        Store::set(self::NS, (string) $userId, $addr);
        return $addr;
    }

    public static function delete(int $userId): void
    {
        Store::delete(self::NS, (string) $userId);
    }

    public static function inbox(array $addr): array
    {
        $url = self::API . '?action=getMessages&login=' . rawurlencode($addr['login']) . '&domain=' . rawurlencode($addr['domain']);
        $res = Http::getJson($url);
        return is_array($res) ? $res : [];
    }

    public static function readMessage(array $addr, int $id): ?array
    {
        $url = self::API . '?action=readMessage&login=' . rawurlencode($addr['login']) . '&domain=' . rawurlencode($addr['domain']) . '&id=' . $id;
        $res = Http::getJson($url);
        return is_array($res) ? $res : null;
    }

    private static function extractCode(string $text): ?string
    {
        return preg_match('/\b(\d{4,8})\b/', $text, $m) ? $m[1] : null;
    }

    public static function card(array $addr): array
    {
        $text = "📧 <b>ایمیل موقت ساخته شد</b>\n\n<code>" . h($addr['address']) . "</code>\n\nهر پیامی که به این آدرس بیاد رو با دکمه‌ی زیر ببین.";
        $kb = ikb([
            [copyBtn('📋 کپی آدرس', $addr['address']), btn('📥 بررسی صندوق', 'mail:check')],
            [btn('🗑 حذف', 'mail:delete')],
        ]);
        return [$text, $kb];
    }

    public static function inboxCard(int $userId): array
    {
        $addr = self::current($userId);
        if ($addr === null) {
            return ['❌ ایمیل فعالی نداری؛ دوباره دستور ساخت رو بزن.', ikb([])];
        }
        $msgs = self::inbox($addr);
        if ($msgs === []) {
            $text = "📧 <code>" . h($addr['address']) . "</code>\n\n📭 هنوز پیامی نیومده.";
            return [$text, ikb([
                [btn('🔄 رفرش', 'mail:check')],
                [btn('🗑 حذف', 'mail:delete')],
            ])];
        }
        $rows  = [];
        $lines = [];
        foreach (array_slice($msgs, 0, 8) as $m) {
            $id     = (int) ($m['id'] ?? 0);
            $from   = mb_substr((string) ($m['from'] ?? '?'), 0, 30);
            $subj   = mb_substr((string) ($m['subject'] ?? '(بدون موضوع)'), 0, 30);
            $lines[] = "✉️ از {$from} — {$subj}";
            $rows[]  = [btn('👁 ' . mb_substr($from, 0, 20), 'mail:read:' . $id)];
        }
        $text = "📧 <code>" . h($addr['address']) . "</code>\n\n" . h(implode("\n", $lines));
        $rows[] = [btn('🔄 رفرش', 'mail:check'), btn('🗑 حذف', 'mail:delete')];
        return [$text, ikb($rows)];
    }

    public static function messageCard(int $userId, int $id): array
    {
        $addr = self::current($userId);
        if ($addr === null) {
            return ['❌ ایمیل فعالی نداری؛ دوباره دستور ساخت رو بزن.', ikb([])];
        }
        $m = self::readMessage($addr, $id);
        if ($m === null) {
            return ['❌ این پیام پیدا نشد.', ikb([[btn('🔙 بازگشت', 'mail:check')]])];
        }
        $from = (string) ($m['from'] ?? '?');
        $subj = (string) ($m['subject'] ?? '(بدون موضوع)');
        $body = trim((string) ($m['textBody'] ?? strip_tags((string) ($m['htmlBody'] ?? ''))));
        $body = mb_substr($body, 0, 1500);
        $code = self::extractCode($body);

        $text = "✉️ <b>از:</b> " . h($from) . "\n<b>موضوع:</b> " . h($subj) . "\n\n" . h($body);
        if ($code !== null) {
            $text .= "\n\n🔢 <b>کد پیدا شده:</b> <code>" . h($code) . "</code>";
        }
        $rows = $code !== null ? [[copyBtn('📋 کپی کد', $code)]] : [];
        $rows[] = [btn('🔙 بازگشت', 'mail:check')];
        return [$text, ikb($rows)];
    }

    public static function onCallback(array $cq, string $data): void
    {
        $tg     = App::tg();
        $uid    = (int) ($cq['from']['id'] ?? 0);
        $chatId = $cq['message']['chat']['id'] ?? null;
        $msgId  = $cq['message']['message_id'] ?? null;
        if ($chatId === null || $msgId === null) {
            $tg->answer($cq);
            return;
        }

        if ($data === 'mail:check') {
            [$text, $kb] = self::inboxCard($uid);
            Rich::editMsg($chatId, (int) $msgId, $text, $kb);
            $tg->answer($cq);
            return;
        }
        if ($data === 'mail:delete') {
            self::delete($uid);
            Rich::editMsg($chatId, (int) $msgId, '🗑 ایمیل موقت حذف شد.', ikb([]));
            $tg->answer($cq, '🗑 حذف شد');
            return;
        }
        if (preg_match('/^mail:read:(\d+)$/', $data, $m)) {
            [$text, $kb] = self::messageCard($uid, (int) $m[1]);
            Rich::editMsg($chatId, (int) $msgId, $text, $kb);
            $tg->answer($cq);
            return;
        }
        $tg->answer($cq);
    }
}
