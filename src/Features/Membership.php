<?php

final class Membership
{
    public static function missing(int $userId): array
    {
        $chats = (array) App::cfg('required_chats', []);
        if (!$chats || $userId <= 0) {
            return [];
        }
        // No caching: membership is re-checked on every request so a user who
        // leaves the channel is blocked immediately and gets the join message again.
        $missing = [];
        foreach ($chats as $ch) {
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
            $info   = self::info($ch);
            $rows[] = [urlBtn(($info['type'] === 'channel' ? '📢' : '👥') . ' ' . $info['title'], $ch['url'], 'danger')];
        }
        if ($check) {
            $rows[] = [btn('✅ عضو شدم', 'joincheck', 'success')];
        }
        return ikb($rows);
    }

    /**
     * Title and type of a required chat. Asked from Telegram only the first time, then kept;
     * it is updated by onTitle() when Telegram reports a rename, so no per-message requests.
     */
    private static function info(array $ch): array
    {
        $key = strtolower((string) $ch['chat_id']);
        $v   = Store::get('chat_info', $key);
        if (is_array($v) && !empty($v['title'])) {
            return $v;
        }
        $r = App::tg()->call('getChat', ['chat_id' => $ch['chat_id']], 8);
        if (empty($r['result']['title'])) {
            return ['title' => (string) $ch['title'], 'type' => ''];
        }
        self::remember($r['result'], (string) $r['result']['title']);
        return ['title' => (string) $r['result']['title'], 'type' => (string) ($r['result']['type'] ?? '')];
    }

    /** Called from Kernel when Telegram sends a "chat title changed" service message. */
    public static function onTitle(array $chat, string $title): void
    {
        if ($title !== '' && isset($chat['id'])) {
            self::remember($chat, $title);
        }
    }

    private static function remember(array $chat, string $title): void
    {
        $info = ['title' => $title, 'type' => (string) ($chat['type'] ?? '')];
        Store::set('chat_info', strtolower((string) $chat['id']), $info);
        if (!empty($chat['username'])) {
            Store::set('chat_info', strtolower('@' . $chat['username']), $info);
        }
    }

    /**
     * Sends the join prompt, first deleting the previous join prompt this user got
     * in this chat, so there is only ever one join message on screen.
     */
    public static function prompt(int|string $chatId, int $userId, array $missing, ?int $replyTo = null, string $for = ''): void
    {
        $key = $chatId . ':' . $userId;
        self::clearPrompt($chatId, $userId);
        $r   = Rich::sendHtml($chatId, self::text($for), $replyTo, self::keyboard($missing));
        $mid = (int) ($r['result']['message_id'] ?? 0);
        if ($mid > 0) {
            Store::set('join_msg', $key, $mid);
        }
    }

    public static function clearPrompt(int|string $chatId, int $userId): void
    {
        $key = $chatId . ':' . $userId;
        $old = (int) Store::get('join_msg', $key, 0);
        if ($old > 0) {
            App::tg()->call('deleteMessage', ['chat_id' => $chatId, 'message_id' => $old], 8);
        }
        Store::delete('join_msg', $key);
    }

    /** Gate for group chats: replies with the join prompt . Returns true if blocked. */
    public static function gateGroup(array $msg, string $for = ''): bool
    {
        $uid = (int) ($msg['from']['id'] ?? 0);
        $missing = self::missing($uid);
        if (!$missing) {
            return false;
        }
        self::prompt($msg['chat']['id'], $uid, $missing, (int) $msg['message_id'], $for);
        return true;
    }

    public static function text(string $for = ''): string
    {
        $what = $for === 'download' ? 'دانلود' : 'استفاده';
        return "🔒 <b>یه قدم تا شروع!</b>\n\nبرای {$what} از نالین\nاول عضو کانال/گروه زیر شو، بعد «✅ عضو شدم» رو بزن 👇";
    }
}
