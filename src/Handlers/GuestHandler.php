<?php

final class GuestHandler
{
    private static int $uid = 0;
    private static int $chat = 0;

    // پیام ضد اسپم: آخرین پیام ربات برای این کاربر رو ادیت می‌کنه؛ اگه نبود به همین پیام مهمان جواب می‌ده
    public static function spamNotice(array $msg, string $html): void
    {
        $uid = (int) ($msg['guest_bot_caller_user']['id'] ?? $msg['from']['id'] ?? 0);
        $qid = (string) ($msg['guest_query_id'] ?? '');
        $last = $uid > 0 ? Store::get('guest_last', (string) $uid) : null;
        $imid = is_array($last) ? (string) ($last['imid'] ?? '') : '';
        if ($imid !== '' && !empty(Rich::editInlineRich($imid, $html)['ok'])) {
            return;
        }
        if ($qid !== '') {
            self::send($qid, $html, null, true);
        }
    }

    public static function handle(array $msg): void
    {
        $qid = (string) ($msg['guest_query_id'] ?? '');
        if ($qid === '') {
            return;
        }
        $from = $msg['guest_bot_caller_user'] ?? $msg['from'] ?? [];
        self::$uid  = (int) ($from['id'] ?? 0);
        self::$chat = (int) ($msg['chat']['id'] ?? 0);
        $text = trim((string) ($msg['text'] ?? $msg['caption'] ?? ''));
        $bot  = App::botUsername();
        $q    = mbTrim(preg_replace('/@' . preg_quote($bot, '/') . '\b/iu', '', $text) ?? $text, " \t\n،,:");
        $q    = mbTrim(preg_replace('/^(?:نالینی|نالین|nullini|nullin)(?=[\s،,:!؟?.]|$)/iu', '', $q) ?? $q, " \t\n،,:");
        Users::hit('guest');

        $missing = Membership::missing((int) ($from['id'] ?? 0));
        if ($missing) {
            self::send($qid, Membership::text(), Membership::keyboard($missing));
            return;
        }

        $reply    = $msg['reply_to_message'] ?? null;
        $rt       = $reply ? trim((string) ($reply['text'] ?? $reply['caption'] ?? '')) : '';
        $hasMedia = Assistant::hasMedia($msg) || ($reply && Assistant::hasMedia($reply));

        if ($q === '' && $rt === '' && !$hasMedia) {
            self::send($qid, Ui::hello(), Ui::groupKeyboard());
            return;
        }

        // دستورهای اسلشی (/wiki ، /translate ، ...) مستقیم اجرا می‌شن و نمی‌رن سراغ هوش مصنوعی
        if (preg_match('/^\/([a-zA-Z_]+)(?:@\w+)?(?:\s+(.*))?$/su', $q, $cm) === 1
            && self::command($msg, $from, $qid, strtolower($cm[1]), trim($cm[2] ?? ''), $rt)) {
            return;
        }

        if (Downloader::isIgStory($q . ' ' . $rt)) {
            return;
        }

        $link = $q !== '' ? Downloader::match($q) : ($rt !== '' ? Downloader::match($rt) : null);
        if (($link !== null && !Switches::on('download')) || ($link === null && !Switches::on('ai'))) {
            return;
        }
        if ($link !== null) {
            $imid = self::send($qid, L('🔎 دارم لینک ', '🔎 Checking the link ') . $link['name'] . L(' رو بررسی می‌کنم...', ' ...'));
            if ($imid !== null) {
                Users::hit('guest:dl');
                Downloader::guest($from, $link, $imid);
            }
            return;
        }

        if (self::toolReply($msg, $reply, $text)) {
            logInfo('reply', 'guest: ignored reply to non-AI bot message ' . self::$chat . ':' . (int) ($reply['message_id'] ?? 0));
            return;
        }

        if (AI::busy((int) ($from['id'] ?? 0))) {
            self::send($qid, (Lang::is('en') ? AI::BUSY_TEXT_EN : AI::BUSY_TEXT), null, true);
            return;
        }
        if (AI::limited((int) ($from['id'] ?? 0))) {
            self::send($qid, AI::limitText(), null, true);
            return;
        }

        $voiceIn = isset($msg['voice']);
        $imid    = $voiceIn ? null : self::send($qid, (Lang::is('en') ? LiveReply::THINKING_EN : LiveReply::THINKING));

        $parts = [];
        $notes = [];
        if ($reply) {
            if ($m = Assistant::mediaPart($reply)) {
                $parts[] = $m;
                $notes[] = ('[The user replied to a ' . Assistant::kind($reply) . ' that is attached' . (Assistant::isFrame($reply) ? '; only its first-frame image is attached' : '') . ($rt !== '' ? "; its text: {$rt}" : '') . ']');
            } elseif ($info = Assistant::fileInfo($reply)) {
                $notes[] = ("[The user replied to a file; {$info}" . ($rt !== '' ? "; its text: {$rt}" : '') . ']');
            } elseif ($data = Assistant::dataInfo($reply)) {
                $notes[] = "[The user replied to a {$data[0]}; {$data[1]}]";
            } elseif ($rt !== '') {
                $notes[] = "[The message the user replied to]: {$rt}";
            } elseif (!empty($reply['from'])) {
                $notes[] = '[The user replied to a message that has no text]';
            }
        }
        if ($reply && $notes) {
            $notes[0] = Assistant::withAlbum($notes[0], $reply);
        }
        $n1 = count($notes);
        if (Assistant::hasMedia($msg)) {
            if ($m = Assistant::mediaPart($msg)) {
                $parts[] = $m;
                $notes[] = isset($msg['voice'])
                    ? '[The user sent a voice message; listen to it and answer]'
                    : (isset($msg['photo'])
                        ? '[The user sent a photo; look at it and answer]'
                        : ('[The user sent a ' . Assistant::kind($msg) . '; ' . (Assistant::isFrame($msg) ? 'only its first-frame image is attached' : 'its image is attached') . ', look at it and answer]'));
            } elseif ($info = Assistant::fileInfo($msg)) {
                $notes[] = "[The user sent a file; {$info}]";
            } elseif ($data = Assistant::dataInfo($msg)) {
                $notes[] = "[The user sent a {$data[0]}; {$data[1]}]";
            }
            if (count($notes) > $n1) {
                $notes[$n1] = Assistant::withAlbum($notes[$n1], $msg);
            }
        }
        $body    = trim(implode("\n", $notes) . ($q !== '' ? "\n\n{$q}" : ''));
        $content = $parts ? array_merge($parts, [['type' => 'text', 'text' => $body ?: 'Hello']]) : ($body ?: 'Hello');

        $ctx  = ['user_name' => userName($from), 'user_id' => (int) ($from['id'] ?? 0), 'chat_type' => 'guest'];
        $live = $imid !== null && App::cfg('ai_stream', true) ? new LiveReply($msg, $imid, '', false) : null;
        $live?->watch();
        $opt  = ['reaction' => false] + ($live ? ['on_text' => $live->feed(...)] : []);
        $res  = AI::reply($content, $ctx, [], $opt);
        $live?->stop();

        if (!empty($res['action'])) {
            [$act, $arg] = $res['action'];
            Users::hit('intent:' . $act);
            if ($act === 'download' && ($dl = Downloader::find($arg . ' ' . $rt)) !== null && $imid !== null) {
                Downloader::guest($from, $dl, $imid);
                return;
            }
            $o = Intent::output(['from' => $from] + $msg, $act, $arg, true, $imid);
            if ($o !== null) {
                self::show($imid, $qid, $o);
                return;
            }
            $res = AI::reply($content, ['no_action' => true] + $ctx, [], $opt);
        }

        if ($res['answer'] === null && empty($res['image']) && $parts) {
            logWarn('guest', 'reply with media failed, retrying text-only');
            $note = '[The user referred to a photo or voice message, but you have no access to its content; if their question depends on it, politely say you can\'t see files in this chat and that it\'s better to send it to you privately]';
            $res  = AI::reply(trim($note . ($rt !== '' ? "\n[Text of the replied message]: {$rt}" : '') . ($q !== '' ? "\n\n{$q}" : '')), $ctx, [], $opt);
        }

        if (!empty($res['image'])) {
            $imid ??= self::send($qid, (Lang::is('en') ? LiveReply::THINKING_EN : LiveReply::THINKING));
            self::image($imid, $qid, (string) $res['image'], (int) ($from['id'] ?? 0));
            return;
        }

        if ($res['answer'] !== null && $voiceIn && self::voice($qid, $res['answer'], (int) ($from['id'] ?? 0))) {
            return;
        }

        $answer = $res['answer'] ?? (Lang::is('en') ? AI::FAIL_TEXT_EN : AI::FAIL_TEXT);
        $html   = AI::toHtml(AI::fit($answer));
        $kb     = null;
        if ($res['answer'] !== null && $imid !== null && !$parts && !AI::$searched && trim($body) !== '' && ($kb = AnswerButtons::searchOnly()) !== null) {
            AnswerButtons::attachInline($imid, ['u' => (int) ($from['id'] ?? 0), 'q' => $body, 'a' => $answer, 'h' => '']);
        }
        if ($res['answer'] === null && $imid !== null && !$parts && trim($body) !== '') {
            $kb = AnswerButtons::retryKeyboard();
            AnswerButtons::attachInline($imid, ['u' => (int) ($from['id'] ?? 0), 'q' => $body, 'a' => $answer, 'h' => '', 'f' => 1]);
        }
        self::edit($imid, $qid, $html, $answer, $kb, true);
    }

