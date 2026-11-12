<?php

final class Rps
{
    private const P      = 'rps';
    private const BEATS  = ['rock' => 'scissors', 'paper' => 'rock', 'scissors' => 'paper'];

    private static function labels(): array
    {
        return ['rock' => L('🪨 سنگ', '🪨 Rock'), 'paper' => L('📄 کاغذ', '📄 Paper'), 'scissors' => L('✂️ قیچی', '✂️ Scissors')];
    }

    public static function intro(): string
    {
        return L("<b>✊ سنگ کاغذ قیچی</b>\n\nبهترین از ۳ دور! حالت بازی رو انتخاب کن:", "<b>✊ Rock Paper Scissors</b>\n\nBest of 3 rounds! Pick a game mode:");
    }

    public static function modeKeyboard(): array
    {
        return GameKit::modeKeyboard(self::P);
    }

    private static function choiceKeyboard(bool $pvp): array
    {
        $row = [];
        foreach (self::labels() as $k => $label) {
            $row[] = btn($label, self::P . ":c:{$k}");
        }
        return ikb([$row, $pvp ? GameKit::cancelRow(self::P) : []]);
    }

    private static function botPick(string $human): string
    {
        $roll = random_int(1, 100);
        if ($roll <= 50) {
            return self::BEATS[$human];
        }
        if ($roll <= 75) {
            return $human;
        }
        return array_search($human, self::BEATS, true);
    }

    private static function winner(string $a, string $b): int
    {
        return $a === $b ? 0 : (self::BEATS[$a] === $b ? 1 : 2);
    }

    private static function header(array $s): string
    {
        $mode = ($s['mode'] ?? '') === 'bot' ? L('با ربات', 'Vs bot') : L('دو نفره', 'Two players');
        return '<b>' . L('✊ سنگ کاغذ قیچی', '✊ Rock Paper Scissors') . "</b> · {$mode}\n" . divider() . "\n";
    }

    private static function scoreLine(array $s): string
    {
        return '🏅 ' . GameKit::name($s['p1']) . ' <b>' . fa($s['score'][0]) . '</b> — <b>' . fa($s['score'][1]) . '</b> ' . GameKit::name($s['p2'] ?? null);
    }

