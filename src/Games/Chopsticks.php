<?php

final class Chopsticks
{
    private const P    = 'chop';
    private const HAND = ['✊', '☝️', '✌️', '🤟', '🖖'];

    public static function intro(): string
    {
        return L(
            "<b>🖐 چاپ‌استیک</b>\n\n"
            . "هر دست با ۱ انگشت شروع می‌کنه. نوبتی با یکی از دست‌هات به یکی از دست‌های حریف می‌زنی و انگشت‌های اون دست به‌اندازه‌ی انگشت‌های دست تو زیاد می‌شه.\n"
            . "هر دستی که به ۵ یا بیشتر برسه بسته می‌شه. به‌جای زدن می‌تونی انگشت‌هات رو بین دو دستت «تقسیم» کنی.\n"
            . "هر کی هر دو دست حریف رو ببنده، می‌بره!\n\n"
            . "حالت بازی رو انتخاب کن:",
            "<b>🖐 Chopsticks</b>\n\n"
            . "Each hand starts with 1 finger. On your turn, hit one of your opponent's hands with one of yours and it gains as many fingers as your hand shows.\n"
            . "A hand that reaches 5 or more closes. Instead of hitting, you can «split» your fingers between your two hands.\n"
            . "Close both of your opponent's hands to win!\n\n"
            . "Pick a game mode:"
        );
    }

    public static function modeKeyboard(): array
    {
        return GameKit::modeKeyboard(self::P);
    }

    private static function emoji(int $n): string
    {
        return self::HAND[max(0, min(4, $n))];
    }

    private static function chip(int $n): string
    {
        return self::emoji($n) . fa($n);
    }

    private static function out(array $h, int $seat): bool
    {
        $i = ($seat - 1) * 2;
        return $h[$i] + $h[$i + 1] === 0;
    }

    private static function moves(array $h, int $t): array
    {
        $me  = ($t - 1) * 2;
        $op  = $t === 1 ? 2 : 0;
        $out = [];
        for ($i = 0; $i < 2; $i++) {
            if ($h[$me + $i] <= 0 || ($i === 1 && $h[$me] === $h[$me + 1])) {
                continue;
            }
            for ($j = 0; $j < 2; $j++) {
                if ($h[$op + $j] <= 0 || ($j === 1 && $h[$op] === $h[$op + 1])) {
                    continue;
                }
                $out[] = ['a', $i, $j];
            }
        }
        $a   = $h[$me];
        $b   = $h[$me + 1];
        $sum = $a + $b;
        for ($l = 0; $l <= 4; $l++) {
            $r = $sum - $l;
            if ($r < $l || $r > 4) {
                continue;
            }
            if ($l === min($a, $b) && $r === max($a, $b)) {
                continue;
            }
            $out[] = ['s', $l, $r];
        }
        return $out;
    }

    private static function apply(array $h, int $t, array $m): array
    {
        $me = ($t - 1) * 2;
        if ($m[0] === 'a') {
            $op      = ($t === 1 ? 2 : 0) + $m[2];
            $h[$op] += $h[$me + $m[1]];
            if ($h[$op] >= 5) {
                $h[$op] = 0;
            }
        } else {
            $h[$me]     = $m[1];
            $h[$me + 1] = $m[2];
        }
        return $h;
    }

    private static function score(array $h, int $t): int
    {
        $me    = ($t - 1) * 2;
        $op    = $t === 1 ? 2 : 0;
        $mine  = ($h[$me] > 0 ? 1 : 0) + ($h[$me + 1] > 0 ? 1 : 0);
        $their = ($h[$op] > 0 ? 1 : 0) + ($h[$op + 1] > 0 ? 1 : 0);
        return ($mine - $their) * 20;
    }

