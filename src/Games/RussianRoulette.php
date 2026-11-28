<?php

final class RussianRoulette
{
    private const P         = 'rr';
    private const CHAMBERS  = 6;
    private const COLS      = 3;
    private const BOT_THINK = [1200, 2200]; // ms the bot waits before it pulls the trigger

    public static function intro(): string
    {
        return L(
            "<b>🔫 روسی‌رولت</b>\n\nتفنگ ۶ تا خونه داره و فقط یکیش گلوله‌ست؛\nهر نفر یه خونه رو انتخاب می‌کنه و ماشه رو می‌کشه،\nهر کی گلوله بخوره می‌بازه!\n\nحالت بازی رو انتخاب کن:",
            "<b>🔫 Russian roulette</b>\n\nThe gun has 6 chambers and only one holds a bullet;\nplayers take turns picking a chamber and pulling the trigger,\nwhoever gets the bullet loses!\n\nPick a game mode:"
        );
    }

    public static function modeKeyboard(): array
    {
        return GameKit::modeKeyboard(self::P);
    }

    private static function start(array $s, string $mode): array
    {
        $s['mode']   = $mode === 'bot' ? 'bot' : 'pvp';
        $s['status'] = 'playing';
        $s['bullet'] = random_int(0, self::CHAMBERS - 1);
        $s['taken']  = array_fill(0, self::CHAMBERS, 0); // 0 untouched, 1/2 safe pull by that seat, 3 the bullet
        $s['turn']   = 1;
        $s['last']   = null;
        $s['loser']  = 0;
        $s['p2']     = $s['mode'] === 'bot' ? ['id' => 0, 'name' => L('🤖 نالین', '🤖 Nullin')] : null;
        return $s;
    }

    /** Pulls the trigger on chamber $i for $seat. Returns true when it was the bullet. */
    private static function pull(array &$s, int $i, int $seat): bool
    {
        unset($s['resign_ask']);
        $hit            = $i === (int) $s['bullet'];
        $s['taken'][$i] = $hit ? 3 : $seat;
        $s['last']      = [$i, $seat];
        if ($hit) {
            $s['status'] = 'over';
            $s['loser']  = $seat;
        } else {
            $s['turn'] = $seat === 1 ? 2 : 1;
        }
        return $hit;
    }

    private static function left(array $s): int
    {
        return count(array_filter($s['taken'], static fn($t) => (int) $t === 0));
    }

    /** The bot plays by itself after your safe pull: waits, then pulls on a random free chamber. */
    private static function botTurn(string $imid): void
    {
        usleep(random_int(self::BOT_THINK[0], self::BOT_THINK[1]) * 1000);
        $s = GameKit::load($imid);
        if ($s === null || ($s['status'] ?? '') !== 'playing' || ($s['mode'] ?? '') !== 'bot' || (int) ($s['turn'] ?? 1) !== 2) {
            return;
        }
        $free = array_values(array_filter(range(0, self::CHAMBERS - 1), static fn($i) => (int) $s['taken'][$i] === 0));
        if ($free === []) {
            return;
        }
        self::pull($s, $free[array_rand($free)], 2);
        GameKit::save($imid, $s);
        self::render($imid, $s);
    }

    private static function thinking(): string
    {
        return L('🤖 نالین داره ماشه رو می‌کشه...', '🤖 Nullin is pulling the trigger...');
    }

    private static function lastLine(array $s): string
    {
        if (empty($s['last'])) {
            return '';
        }
        $i    = (int) $s['last'][0];
        $seat = (int) $s['last'][1];
        $who  = GameKit::name($seat === 1 ? $s['p1'] : ($s['p2'] ?? null));
        $n    = fa((string) ($i + 1));
        return L("💨 {$who} خونه‌ی {$n} رو زد؛\nخالی بود 😮‍💨\n\n", "💨 {$who} pulled chamber {$n};\nit was empty 😮‍💨\n\n");
    }

    private static function board(array $s, bool $active): array
    {
        $taken = $s['taken'] ?? [];
        $rows  = [];
        for ($r = 0; $r * self::COLS < self::CHAMBERS; $r++) {
            $row = [];
            for ($c = 0; $c < self::COLS; $c++) {
                $i = $r * self::COLS + $c;
                $t = (int) ($taken[$i] ?? 0);
                if ($t === 3) {
                    $row[] = btn('💥', 'noop', 'danger');
                } elseif ($t > 0) {
                    $row[] = btn('💨', 'noop', $t === 1 ? 'primary' : 'danger');
                } elseif (!$active) {
                    $row[] = btn('⚪', 'noop', null);
                } else {
                    $row[] = btn('🔘 ' . fa((string) ($i + 1)), self::P . ":cell:{$i}", null);
                }
            }
            $rows[] = $row;
        }
        if ($active) {
            if (($s['mode'] ?? '') === 'pvp' && empty($s['p2'])) {
                $rows[] = GameKit::cancelRow(self::P);
            }
        } else {
            $rows[] = [btn(L('🔁 بازی دوباره', '🔁 Play again'), self::P . ':replay', 'success')];
        }
        return ikb($rows);
    }

