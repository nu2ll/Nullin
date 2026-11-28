<?php

final class RussianRoulette
{
    private const P         = 'rr';
    private const CHAMBERS  = 6;
    private const COLS      = 3;
    private const MAX       = 4;
    private const BOT_KEY   = 'bot';
    private const BOT_THINK = [1200, 2200]; // ms the bot waits before it pulls the trigger
    private const GAP       = '　';
    private const ARROWS    = [1 => ['⬆️'], 2 => ['⬆️', '⬇️'], 3 => ['⬆️', '↘️', '↙️'], 4 => ['⬆️', '➡️', '⬇️', '⬅️']];
    private const SEATS     = [1 => [[0, 1]], 2 => [[0, 1], [2, 1]], 3 => [[0, 1], [2, 2], [2, 0]], 4 => [[0, 1], [1, 2], [2, 1], [1, 0]]];

    public static function intro(): string
    {
        return L(
            "<b>🔫 روسی‌رولت</b>\n\nتفنگ ۶ تا خونه داره و فقط یکیش گلوله‌ست؛\nهر نفر یه خونه رو انتخاب می‌کنه و ماشه رو می‌کشه،\nهر کی گلوله بخوره حذف می‌شه 😵\nو آخرین نفر زنده می‌بره!\n\nحالت بازی رو انتخاب کن:",
            "<b>🔫 Russian roulette</b>\n\nThe gun has 6 chambers and only one holds a bullet;\nplayers take turns picking a chamber and pulling the trigger,\nwhoever gets the bullet is out 😵\nand the last player standing wins!\n\nPick a game mode:"
        );
    }

    public static function modeKeyboard(): array
    {
        return GameKit::modeKeyboard(self::P, L('👥 چند نفره', '👥 Multiplayer'));
    }

    private static function title(): string
    {
        return L('🔫 روسی‌رولت', '🔫 Russian roulette');
    }

    private static function order(array $s): array
    {
        $o = $s['order'] ?? array_keys($s['players'] ?? []);
        return array_values(array_map('strval', $o));
    }

    private static function alive(array $s): array
    {
        return array_values(array_filter(self::order($s), static fn($u) => !empty($s['players'][$u]['alive'])));
    }

    private static function nm(array $s, string $u): string
    {
        return bidi(h((string) ($s['players'][$u]['name'] ?? '?')));
    }

    private static function arrow(array $s, string $u): string
    {
        $order = self::order($s);
        $n     = max(1, min(self::MAX, count($order)));
        $k     = array_search($u, $order, true);
        return self::ARROWS[$n][$k === false ? 0 : $k] ?? '⬆️';
    }

    private static function left(array $s): int
    {
        return count(array_filter($s['taken'] ?? [], static fn($t) => (int) $t === 0));
    }

    private static function begin(array $s, array $order): array
    {
        $s['status'] = 'playing';
        $s['order']  = array_values(array_map('strval', $order));
        $s['round']  = 1;
        $s['bullet'] = random_int(0, self::CHAMBERS - 1);
        $s['taken']  = array_fill(0, self::CHAMBERS, 0); // 0 untouched, 1 safe pull, 3 the bullet
        $s['turn']   = (string) $order[0];
        $s['last']   = null;
        $s['winner'] = '';
        foreach ($s['players'] as $u => $p) {
            $s['players'][$u]['alive'] = true;
        }
        return $s;
    }

    private static function nextAlive(array $s, string $from): string
    {
        $o = self::order($s);
        $n = count($o);
        $k = (int) array_search($from, $o, true);
        for ($j = 1; $j <= $n; $j++) {
            $u = $o[($k + $j) % $n];
            if (!empty($s['players'][$u]['alive'])) {
                return $u;
            }
        }
        return $from;
    }

    /** Pulls the trigger on chamber $i for $uid. A hit knocks that player out and reloads the gun for the next round. */
    private static function pull(array $s, int $i, string $uid): array
    {
        $hit            = $i === (int) $s['bullet'];
        $s['taken'][$i] = $hit ? 3 : 1;
        $s['last']      = ['uid' => $uid, 'i' => $i, 'hit' => $hit];
        if (!$hit) {
            $s['turn'] = self::nextAlive($s, $uid);
            return $s;
        }
        $s['players'][$uid]['alive'] = false;
        $alive                       = self::alive($s);
        if (count($alive) <= 1) {
            $s['status'] = 'over';
            $s['winner'] = (string) ($alive[0] ?? '');
            return $s;
        }
        $s['round']++;
        $s['bullet'] = random_int(0, self::CHAMBERS - 1);
        $s['taken']  = array_fill(0, self::CHAMBERS, 0);
        $s['turn']   = self::nextAlive($s, $uid);
        return $s;
    }

