<?php

final class Memory
{
    private const P     = 'mem';
    private const PAIRS = 8;
    private const COLS  = 4;
    private const HIDE  = '❓';
    private const BOT_MEMORY = 60;
    private const BOT_THINK  = [1200, 2200]; // ms the bot waits before each of its moves

    public static function intro(): string
    {
        return L("<b>🃏 بازی حافظه</b>\n\nکارت‌های هم‌شکل رو پیدا کن!\nحالت بازی رو انتخاب کن:", "<b>🃏 Memory</b>\n\nFind the matching cards!\nPick a game mode:");
    }

    public static function modeKeyboard(): array
    {
        return GameKit::modeKeyboard(self::P);
    }

    private static function deal(): array
    {
        $pool  = MemoryEmojis::all();
        $count = count($pool);
        $pick  = [];
        while (count($pick) < self::PAIRS) {
            $e = $pool[random_int(0, $count - 1)];
            if (!in_array($e, $pick, true)) {
                $pick[] = $e;
            }
        }
        $cards = array_merge($pick, $pick);
        for ($i = count($cards) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$cards[$i], $cards[$j]] = [$cards[$j], $cards[$i]];
        }
        return $cards;
    }

    private static function start(array $s, string $mode): array
    {
        $s['mode']   = $mode === 'bot' ? 'bot' : 'pvp';
        $s['status'] = 'playing';
        $s['cards']  = self::deal();
        $s['own']    = array_fill(0, self::PAIRS * 2, 0);
        $s['pick']   = -1;
        $s['bad']    = [];
        $s['seen']   = [];
        $s['turn']   = 1;
        $s['score']  = [0, 0];
        $s['p2']     = $s['mode'] === 'bot' ? ['id' => 0, 'name' => L('🤖 نالین', '🤖 Nullin')] : null;
        return $s;
    }

    private static function finished(array $s): bool
    {
        return !in_array(0, $s['own'], true);
    }

    /** One bot move (two cards). Returns true when it found a pair (it then plays again). */
    private static function botStep(array &$s): bool
    {
        unset($s['resign_ask']);
        $n    = count($s['cards']);
        $own  = $s['own'];
        $open = array_values(array_filter(range(0, $n - 1), static fn($i) => (int) $own[$i] === 0));
        $s['bad'] = [];
        if (count($open) < 2) {
            $s['turn'] = 1;
            if (self::finished($s)) {
                $s['status'] = 'over';
            }
            return false;
        }
        $known  = array_values(array_intersect($s['seen'], $open));
        $unseen = array_values(array_diff($open, $known));

        $byCard = [];
        foreach ($known as $i) {
            $byCard[$s['cards'][$i]][] = $i;
        }
        $pair = null;
        foreach ($byCard as $idx) {
            if (count($idx) >= 2 && random_int(1, 100) <= self::BOT_MEMORY) {
                $pair = [$idx[0], $idx[1]];
                break;
            }
        }

        if ($pair !== null) {
            [$a, $b] = $pair;
        } else {
            $a    = $unseen !== [] ? $unseen[array_rand($unseen)] : $open[array_rand($open)];
            $mate = null;
            foreach ($known as $k) {
                if ($k !== $a && $s['cards'][$k] === $s['cards'][$a]) {
                    $mate = $k;
                    break;
                }
            }
            if ($mate !== null && random_int(1, 100) <= self::BOT_MEMORY) {
                $b = $mate;
            } else {
                $rest = array_values(array_diff($unseen, [$a]));
                if ($rest === []) {
                    $rest = array_values(array_diff($open, [$a]));
                }
                $b = $rest[array_rand($rest)];
            }
        }

        $s['seen'] = array_values(array_unique(array_merge($s['seen'], [$a, $b])));
        if ($s['cards'][$a] === $s['cards'][$b]) {
            $s['own'][$a] = 2;
            $s['own'][$b] = 2;
            $s['score'][1]++;
            if (self::finished($s)) {
                $s['status'] = 'over';
                $s['turn']   = 1;
            }
            return true;
        }
        $s['bad']  = [$a, $b];
        $s['turn'] = 1;
        return false;
    }

    /** The bot plays by itself after your miss: waits, flips, shows its cards, repeats while it finds pairs. */
    private static function botTurn(string $imid): void
    {
        for ($guard = 0; $guard < self::PAIRS; $guard++) {
            usleep(random_int(self::BOT_THINK[0], self::BOT_THINK[1]) * 1000);
            $s = GameKit::load($imid);
            if ($s === null || ($s['status'] ?? '') !== 'playing' || ($s['mode'] ?? '') !== 'bot' || (int) ($s['turn'] ?? 1) !== 2) {
                return;
            }
            $matched = self::botStep($s);
            $more    = $matched && $s['status'] === 'playing';
            GameKit::save($imid, $s);
            self::render($imid, $s, $more ? self::thinking() : null);
            if (!$more) {
                return;
            }
        }
    }

    private static function thinking(): string
    {
        return L('🤖 نالین داره فکر می‌کنه...', '🤖 Nullin is thinking...');
    }

    private static function board(array $s, bool $active): array
    {
        $cards = $s['cards'] ?? [];
        $own   = $s['own'] ?? [];
        $bad   = $s['bad'] ?? [];
        $pick  = (int) ($s['pick'] ?? -1);
        $rows  = [];
        for ($r = 0; $r * self::COLS < count($cards); $r++) {
            $row = [];
            for ($c = 0; $c < self::COLS; $c++) {
                $i = $r * self::COLS + $c;
                $o = (int) ($own[$i] ?? 0);
                if ($o > 0) {
                    $row[] = btn($cards[$i], 'noop', $o === 1 ? 'primary' : 'danger');
                } elseif ($i === $pick) {
                    $row[] = btn($cards[$i], 'noop', 'success');
                } elseif (!$active) {
                    $row[] = btn($cards[$i], 'noop', null);
                } elseif (in_array($i, $bad, true)) {
                    $row[] = btn($cards[$i], self::P . ":cell:{$i}", null);
                } else {
                    $row[] = btn(self::HIDE, self::P . ":cell:{$i}", null);
                }
            }
            $rows[] = $row;
        }
        if ($active) {
            $rows[] = GameKit::controlRow($s, self::P);
        } else {
            $rows[] = [btn(L('🔁 بازی دوباره', '🔁 Play again'), self::P . ':replay', 'success')];
        }
        return ikb($rows);
    }

    private static function header(array $s): string
    {
        $sc = $s['score'] ?? [0, 0];
        return '<b>' . L('🃏 بازی حافظه', '🃏 Memory') . '</b> · ' . (($s['mode'] ?? '') === 'bot' ? L('با ربات', 'Vs bot') : L('دو نفره', 'Two players')) . "\n" . divider() . "\n"
            . '🔵 ' . GameKit::name($s['p1']) . ' ' . fa((string) (int) $sc[0]) . '  vs  🔴 ' . GameKit::name($s['p2'] ?? null, L('❔ منتظر حریف', '❔ Waiting for opponent')) . ' ' . fa((string) (int) $sc[1]) . "\n" . divider() . "\n";
    }

    private static function render(string $imid, array $s, ?string $note = null): void
    {
        if ($s['status'] === 'over') {
            $a   = (int) $s['score'][0];
            $b   = (int) $s['score'][1];
            $end = !empty($s['resigned']) ? GameKit::resignLine($s)
                : ($a === $b ? L('🤝 <b>بازی مساوی شد!</b>', '🤝 <b>It’s a draw!</b>') : '🏆 <b>' . GameKit::name($a > $b ? $s['p1'] : $s['p2']) . L(' برد!</b>', ' wins!</b>'));
            Rich::editInline($imid, self::header($s) . $end, self::board($s, false));
            return;
        }
        $turn = (int) $s['turn'] === 1 ? '🔵 ' . GameKit::name($s['p1']) : '🔴 ' . GameKit::name($s['p2'] ?? null, L('هر کی اول بزنه', 'Whoever taps first'));
        Rich::editInline($imid, self::header($s) . ($note ?? L("👉 نوبت: {$turn}", "👉 Turn: {$turn}")) . GameKit::resignNote($s), self::board($s, true));
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
            },
            function () use (&$s, $imid) {
                GameKit::save($imid, $s);
                self::render($imid, $s);
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
        if ($i < 0 || $i >= count($s['cards'])) {
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

        $s['bad'] = [];
        if ((int) $s['own'][$i] > 0 || $i === (int) $s['pick']) {
            $tg->answer($cq, L('🈵 این کارت بازه', '🈵 This card is open'));
            return;
        }

        unset($s['resign_ask']);
        $s['seen'] = array_values(array_unique(array_merge($s['seen'], [$i])));
        if ((int) $s['pick'] < 0) {
            $s['pick'] = $i;
        } else {
            $a         = (int) $s['pick'];
            $s['pick'] = -1;
            if ($s['cards'][$a] === $s['cards'][$i]) {
                $s['own'][$a] = $turn;
                $s['own'][$i] = $turn;
                $s['score'][$turn - 1]++;
            } else {
                $s['bad']  = [$a, $i];
                $s['turn'] = $turn === 1 ? 2 : 1;
            }
        }
        if (self::finished($s)) {
            $s['status'] = 'over';
        }
        $botNext = ($s['mode'] ?? '') === 'bot' && $s['status'] === 'playing' && (int) $s['turn'] === 2;
        GameKit::save($imid, $s);
        self::render($imid, $s, $botNext ? self::thinking() : null);
        $tg->answer($cq);
        if ($botNext) {
            self::botTurn($imid);
        }
    }
}