    private static function header(array $s): string
    {
        return '<b>' . L('🔫 روسی‌رولت', '🔫 Russian roulette') . '</b> · ' . (($s['mode'] ?? '') === 'bot' ? L('با ربات', 'Vs bot') : L('دو نفره', 'Two players')) . "\n" . divider() . "\n"
            . '🔵 ' . GameKit::name($s['p1']) . '  vs  🔴 ' . GameKit::name($s['p2'] ?? null, L('❔ منتظر حریف', '❔ Waiting for opponent')) . "\n" . divider() . "\n";
    }

    private static function render(string $imid, array $s, ?string $note = null): void
    {
        if ($s['status'] === 'over') {
            if (!empty($s['loser'])) {
                $loser  = (int) $s['loser'] === 1 ? $s['p1'] : ($s['p2'] ?? null);
                $winner = (int) $s['loser'] === 1 ? ($s['p2'] ?? null) : $s['p1'];
                $end    = L('💥 <b>بوم!</b> ', '💥 <b>Boom!</b> ') . GameKit::name($loser) . L(" گلوله خورد؛\n🏆 <b>", " got the bullet;\n🏆 <b>") . GameKit::name($winner) . L(' برد!</b>', ' wins!</b>');
            } else {
                $end = '';
            }
            Rich::editInline($imid, self::header($s) . $end, self::board($s, false));
            return;
        }
        $turn = (int) $s['turn'] === 1 ? '🔵 ' . GameKit::name($s['p1']) : '🔴 ' . GameKit::name($s['p2'] ?? null, L('هر کی اول بزنه', 'Whoever taps first'));
        $n    = fa((string) self::left($s));
        $c    = fa((string) self::CHAMBERS);
        Rich::editInline(
            $imid,
            self::header($s) . self::lastLine($s)
                . L("🔫 خونه‌های باقی‌مونده: {$n} از {$c}؛\nیکیشون گلوله‌ست 😈\n\n", "🔫 Chambers left: {$n} of {$c};\none of them holds the bullet 😈\n\n")
                . ($note ?? L("👉 نوبت: {$turn}", "👉 Turn: {$turn}")),
            self::board($s, true)
        );
    }

    public static function onCallback(array $cq, string $data, string $imid): void
    {
        $tg   = App::tg();
        $from = $cq['from'];
        $s    = GameKit::ensure($imid, self::P, $from);

        $handled = GameKit::common($s, $cq, $data, self::P,
            function (string $mode) use (&$s, $imid) {
                $s = self::start($s, $mode);
                GameKit::save($imid, $s);
                self::render($imid, $s);
            },
            function () use (&$s, $imid, $tg) {
                $s = GameKit::fresh(self::P, $s['p1']);
                GameKit::save($imid, $s);
                Rich::editInline($imid, self::intro(), self::modeKeyboard());
            },
            function (string $by) use (&$s, $imid, $tg) {
                GameKit::save($imid, $s);
                Rich::editInline($imid, self::header($s) . L("✖️ بازی توسط {$by} لغو شد.", "✖️ Game cancelled by {$by}."), self::board($s, false));
            }
        );
        if ($handled) {
            return;
        }

        if (($s['status'] ?? '') !== 'playing') {
            $tg->answer($cq, L('⌛️ این بازی تموم شده', '⌛️ This game is over'));
            return;
        }

        if (!str_starts_with($data, self::P . ':cell:')) {
            $tg->answer($cq);
            return;
        }
        $i = (int) substr($data, strlen(self::P . ':cell:'));
        if ($i < 0 || $i >= self::CHAMBERS) {
            $tg->answer($cq);
            return;
        }

        $turn = (int) $s['turn'];
        if ($s['mode'] === 'bot') {
            if (!GameKit::isCreator($s, (int) $from['id'])) {
                $tg->alert($cq, L('🚫 این بازی مال یکی دیگه‌ست؛' . "\n" . 'خودت از منو یکی بساز!', '🚫 This game belongs to someone else;' . "\n" . 'start your own from the menu!'));
                return;
            }
            if ($turn !== 1) {
                $tg->answer($cq, L('⏳ نوبت تو نیست', '⏳ It’s not your turn'));
                return;
            }
        } else {
            $seat = GameKit::seat($s, $from, $turn);
            if ($seat === 0) {
                if (empty($s['p2'])) {
                    $tg->answer($cq, L('⏳ نوبت تو نیست', '⏳ It’s not your turn'));
                } else {
                    $tg->alert($cq, L('🚫 این بازی دو نفره پر شده.', '🚫 This two-player game is full.'));
                }
                return;
            }
            if ($seat !== $turn) {
                $tg->answer($cq, L('⏳ نوبت تو نیست', '⏳ It’s not your turn'));
                return;
            }
        }

        if ((int) $s['taken'][$i] !== 0) {
            $tg->answer($cq, L('🈵 این خونه رو قبلاً زدن', '🈵 This chamber is already used'));
            return;
        }

        $hit     = self::pull($s, $i, $turn);
        $botNext = ($s['mode'] ?? '') === 'bot' && $s['status'] === 'playing' && (int) $s['turn'] === 2;
        GameKit::save($imid, $s);
        self::render($imid, $s, $botNext ? self::thinking() : null);
        $tg->answer($cq, $hit ? L('💥 بوم!', '💥 Boom!') : L('💨 خالی بود', '💨 Empty'));
        if ($botNext) {
            self::botTurn($imid);
        }
    }
}