    /**
     * مثل MessageHandler: جواب هوش مصنوعی با AI::markMsg برچسب می‌خوره و ریپلای به بقیه‌ی پیام‌های ربات (ابزارها) نادیده گرفته می‌شه.
     * answerGuestQuery فقط inline_message_id می‌ده؛ شماره‌ی پیام توش جاسازی شده (base64url از dc_id, owner_id, id, access_hash).
     */
    private static function messageId(string $imid): ?int
    {
        $b = base64_decode(strtr($imid, '-_', '+/') . str_repeat('=', (4 - strlen($imid) % 4) % 4), true);
        if ($b === false) {
            return null;
        }
        if (strlen($b) === 24) {
            $u = unpack('Vdc/Powner/Vid/Pacc', $b);
        } elseif (strlen($b) === 20) {
            $u = unpack('Vdc/Pid/Pacc', $b);
        } else {
            return null;
        }
        $id = is_array($u) ? (int) $u['id'] : 0;
        return $id > 0 && $id < 2147483648 ? $id : null;
    }

    private static function markAi(?string $imid): void
    {
        if ($imid === null || $imid === '' || self::$chat === 0) {
            return;
        }
        $mid = self::messageId($imid);
        if ($mid === null) {
            logWarn('guest', 'could not read the message id from inline_message_id (length ' . strlen($imid) . ')');
            return;
        }
        AI::markMsg(self::$chat, $mid);
        Store::set('guest_decode', 'ok', 1);
    }

