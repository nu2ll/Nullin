<?php

final class Chess
{
    private const P      = 'chess';
    private const BLANK  = "\u{2800}";
    private const GLYPH  = [
        'K' => '♔', 'Q' => '♕', 'R' => '♖', 'B' => '♗', 'N' => '♘', 'P' => '♙',
        'k' => '♚', 'q' => '♛', 'r' => '♜', 'b' => '♝', 'n' => '♞', 'p' => '♟️',
    ];
    private static function levels(): array
    {
        return [1 => L('🙂 آسون', '🙂 Easy'), 2 => L('😎 متوسط', '😎 Medium'), 3 => L('🔥 سخت', '🔥 Hard')];
    }

    public static function intro(): string
    {
        return L("<b>♟️ شطرنج</b>\n\nشطرنج کامل با همه‌ی قوانین رسمی!\nبا ربات (۳ سطح سختی) یا با دوستت.\n\nحالت بازی رو انتخاب کن:", "<b>♟️ Chess</b>\n\nFull chess with all the official rules!\nPlay the bot (3 difficulty levels) or a friend.\n\nPick a game mode:");
    }

    public static function modeKeyboard(): array
    {
        return GameKit::modeKeyboard(self::P);
    }

    private static function show(string $imid, array &$s, string $text, ?array $kb): void
    {
        Rich::editInline($imid, $text, $kb);
    }

    private static function glyph(string $p): string
    {
        return $p === '.' ? self::BLANK : self::GLYPH[$p];
    }

    private static function board(array $s, bool $active, bool $frozen = false): array
    {
        $pos     = $s['pos'];
        $sel     = (int) ($s['sel'] ?? -1);
        $targets = [];
        if ($active && $sel >= 0) {
            foreach (ChessEngine::legalFrom($pos, $sel) as $m) {
                $targets[ChessEngine::to($m)] = true;
            }
        }
        $checkSq = ChessEngine::inCheck($pos) ? ChessEngine::kingSquare($pos['b'], $pos['t']) : -1;

        $rows = [];
        for ($r = 0; $r < 8; $r++) {
            $row = [];
            for ($c = 0; $c < 8; $c++) {
                $sq    = $r * 8 + $c;
                $p     = $pos['b'][$sq];
                $text  = self::glyph($p);
                $style = null;
                if ($sq === $sel) {
                    $style = 'primary';
                } elseif (isset($targets[$sq])) {
                    $style = $p !== '.' || ($sq === $pos['ep'] && strtoupper($pos['b'][$sel]) === 'P') ? 'danger' : 'success';
                    if ($p === '.') {
                        $text = '•';
                    }
                } elseif ($sq === $checkSq) {
                    $style = 'danger';
                } elseif ($sel < 0 && in_array($sq, $s['last'] ?? [], true)) {
                    $style = 'primary';
                }
                $row[] = btn($text, ($active && !$frozen) ? self::P . ":s:{$sq}" : 'noop', $style);
            }
            $rows[] = $row;
        }

        if ($frozen) {
        } elseif (!$active) {
            $rows[] = [btn(L('🔁 بازی دوباره', '🔁 Play again'), self::P . ':replay', 'success')];
        } elseif (!empty($s['promo'])) {
            $white  = $pos['t'] === 'w';
            $choice = [];
            foreach ([1 => 'Q', 2 => 'R', 3 => 'B', 4 => 'N'] as $code => $piece) {
                $choice[] = btn(self::glyph($white ? $piece : strtolower($piece)), self::P . ":pr:{$code}", 'success');
            }
            $choice[] = btn('✖️', self::P . ':pr:0', 'danger');
            $rows[]   = $choice;
        } else {
            $ctl = GameKit::controlRow($s, self::P);
            if ($s['mode'] === 'pvp' && !empty($s['p2'])) {
                array_unshift($ctl, btn(L('🤝 تساوی', '🤝 Draw'), self::P . ':draw', null));
            }
            $rows[] = $ctl;
        }
        return ikb($rows);
    }

