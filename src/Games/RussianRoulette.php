<?php

final class RussianRoulette
{
    private const P         = 'rr';
    private const CHAMBERS  = 6;
    private const COLS      = 3;
    private const MAX       = 4;
    private const DIRS      = ['⬆️', '↗️', '➡️', '↘️', '⬇️', '↙️', '⬅️', '↖️']; // where the spun gun points
    private const BOT_KEY   = 'bot';
    private const BOT_THINK = [1200, 2200]; // ms the bot waits before it pulls the trigger

    public static function intro(): string
    {
        return L(
            "<b>🔫 روسی‌رولت</b>\n\nتفنگ ۶ تا خونه داره و فقط یکیش گلوله‌ست؛\nهر نفر به نوبت ماشه رو می‌کشه و قبل از هر نوبت تفنگ می‌چرخه 🌀،\nهر کی گلوله بخوره حذف می‌شه 😵\nو آخرین نفر زنده می‌بره!\n\nحالت بازی رو انتخاب کن:",
            "<b>🔫 Russian roulette</b>\n\nThe gun has 6 chambers and only one holds a bullet;\nplayers take turns pulling the trigger and the gun is spun before every turn 🌀,\nwhoever gets the bullet is out 😵\nand the last player standing wins!\n\nPick a game mode:"
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


    private static function begin(array $s, array $order): array
    {
        $s['status'] = 'playing';
        $s['order']  = array_values(array_map('strval', $order));
        $s['round']  = 1;
        $s['dir']    = self::spin(null);
        $s['turn']   = (string) $order[0];
        $s['last']   = null;
        $s['winner'] = '';
        foreach (array_keys($s['players']) as $u) {
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
    /** The gun is spun before every pull: a new direction, and each pull is a fresh 1-in-6 chance. */
    private static function spin(?int $prev): int
    {
        do {
            $d = random_int(0, count(self::DIRS) - 1);
        } while ($d === $prev);
        return $d;
    }

    /** Pulls the trigger for $uid. A hit knocks that player out; either way the gun is spun again. */
    private static function pull(array $s, string $uid): array
    {
        $hit        = random_int(0, self::CHAMBERS - 1) === 0;
        $s['last']  = ['uid' => $uid, 'hit' => $hit];
        $s['dir']   = self::spin(isset($s['dir']) ? (int) $s['dir'] : null);
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
        $s['turn'] = self::nextAlive($s, $uid);
        return $s;
    }

    /** The bot plays by itself after your safe pull: waits, then pulls the trigger. */
    private static function botTurn(string $imid): void
    {
        usleep(random_int(self::BOT_THINK[0], self::BOT_THINK[1]) * 1000);
        $upd = Store::update(GameKit::NS, $imid, static function ($st) {
            if (!is_array($st) || ($st['status'] ?? '') !== 'playing' || ($st['mode'] ?? '') !== 'bot' || (string) ($st['turn'] ?? '') !== self::BOT_KEY) {
                return null;
            }
            return self::pull($st, self::BOT_KEY);
        });
        if (is_array($upd)) {
            self::render($imid, $upd);
        }
    }

    private static function thinking(): string
    {
        return L('🤖 نالین داره ماشه رو می‌کشه...', '🤖 Nullin is pulling the trigger...');
    }

    /** One player's seat on the table: the name sits in the button (👤 alive, 😵 out, 🏆 winner, green = their turn). */
    private static function seat(array $s, string $u, int $max): array
    {
        $p    = $s['players'][$u] ?? ['name' => '?'];
        $name = $u === self::BOT_KEY ? L('نالین', 'Nullin') : (string) $p['name'];
        if (mb_strlen($name) > $max) {
            $name = mb_substr($name, 0, $max - 1) . '…';
        }
        $status = $s['status'] ?? '';
        if (empty($p['alive'])) {
            [$icon, $style] = ['😵', 'danger'];
        } elseif ($status === 'over') {
            [$icon, $style] = ['🏆', 'success'];
        } elseif ($status === 'playing' && (string) ($s['turn'] ?? '') === $u) {
            [$icon, $style] = ['👤', 'success'];
        } else {
            [$icon, $style] = ['👤', 'primary'];
        }
        if ($u === self::BOT_KEY && $icon === '👤') {
            $icon = '🤖';
        }
        $b        = btn("{$icon} {$name}", 'noop', $style);
        $b['ctr'] = true;
        return $b;
    }

    /** The middle of the table: the spun gun as an arrow that changes direction every turn (💥 right after a hit). */
    private static function gun(array $s): array
    {
        if (!empty($s['last']['hit'])) {
            $e = '💥';
            $b = btn($e, 'noop', 'danger');
        } elseif (isset($s['dir'])) {
            $b = btn(self::DIRS[(int) $s['dir'] % count(self::DIRS)], 'noop', null);
        } else {
            $b = btn('🔫', 'noop', null);
        }
        $b['ctr'] = true;
        return $b;
    }

    /** The table as buttons: seats around the gun. 2 = top/bottom, 3 = top + two below, 4 = a cross. */
    private static function tableRows(array $s): array
    {
        $o = self::order($s);
        $n = min(self::MAX, count($o));
        $g = self::gun($s);
        if ($n <= 1) {
            return [[self::seat($s, $o[0], 18)], [$g]];
        }
        if ($n === 2) {
            return [[self::seat($s, $o[0], 18)], [$g], [self::seat($s, $o[1], 18)]];
        }
        if ($n === 3) {
            return [[self::seat($s, $o[0], 18)], [$g], [self::seat($s, $o[2], 10), self::seat($s, $o[1], 10)]];
        }
        return [[self::seat($s, $o[0], 18)], [self::seat($s, $o[3], 8), $g, self::seat($s, $o[1], 8)], [self::seat($s, $o[2], 18)]];
    }

    private static function lobbyKeyboard(array $s): array
    {
        return ikb(array_merge(self::tableRows($s), [
            [btn(L('➕ پیوستن', '➕ Join'), self::P . ':join', 'success'), btn(L('▶️ شروع', '▶️ Start'), self::P . ':start')],
            GameKit::cancelRow(self::P),
        ]));
    }

    private static function playKeyboard(array $s): array
    {
        $rows   = self::tableRows($s);
        $rows[] = [btn(L('🔫 ماشه رو بکش', '🔫 Pull the trigger'), self::P . ':pull', 'danger')];
        $rows[] = GameKit::cancelRow(self::P);
        return ikb($rows);
    }

    private static function overKeyboard(array $s): array
    {
        return ikb(array_merge(self::tableRows($s), [[btn(L('🔁 بازی دوباره', '🔁 Play again'), self::P . ':replay', 'success')]]));
    }

    private static function head(array $s, string $label): string
    {
        return '<b>' . self::title() . '</b> · ' . $label . "\n" . divider() . "\n";
    }

    private static function lastLine(array $s): string
    {
        if (empty($s['last'])) {
            return '';
        }
        $who = self::nm($s, (string) $s['last']['uid']);
        if (!empty($s['last']['hit'])) {
            return L("💥 <b>بوم!</b> {$who} گلوله خورد و حذف شد 😵؛\n🔄 تفنگ دوباره پر شد و چرخید 🌀\n\n", "💥 <b>Boom!</b> {$who} got the bullet and is out 😵;\n🔄 the gun is reloaded and spun 🌀\n\n");
        }
        return L("💨 {$who} ماشه رو کشید؛\nخالی بود 😮‍💨\n🌀 تفنگ دوباره چرخید.\n\n", "💨 {$who} pulled the trigger;\nit was empty 😮‍💨\n🌀 the gun was spun again.\n\n");
    }

    private static function lobbyText(array $s): string
    {
        return self::head($s, L('لابی', 'Lobby'))
            . L('👥 بازیکن‌ها: ', '👥 Players: ') . fa((string) count($s['players'])) . L(' از ', ' of ') . fa((string) self::MAX) . "\n\n"
            . L("🔫 تفنگ ۶ تا خونه داره و یکیش گلوله‌ست؛\nهر کی گلوله بخوره حذف می‌شه 😵\n", "🔫 The gun has 6 chambers and one holds a bullet;\nwhoever gets it is out 😵\n")
            . L('➕ برای اومدن دکمه‌ی «پیوستن» رو بزن؛' . "\n" . 'با ۲ تا ۴ نفر سازنده می‌تونه شروع کنه.', '➕ Tap “Join” to get in;' . "\n" . 'the creator can start with 2 to 4 players.');
    }

    private static function render(string $imid, array $s, ?string $note = null): void
    {
        $head = self::head($s, L('دور ', 'Round ') . fa((string) (int) ($s['round'] ?? 1)));
        if (($s['status'] ?? '') === 'over') {
            $loser = (string) ($s['last']['uid'] ?? '');
            $end   = L('💥 <b>بوم!</b> ', '💥 <b>Boom!</b> ') . self::nm($s, $loser) . L(" گلوله خورد؛\n🏆 <b>", " got the bullet;\n🏆 <b>") . self::nm($s, (string) $s['winner']) . L(' برد!</b>', ' wins!</b>');
            Rich::editInline($imid, $head . $end, self::overKeyboard($s));
            return;
        }
        $c    = fa((string) self::CHAMBERS);
        $turn = self::nm($s, (string) $s['turn']);
        Rich::editInline(
            $imid,
            $head . self::lastLine($s)
                . L("🔫 تفنگ {$c} خونه داره و یکیش گلوله‌ست؛\nهر نوبت دوباره می‌چرخه، پس شانس هر بار ۱ از {$c}ـه 😈\n\n", "🔫 The gun has {$c} chambers and one holds a bullet;\nit is spun every turn, so each pull is a 1 in {$c} chance 😈\n\n")
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
                    Rich::editInline($imid, self::lobbyText($s), self::lobbyKeyboard($s));
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
                Rich::editInline($imid, self::lobbyText($joined), self::lobbyKeyboard($joined));
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

        if ($data !== self::P . ':pull') {
            $tg->answer($cq);
            return;
        }
        if (($s['status'] ?? '') !== 'playing') {
            $tg->answer($cq, L('⌛️ این بازی تموم شده', '⌛️ This game is over'));
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
        $upd = Store::update(GameKit::NS, $imid, static function ($st) use ($uid, &$res) {
            if (!is_array($st) || ($st['status'] ?? '') !== 'playing') {
                $res = 'over';
                return null;
            }
            if ((string) ($st['turn'] ?? '') !== $uid) {
                $res = 'turn';
                return null;
            }
            $st  = self::pull($st, $uid);
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
            default:
                $tg->answer($cq, L('⌛️ این بازی تموم شده', '⌛️ This game is over'));
                return;
        }
    }
}
