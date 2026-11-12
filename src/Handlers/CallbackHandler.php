<?php

final class CallbackHandler
{
    private const GAMES = [
        'rps'   => [Rps::class, 'onCallback'],
        'ttt'   => [TicTacToe::class, 'onCallback'],
        'c4'    => [ConnectFour::class, 'onCallback'],
        'math'  => [MathDuel::class, 'onCallback'],
        'chess' => [Chess::class, 'onCallback'],
    ];

    public static function handle(array $cq): void
    {
        $tg   = App::tg();
        $data = (string) ($cq['data'] ?? '');
        $imid = $cq['inline_message_id'] ?? null;
        $uid  = (int) ($cq['from']['id'] ?? 0);

        if ($data === 'noop' || $data === 'love:noop' || $data === 'info:label') {
            $tg->answer($cq);
            return;
        }

        if ($data === 'lang:menu' || $data === 'lang:fa' || $data === 'lang:en') {
            $lc = $cq['message']['chat']['id'] ?? null;
            $lm = (int) ($cq['message']['message_id'] ?? 0);
            if ($data !== 'lang:menu') {
                Lang::save($uid, substr($data, 5));
                try {
                    Ui::pushUserCommands($tg, $uid);
                    if (in_array($cq['message']['chat']['type'] ?? '', ['group', 'supergroup'], true)) {
                        Ui::syncMemberCommands($tg, (int) ($cq['message']['chat']['id'] ?? 0), $uid, true);
                    }
                } catch (\Throwable $e) {
                    logError('lang', 'pushUserCommands: ' . $e->getMessage());
                }
            }
            if ($lc !== null && $lm > 0) {
                if ($data === 'lang:menu') {
                    Ui::show($lc, $lm, L('🌐 زبان ربات رو انتخاب کن:', '🌐 Choose the bot language:'), Lang::toggleKeyboard());
                } else {
                    Ui::show($lc, $lm, Ui::startText($cq['from']), Ui::startKeyboard());
                }
            }
            $tg->answer($cq, $data === 'lang:menu' ? null : L('✅ زبان فارسی شد', '✅ Language set to English'));
            return;
        }

        if (str_starts_with($data, 'ail:')) {
            $p    = explode(':', $data);
            $lc   = $cq['message']['chat']['id'] ?? null;
            $lm   = (int) ($cq['message']['message_id'] ?? 0);
            $page = (int) ($p[3] ?? $p[2] ?? 0);
            $note = null;
            if (($p[1] ?? '') === 's') {
                Lang::aiSave($uid, $p[2] ?? '');
                $note = L('✅ زبان هوش مصنوعی تنظیم شد', '✅ AI language saved');
            } elseif (($p[1] ?? '') === 'r') {
                Lang::aiSave($uid, null);
                $note = L('✅ روی خودکار برگشت', '✅ Back to auto');
            }
            if ($lc !== null && $lm > 0) {
                Ui::show($lc, $lm, Lang::aiPanelText($uid), Lang::aiKeyboard($uid, $page));
            }
            $tg->answer($cq, $note);
            return;
        }

        // Forced-join gate for every button press (private, group, inline messages)
        if ($data !== 'joincheck' && $uid > 0) {
            $missing = Membership::missing($uid);
            if ($missing) {
                $tg->alert($cq, L('⏳ هنوز عضو نشدی! اول عضو شو بعد دکمه رو بزن.', '⏳ You haven\'t joined yet! Join first, then tap the button.'));
                if (($cq['message']['chat']['type'] ?? '') === 'private') {
                    Membership::prompt($cq['message']['chat']['id'], $uid, $missing);
                }
                return;
            }
        }

        if (str_starts_with($data, 'dl:')) {
            Downloader::onCallback($cq, $data);
            return;
        }
        if (str_starts_with($data, 'ck:')) {
            Cookies::onCallback($cq, $data);
            return;
        }
        if (str_starts_with($data, 'bc:')) {
            Broadcast::onCallback($cq, $data);
            return;
        }
        if (str_starts_with($data, 'gw:')) {
            Giveaway::onCallback($cq, $data);
            return;
        }
        if (str_starts_with($data, 'bz:')) {
            Business::onCallback($cq, $data);
            return;
        }
        if (str_starts_with($data, 'ai:')) {
            AiPanel::onCallback($cq, $data);
            return;
        }
        if (str_starts_with($data, 'ab:')) {
            AnswerButtons::onCallback($cq);
            return;
        }
        if (str_starts_with($data, 'tl:')) {
            Switches::onCallback($cq, $data);
            return;
        }
        if (str_starts_with($data, 'vc:')) {
            VoicePick::onCallback($cq, $data);
            return;
        }
        if (str_starts_with($data, 'mem:')) {
            UserMemory::onCallback($cq, $data);
            return;
        }

        if (str_starts_with($data, 'rates:')) {
            Currency::onCallback($cq, $data, $imid ?: null);
            return;
        }
        if (str_starts_with($data, 'weather:')) {
            Weather::onCallback($cq, $imid ?: null, $data);
            return;
        }

        if ($imid && $data === 'joincheck') {
            if (Membership::missing($uid)) {
                $tg->alert($cq, L('⏳ هنوز عضو نشدی! اول عضو شو بعد دکمه رو بزن.', '⏳ You haven\'t joined yet! Join first, then tap the button.'));
                return;
            }
            Rich::editInline($imid, L("✅ <b>عضو شدی!</b>\n\nحالا دوباره نالین رو صدا بزن 😉", "✅ <b>You're in!</b>\n\nNow call Nullin again 😉"), null);
            $tg->answer($cq, L('✅ خوش اومدی!', '✅ Welcome!'));
            return;
        }

        if (!$imid) {
            self::privateButtons($cq, $data);
            return;
        }

        $prefix = strstr($data, ':', true) ?: $data;
        if (isset(self::GAMES[$prefix])) {
            if (!Switches::on($prefix)) {
                $tg->answer($cq);
                return;
            }
            call_user_func(self::GAMES[$prefix], $cq, $data, $imid);
            return;
        }

        if (preg_match('/^(ai|tr)q:(\w+)$/', $data, $m)) {
            $tg->answer($cq, L('⏳ دارم می‌نویسم...', '⏳ Writing...'));
            InlineHandler::resolveDeferred($m[2], $imid);
            return;
        }
        if (str_starts_with($data, 'lk:')) {
            if (!Switches::on('like')) {
                $tg->answer($cq);
                return;
            }
            Likes::onCallback($cq, $data, $imid);
            return;
        }
        if (str_starts_with($data, 'wh:')) {
            Whisper::onCallback($cq, $data, $imid);
            return;
        }
        $owner = Store::get('owner', $imid);
        if ($owner === null) {
            Store::set('owner', $imid, $uid);
            $owner = $uid;
        }
        if ((int) $owner !== $uid) {
            $tg->alert($cq, L('🔒 این دکمه مال کسیه که این پیام رو فرستاده؛', '🔒 This button belongs to whoever sent this message;') . "\n" . L('خودت از منوی نالین یکی بساز!', 'make your own from the Nullin menu!'));
            return;
        }

        if (str_starts_with($data, 'calc:')) {
            $expr = (string) Store::get('calc', $imid, '0');
            $key  = substr($data, 5);
            $new  = Calculator::press($expr, $key);
            if ($new === $expr && $key !== '=') {
                $tg->answer($cq);
                return;
            }
            Store::set('calc', $imid, $new);
            Rich::editInline($imid, Calculator::render($new, $key === '=' && $new !== Calculator::ERROR), Calculator::keyboard());
            $tg->answer($cq);
            return;
        }

        if (str_starts_with($data, 'unit:')) {
            UnitConvert::onCallback($cq, substr($data, 5), $imid);
            return;
        }

        if (str_starts_with($data, 'pass:')) {
            Rich::editInline($imid, Tools::passwordText((int) substr($data, 5)), Tools::passwordKeyboard());
            $tg->answer($cq, L('🔑 پسورد جدید ساخته شد', '🔑 New password generated'));
            return;
        }

        switch ($data) {
            case 'coin:flip':
                Rich::editInline($imid, Tools::coinResult(), ikb([[btn(L('🔁 دوباره بنداز', '🔁 Roll again'), 'coin:flip')]]));
                $tg->answer($cq);
                return;
            case 'fal:refresh':
                Tools::showHafez($imid);
                $tg->answer($cq, L('🔮 یه فال تازه', '🔮 A new fortune'));
                return;
        }
        $tg->answer($cq);
    }

