<?php

final class Membership
{
    public static function missing(int $userId): array
    {
        $channels = (array) App::cfg('required_channels', []);
        if (!$channels || $userId <= 0) {
            return [];
        }
        // No caching: membership is re-checked on every request so a user who
        // leaves the channel is blocked immediately and gets the join message again.
        $missing = [];
        foreach ($channels as $ch) {
            $r      = App::tg()->call('getChatMember', ['chat_id' => $ch['chat_id'], 'user_id' => $userId], 8);
            $status = $r['result']['status'] ?? '';
            if (!in_array($status, ['member', 'administrator', 'creator', 'restricted'], true)) {
                $missing[] = $ch;
            }
        }
        return $missing;
    }

    public static function keyboard(array $missing, bool $check = true): array
    {
        $rows = [];
        foreach ($missing as $ch) {
            if (($ch['url'] ?? '') === '') {
                continue;
            }
            $rows[] = [urlBtn('📢 ' . $ch['title'], $ch['url'], 'danger')];
        }
        if ($check) {
            $rows[] = [btn('✅ عضو شدم', 'joincheck', 'success')];
        }
        return ikb($rows);
    }

    /** Gate for group chats: replies with the join prompt . Returns true if blocked. */
    public static function gateGroup(array $msg): bool
    {
        $uid = (int) ($msg['from']['id'] ?? 0);
        $missing = self::missing($uid);
        if (!$missing) {
            return false;
        }
        Rich::sendHtml($msg['chat']['id'], self::text(), (int) $msg['message_id'], self::keyboard($missing));
        return true;
    }

    public static function text(): string
    {
        return "🔒 <b>یه قدم تا شروع!</b>\n\nبرای استفاده از نالین\nاول عضو کانال/گروه زیر شو، بعد «✅ عضو شدم» رو بزن 👇";
    }
}