    private static function captured(string $board): string
    {
        $start = ['Q' => 1, 'R' => 2, 'B' => 2, 'N' => 2, 'P' => 8];
        $val   = ['Q' => 9, 'R' => 5, 'B' => 3, 'N' => 3, 'P' => 1];
        $lost  = ['w' => '', 'b' => ''];
        $score = 0;
        foreach ($start as $u => $n) {
            $w = $n - substr_count($board, $u);
            $b = $n - substr_count($board, strtolower($u));
            $lost['w'] .= str_repeat(self::glyph($u), max(0, $w));
            $lost['b'] .= str_repeat(self::glyph(strtolower($u)), max(0, $b));
            $score += ($b - $w) * $val[$u];
        }
        if ($lost['w'] === '' && $lost['b'] === '') {
            return '';
        }
        $adv = $score === 0 ? '' : ($score > 0 ? ' (⚪ +' . fa($score) . ')' : ' (⚫ +' . fa(-$score) . ')');
        return '🍽 ⚪ ' . ($lost['b'] !== '' ? $lost['b'] : '—') . '  ⚫ ' . ($lost['w'] !== '' ? $lost['w'] : '—') . $adv;
    }

    private static function sqName(int $sq): string
    {
        return chr(97 + $sq % 8) . (8 - intdiv($sq, 8));
    }

    private static function lastMove(array $s): string
    {
        $last = $s['last'] ?? [];
        if (count($last) !== 2) {
            return '';
        }
        [$from, $to] = $last;
        $p     = $s['pos']['b'][$to] ?? '.';
        if ($p === '.') {
            return '';
        }
        $side = ChessEngine::color($p) === 'w' ? '⚪' : '⚫';
        return L("🔄 حرکت آخر {$side}: <code>", "🔄 Last move {$side}: <code>") . self::glyph($p) . ' ' . self::sqName($from) . ' → ' . self::sqName($to) . '</code>';
    }

    private static function text(array $s, string $status): string
    {
        $mode = $s['mode'] === 'bot' ? L('با ربات · ', 'Vs bot · ') . self::levels()[$s['level']] : L('دو نفره', 'Two players');
        $l    = ['<b>' . L('♟️ شطرنج', '♟️ Chess') . "</b> · {$mode}", divider()];
        $l[]  = '⚪ ' . GameKit::name($s['p1']) . '  vs  ⚫ ' . GameKit::name($s['p2'] ?? null, L('❔ منتظر حریف', '❔ Waiting for opponent'));
        $l[]  = divider();
        if ($lm = self::lastMove($s)) {
            $l[] = $lm;
        }
        if ($cap = self::captured($s['pos']['b'])) {
            $l[] = $cap;
        }
        $l[] = '';
        $l[] = $status;
        return implode("\n", $l);
    }

    private static function turnLine(array $s): string
    {
        $pos  = $s['pos'];
        $line = ChessEngine::inCheck($pos) ? L("⚠️ <b>کیش!</b>\n", "⚠️ <b>Check!</b>\n") : '';
        if (!empty($s['draw_offer'])) {
            $who   = GameKit::name($s['draw_offer'] === 1 ? $s['p1'] : $s['p2']);
            $line .= L("🤝 {$who} پیشنهاد مساوی داده؛\nحریف دکمه‌ی «تساوی» رو بزنه تا قبول بشه.\n", "🤝 {$who} offered a draw;\nthe opponent can tap “Draw” to accept.\n");
        }
        $turn = $pos['t'] === 'w' ? '⚪ ' . GameKit::name($s['p1']) : '⚫ ' . GameKit::name($s['p2'] ?? null, L('هر کی اول بازی کنه', 'Whoever moves first'));
        $line .= L("👉 نوبت: {$turn}", "👉 Turn: {$turn}");
        if (!empty($s['promo'])) {
            $line .= L("\n👑 پیاده به ته رسید؛\nتبدیلش کن به:", "\n👑 Your pawn reached the end;\npromote it to:");
        } elseif (count($s['hist'] ?? []) < 2) {
            $line .= L("\n<i>اول مهره رو بزن، بعد یکی از خونه‌های سبز رو.</i>", "\n<i>Tap a piece first, then one of the green squares.</i>");
        }
        return $line . GameKit::resignNote($s);
    }