    private static function search(array $h, int $t, int $depth, int $alpha, int $beta): int
    {
        if ($depth === 0) {
            return self::score($h, $t);
        }
        $opp  = 3 - $t;
        $best = -10000;
        foreach (self::moves($h, $t) as $m) {
            $n = self::apply($h, $t, $m);
            if (self::out($n, $opp)) {
                return 1000 + $depth;
            }
            $score = -self::search($n, $opp, $depth - 1, -$beta, -$alpha);
            if ($score > $best) {
                $best = $score;
            }
            if ($best > $alpha) {
                $alpha = $best;
            }
            if ($alpha >= $beta) {
                break;
            }
        }
        return $best;
    }

    private static function botMove(array $h): array
    {
        $moves = self::moves($h, 2);
        if (random_int(1, 100) <= 25) {
            return $moves[array_rand($moves)];
        }
        $top  = -100000;
        $best = [];
        foreach ($moves as $m) {
            $n     = self::apply($h, 2, $m);
            $score = self::out($n, 1) ? 2000 : -self::search($n, 1, 5, -10000, 10000);
            if ($score > $top) {
                $top  = $score;
                $best = [$m];
            } elseif ($score === $top) {
                $best[] = $m;
            }
        }
        return $best[array_rand($best)];
    }

    private static function play(array &$s, int $seat, array $m): void
    {
        $h   = $s['hands'];
        $me  = ($seat - 1) * 2;
        $op  = $seat === 1 ? 2 : 0;
        $log = $s['log'] ?? [];
        $log[] = $m[0] === 'a' ? [$seat, 'a', $h[$me + $m[1]], $h[$op + $m[2]]] : [$seat, 's', 0, 0];
        $s['log']   = array_slice($log, -4);
        $s['hands'] = self::apply($h, $seat, $m);
        if (self::out($s['hands'], 3 - $seat)) {
            $s['status'] = 'over';
            $s['winner'] = $seat;
            return;
        }
        $s['turn'] = 3 - $seat;
    }

    private static function board(array $s): array
    {
        $t    = (int) $s['turn'];
        $h    = $s['hands'];
        $me   = ($t - 1) * 2;
        $op   = $t === 1 ? 2 : 0;
        $atk  = [];
        $spl  = [];
        foreach (self::moves($h, $t) as $m) {
            if ($m[0] === 'a') {
                $mine  = $h[$me + $m[1]];
                $their = $h[$op + $m[2]];
                $label = "\u{200E}" . self::emoji($mine) . $mine . ' ➜ ' . self::emoji($their) . $their;
                $atk[] = btn($label, self::P . ":a:{$m[1]}:{$m[2]}", 'danger');
            } else {
                $label = "\u{200E}🔀 " . $m[1] . ' · ' . $m[2];
                $spl[] = btn($label, self::P . ":s:{$m[1]}:{$m[2]}", 'primary');
            }
        }
        $rows = array_merge(array_chunk($atk, 2), array_chunk($spl, 3));
        $rows[] = GameKit::controlRow($s, self::P);
        return ikb($rows);
    }

    private static function header(array $s): string
    {
        return '<b>' . L('🖐 چاپ‌استیک', '🖐 Chopsticks') . '</b> · ' . ($s['mode'] === 'bot' ? L('با ربات', 'Vs bot') : L('دو نفره', 'Two players')) . "\n" . divider() . "\n";
    }

    private static function handsText(array $s): string
    {
        $out = '';
        foreach ([1, 2] as $seat) {
            $p    = $seat === 1 ? $s['p1'] : ($s['p2'] ?? null);
            $mark = (($s['status'] ?? '') === 'playing' && (int) $s['turn'] === $seat) ? '👉' : '▫️';
            $i    = ($seat - 1) * 2;
            $out .= "{$mark} " . GameKit::name($p, L('❔ منتظر حریف', '❔ Waiting for opponent')) . ':  ' . self::chip($s['hands'][$i]) . '  ' . self::chip($s['hands'][$i + 1]) . "\n";
        }
        return $out;
    }