    private static function toolReply(array $msg, ?array $reply, string $text): bool
    {
        if (!$reply || Store::get('guest_decode', 'ok') === null) {
            return false;
        }
        $bot = App::botUsername();
        $r   = $reply['from'] ?? null;
        $own = ($r && !empty($r['is_bot']) && strcasecmp((string) ($r['username'] ?? ''), $bot) === 0)
            || strcasecmp((string) ($reply['via_bot']['username'] ?? ''), $bot) === 0;
        if (!$own) {
            return false;
        }
        if (preg_match('/@' . preg_quote($bot, '/') . '\b/iu', $text) === 1
            || preg_match('/^(?:نالینی|نالین|nullini|nullin)(?=[\s،,:!؟?.]|$)/iu', ltrim($text)) === 1) {
            return false;
        }
        return !AI::isAiMsg((int) ($msg['chat']['id'] ?? 0), (int) ($reply['message_id'] ?? 0));
    }

    private const CMD_SWITCH  = ['wiki' => 'wiki', 'translate' => 'translate', 'font' => 'font', 'gif' => 'gif', 'screenshot' => 'screenshot', 'giveaway' => 'giveaway', 'image' => 'image', 'sharetrace' => 'linkcheck'];
    // دستورهایی که تو گست معنی ندارن؛ بی‌صدا نادیده گرفته می‌شن (نه این‌که برن سراغ AI)
    private const CMD_IGNORED = ['start', 'secretary', 'stats', 'ai', 'quota', 'backup', 'cookies', 'tools', 'ban', 'unban', 'banned', 'broadcast'];