    private static function render(string $imid, array &$s, ?string $override = null): void
    {
        $active = ($s['status'] ?? '') === 'playing';
        $status = $override ?? ($active ? self::turnLine($s) : self::resultLine($s));
        self::show($imid, $s, self::text($s, $status), self::board($s, $active, $override !== null));
    }

    private static function resultLine(array $s): string
    {
        $r = $s['result'] ?? '';
        $winnerName = static function (string $color) use ($s): string {
            return $color === 'w' ? '⚪ ' . GameKit::name($s['p1']) : '⚫ ' . GameKit::name($s['p2'] ?? null);
        };
        return match ($r) {
            'mate'       => L('🏆 <b>کیش و مات!</b> ', '🏆 <b>Checkmate!</b> ') . $winnerName($s['winner']) . L(' برد 🎉', ' wins 🎉'),
            'resign'     => '🏳 ' . $winnerName(ChessEngine::opp($s['winner'])) . L(' تسلیم شد؛ 🏆 <b>', ' resigned; 🏆 <b>') . $winnerName($s['winner']) . L(' برد!</b>', ' wins!</b>'),
            'stalemate'  => L('🤝 <b>پات!</b> بازی مساوی شد.', '🤝 <b>Stalemate!</b> The game is a draw.'),
            'repetition' => L('🤝 تکرار سه‌باره‌ی موقعیت؛ مساوی.', '🤝 Threefold repetition; draw.'),
            'fifty'      => L('🤝 قانون ۵۰ حرکت؛ مساوی.', '🤝 Fifty-move rule; draw.'),
            'material'   => L('🤝 مهره‌ی کافی برای مات نمونده؛ مساوی.', '🤝 Not enough pieces left to checkmate; draw.'),
            'agreed'     => L('🤝 با توافق، مساوی شد.', '🤝 Draw by agreement.'),
            'cancel'     => L('✖️ بازی لغو شد.', '✖️ Game cancelled.'),
            default      => L('بازی تموم شد.', 'The game is over.'),
        };
    }

    private static function start(array $s, string $mode, int $level = 2): array
    {
        $s['mode']       = $mode;
        $s['level']      = $level;
        $s['status']     = 'playing';
        $s['pos']        = ChessEngine::newState();
        $s['sel']        = -1;
        $s['last']       = [];
        $s['hist']       = [];
        $s['reps']       = [ChessEngine::key($s['pos']) => 1];
        $s['promo']      = null;
        $s['draw_offer'] = 0;
        $s['p2']         = $mode === 'bot' ? ['id' => 0, 'name' => L('🤖 نالین', '🤖 Nullin')] : null;
        return $s;
    }

    private static function play(array $s, int $m): array
    {
        $san        = ChessEngine::san($s['pos'], $m);
        $mover      = $s['pos']['t'];
        $s['pos']   = ChessEngine::make($s['pos'], $m);
        $s['hist'][] = $san;
        $s['last']  = [ChessEngine::from($m), ChessEngine::to($m)];
        $s['sel']   = -1;
        $s['promo'] = null;
        $s['draw_offer'] = 0;
        unset($s['resign_ask']);

        if ($s['pos']['h'] === 0) {
            $s['reps'] = [];
        }
        $key             = ChessEngine::key($s['pos']);
        $s['reps'][$key] = ($s['reps'][$key] ?? 0) + 1;

        $out = ChessEngine::outcome($s['pos'], $s['reps']);
        if ($out !== null) {
            $s['status'] = 'over';
            $s['result'] = $out;
            $s['winner'] = $out === 'mate' ? $mover : '';
        }
        return $s;
    }