    private static function privateButtons(array $cq, string $data): void
    {
        $tg     = App::tg();
        $chatId = $cq['message']['chat']['id'] ?? null;
        $msgId  = $cq['message']['message_id'] ?? null;
        if ($chatId === null || $msgId === null) {
            $tg->answer($cq);
            return;
        }

        if ($data === 'joincheck') {
            Store::delete('join', (string) $cq['from']['id']);
            $missing = Membership::missing((int) $cq['from']['id']);
            if ($missing) {
                $tg->alert($cq, L('⏳ هنوز عضو نشدی! اول عضو شو بعد دکمه رو بزن.', '⏳ You haven\'t joined yet! Join first, then tap the button.'));
                return;
            }
            Membership::clearPrompt($chatId, (int) $cq['from']['id']);
            $tg->call('deleteMessage', ['chat_id' => $chatId, 'message_id' => $msgId], 8);
            if (($cq['message']['chat']['type'] ?? '') === 'private') {
                Ui::sendMain($chatId, $cq['from']);
            }
            $tg->answer($cq, L('✅ خوش اومدی!', '✅ Welcome!'));
            return;
        }

        if ($data === 'home') {
            Ui::show($chatId, $msgId, Ui::startText($cq['from']), Ui::startKeyboard());
        } elseif ($data === 'about') {
            Ui::show($chatId, $msgId, Ui::aboutText(), Ui::aboutKeyboard(true));
        } elseif ($data === 'help:menu' || $data === 'help:p1' || $data === 'help:p2') {
            $page = $data === 'help:p2' ? 2 : 1;
            Ui::show($chatId, $msgId, Ui::helpText($page), Ui::helpMenu(($cq['message']['chat']['type'] ?? '') === 'private', $page));
        } elseif (str_starts_with($data, 'help:') && isset(Ui::GUIDES[substr($data, 5)])) {
            Ui::show($chatId, $msgId, Ui::guide(substr($data, 5)), Ui::guideKeyboard(substr($data, 5), ($cq['message']['chat']['type'] ?? '') === 'private'));
        }
        $tg->answer($cq);
    }
}
