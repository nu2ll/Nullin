<?php

final class DotsBoxes
{
    private const P     = 'dab';
    private const SIDE  = 3;
    private const H     = 12;
    private const EDGES = 24;
    private const BOXES = 9;
    private const FULL  = (1 << 24) - 1;
    private const SKILL = 80;
    private const SOLVE = 15;

    private static array $memo = [];
    private static array $adj  = [];
    private static array $bm   = [];

    public static function intro(): string
    {
        return L(
            "<b>📦 نقطه و جعبه</b>\n\nبین نقطه‌ها خط بکش؛\nمربعی که ببندی مال خودته و یه نوبت دیگه می‌گیری.\nآخرش هر کی مربع بیشتری داشته باشه می‌بره!\n\nحالت بازی رو انتخاب کن:",
            "<b>📦 Dots & Boxes</b>\n\nDraw lines between the dots; close a square to own it and play again.\nThe player with the most squares wins!\n\nPick a game mode:"
        );
    }

    public static function modeKeyboard(): array
    {
        return GameKit::modeKeyboard(self::P);
    }

    private static function edgeBoxes(int $e): array
    {
        $out = [];
        if ($e < self::H) {
            $r = intdiv($e, self::SIDE);
            $c = $e % self::SIDE;
            if ($r > 0) {
                $out[] = ($r - 1) * self::SIDE + $c;
            }
            if ($r < self::SIDE) {
                $out[] = $r * self::SIDE + $c;
            }
            return $out;
        }
        $k = $e - self::H;
        $r = intdiv($k, self::SIDE + 1);
        $c = $k % (self::SIDE + 1);
        if ($c > 0) {
            $out[] = $r * self::SIDE + $c - 1;
        }
        if ($c < self::SIDE) {
            $out[] = $r * self::SIDE + $c;
        }
        return $out;
    }

    private static function boxEdges(int $b): array
    {
        $r = intdiv($b, self::SIDE);
        $c = $b % self::SIDE;
        $v = self::H + $r * (self::SIDE + 1) + $c;
        return [$r * self::SIDE + $c, ($r + 1) * self::SIDE + $c, $v, $v + 1];
    }

    private static function sides(array $edges, int $b): int
    {
        $n = 0;
        foreach (self::boxEdges($b) as $e) {
            if ($edges[$e] !== 0) {
                $n++;
            }
        }
        return $n;
    }

    private static function play(array &$s, int $e, int $seat): int
    {
        $s['edges'][$e] = $seat;
        $got            = 0;
        foreach (self::edgeBoxes($e) as $b) {
            if ($s['boxes'][$b] === 0 && self::sides($s['edges'], $b) === 4) {
                $s['boxes'][$b] = $seat;
                $got++;
            }
        }
        return $got;
    }

    private static function done(array $s): bool
    {
        return !in_array(0, $s['boxes'], true);
    }

    private static function score(array $s): array
    {
        return [count(array_keys($s['boxes'], 1, true)), count(array_keys($s['boxes'], 2, true))];
    }

    private static function prepare(): void
    {
        if (self::$bm) {
            return;
        }
        for ($b = 0; $b < self::BOXES; $b++) {
            $m = 0;
            foreach (self::boxEdges($b) as $e) {
                $m |= 1 << $e;
            }
            self::$bm[$b] = $m;
        }
        for ($e = 0; $e < self::EDGES; $e++) {
            self::$adj[$e] = self::edgeBoxes($e);
        }
    }

    private static function gain(int $mask, int $e): int
    {
        $n   = $mask | (1 << $e);
        $got = 0;
        foreach (self::$adj[$e] as $b) {
            if (($n & self::$bm[$b]) === self::$bm[$b]) {
                $got++;
            }
        }
        return $got > 0 ? $got + self::value($n) : -self::value($n);
    }

    private static function value(int $mask): int
    {
        if ($mask === self::FULL) {
            return 0;
        }
        if (isset(self::$memo[$mask])) {
            return self::$memo[$mask];
        }
        $best = -99;
        for ($e = 0; $e < self::EDGES; $e++) {
            if ($mask & (1 << $e)) {
                continue;
            }
            $v = self::gain($mask, $e);
            if ($v > $best) {
                $best = $v;
            }
        }
        return self::$memo[$mask] = $best;
    }