    /** true یعنی دستور رسیدگی شد (یا عمداً نادیده گرفته شد)؛ false یعنی دستور ناشناسه و مثل قبل می‌ره سراغ AI */
    private static function command(array $msg, array $from, string $qid, string $cmd, string $args, string $rt): bool
    {
        $uid = (int) ($from['id'] ?? 0);
        if ($cmd === 'help') {
            Users::hit('cmd:help');
            $imid = self::send($qid, Ui::helpText(), Ui::helpMenu(false, 1, false));
            if ($imid !== null && $uid > 0) {
                Store::set('menuowner', 'i:' . $imid, $uid);
            }
            return true;
        }
        if (in_array($cmd, self::CMD_IGNORED, true)) {
            return true;
        }
        if (self::panelCommand($cmd, $from, $qid)) {
            return true;
        }
        if (!isset(self::CMD_SWITCH[$cmd])) {
            return false;
        }
        if (!Switches::on(self::CMD_SWITCH[$cmd])) {
            return true;
        }
        Users::hit("cmd:{$cmd}");
        Users::hit('guest:cmd');
        $m  = ['from' => $from] + $msg;
        $rt = mb_substr(trim($rt), 0, 300);

        switch ($cmd) {
            case 'wiki':
            case 'font':
                $arg = mb_substr($args !== '' ? $args : $rt, 0, 300);
                if ($arg === '') {
                    self::send($qid, Ui::guide($cmd));
                    return true;
                }
                if ($cmd === 'font') {
                    self::send($qid, Fonts::listText($arg));
                    return true;
                }
                $imid = self::send($qid, L('📖 دارم تو ویکی‌پدیا می‌گردم…', '📖 Searching Wikipedia…'));
                $o    = Intent::output($m, 'wiki', $arg, true, $imid);
                if ($o !== null) {
                    self::show($imid, $qid, $o);
                }
                return true;

            case 'translate':
                $src = $args !== '' ? $args : $rt;
                if ($src === '') {
                    self::send($qid, Ui::guide('translate'));
                    return true;
                }
                $imid = self::send($qid, L('🌐 دارم ترجمه می‌کنم…', '🌐 Translating…'));
                $out  = AI::translateFast($src, 'auto');
                $o    = $out
                    ? ['text' => L("🌐 <b>ترجمه:</b>\n\n", "🌐 <b>Translation:</b>\n\n") . h($out)]
                    : Intent::output($m, 'translate', 'auto | ' . $src, true, $imid);
                if ($o !== null) {
                    self::show($imid, $qid, $o);
                }
                return true;

            case 'gif':
                if ($args === '') {
                    self::send($qid, Ui::guide('gif'));
                    return true;
                }
                $imid = self::send($qid, L('🎞 دارم گیف میارم…', '🎞 Getting the GIF…'));
                $o    = Intent::output($m, 'gif', mb_substr($args, 0, 300), true, $imid);
                if ($o !== null) {
                    self::show($imid, $qid, $o);
                }
                return true;

            case 'screenshot':
                $arg = mb_substr($args !== '' ? $args : $rt, 0, 300);
                if (Screenshot::parse($arg) === null) {
                    self::send($qid, $arg === '' ? Ui::guide('screenshot') : Screenshot::notice('url'));
                    return true;
                }
                $imid = self::send($qid, Screenshot::wait());
                $o    = Intent::output($m, 'screenshot', $arg, true, $imid);
                if ($o !== null) {
                    self::show($imid, $qid, $o);
                }
                return true;

            case 'giveaway':
                $o = Intent::output($m, 'giveaway', '', true);
                if ($o !== null) {
                    self::send($qid, $o['text'], $o['kb'] ?? null);
                }
                return true;

            case 'image':
                if ($args === '') {
                    self::send($qid, Ui::guide('image'));
                    return true;
                }
                $imid = self::send($qid, (Lang::is('en') ? LiveReply::DRAWING_EN : LiveReply::DRAWING));
                $en   = AI::googleTranslate($args, 'English');
                if ($en === null) {
                    $plan = AI::imagePrompt($args);
                    $en   = $plan['prompt'] ?? null;
                }
                if ($en === null) {
                    self::edit($imid, $qid, L('😕 الان نتونستم درخواستت رو بفهمم؛', '😕 I couldn\'t understand your request just now;') . "\n" . L('یه‌کم دیگه دوباره بگو.', 'say it again in a bit.'));
                    return true;
                }
                self::image($imid, $qid, $en, $uid);
                return true;

            case 'sharetrace':
                $imid = self::send($qid, L('🛡 دارم لینک رو بررسی می‌کنم…', '🛡 Checking the link…'));
                $res  = LinkCheck::respondFull($args, LinkCheck::replyText($msg), $uid);
                $o    = ['text' => $res['text'], 'rich' => !empty($res['rich'])];
                if (empty($res['rich']) && !empty($res['avatar'])) {
                    $bytes = LinkCheck::avatarBytes((string) $res['avatar']);
                    $fid   = $bytes !== null ? ImageGen::fileId($bytes) : null;
                    if ($fid !== null) {
                        $o['photo'] = $fid;
                    }
                }
                self::show($imid, $qid, $o);
                return true;
        }
        return false;
    }