    /** The bot plays by itself after your safe pull: waits, then pulls on a random free chamber. */
    private static function botTurn(string $imid): void
    {
        usleep(random_int(self::BOT_THINK[0], self::BOT_THINK[1]) * 1000);
        $upd = Store::update(GameKit::NS, $imid, static function ($st) {
            if (!is_array($st) || ($st['status'] ?? '') !== 'playing' || ($st['mode'] ?? '') !== 'bot' || (string) ($st['turn'] ?? '') !== self::BOT_KEY) {
                return null;
            }
            $free = array_values(array_filter(range(0, self::CHAMBERS - 1), static fn($i) => (int) $st['taken'][$i] === 0));
            if ($free === []) {
                return null;
            }
            return self::pull($st, $free[array_rand($free)], self::BOT_KEY);
        });
        if (is_array($upd)) {
            self::render($imid, $upd);
        }
    }

    private static function thinking(): string
    {
        return L('🤖 نالین داره ماشه رو می‌کشه...', '🤖 Nullin is pulling the trigger...');
    }

    /** The table: a player emoji for every seat (😵 once knocked out) and the gun in the middle. */
    private static function table(array $s): string
    {
        $order = self::order($s);
        $n     = max(1, min(self::MAX, count($order)));
        $g     = array_fill(0, 3, array_fill(0, 3, self::GAP));
        $g[1][1] = !empty($s['last']['hit']) ? '💥' : '🔫';
        foreach ($order as $k => $u) {
            if (!isset(self::SEATS[$n][$k])) {
                continue;
            }
            [$r, $c]    = self::SEATS[$n][$k];
            $g[$r][$c] = !empty($s['players'][$u]['alive']) ? '👤' : '😵';
        }
        return implode("\n", array_map(static fn($row) => implode(' ', $row), $g));
    }

    private static function playerList(array $s, bool $turn): string
    {
        $l = [];
        foreach (self::order($s) as $u) {
            $l[] = self::arrow($s, $u) . ' ' . self::nm($s, $u)
                . (empty($s['players'][$u]['alive']) ? ' 😵' : '')
                . ($turn && (string) ($s['turn'] ?? '') === $u ? ' 👈' : '');
        }
        return implode("\n", $l);
    }

    private static function lastLine(array $s): string
    {
        if (empty($s['last'])) {
            return '';
        }
        $who = self::nm($s, (string) $s['last']['uid']);
        $n   = fa((string) ((int) $s['last']['i'] + 1));
        if (!empty($s['last']['hit'])) {
            return L("💥 <b>بوم!</b> {$who} گلوله خورد و حذف شد 😵؛\n🔄 تفنگ دوباره پر شد.\n\n", "💥 <b>Boom!</b> {$who} got the bullet and is out 😵;\n🔄 the gun is reloaded.\n\n");
        }
        return L("💨 {$who} خونه‌ی {$n} رو زد؛\nخالی بود 😮‍💨\n\n", "💨 {$who} pulled chamber {$n};\nit was empty 😮‍💨\n\n");
    }

    private static function lobbyText(array $s): string
    {
        return '<b>' . self::title() . '</b> · ' . L('لابی', 'Lobby') . "\n" . divider() . "\n"
            . self::table($s) . "\n" . divider() . "\n"
            . L('👥 بازیکن‌ها (', '👥 Players (') . fa((string) count($s['players'])) . "):\n" . self::playerList($s, false) . "\n" . divider() . "\n"
            . L("🔫 تفنگ ۶ تا خونه داره و یکیش گلوله‌ست؛\nهر کی گلوله بخوره حذف می‌شه 😵\n", "🔫 The gun has 6 chambers and one holds a bullet;\nwhoever gets it is out 😵\n")
            . L('➕ برای اومدن دکمه‌ی «پیوستن» رو بزن؛' . "\n" . 'با ۲ تا ۴ نفر سازنده می‌تونه شروع کنه.', '➕ Tap “Join” to get in;' . "\n" . 'the creator can start with 2 to 4 players.');
    }

    private static function lobbyKeyboard(): array
    {
        return ikb([
            [btn(L('➕ پیوستن', '➕ Join'), self::P . ':join', 'success'), btn(L('▶️ شروع', '▶️ Start'), self::P . ':start')],
            GameKit::cancelRow(self::P),
        ]);
    }

