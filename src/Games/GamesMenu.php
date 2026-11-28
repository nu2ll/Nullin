<?php

/**
 * /games — a game picker that works in plain chats (no inline mode needed).
 *
 * The list message is sent by the command; tapping a game turns THAT SAME message into the game.
 * Whoever sent the command owns the list and becomes player 1 (creator) of the game they pick.
 *
 * The game classes are written around an inline_message_id. A chat message has no such id, so we
 * give it a virtual one, "c:<chat_id>:<message_id>" (see GamesMenu::vid). Telegram::call() turns it
 * back into chat_id + message_id on every editMessageText, so the game classes need no changes.
 */
final class GamesMenu
{
    public const P = 'gm';

    /** key => game class (keys match Switches::TOOLS and the callback prefixes). */
    private const GAMES = [
        'chess' => Chess::class,
        'ttt'   => TicTacToe::class,
        'c4'    => ConnectFour::class,
        'rps'   => Rps::class,
        'math'  => MathDuel::class,
        'mem'   => Memory::class,
        'dab'   => DotsBoxes::class,
    ];

    public static function vid(int|string $chatId, int $messageId): string
    {
        return "c:{$chatId}:{$messageId}";
    }

    /** @return string[] the games that are switched on */
    public static function enabled(): array
    {
        return array_values(array_filter(array_keys(self::GAMES), static fn(string $k) => Switches::on($k)));
    }

    public static function anyOn(): bool
    {
        return self::enabled() !== [];
    }

    private static function text(array $from): string
    {
        return L("<b>🎮 بازی‌ها</b>\n", "<b>🎮 Games</b>\n") . divider() . "\n"
            . L('👤 ', '👤 ') . safeName($from) . L(" بازی رو انتخاب کن؛\nهمین پیام تبدیل به بازی می‌شه 👇", " pick a game;\nthis very message turns into the game 👇");
    }

    private static function keyboard(): array
    {
        // rows of 2, then 3, then 2 buttons (repeating if more games are ever added)
        $sizes = [2, 3, 2];
        $rows  = [];
        $row   = [];
        $r     = 0;
        foreach (self::enabled() as $key) {
            $row[] = btn(Switches::label($key), self::P . ':' . $key, 'primary');
            if (count($row) === $sizes[$r % count($sizes)]) {
                $rows[] = $row;
                $row    = [];
                $r++;
            }
        }
        if ($row) {
            $rows[] = $row;
        }
        return ikb($rows);
    }

    /** منوی بازی‌ها برای حالت مهمان: [متن، کیبورد] */
    public static function guestMenu(array $from): array
    {
        return [self::text($from), self::keyboard()];
    }

    public static function command(array $msg, bool $private): void
    {
        $chatId = $msg['chat']['id'];
        $from   = $msg['from'];
        if (!self::anyOn()) {
            Rich::notice($msg, L('🎮 بازی‌ها فعلاً خاموشن.', '🎮 Games are switched off right now.'));
            return;
        }
        $r   = Rich::send($chatId, self::text($from), self::keyboard(), $private ? null : (int) ($msg['message_id'] ?? 0));
        $mid = (int) ($r['result']['message_id'] ?? 0);
        // In a group only the person who sent /games may choose from the list (CallbackHandler checks menuowner).
        if (!$private && $mid > 0) {
            Store::set('menuowner', "{$chatId}:{$mid}", (int) $from['id']);
        }
    }

    public static function onCallback(array $cq, string $data): void
    {
        $tg     = App::tg();
        $chatId = $cq['message']['chat']['id'] ?? null;
        $mid    = (int) ($cq['message']['message_id'] ?? 0);
        $key    = substr($data, strlen(self::P) + 1);
        $imid   = $cq['inline_message_id'] ?? null;
        if ($key === 'back') {
            self::back($cq);
            return;
        }
        if (($imid === null && ($chatId === null || $mid <= 0)) || !isset(self::GAMES[$key])) {
            $tg->answer($cq);
            return;
        }
        // منوی ساخته‌شده تو حالت مهمان: فقط صاحبش می‌تونه بازی رو انتخاب کنه
        if ($imid !== null) {
            $own = (int) Store::get('menuowner', 'i:' . $imid, 0);
            if ($own > 0 && $own !== (int) ($cq['from']['id'] ?? 0)) {
                $tg->alert($cq, L("این منو مال تو نیست؛\nخودت دستورش رو بزن 🙂", "This menu isn't yours;\nsend the command yourself 🙂"));
                return;
            }
        }
        if (!Switches::on($key)) {
            $tg->alert($cq, L('🎮 این بازی فعلاً خاموشه.', '🎮 This game is switched off right now.'));
            return;
        }

        $vid = $imid ?? self::vid($chatId, $mid);
        // The list is no longer a private menu: from here on the opponent has to be able to tap it.
        Store::delete('menuowner', $imid !== null ? 'i:' . $imid : "{$chatId}:{$mid}");
        GameKit::save($vid, GameKit::fresh($key, GameKit::player($cq['from'])));
        $cls = self::GAMES[$key];
        GameKit::$chat = true;
        Rich::editInline($vid, $cls::intro(), $cls::modeKeyboard());
        $tg->answer($cq);
    }

    /** "🔙 Games list" under the mode screen: put the picker back on the same message. */
    private static function back(array $cq): void
    {
        $tg     = App::tg();
        $chatId = $cq['message']['chat']['id'] ?? null;
        $mid    = (int) ($cq['message']['message_id'] ?? 0);
        $uid    = (int) ($cq['from']['id'] ?? 0);
        $imid   = $cq['inline_message_id'] ?? null;
        if (($imid === null && ($chatId === null || $mid <= 0)) || $uid <= 0) {
            $tg->answer($cq);
            return;
        }
        $vid = $imid ?? self::vid($chatId, $mid);
        $s   = GameKit::load($vid);
        if ($s !== null) {
            if (($s['status'] ?? '') !== 'mode') {
                $tg->answer($cq, L('⏳ بازی شروع شده', '⏳ The game has already started'));
                return;
            }
            if (!GameKit::isCreator($s, $uid)) {
                $tg->alert($cq, L('🔒 فقط سازنده می‌تونه برگرده به لیست.', '🔒 Only the creator can go back to the list.'));
                return;
            }
        }
        Store::delete(GameKit::NS, $vid);
        if ($imid !== null) {
            Store::set('menuowner', 'i:' . $imid, $uid);
        } elseif (in_array($cq['message']['chat']['type'] ?? '', ['group', 'supergroup'], true)) {
            Store::set('menuowner', "{$chatId}:{$mid}", $uid);
        }
        Rich::editInline($vid, self::text($cq['from']), self::keyboard());
        $tg->answer($cq);
    }
}