    /** ping / voice / lang / forget / games: پنل‌هایی که دکمه دارن و روی پیام اینلاینِ مهمان کار می‌کنن */
    private static function panelCommand(string $cmd, array $from, string $qid): bool
    {
        $uid = (int) ($from['id'] ?? 0);
        switch ($cmd) {
            case 'ping':
                Users::hit("cmd:{$cmd}");
                $t0 = microtime(true);
                App::tg()->call('getMe', [], 10);
                self::send($qid, Ui::ping((int) round((microtime(true) - $t0) * 1000)), ikb([[btn('Nullin', 'noop', null)]]));
                return true;

            case 'voice':
                Users::hit("cmd:{$cmd}");
                [$vt, $vk] = VoicePick::panel($uid);
                self::send($qid, $vt, $vk);
                return true;

            case 'lang':
                Users::hit("cmd:{$cmd}");
                self::send($qid, L('🌐 زبان ربات رو انتخاب کن:', '🌐 Choose the bot language:'), Lang::toggleKeyboard());
                return true;

            case 'forget':
                Users::hit("cmd:{$cmd}");
                $count = count(UserMemory::all($uid));
                if ($count === 0) {
                    self::send($qid, L('هنوز چیزی ازت تو حافظه‌م نیست 🤷', 'I don\'t have anything stored about you yet 🤷'));
                    return true;
                }
                self::send($qid, L("🧹 <b>پاک کردن حافظه</b>\n\n", "🧹 <b>Clear memory</b>\n\n")
                    . L("الان <b>", "I currently remember <b>") . fa((string) $count) . L(" مورد</b> درباره‌ات یادمه؛\n", " items</b> about you;\n")
                    . L("اگه پاکشون کنم، از اول باهم آشنا می‌شیم.\n\n", "If I clear them, we'll get to know each other from scratch.\n\n")
                    . L("⚠️ <i>این کار برگشت نداره.</i> مطمئنی؟", "⚠️ <i>This can't be undone.</i> Are you sure?"), ikb([[
                    btn(L('🗑 آره، پاک کن', '🗑 Yes, clear it'), 'mem:' . $uid . ':y', 'danger'),
                    btn(L('↩️ نه، بمونه', '↩️ No, keep it'), 'mem:' . $uid . ':n', 'success'),
                ]]));
                return true;

            case 'games':
            case 'game':
                Users::hit("cmd:{$cmd}");
                if (!GamesMenu::anyOn()) {
                    self::send($qid, L('🎮 بازی‌ها فعلاً خاموشن.', '🎮 Games are switched off right now.'), null, true);
                    return true;
                }
                [$gt, $gk] = GamesMenu::guestMenu($from);
                $imid = self::send($qid, $gt, $gk);
                if ($imid !== null && $uid > 0) {
                    Store::set('menuowner', 'i:' . $imid, $uid);
                }
                return true;
        }
        return false;
    }

