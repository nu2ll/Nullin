<?php

final class Broadcast
{
    private const NS  = 'bc_wait';
    private const TTL = 120;

    private static function keyboard(): array
    {
        return ikb([[btn('❌ انصراف', 'bc:x', null)]]);
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

    private static function prompt(int|string $chat, int $uid): void
    {
        $old = self::waiting($uid);
        if ($old && !empty($old['m'])) {
            self::del($chat, (int) $old['m']);
        }
        $other = Store::get('ck_wait', (string) $uid);
        if (is_array($other) && !empty($other['m'])) {
            self::del($chat, (int) $other['m']);
        }
        Store::delete('ck_wait', (string) $uid);
        $r   = App::tg()->send($chat, '📣', ['reply_markup' => self::keyboard()]);
        $mid = (int) ($r['result']['message_id'] ?? 0);
        Store::set(self::NS, (string) $uid, ['m' => $mid, 'at' => time()]);
    }

    private static function dispatch(int|string $chat, int $uid, int $srcMid, ?int $promptMid): void
    {
        $tg = App::tg();
        Store::delete(self::NS, (string) $uid);
        if ($promptMid) {
            self::del($chat, $promptMid);
        }
        $last = Store::get('bc_last', (string) $uid);
        if (is_array($last)) {
            self::del($chat, (int) ($last['s'] ?? 0));
            self::del($chat, (int) ($last['r'] ?? 0));
        }
        $ok   = 0;
        $fail = 0;
        Users::eachReachable(static function (int $id) use ($tg, $chat, $srcMid, &$ok, &$fail) {
            $r = $tg->call('copyMessage', ['chat_id' => $id, 'from_chat_id' => $chat, 'message_id' => $srcMid], 10);
            if (!empty($r['ok'])) {
                $ok++;
            } else {
                $fail++;
                if ((int) ($r['error_code'] ?? 0) === 403) {
                    Users::markBlocked($id);
                }
            }
            usleep(40000);
        });
        logInfo('broadcast', "ok={$ok} fail={$fail}");
        $rid = Rich::replyWithButton(['chat' => ['id' => $chat], 'message_id' => $srcMid], fa($ok) . '✔️' . fa($fail) . '✖️', 'Nullin', 'noop', null);
        Store::set('bc_last', (string) $uid, ['s' => $srcMid, 'r' => (int) $rid]);
    }

    public static function command(array $msg): void
    {
        $chat  = $msg['chat']['id'];
        $uid   = (int) $msg['from']['id'];
        $cmdId = !empty($msg['message_id']) ? (int) $msg['message_id'] : null;
        $src   = !empty($msg['reply_to_message']['message_id']) ? (int) $msg['reply_to_message']['message_id'] : null;

        self::del($chat, $cmdId);
        if ($src) {
            $w = self::waiting($uid);
            self::dispatch($chat, $uid, $src, $w ? (int) ($w['m'] ?? 0) : null);
            return;
        }
        self::prompt($chat, $uid);
    }

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
        $mid = !empty($msg['message_id']) ? (int) $msg['message_id'] : 0;
        if (!$mid) {
            return true;
        }
        self::dispatch($msg['chat']['id'], $uid, $mid, (int) ($w['m'] ?? 0));
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
        if ($data === 'bc:x') {
            Store::delete(self::NS, (string) $uid);
            self::del($cq['message']['chat']['id'] ?? $uid, (int) ($cq['message']['message_id'] ?? 0));
        }
        $tg->answer($cq);
    }
}