    private static function playKeyboard(array $s): array
    {
        $taken = $s['taken'] ?? [];
        $rows  = [];
        for ($r = 0; $r * self::COLS < self::CHAMBERS; $r++) {
            $row = [];
            for ($c = 0; $c < self::COLS; $c++) {
                $i = $r * self::COLS + $c;
                $row[] = (int) ($taken[$i] ?? 0) > 0 ? btn('💨', 'noop', 'primary') : btn('🔘 ' . fa((string) ($i + 1)), self::P . ":cell:{$i}", null);
            }
            $rows[] = $row;
        }
        $rows[] = GameKit::cancelRow(self::P);
        return ikb($rows);
    }

    private static function render(string $imid, array $s, ?string $note = null): void
    {
        $head = '<b>' . self::title() . '</b> · ' . L('دور ', 'Round ') . fa((string) (int) ($s['round'] ?? 1)) . "\n" . divider() . "\n"
            . self::table($s) . "\n" . divider() . "\n"
            . self::playerList($s, ($s['status'] ?? '') === 'playing') . "\n" . divider() . "\n";

        if (($s['status'] ?? '') === 'over') {
            $loser = (string) ($s['last']['uid'] ?? '');
            $end   = L('💥 <b>بوم!</b> ', '💥 <b>Boom!</b> ') . self::nm($s, $loser) . L(" گلوله خورد؛\n🏆 <b>", " got the bullet;\n🏆 <b>") . self::nm($s, (string) $s['winner']) . L(' برد!</b>', ' wins!</b>');
            Rich::editInline($imid, $head . $end, GameKit::replayKeyboard(self::P));
            return;
        }
        $n    = fa((string) self::left($s));
        $c    = fa((string) self::CHAMBERS);
        $turn = self::arrow($s, (string) $s['turn']) . ' ' . self::nm($s, (string) $s['turn']);
        Rich::editInline(
            $imid,
            $head . self::lastLine($s)
                . L("🔫 خونه‌های باقی‌مونده: {$n} از {$c}؛\nیکیشون گلوله‌ست 😈\n\n", "🔫 Chambers left: {$n} of {$c};\none of them holds the bullet 😈\n\n")
                . ($note ?? L("👉 نوبت: {$turn}", "👉 Turn: {$turn}")),
            self::playKeyboard($s)
        );
    }