    private static function solve(array $s): int
    {
        self::prepare();
        $mask = 0;
        foreach ($s['edges'] as $e => $o) {
            if ($o !== 0) {
                $mask |= 1 << $e;
            }
        }
        $best = -99;
        $pool = [];
        for ($e = 0; $e < self::EDGES; $e++) {
            if ($mask & (1 << $e)) {
                continue;
            }
            $v = self::gain($mask, $e);
            if ($v > $best) {
                $best = $v;
                $pool = [$e];
            } elseif ($v === $best) {
                $pool[] = $e;
            }
        }
        return $pool[array_rand($pool)];
    }

    private static function botMove(array $s): int
    {
        $free  = array_keys($s['edges'], 0, true);
        $sharp = random_int(1, 100) <= self::SKILL;
        if ($sharp && count($free) <= self::SOLVE) {
            return self::solve($s);
        }
        $take = [];
        $cost = [];
        foreach ($free as $e) {
            $give = 0;
            $grab = false;
            foreach (self::edgeBoxes($e) as $b) {
                $n = self::sides($s['edges'], $b);
                if ($n === 3) {
                    $grab = true;
                } elseif ($n === 2) {
                    $give++;
                }
            }
            if ($grab) {
                $take[] = $e;
            }
            $cost[$give][] = $e;
        }
        if ($take && $sharp) {
            return $take[array_rand($take)];
        }
        ksort($cost);
        $pool = reset($cost);
        return $pool[array_rand($pool)];
    }

    private static function botTurn(array &$s): void
    {
        do {
            $got = self::play($s, self::botMove($s), 2);
        } while ($got > 0 && !self::done($s));
        $s['turn'] = 1;
    }

    private static function edgeButton(array $s, int $e, bool $active): array
    {
        $o = $s['edges'][$e];
        if ($o === 0) {
            return btn('⬜', $active ? self::P . ":e:{$e}" : 'noop', null);
        }
        return btn($o === 1 ? '🟥' : '🟦', 'noop', $o === 1 ? 'danger' : 'primary');
    }

    private static function board(array $s, bool $active): array
    {
        $size = self::SIDE * 2 + 1;
        $rows = [];
        for ($y = 0; $y < $size; $y++) {
            $row = [];
            for ($x = 0; $x < $size; $x++) {
                if ($y % 2 === 0 && $x % 2 === 0) {
                    $row[] = btn('⚫', 'noop', null);
                } elseif ($y % 2 === 0) {
                    $row[] = self::edgeButton($s, intdiv($y, 2) * self::SIDE + intdiv($x, 2), $active);
                } elseif ($x % 2 === 0) {
                    $row[] = self::edgeButton($s, self::H + intdiv($y, 2) * (self::SIDE + 1) + intdiv($x, 2), $active);
                } else {
                    $o     = $s['boxes'][intdiv($y, 2) * self::SIDE + intdiv($x, 2)];
                    $row[] = $o === 0 ? btn('▫️', 'noop', null) : btn($o === 1 ? '🔴' : '🔵', 'noop', $o === 1 ? 'danger' : 'primary');
                }
            }
            $rows[] = $row;
        }
        $rows[] = $active ? GameKit::controlRow($s, self::P) : [btn(L('🔁 بازی دوباره', '🔁 Play again'), self::P . ':replay', 'success')];
        return ikb($rows);
    }

    private static function header(array $s): string
    {
        [$a, $b] = self::score($s);
        return '<b>' . L('📦 نقطه و جعبه', '📦 Dots & Boxes') . '</b> · ' . ($s['mode'] === 'bot' ? L('با ربات', 'Vs bot') : L('دو نفره', 'Two players')) . "\n" . divider() . "\n"
            . '🔴 ' . GameKit::name($s['p1']) . '  vs  🔵 ' . GameKit::name($s['p2'] ?? null, L('❔ منتظر حریف', '❔ Waiting for opponent')) . "\n"
            . '🏅 <b>' . fa($a) . '</b> — <b>' . fa($b) . '</b>' . "\n" . divider() . "\n";
    }