    private static function logText(array $s): string
    {
        $lines = [];
        foreach (array_slice($s['log'] ?? [], -2) as [$seat, $type, $x, $y]) {
            $who = GameKit::name($seat === 1 ? $s['p1'] : ($s['p2'] ?? null));
            $lines[] = $type === 'a'
                ? "🔹 {$who}: " . self::chip((int) $x) . ' ➜ ' . self::chip((int) $y)
                : "🔀 {$who}" . L(' انگشت‌هاش رو تقسیم کرد', ' split their fingers');
        }
        return $lines ? "\n" . implode("\n", $lines) . "\n" : '';
    }

    private static function render(string $imid, array $s): void
    {
        $body = self::header($s) . self::handsText($s) . self::logText($s) . "\n";
        if ($s['status'] === 'over') {
            $end = !empty($s['resigned'])
                ? GameKit::resignLine($s)
                : '🏆 <b>' . GameKit::name((int) ($s['winner'] ?? 1) === 1 ? $s['p1'] : $s['p2']) . L(' برد!</b>', ' wins!</b>');
            Rich::editInline($imid, $body . $end, GameKit::replayKeyboard(self::P));
            return;
        }
        $turn = (int) $s['turn'] === 1
            ? GameKit::name($s['p1'])
            : GameKit::name($s['p2'] ?? null, L('هر کی اول بزنه', 'Whoever taps first'));
        Rich::editInline($imid, $body . L("👇 نوبت: {$turn}", "👇 Turn: {$turn}") . GameKit::resignNote($s), self::board($s));
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
                $s['hands']  = [1, 1, 1, 1];
                $s['turn']   = 1;
                $s['log']    = [];
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
                Rich::editInline($imid, self::header($s) . self::handsText($s) . "\n" . L("✖️ بازی توسط {$by} لغو شد.", "✖️ Game cancelled by {$by}."), GameKit::replayKeyboard(self::P));
            },
            function () use (&$s, $imid) {
                GameKit::save($imid, $s);
                self::render($imid, $s);
            }
        );
        if ($handled) {
            return;
        }

        if (!preg_match('/^' . self::P . ':([as]):(\d):(\d)$/', $data, $mm) || ($s['status'] ?? '') !== 'playing') {
            $tg->answer($cq, L('⌛️ این بازی تموم شده', '⌛️ This game is over'));
            return;
        }
        $move = [$mm[1], (int) $mm[2], (int) $mm[3]];

        $turnSeat = (int) $s['turn'];
        if ($s['mode'] === 'bot') {
            if (!GameKit::isCreator($s, (int) $from['id'])) {
                $tg->alert($cq, L('🚫 این بازی مال یکی دیگه‌ست؛' . "\n" . 'خودت از منو یکی بساز!', '🚫 This game belongs to someone else;' . "\n" . 'start your own from the menu!'));
                return;
            }
        } else {
            if (empty($s['p2']) && !GameKit::isCreator($s, (int) $from['id']) && $turnSeat === 1) {
                $tg->answer($cq, L('⏳ اول سازنده‌ی بازی می‌زنه؛ بعدش نوبت توئه', '⏳ The creator goes first; then it’s your turn'));
                return;
            }
            $seat = GameKit::seat($s, $from, $turnSeat);
            if ($seat === 0) {
                $tg->alert($cq, L('🚫 این بازی دو نفره پر شده.', '🚫 This two-player game is full.'));
                return;
            }
            if ($seat !== $turnSeat) {
                $tg->answer($cq, L('⏳ نوبت تو نیست', '⏳ It’s not your turn'));
                return;
            }
        }

        if (!in_array($move, self::moves($s['hands'], $turnSeat), true)) {
            $tg->answer($cq, L('🚫 این حرکت الان ممکن نیست', '🚫 That move isn’t possible right now'));
            return;
        }

        unset($s['resign_ask']);
        self::play($s, $turnSeat, $move);
        if ($s['status'] === 'playing' && $s['mode'] === 'bot') {
            self::play($s, 2, self::botMove($s['hands']));
        }
        GameKit::save($imid, $s);
        self::render($imid, $s);
        $tg->answer($cq);
    }
}