    public static function onCallback(array $cq, string $data, string $imid): void
    {
        $tg   = App::tg();
        $from = $cq['from'];
        $uid  = (string) $from['id'];
        $s    = GameKit::ensure($imid, self::P, $from);
        $me   = ['name' => mb_substr(userName($from), 0, 28), 'alive' => true];

        $handled = GameKit::common($s, $cq, $data, self::P,
            function (string $mode) use (&$s, $imid, $uid, $me) {
                $s['mode']    = $mode === 'bot' ? 'bot' : 'pvp';
                $s['players'] = [$uid => $me];
                if ($s['mode'] === 'bot') {
                    $s['players'][self::BOT_KEY] = ['name' => L('🤖 نالین', '🤖 Nullin'), 'alive' => true];
                    $s                           = self::begin($s, [$uid, self::BOT_KEY]);
                    GameKit::save($imid, $s);
                    self::render($imid, $s);
                } else {
                    $s['status'] = 'lobby';
                    GameKit::save($imid, $s);
                    Rich::editInline($imid, self::lobbyText($s), self::lobbyKeyboard());
                }
            },
            function () use (&$s, $imid, $tg) {
                $s = GameKit::fresh(self::P, $s['p1']);
                GameKit::save($imid, $s);
                Rich::editInline($imid, self::intro(), self::modeKeyboard());
            },
            function (string $by) use (&$s, $imid, $tg) {
                GameKit::save($imid, $s);
                Rich::editInline($imid, '<b>' . self::title() . "</b>\n" . divider() . "\n" . L("✖️ بازی توسط {$by} لغو شد.", "✖️ Game cancelled by {$by}."), GameKit::replayKeyboard(self::P));
            }
        );
        if ($handled) {
            return;
        }

        if ($data === self::P . ':join') {
            if (($s['status'] ?? '') !== 'lobby') {
                $tg->answer($cq, L('⏳ بازی شروع شده', '⏳ The game has already started'));
                return;
            }
            $res    = null;
            $joined = Store::update(GameKit::NS, $imid, static function ($st) use ($uid, $me, &$res) {
                if (!is_array($st) || ($st['status'] ?? '') !== 'lobby') {
                    $res = 'started';
                    return null;
                }
                if (isset($st['players'][$uid])) {
                    $res = 'in';
                    return null;
                }
                if (count($st['players']) >= self::MAX) {
                    $res = 'full';
                    return null;
                }
                $st['players'][$uid] = $me;
                $res                 = 'ok';
                return $st;
            });
            if ($res === 'ok' && is_array($joined)) {
                Rich::editInline($imid, self::lobbyText($joined), self::lobbyKeyboard());
                $tg->answer($cq, L('✅ اومدی تو بازی!', '✅ You joined the game!'));
            } elseif ($res === 'full') {
                $tg->alert($cq, L('👥 ظرفیت پر شده؛ حداکثر ۴ نفر.', '👥 The table is full; 4 players at most.'));
            } elseif ($res === 'in') {
                $tg->answer($cq, L('✅ قبلاً اومدی تو بازی', '✅ You’re already in the game'));
            } else {
                $tg->answer($cq, L('⏳ بازی شروع شده', '⏳ The game has already started'));
            }
            return;
        }

        if ($data === self::P . ':start') {
            if (($s['status'] ?? '') !== 'lobby') {
                $tg->answer($cq);
                return;
            }
            if (!GameKit::isCreator($s, (int) $uid)) {
                $tg->alert($cq, L('🔒 فقط سازنده می‌تونه بازی رو شروع کنه.', '🔒 Only the creator can start the game.'));
                return;
            }
            if (count($s['players']) < 2) {
                $tg->alert($cq, L('👥 حداقل ۲ نفر باید بیان تو بازی.', '👥 At least 2 players need to join.'));
                return;
            }
            $order = array_map('strval', array_keys($s['players']));
            shuffle($order);
            $s = self::begin($s, $order);
            GameKit::save($imid, $s);
            self::render($imid, $s);
            $tg->answer($cq, L('🚀 شروع!', '🚀 Go!'));
            return;
        }

        if (!str_starts_with($data, self::P . ':cell:')) {
            $tg->answer($cq);
            return;
        }
        if (($s['status'] ?? '') !== 'playing') {
            $tg->answer($cq, L('⌛️ این بازی تموم شده', '⌛️ This game is over'));
            return;
        }
        $i = (int) substr($data, strlen(self::P . ':cell:'));
        if ($i < 0 || $i >= self::CHAMBERS) {
            $tg->answer($cq);
            return;
        }
        if (!isset($s['players'][$uid])) {
            $tg->alert($cq, L('🚫 تو این بازی نیستی؛' . "\n" . 'منتظر بازی بعدی باش.', '🚫 You’re not in this game;' . "\n" . 'wait for the next one.'));
            return;
        }
        if (empty($s['players'][$uid]['alive'])) {
            $tg->answer($cq, L('😵 تو از بازی حذف شدی', '😵 You are out of the game'));
            return;
        }

        $res = null;
        $upd = Store::update(GameKit::NS, $imid, static function ($st) use ($uid, $i, &$res) {
            if (!is_array($st) || ($st['status'] ?? '') !== 'playing') {
                $res = 'over';
                return null;
            }
            if ((string) ($st['turn'] ?? '') !== $uid) {
                $res = 'turn';
                return null;
            }
            if ((int) ($st['taken'][$i] ?? 1) !== 0) {
                $res = 'used';
                return null;
            }
            $st  = self::pull($st, $i, $uid);
            $res = !empty($st['last']['hit']) ? 'hit' : 'safe';
            return $st;
        });

        switch ($res) {
            case 'safe':
            case 'hit':
                $botNext = ($upd['mode'] ?? '') === 'bot' && $upd['status'] === 'playing' && (string) $upd['turn'] === self::BOT_KEY;
                self::render($imid, $upd, $botNext ? self::thinking() : null);
                $tg->answer($cq, $res === 'hit' ? L('💥 بوم!', '💥 Boom!') : L('💨 خالی بود', '💨 Empty'));
                if ($botNext) {
                    self::botTurn($imid);
                }
                return;
            case 'turn':
                $tg->answer($cq, L('⏳ نوبت تو نیست', '⏳ It’s not your turn'));
                return;
            case 'used':
                $tg->answer($cq, L('🈵 این خونه رو قبلاً زدن', '🈵 This chamber is already used'));
                return;
            default:
                $tg->answer($cq, L('⌛️ این بازی تموم شده', '⌛️ This game is over'));
                return;
        }
    }
}