    private static function render(string $imid, array $s): void
    {
        if ($s['status'] === 'over') {
            [$a, $b] = self::score($s);
            $end     = !empty($s['resigned']) ? GameKit::resignLine($s)
                : '🏆 <b>' . GameKit::name($a > $b ? $s['p1'] : $s['p2']) . L(' برد!</b>', ' wins!</b>');
            Rich::editInline($imid, self::header($s) . $end, self::board($s, false));
            return;
        }
        $turn = (int) $s['turn'] === 1 ? '🔴 ' . GameKit::name($s['p1']) : '🔵 ' . GameKit::name($s['p2'] ?? null, L('هر کی اول بزنه', 'Whoever taps first'));
        Rich::editInline($imid, self::header($s) . L("👉 نوبت: {$turn}", "👉 Turn: {$turn}") . GameKit::resignNote($s), self::board($s, true));
    }

    public static function onCallback(array $cq, string $data, string $imid): void
    {
        $tg   = App::tg();
        $from = $cq['from'];
        $s    = GameKit::ensure($imid, self::P, $from);

        $handled = GameKit::common($s, $cq, $data, self::P,
            function (string $mode) use (&$s, $imid) {
                $s['mode']   = $mode === 'bot' ? 'bot' : 'pvp';
                $s['status'] = 'playing';
                $s['edges']  = array_fill(0, self::EDGES, 0);
                $s['boxes']  = array_fill(0, self::BOXES, 0);
                $s['turn']   = 1;
                $s['p2']     = $s['mode'] === 'bot' ? ['id' => 0, 'name' => L('🤖 نالین', '🤖 Nullin')] : null;
                GameKit::save($imid, $s);
                self::render($imid, $s);
            },
            function () use (&$s, $imid) {
                $s = GameKit::fresh(self::P, $s['p1']);
                GameKit::save($imid, $s);
                Rich::editInline($imid, self::intro(), self::modeKeyboard());
            },
            function (string $by) use (&$s, $imid) {
                GameKit::save($imid, $s);
                Rich::editInline($imid, self::header($s) . L("✖️ بازی توسط {$by} لغو شد.", "✖️ Game cancelled by {$by}."), self::board($s, false));
            },
            function () use (&$s, $imid) {
                GameKit::save($imid, $s);
                self::render($imid, $s);
            }
        );
        if ($handled) {
            return;
        }

        if (!str_starts_with($data, self::P . ':e:') || ($s['status'] ?? '') !== 'playing') {
            $tg->answer($cq, L('⌛️ این بازی تموم شده', '⌛️ This game is over'));
            return;
        }
        $e = (int) substr($data, strlen(self::P . ':e:'));
        if ($e < 0 || $e >= self::EDGES) {
            $tg->answer($cq);
            return;
        }

        $turnSeat = (int) $s['turn'];
        if ($s['mode'] === 'bot') {
            if (!GameKit::isCreator($s, (int) $from['id'])) {
                $tg->alert($cq, L('🚫 این بازی مال یکی دیگه‌ست؛' . "\n" . 'خودت از منو یکی بساز!', '🚫 This game belongs to someone else;' . "\n" . 'start your own from the menu!'));
                return;
            }
        } else {
            $seat = GameKit::seat($s, $from, empty($s['p2']) ? 2 : $turnSeat);
            if ($seat === 0) {
                $tg->alert($cq, L('🚫 این بازی دو نفره پر شده.', '🚫 This two-player game is full.'));
                return;
            }
            if ($seat !== $turnSeat) {
                $tg->answer($cq, L('⏳ نوبت تو نیست', '⏳ It’s not your turn'));
                return;
            }
        }
        if ($s['edges'][$e] !== 0) {
            $tg->answer($cq, L('🈵 این خط کشیده شده', '🈵 This line is already drawn'));
            return;
        }

        unset($s['resign_ask']);
        $got = self::play($s, $e, $turnSeat);
        if (!self::done($s) && $got === 0) {
            if ($s['mode'] === 'bot') {
                self::botTurn($s);
            } else {
                $s['turn'] = $turnSeat === 1 ? 2 : 1;
            }
        }
        if (self::done($s)) {
            $s['status'] = 'over';
        }
        GameKit::save($imid, $s);
        self::render($imid, $s);
        $tg->answer($cq, $got > 0 ? L('📦 مربع گرفتی! دوباره بزن', '📦 You got a square! Go again') : null);
    }
}