    public static function onCallback(array $cq, string $data, string $imid): void
    {
        $tg   = App::tg();
        $from = $cq['from'];
        $s    = GameKit::ensure($imid, self::P, $from);

        $handled = GameKit::common($s, $cq, $data, self::P,
            function (string $mode) use (&$s, $imid, $tg) {
                $s['mode']   = $mode === 'bot' ? 'bot' : 'pvp';
                $s['status'] = 'playing';
                $s['round']  = 1;
                $s['score']  = [0, 0];
                $s['picks']  = [null, null];
                if ($s['mode'] === 'bot') {
                    $s['p2'] = ['id' => 0, 'name' => L('🤖 نالین', '🤖 Nullin')];
                }
                GameKit::save($imid, $s);
                Rich::editInline($imid, self::header($s) . L("دور ۱ از ۳\n👇 انتخابت رو بزن:", "Round 1 of 3\n👇 Make your pick:"), self::choiceKeyboard($s['mode'] === 'pvp'));
            },
            function () use (&$s, $imid, $tg) {
                $s = GameKit::fresh(self::P, $s['p1']);
                GameKit::save($imid, $s);
                Rich::editInline($imid, self::intro(), self::modeKeyboard());
            },
            function (string $by) use (&$s, $imid, $tg) {
                GameKit::save($imid, $s);
                Rich::editInline($imid, self::header($s) . L("✖️ بازی توسط {$by} لغو شد.", "✖️ Game cancelled by {$by}."), GameKit::replayKeyboard(self::P));
            }
        );
        if ($handled) {
            return;
        }

        if (!str_starts_with($data, self::P . ':c:') || ($s['status'] ?? '') !== 'playing') {
            $tg->answer($cq, L('⌛️ این بازی تموم شده', '⌛️ This game is over'));
            return;
        }
        $pick = substr($data, strlen(self::P . ':c:'));
        if (!isset(self::labels()[$pick])) {
            $tg->answer($cq);
            return;
        }

        if ($s['mode'] === 'bot') {
            if (!GameKit::isCreator($s, (int) $from['id'])) {
                $tg->alert($cq, L('🚫 این بازی مال یکی دیگه‌ست؛' . "\n" . 'خودت از منو یکی بساز!', '🚫 This game belongs to someone else;' . "\n" . 'start your own from the menu!'));
                return;
            }
            $s['picks'] = [$pick, self::botPick($pick)];
        } else {
            $seat = GameKit::seat($s, $from, empty($s['p2']) ? 2 : 1);
            if ($seat === 0) {
                $tg->alert($cq, L('🚫 این بازی دو نفره پر شده.', '🚫 This two-player game is full.'));
                return;
            }
            if ($s['picks'][$seat - 1] !== null) {
                $tg->answer($cq, L('✅ انتخابت ثبت شده؛ منتظر حریف باش', '✅ Your pick is locked in; wait for your opponent'));
                return;
            }
            $s['picks'][$seat - 1] = $pick;

            if ($s['picks'][0] === null || $s['picks'][1] === null) {
                GameKit::save($imid, $s);
                $who = $s['picks'][0] !== null ? GameKit::name($s['p1']) : GameKit::name($s['p2']);
                Rich::editInline($imid, self::header($s) . L('دور ' . fa($s['round']) . " از ۳\n✅ {$who} انتخاب کرد؛\nمنتظر نفر بعدی... 🤫\n\n", 'Round ' . $s['round'] . " of 3\n✅ {$who} has picked;\nwaiting for the other player... 🤫\n\n") . self::scoreLine($s), self::choiceKeyboard(true));
                $tg->answer($cq, L('✅ ثبت شد (مخفیه)', '✅ Locked in (it’s hidden)'));
                return;
            }
        }

        [$a, $b] = $s['picks'];
        $w       = self::winner($a, $b);
        if ($w > 0) {
            $s['score'][$w - 1]++;
        }
        $roundText = $w === 0 ? L('🤝 این دور مساوی شد', '🤝 This round is a draw') : L('🎉 این دور رو ', '🎉 Round won by ') . GameKit::name($w === 1 ? $s['p1'] : $s['p2']) . L(' برد', '');
        $body      = self::header($s)
            . L('دور ' . fa($s['round']) . " از ۳\n", 'Round ' . $s['round'] . " of 3\n")
            . GameKit::name($s['p1']) . ': ' . self::labels()[$a] . "\n"
            . GameKit::name($s['p2']) . ': ' . self::labels()[$b] . "\n\n"
            . "<b>{$roundText}</b>\n" . self::scoreLine($s);

        $over = max($s['score']) >= 2 || $s['round'] >= 3;
        if ($over) {
            $s['status'] = 'over';
            [$x, $y]     = $s['score'];
            $final       = $x === $y ? L('🤝 <b>کل مسابقه مساوی شد!</b>', '🤝 <b>The whole match is a draw!</b>') : L('🏆 <b>قهرمان: ', '🏆 <b>Champion: ') . GameKit::name($x > $y ? $s['p1'] : $s['p2']) . '</b>';
            GameKit::save($imid, $s);
            Rich::editInline($imid, $body . "\n" . divider() . "\n" . $final, GameKit::replayKeyboard(self::P));
        } else {
            $s['round']++;
            $s['picks'] = [null, null];
            GameKit::save($imid, $s);
            Rich::editInline($imid, $body . "\n\n" . L('👇 دور ' . fa($s['round']) . ':', '👇 Round ' . $s['round'] . ':'), self::choiceKeyboard($s['mode'] === 'pvp'));
        }
        $tg->answer($cq);
    }
}