    private static function voice(string $qid, string $answer, int $uid = 0): bool
    {
        $html = AI::toHtml($answer);
        if (AI::visible($html) > 1024) {
            return false;
        }
        $chat = (string) App::cfg('storage_chat', '');
        $chat = $chat !== '' ? $chat : (string) (App::cfg('admins', [])[0] ?? '');
        if ($chat === '') {
            return false;
        }
        $file = AI::speak($answer, null, 'ogg', VoicePick::get($uid));
        if ($file === null) {
            return false;
        }
        $tg = App::tg();
        $id = $tg->voiceFileId($chat, $file);
        @unlink($file);
        if ($id === null) {
            return false;
        }
        $r = $tg->call('answerGuestQuery', ['guest_query_id' => $qid, 'result' => [
            'type'          => 'voice',
            'id'            => substr(md5($qid . microtime()), 0, 16),
            'voice_file_id' => $id,
            'title'         => L('نالین', 'Nullin'),
            'caption'       => $html,
            'parse_mode'    => 'HTML',
        ]], 15);
        if (!empty($r['ok'])) {
            self::markAi((string) ($r['result']['inline_message_id'] ?? ''));
        }
        return !empty($r['ok']);
    }

    private static function failImage(?string $imid, ?string $qid, string $text, string $prompt, int $uid): void
    {
        if ($imid !== null && $prompt !== '') {
            AnswerButtons::attachInline($imid, ['u' => $uid, 'q' => $prompt, 'a' => $text, 'h' => '', 'f' => 1, 'k' => 'img']);
            self::edit($imid, $qid, $text, null, AnswerButtons::retryKeyboard());
            return;
        }
        self::edit($imid, $qid, $text);
    }

    public static function image(?string $imid, ?string $qid, string $prompt, int $uid = 0): void
    {
        self::markAi($imid);
        self::edit($imid, null, (Lang::is('en') ? LiveReply::DRAWING_EN : LiveReply::DRAWING));
        $bytes = ImageGen::generate($prompt, static function () {});
        if ($bytes === null) {
            self::failImage($imid, $qid, ImageGen::failText(), $prompt, $uid);
            return;
        }
        if ($imid === null) {
            return;
        }
        $media = ImageGen::fileId($bytes);
        if ($media === null) {
            self::failImage($imid, $qid, L("😕 سرویس ساخت عکس الان جواب نمی‌ده؛\nچند دقیقه دیگه امتحان کن.", "😕 The image service isn't responding right now;\ntry again in a few minutes."), $prompt, $uid);
            return;
        }
        $tg = App::tg();
        $r  = $tg->call('editMessageMedia', [
            'inline_message_id' => $imid,
            'media'             => ['type' => 'photo', 'media' => $media, 'caption' => L('🎨 بفرما، اینم عکست!', '🎨 Here\'s your picture!'), 'parse_mode' => 'HTML'],
        ], 60);
        if (empty($r['ok'])) {
            self::failImage($imid, $qid, L('😕 عکس ساخته شد ولی ارسالش به مشکل خورد؛', '😕 The image was created but sending it failed;') . "\n" . L('دوباره امتحان کن.', 'try again.'), $prompt, $uid);
        }
    }