    public static function onCallback(array $cq, string $data, string $imid): void
    {
        $tg   = App::tg();
        $from = $cq['from'];
        $uid  = (int) $from['id'];
        $s    = GameKit::ensure($imid, self::P, $from);

        $handled = GameKit::common($s, $cq, $data, self::P,
            function (string $mode) use (&$s, $imid, $tg) {
                if ($mode === 'bot') {
                    $s['status'] = 'mode';
                    GameKit::save($imid, $s);
                    $row = [];
                    foreach (self::levels() as $lvl => $label) {
                        $row[] = btn($label, self::P . ":lvl:{$lvl}", $lvl === 3 ? 'danger' : ($lvl === 1 ? 'success' : 'primary'));
                    }
                    self::show($imid, $s, L("<b>♟️ شطرنج</b> · با ربات\n\nچقدر سخت بگیرم؟ 😏", "<b>♟️ Chess</b> · Vs bot\n\nHow hard should I go? 😏"), ikb([$row, GameKit::cancelRow(self::P)]));
                    return;
                }
                $s = self::start($s, 'pvp');
                GameKit::save($imid, $s);
                self::render($imid, $s);
            },
            function () use (&$s, $imid, $tg) {
                $s = GameKit::fresh(self::P, $s['p1']);
                GameKit::save($imid, $s);
                self::show($imid, $s, self::intro(), self::modeKeyboard());
            },
            function (string $by) use (&$s, $imid, $tg) {
                if (isset($s['pos'])) {
                    $s['result'] = 'cancel';
                    GameKit::save($imid, $s);
                    self::render($imid, $s);
                } else {
                    GameKit::save($imid, $s);
                    self::show($imid, $s, L("<b>♟️ شطرنج</b>\n\n✖️ بازی توسط {$by} لغو شد.", "<b>♟️ Chess</b>\n\n✖️ Game cancelled by {$by}."), GameKit::replayKeyboard(self::P));
                }
            },
            function (bool $done) use (&$s, $imid) {
                if ($done) {
                    $s['result'] = 'resign';
                    $s['winner'] = (int) $s['resigned'] === 1 ? 'b' : 'w';
                }
                GameKit::save($imid, $s);
                self::render($imid, $s);
            }
        );
        if ($handled) {
            return;
        }

        if (str_starts_with($data, self::P . ':lvl:')) {
            if (($s['status'] ?? '') !== 'mode' || !GameKit::isCreator($s, $uid)) {
                $tg->answer($cq, L('🔒 فقط سازنده انتخاب می‌کنه', '🔒 Only the creator can choose'));
                return;
            }
            $lvl = max(1, min(3, (int) substr($data, strlen(self::P . ':lvl:'))));
            $s   = self::start($s, 'bot', $lvl);
            GameKit::save($imid, $s);
            self::render($imid, $s);
            $tg->answer($cq, L('تو سفیدی، شروع کن! ⚪', 'You’re white, you start! ⚪'));
            return;
        }

        if (($s['status'] ?? '') !== 'playing') {
            $tg->answer($cq, L('⌛️ این بازی تموم شده', '⌛️ This game is over'));
            return;
        }

        $pos      = $s['pos'];
        $turnSeat = $pos['t'] === 'w' ? 1 : 2;

        if ($data === self::P . ':draw') {
            if ($s['mode'] !== 'pvp' || empty($s['p2']) || !GameKit::isPlayer($s, $uid)) {
                $tg->alert($cq, L('🚫 تساوی فقط بین دو بازیکن این بازی ممکنه.', '🚫 Only the two players of this game can agree to a draw.'));
                return;
            }
            $seat = GameKit::isCreator($s, $uid) ? 1 : 2;
            if (!empty($s['draw_offer']) && (int) $s['draw_offer'] !== $seat) {
                $s['status'] = 'over';
                $s['result'] = 'agreed';
                GameKit::save($imid, $s);
                self::render($imid, $s);
                $tg->answer($cq, L('🤝 قبول شد', '🤝 Accepted'));
                return;
            }
            $s['draw_offer'] = $seat;
            GameKit::save($imid, $s);
            self::render($imid, $s);
            $tg->answer($cq, L('🤝 پیشنهاد مساوی فرستاده شد', '🤝 Draw offer sent'));
            return;
        }

        if ($s['mode'] === 'bot') {
            if (!GameKit::isCreator($s, $uid)) {
                $tg->alert($cq, L('🚫 این بازی مال یکی دیگه‌ست؛' . "\n" . 'خودت از منو یکی بساز!', '🚫 This game belongs to someone else;' . "\n" . 'start your own from the menu!'));
                return;
            }
            if ($turnSeat !== 1) {
                $tg->answer($cq, L('🤔 صبر کن، دارم فکر می‌کنم...', '🤔 Hold on, I’m thinking...'));
                return;
            }
        } else {
            $seat = GameKit::seat($s, $from, $turnSeat);
            if ($seat === 0) {
                $tg->alert($cq, L('🚫 این بازی دو نفره پر شده.', '🚫 This two-player game is full.'));
                return;
            }
            if ($seat !== $turnSeat) {
                $tg->answer($cq, L('⏳ نوبت حریفته', '⏳ It’s your opponent’s turn'));
                return;
            }
        }

        if (str_starts_with($data, self::P . ':pr:')) {
            $code = (int) substr($data, strlen(self::P . ':pr:'));
            $pp   = $s['promo'] ?? null;
            if (!$pp) {
                $tg->answer($cq);
                return;
            }
            if ($code < 1 || $code > 4) {
                $s['promo'] = null;
                $s['sel']   = -1;
                GameKit::save($imid, $s);
                self::render($imid, $s);
                $tg->answer($cq, L('لغو شد', 'Cancelled'));
                return;
            }
            self::commit($cq, $imid, $s, ChessEngine::move($pp[0], $pp[1], $code));
            return;
        }

        if (!str_starts_with($data, self::P . ':s:')) {
            $tg->answer($cq);
            return;
        }
        if (!empty($s['promo'])) {
            $tg->answer($cq, L('👑 اول مهره‌ی ارتقا رو انتخاب کن', '👑 Choose the promotion piece first'));
            return;
        }

        $sq  = (int) substr($data, strlen(self::P . ':s:'));
        $p   = $pos['b'][$sq] ?? '.';
        $sel = (int) ($s['sel'] ?? -1);

        if ($p !== '.' && ChessEngine::color($p) === $pos['t']) {
            if ($sq === $sel) {
                $s['sel'] = -1;
                GameKit::save($imid, $s);
                self::render($imid, $s);
                $tg->answer($cq);
                return;
            }
            if (!ChessEngine::legalFrom($pos, $sq)) {
                $tg->answer($cq, L('🚫 این مهره الان حرکتی نداره', '🚫 This piece has no moves right now'));
                return;
            }
            $s['sel'] = $sq;
            GameKit::save($imid, $s);
            self::render($imid, $s);
            $tg->answer($cq);
            return;
        }

        if ($sel < 0) {
            $tg->answer($cq, L('👆 اول یکی از مهره‌های خودت رو انتخاب کن', '👆 Pick one of your own pieces first'));
            return;
        }

        $candidates = array_values(array_filter(ChessEngine::legalFrom($pos, $sel), static fn($m) => ChessEngine::to($m) === $sq));
        if (!$candidates) {
            $s['sel'] = -1;
            GameKit::save($imid, $s);
            self::render($imid, $s);
            $tg->answer($cq, L('🚫 حرکت غیرمجاز', '🚫 Illegal move'));
            return;
        }
        if (count($candidates) > 1) {
            $s['promo'] = [$sel, $sq];
            GameKit::save($imid, $s);
            self::render($imid, $s);
            $tg->answer($cq, L('👑 ارتقا!', '👑 Promoted!'));
            return;
        }
        self::commit($cq, $imid, $s, $candidates[0]);
    }

    private static function commit(array $cq, string $imid, array $s, int $move): void
    {
        $tg = App::tg();
        $s  = self::play($s, $move);
        GameKit::save($imid, $s);

        if ($s['status'] !== 'playing' || $s['mode'] !== 'bot') {
            self::render($imid, $s);
            $tg->answer($cq, $s['status'] === 'over' ? L('🏁 بازی تموم شد', '🏁 Game over') : null);
            return;
        }

        $tg->answer($cq, L('🤔 بذار فکر کنم...', '🤔 Let me think...'));
        self::render($imid, $s, L('🤖 نالین داره فکر می‌کنه...', '🤖 Nullin is thinking...') . (ChessEngine::inCheck($s['pos']) ? L(' (کیشم کردی! 😳)', ' (you put me in check! 😳)') : ''));

        $bm = ChessEngine::bestMove($s['pos'], (int) $s['level']);
        if ($bm !== null) {
            $s = self::play($s, $bm);
        }
        GameKit::save($imid, $s);
        self::render($imid, $s);
    }
}