    public static function show(?string $imid, string $qid, array $o): void
    {
        $tg = App::tg();
        if ($imid !== null && !empty($o['retry'])) {
            AnswerButtons::attachInline($imid, $o['retry'] + ['q' => (string) ($o['retry']['q'] ?? '')]);
        }
        if (!empty($o['video'])) {
            $imid ??= self::send($qid, L('🎞 دارم گیف میارم…', '🎞 Getting the GIF…'), ikb([[btn('Nullin', 'noop', null)]]));
            if ($imid === null) {
                return;
            }
            $r = Rich::editInlineVideo($imid, $o['text'], (string) $o['video'], $o['kb'] ?? null);
            if (!empty($r['ok']) && !empty($o['gif'])) {
                Gif::bind((string) $o['gif'], ['im' => (string) $imid, 'b' => App::botIndex()]);
            }
            if (empty($r['ok'])) {
                logWarn('gif', 'guest rich video edit failed: ' . (string) ($r['description'] ?? ''));
                Rich::editInlineRich($imid, Gif::notice('send'));
            }
            return;
        }
        if ($imid === null) {
            self::send($qid, $o['text'], $o['kb'] ?? null, !empty($o['rich']));
            return;
        }
        if (!empty($o['rich'])) {
            Rich::editInlineRich($imid, $o['text']);
            return;
        }
        if (!empty($o['md'])) {
            Rich::editInlineMd($imid, $o['md'], $o['kb'] ?? null);
            return;
        }
        if (!empty($o['photo']) && is_string($o['photo']) && str_starts_with($o['photo'], 'https://')) {
            $o['photo'] = self::photoId($o['photo']) ?? $o['photo'];
        }
        if (!empty($o['photo']) && is_string($o['photo']) && preg_match('#^(?:https://|[A-Za-z0-9_-]{20,}$)#', $o['photo']) === 1 && (Rich::usable($o['kb'] ?? null) || !Rich::hasButtons($o['kb'] ?? null))) {
            $r = Rich::editInlinePhoto($imid, $o['text'], $o['photo'], $o['kb'] ?? null);
            if (!empty($r['ok'])) {
                return;
            }
        }
        if (!empty($o['photo']) || !empty($o['anim'])) {
            $r = $tg->call('editMessageMedia', [
                'inline_message_id' => $imid,
                'media'             => ['type' => !empty($o['photo']) ? 'photo' : 'animation', 'media' => $o['photo'] ?? $o['anim'], 'caption' => $o['text'], 'parse_mode' => 'HTML'],
                'reply_markup'      => $o['kb'] ?? null,
            ], 60);
            if (!empty($r['ok'])) {
                return;
            }
        }
        $img = $o['img'] ?? null;
        if (is_string($img) && str_starts_with($img, 'https://')) {
            $img = self::photoId($img);
        }
        $r = Rich::editInline($imid, $o['text'], $o['kb'] ?? null, $img, true, $o['preview'] ?? null);
        if (empty($r['ok']) && $img !== null) {
            Rich::editInline($imid, $o['text'], $o['kb'] ?? null, null, true, $o['preview'] ?? null);
        }
    }

    private static function photoId(string $url): ?string
    {
        return Wiki::photoId($url);
    }

    private static function send(string $qid, string $html, ?array $markup = null, bool $rich = false): ?string
    {
        $result = [
            'type'                  => 'article',
            'id'                    => substr(md5($qid . microtime()), 0, 16),
            'title'                 => L('نالین', 'Nullin'),
            'input_message_content' => ['message_text' => $html, 'parse_mode' => 'HTML', 'link_preview_options' => ['is_disabled' => true]],
        ];
        if ($rich || Rich::usable($markup)) {
            $result['input_message_content'] = Rich::inlineContent($html, $markup);
        } elseif ($markup !== null) {
            $result['reply_markup'] = $markup;
        }
        $r = App::tg()->call('answerGuestQuery', ['guest_query_id' => $qid, 'result' => $result], 15);
        $imid = !empty($r['ok']) ? ($r['result']['inline_message_id'] ?? null) : null;
        if ($imid !== null && self::$uid > 0) {
            Store::set('guest_last', (string) self::$uid, ['imid' => $imid, 'at' => time()]);
        }
        return $imid;
    }

    private static function edit(?string $imid, ?string $qid, string $html, ?string $plain = null, ?array $kb = null, bool $ai = false): void
    {
        $tg = App::tg();
        if ($imid === null) {
            if ($qid !== null) {
                $n = self::send($qid, $html);
                if ($ai) {
                    self::markAi($n);
                }
            }
            return;
        }
        if ($ai) {
            self::markAi($imid);
        }
        if (empty(Rich::editInline($imid, $html, $kb)['ok']) && $plain !== null) {
            Rich::editInline($imid, mb_substr($plain, 0, 4096), $kb, null, false);
        }
    }
}
