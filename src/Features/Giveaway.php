<?php

final class Giveaway
{
    private const DURATIONS = [
        1800   => ['۳۰ دقیقه', '30 min'],
        3600   => ['۱ ساعت', '1 hour'],
        21600  => ['۶ ساعت', '6 hours'],
        86400  => ['۱ روز', '1 day'],
        259200 => ['۳ روز', '3 days'],
        604800 => ['۷ روز', '7 days'],
    ];
    private const MEDALS    = ['🥇', '🥈', '🥉'];
    private const MAX_WIN   = 50;

    private static function botId(): int
    {
        return (int) strstr(App::tg()->token(), ':', true);
    }

    private static function rights(array $on): array
    {
        $all = ['is_anonymous', 'can_manage_chat', 'can_delete_messages', 'can_manage_video_chats', 'can_restrict_members', 'can_promote_members',
            'can_change_info', 'can_invite_users', 'can_post_stories', 'can_edit_stories', 'can_delete_stories', 'can_post_messages',
            'can_edit_messages', 'can_pin_messages', 'can_manage_topics'];
        $r = [];
        foreach ($all as $k) {
            $r[$k] = in_array($k, $on, true);
        }
        return $r;
    }

    private static function targetKeyboard(): array
    {
        $post = self::rights(['can_post_messages', 'can_edit_messages']);
        return [
            'keyboard' => [
                [['text' => L('📢 انتخاب کانال', '📢 Choose channel'), 'request_chat' => ['request_id' => 1, 'chat_is_channel' => true, 'user_administrator_rights' => $post, 'bot_administrator_rights' => $post, 'request_title' => true, 'request_username' => true]]],
                [['text' => L('👥 انتخاب گروه', '👥 Choose group'), 'request_chat' => ['request_id' => 2, 'chat_is_channel' => false, 'user_administrator_rights' => self::rights([]), 'bot_is_member' => true, 'request_title' => true, 'request_username' => true]]],
            ],
            'resize_keyboard'   => true,
            'one_time_keyboard' => true,
        ];
    }

    public static function start(array $msg, string $arg = ''): void
    {
        $tg   = App::tg();
        $uid  = (int) $msg['from']['id'];
        $chat = $msg['chat']['id'];
        if (preg_match('/^gw_(-?\d+)$/', $arg, $m)) {
            $t = self::checkTarget($uid, (int) $m[1]);
            if (isset($t['error'])) {
                $tg->send($chat, $t['error']);
                return;
            }
            self::askPrize($chat, $uid, $t);
            return;
        }
        $old = Store::getToday('gw_wiz', (string) $uid);
        if (is_array($old)) {
            foreach (['c', 'm', 'k'] as $f) {
                if (!empty($old[$f])) {
                    $tg->call('deleteMessage', ['chat_id' => $chat, 'message_id' => (int) $old[$f]], 8);
                }
            }
        }
        $a = Rich::send($chat, self::targetText($uid), self::cancelKeyboard());
        $k = $tg->send($chat, '👇', ['reply_markup' => self::targetKeyboard()]);
        Store::set('gw_wiz', (string) $uid, ['step' => 'target', 'g' => [], 'c' => (int) ($msg['message_id'] ?? 0), 'm' => (int) ($a['result']['message_id'] ?? 0), 'k' => (int) ($k['result']['message_id'] ?? 0)]);
    }

    private static function targetText(int $uid): string
    {
        $head = L("🎁 <b>ساخت قرعه‌کشی</b>\n\n🎯 قرعه‌کشی کجا برگزار بشه؟\n", "🎁 <b>Create giveaway</b>\n\n🎯 Where should the giveaway be held?\n");
        return Store::get('gw_known', (string) $uid)
            ? $head . L("از دکمه‌ها کانال یا گروهت رو انتخاب کن،\nیا آیدی جدیدی بفرست.", "Choose your channel or group with the buttons,\nor send a new ID.")
            : $head . L('آیدی کانال یا گروهت رو بفرست.', 'Send the ID of your channel or group.');
    }

    private static function cancelKeyboard(): array
    {
        return ikb([[btn(L('❌ لغو', '❌ Cancel'), 'gw:x', null)]]);
    }

    private static function dropKeyboard(int|string $chat, mixed $w): void
    {
        if (!is_array($w) || ($w['step'] ?? '') !== 'target') {
            return;
        }
        $tg = App::tg();
        $r  = $tg->send($chat, '👌', ['reply_markup' => ['remove_keyboard' => true]]);
        if (!empty($r['result']['message_id'])) {
            $tg->call('deleteMessage', ['chat_id' => $chat, 'message_id' => (int) $r['result']['message_id']], 8);
        }
        if (!empty($w['k'])) {
            $tg->call('deleteMessage', ['chat_id' => $chat, 'message_id' => (int) $w['k']], 8);
        }
    }

    private static function resend(int|string $chat, int $mid, string $text, ?array $kb = null): int
    {
        if ($mid) {
            App::tg()->call('deleteMessage', ['chat_id' => $chat, 'message_id' => $mid], 8);
        }
        $r = Rich::send($chat, $text, $kb);
        return (int) ($r['result']['message_id'] ?? 0);
    }

    private static function put(int|string $chat, int $mid, string $text, ?array $kb = null): int
    {
        $tg = App::tg();
        if ($mid) {
            $r = Rich::editMsg($chat, $mid, $text, $kb);
            if (!empty($r['ok']) || stripos((string) ($r['description'] ?? ''), 'not modified') !== false) {
                return $mid;
            }
        }
        $r = Rich::send($chat, $text, $kb);
        return (int) ($r['result']['message_id'] ?? 0);
    }

    public static function parse(string $q): array
    {
        $q   = mbTrim($q);
        $out = AI::chat([
            ['role' => 'system', 'content' => 'Read the user\'s giveaway request (any language or phrasing) and reply with JSON only: {"prize": "<the prize exactly as the user wrote it, without the winner count or the time>", "winners": <number of winners, 1 if not said>, "minutes": <minutes until the draw, 0 if not said>}'],
            ['role' => 'user', 'content' => $q],
        ], ['tier' => 'lite', 'temperature' => 0, 'thinking' => null, 'deadline' => microtime(true) + 12]);
        $j     = $out !== null && preg_match('/\{.*\}/s', $out, $m) ? json_decode($m[0], true) : null;
        $prize = is_array($j) ? mbTrim((string) ($j['prize'] ?? '')) : '';
        $win   = is_array($j) ? (int) ($j['winners'] ?? 1) : 1;
        $min   = is_array($j) ? (int) ($j['minutes'] ?? 0) : 0;
        return [
            'prize'   => mb_substr($prize !== '' ? $prize : $q, 0, 200),
            'winners' => max(1, min(self::MAX_WIN, $win)),
            'dur'     => $min > 0 ? max(300, min(30 * 86400, $min * 60)) : 0,
        ];
    }

    public static function inlineResult(array $from, string $q): ?array
    {
        $q = mbTrim($q);
        if ($q === '') {
            return null;
        }
        $uid = (int) $from['id'];
        $id  = substr(md5($uid . '|' . $q . '|' . intdiv(time(), 600)), 0, 10);
        Store::set('gw_draft', $id, ['q' => mb_substr($q, 0, 300), 'owner' => $uid]);
        return Ui::article('gwi_' . $id, 'gift', L('🎁 قرعه‌کشی: ', '🎁 Giveaway: ') . mb_substr($q, 0, 60), L('بزن تا بسازمش', 'Tap to create it'),
            L("<b>🎁 قرعه‌کشی</b>\n\n⏳ دارم آماده‌اش می‌کنم...", "<b>🎁 Giveaway</b>\n\n⏳ Getting it ready..."), ikb([[btn(L('⏳ صبر کن...', '⏳ Hold on...'), 'noop', null)]]));
    }

    private static function inlineKeyboard(array $g, string $ask = ''): array
    {
        $id = $g['id'];
        if ($ask !== '') {
            return self::managerKeyboard($g, $ask);
        }
        if ($g['status'] === 'live') {
            return ikb([
                [btn(L('🎉 شرکت در قرعه‌کشی', '🎉 Join the giveaway'), 'gw:j:' . $id, 'success')],
                [btn(L('🏁 قرعه بکش', '🏁 Draw winners'), "gw:d:{$id}", null), btn(L('❌ لغو', '❌ Cancel'), "gw:c:{$id}", null)],
            ]);
        }
        if ($g['status'] === 'done' && (int) ($g['total'] ?? 0) > 0) {
            return ikb([[btn(L('🔁 دوباره قرعه بکش', '🔁 Draw again'), "gw:r:{$id}", null)]]);
        }
        return ikb([]);
    }

    public static function fromDraft(string $id, string $imid, array $from): ?array
    {
        $d = Store::getToday('gw_draft', $id);
        if (!is_array($d) || (int) $d['owner'] !== (int) ($from['id'] ?? 0)) {
            return null;
        }
        if (!isset($d['prize'])) {
            $d = self::parse((string) ($d['q'] ?? '')) + ['owner' => (int) $d['owner']];
            Store::set('gw_draft', $id, $d);
        }
        return Store::update('gw', $id, static function ($g) use ($d, $id, $imid) {
            if (is_array($g)) {
                return null;
            }
            $ends = $d['dur'] ? time() + (int) $d['dur'] : 0;
            if ($ends) {
                Store::set('gw_due', $id, $ends);
            }
            return [
                'id' => $id, 'owner' => (int) $d['owner'], 'chat' => 0, 'imid' => $imid, 'title' => L('چت', 'Chat'), 'type' => 'inline', 'username' => '',
                'prize' => $d['prize'], 'winners' => (int) $d['winners'], 'ends' => $ends, 'status' => 'live', 'created' => time(),
                'msg' => 0, 'mgr' => [0, 0], 'won' => [], 'total' => 0, 'bot' => App::botIndex(),
            ];
        }) ?? Store::get('gw', $id);
    }

    public static function onChosen(string $id, string $imid, array $from): void
    {
        if (self::fromDraft($id, $imid, $from) !== null) {
            Users::hit('gw:inline');
            $g = Store::get('gw', $id);
            if (is_array($g)) {
                self::refreshPublic($g);
            }
        }
    }

    private static function refreshPublic(array $g): void
    {
        if (!empty($g['imid'])) {
            Rich::editInline($g['imid'], self::publicText($g, self::count($g['id'])), self::inlineKeyboard($g));
        } else {
            $txt = self::publicText($g, self::count($g['id']));
            $kb  = self::joinKeyboard($g['id']);
            Rich::editMsg($g['chat'], (int) $g['msg'], $txt, $kb);
        }
    }

    private static function editPublic(array $g, string $text, ?array $kb = null): void
    {
        if (!empty($g['imid'])) {
            Rich::editInline($g['imid'], $text, $kb);
        } else {
            Rich::editMsg($g['chat'], (int) $g['msg'], $text, $kb);
        }
    }

    public static function fromGroup(array $msg): void
    {
        $tg = App::tg();
        if (!empty($msg['sender_chat'])) {
            $tg->reply($msg, L("🕶 ناشناس پیام دادی و نمی‌شناسمت!\nبیا پیوی ربات و /giveaway بزن،\nبعد این گروه رو انتخاب کن.", "🕶 You’re posting anonymously and I can’t tell who you are!\nCome to the bot’s private chat and send /giveaway,\nthen choose this group."));
            return;
        }
        $st = self::status($msg['chat']['id'], (int) $msg['from']['id']);
        if (!in_array($st, ['creator', 'administrator'], true)) {
            $tg->reply($msg, L('🙅 فقط ادمین‌های گروه می‌تونن قرعه‌کشی بسازن.', '🙅 Only group admins can create giveaways.'));
            return;
        }
        $bot = App::botUsername();
        Rich::replyTo($msg, L("🎁 برای تنظیم قرعه‌کشی بیا پیوی 👇", "🎁 Come to my private chat to set up the giveaway 👇"), ikb([[urlBtn(L('⚙️ تنظیم قرعه‌کشی', '⚙️ Set up giveaway'), "https://t.me/{$bot}?start=gw_" . $msg['chat']['id'], 'success')]]));
    }

    public static function clear(int $uid): void
    {
        Store::delete('gw_wiz', (string) $uid);
    }

    public static function active(int $uid): bool
    {
        return is_array(Store::getToday('gw_wiz', (string) $uid));
    }

    public static function input(array $msg): bool
    {
        $tg   = App::tg();
        $uid  = (int) $msg['from']['id'];
        $chat = $msg['chat']['id'];
        $w    = Store::getToday('gw_wiz', (string) $uid);
        if (!is_array($w)) {
            return false;
        }
        $text = mbTrim((string) ($msg['text'] ?? ''));
        if ($w['step'] === 'target') {
            $target = null;
            $fwd    = $msg['forward_origin']['chat'] ?? $msg['forward_from_chat'] ?? null;
            if (isset($msg['chat_shared']['chat_id'])) {
                $target = (int) $msg['chat_shared']['chat_id'];
            } elseif (is_array($fwd) && isset($fwd['id'])) {
                $target = (int) $fwd['id'];
            } elseif (preg_match('#(?:t\.me/|^@?)([A-Za-z]\w{3,31})$#', $text, $m)) {
                $target = '@' . $m[1];
            } elseif (preg_match('/^-\d{6,}$/', en($text))) {
                $target = (int) en($text);
            }
            if (!empty($msg['message_id'])) {
                $tg->call('deleteMessage', ['chat_id' => $chat, 'message_id' => (int) $msg['message_id']], 8);
            }
            if ($target === null) {
                $w['m'] = self::resend($chat, (int) ($w['m'] ?? 0), self::targetText($uid), self::cancelKeyboard());
                Store::set('gw_wiz', (string) $uid, $w);
                return true;
            }
            $t = self::checkTarget($uid, $target);
            if (isset($t['error'])) {
                $w['m'] = self::resend($chat, (int) ($w['m'] ?? 0), $t['error'], self::cancelKeyboard());
                Store::set('gw_wiz', (string) $uid, $w);
                return true;
            }
            self::askPrize($chat, $uid, $t);
            return true;
        }

        if ($w['step'] === 'prize') {
            if ($text === '' || $text[0] === '/') {
                self::put($chat, (int) ($w['m'] ?? 0), L('✍️ جایزه رو به صورت متن بنویس.', '✍️ Write the prize as text.'));
                return true;
            }
            if (mb_strlen($text) > 200) {
                self::put($chat, (int) ($w['m'] ?? 0), L('✂️ یه‌کم کوتاه‌ترش کن (حداکثر ۲۰۰ کاراکتر).', '✂️ Make it a bit shorter (200 characters max).'));
                return true;
            }
            $w['g']['prize'] = $text;
            $w['step']       = 'winners';
            $r = Rich::send($chat, L('🏆 جایزه: <b>', '🏆 Prize: <b>') . h($text) . L("</b>\n\n👑 <b>چند نفر برنده بشن؟</b>\nاز دکمه‌ها انتخاب کن یا یه عدد (تا ", "</b>\n\n👑 <b>How many winners?</b>\nChoose with the buttons or send a number (up to ") . fa(self::MAX_WIN) . L(') بفرست.', ').'), self::winnersKeyboard());
            $w['m'] = (int) ($r['result']['message_id'] ?? 0);
            Store::set('gw_wiz', (string) $uid, $w);
            return true;
        }

        if ($w['step'] === 'winners') {
            $n = (int) en($text);
            if (!preg_match('/^\d{1,3}$/', en($text)) || $n < 1 || $n > self::MAX_WIN) {
                self::put($chat, (int) ($w['m'] ?? 0), L('🔢 یه عدد بین ۱ تا ' . fa(self::MAX_WIN) . ' بفرست یا از دکمه‌ها انتخاب کن.', '🔢 Send a number between 1 and ' . self::MAX_WIN . ' or choose with the buttons.'), self::winnersKeyboard());
                return true;
            }
            self::setWinners($uid, $chat, (int) ($w['m'] ?? 0), $n);
            return true;
        }
        return false;
    }

    private static function checkTarget(int $uid, int|string $target): array
    {
        $tg = App::tg();
        $c  = $tg->call('getChat', ['chat_id' => $target], 10);
        if (empty($c['ok'])) {
            return ['error' => L("🤷 پیداش نکردم.\nمطمئن شو منو اون‌جا اضافه کردی\n(تو کانال باید ادمین باشم).", "🤷 I couldn’t find it.\nMake sure you’ve added me there\n(in a channel I must be an admin).")];
        }
        $c    = $c['result'];
        $type = (string) ($c['type'] ?? '');
        if (!in_array($type, ['channel', 'group', 'supergroup'], true)) {
            return ['error' => L('📍 فقط کانال یا گروه رو می‌شه انتخاب کرد.', '📍 You can only choose a channel or a group.')];
        }
        $b  = $tg->call('getChatMember', ['chat_id' => $c['id'], 'user_id' => self::botId()], 10);
        $bs = (string) ($b['result']['status'] ?? '');
        if ($type === 'channel' && ($bs !== 'administrator' || empty($b['result']['can_post_messages']))) {
            return ['error' => L("📢 اول منو تو کانال ادمین کن\n(با دسترسی ارسال و ویرایش پیام)،\nبعد دوباره انتخابش کن.", "📢 First make me an admin in the channel\n(with permission to post and edit messages),\nthen choose it again.")];
        }
        if ($type !== 'channel' && !in_array($bs, ['member', 'administrator'], true)) {
            return ['error' => L("👥 اول منو به گروه اضافه کن،\nبعد دوباره انتخابش کن.", "👥 First add me to the group,\nthen choose it again.")];
        }
        if (!in_array(self::status($c['id'], $uid), ['creator', 'administrator'], true)) {
            return ['error' => L('🙅 فقط ادمین‌های اون‌جا می‌تونن قرعه‌کشی بسازن.', '🙅 Only admins there can create giveaways.')];
        }
        return ['id' => (int) $c['id'], 'title' => mb_substr((string) ($c['title'] ?? ''), 0, 100), 'type' => $type, 'username' => (string) ($c['username'] ?? '')];
    }

    private static function status(int|string $chat, int $uid): string
    {
        $r = App::tg()->call('getChatMember', ['chat_id' => $chat, 'user_id' => $uid], 10);
        $s = (string) ($r['result']['status'] ?? '');
        if ($s === 'restricted') {
            return !empty($r['result']['is_member']) ? 'member' : 'left';
        }
        return $s;
    }

    private static function members(int|string $chat, array $uids): array
    {
        $res = App::tg()->multi('getChatMember', array_combine($uids, array_map(static fn($u) => ['chat_id' => $chat, 'user_id' => $u], $uids)), 10);
        $out = [];
        foreach ($uids as $u) {
            $r = $res[$u] ?? null;
            if (!is_array($r) || (empty($r['ok']) && (int) ($r['error_code'] ?? 0) === 429)) {
                $out[$u] = self::isMember($chat, $u);
                continue;
            }
            $st      = (string) ($r['result']['status'] ?? '');
            $out[$u] = in_array($st, ['creator', 'administrator', 'member'], true) || ($st === 'restricted' && !empty($r['result']['is_member']));
        }
        return $out;
    }

    private static function isMember(int|string $chat, int $uid): bool
    {
        return in_array(self::status($chat, $uid), ['creator', 'administrator', 'member'], true);
    }

    private static function askPrize(int|string $chat, int $uid, array $t): void
    {
        Store::set('gw_known', (string) $uid, 1);
        $old = Store::getToday('gw_wiz', (string) $uid);
        if (is_array($old)) {
            foreach (['m', 'k'] as $f) {
                if (!empty($old[$f])) {
                    App::tg()->call('deleteMessage', ['chat_id' => $chat, 'message_id' => (int) $old[$f]], 8);
                }
            }
        }
        $r = App::tg()->send($chat, ($t['type'] === 'channel' ? '📢' : '👥') . L(' مقصد: <b>', ' Destination: <b>') . h($t['title']) . "</b>\n\n"
            . L("🏆 <b>جایزه چیه؟</b>\n", "🏆 <b>What’s the prize?</b>\n")
            . L("بنویسش؛\nمثلاً: <code>۳ ماه تلگرام پریمیوم</code>", "Write it down;\nfor example: <code>3 months of Telegram Premium</code>"), ['reply_markup' => ['remove_keyboard' => true]]);
        Store::set('gw_wiz', (string) $uid, ['step' => 'prize', 'g' => ['chat' => $t], 'c' => (int) ($old['c'] ?? 0), 'm' => (int) ($r['result']['message_id'] ?? 0)]);
    }

    private static function winnersKeyboard(): array
    {
        return ikb([
            array_map(static fn($n) => btn(fa($n), 'gw:w:' . $n, null), [1, 2, 3, 5, 10]),
            [btn(L('❌ لغو', '❌ Cancel'), 'gw:x', null)],
        ]);
    }

    private static function timeKeyboard(): array
    {
        $rows = [];
        $row  = [];
        foreach (self::DURATIONS as $s => $label) {
            $row[] = btn('⏱ ' . L($label[0], $label[1]), 'gw:t:' . $s, null);
            if (count($row) === 3) {
                $rows[] = $row;
                $row    = [];
            }
        }
        $rows[] = [btn(L('✋ خودم دستی قرعه می‌کشم', '✋ I’ll draw manually'), 'gw:t:0', null)];
        $rows[] = [btn(L('❌ لغو', '❌ Cancel'), 'gw:x', null)];
        return ikb($rows);
    }

    private static function setWinners(int $uid, int|string $chat, int $mid, int $n): void
    {
        $w = Store::getToday('gw_wiz', (string) $uid);
        if (!is_array($w) || !in_array($w['step'], ['winners', 'time'], true)) {
            return;
        }
        $w['g']['winners'] = $n;
        $w['step']         = 'time';
        $text = L('🏆 جایزه: <b>', '🏆 Prize: <b>') . h($w['g']['prize']) . L("</b>\n👑 برنده: <b>", "</b>\n👑 Winners: <b>") . fa($n) . L(" نفر</b>\n\n⏳ <b>قرعه کی کشیده بشه؟</b>", "</b>\n\n⏳ <b>When should the draw happen?</b>");
        $w['m'] = self::put($chat, $mid, $text, self::timeKeyboard());
        Store::set('gw_wiz', (string) $uid, $w);
    }

    private static function when(int $ends): string
    {
        if (!$ends) {
            return L('با اعلام برگزارکننده', 'announced by the organizer');
        }
        return Lang::is('en') ? date('Y-m-d', $ends) . ' at ' . Jalali::time($ends) : Jalali::numericDate($ends) . ' ساعت ' . Jalali::time($ends);
    }

    public static function publicText(array $g, int $count): string
    {
        return L("🎁 <b>قرعه‌کشی!</b>\n\n", "🎁 <b>Giveaway!</b>\n\n")
            . L('🏆 جایزه: <b>', '🏆 Prize: <b>') . h($g['prize']) . "</b>\n"
            . L('👑 تعداد برنده: <b>', '👑 Winners: <b>') . fa($g['winners']) . L("</b> نفر\n", "</b>\n")
            . L('⏳ پایان: <b>', '⏳ Ends: <b>') . self::when((int) $g['ends']) . "</b>\n"
            . L('👥 شرکت‌کننده‌ها: <b>', '👥 Participants: <b>') . fa(number_format($count)) . L("</b> نفر\n\n", "</b>\n\n")
            . L('برای شرکت، دکمه‌ی زیر رو بزن 👇', 'Tap the button below to join 👇');
    }

    private static function joinKeyboard(string $id): array
    {
        return ikb([[btn(L('🎉 شرکت در قرعه‌کشی', '🎉 Join the giveaway'), 'gw:j:' . $id, 'success')]]);
    }

    private static function mention(array $p): string
    {
        $name = mbTrim((string) ($p['name'] ?? ''));
        return '<a href="tg://user?id=' . (int) $p['uid'] . '">' . h($name !== '' ? mb_substr($name, 0, 40) : L('کاربر', 'User')) . '</a>';
    }

    private static function winnerList(array $won): string
    {
        $out = [];
        foreach (array_values($won) as $i => $p) {
            $out[] = (self::MEDALS[$i] ?? fa($i + 1) . '.') . ' ' . self::mention($p);
        }
        return implode("\n", $out);
    }

    private static function resultText(array $g, bool $again): string
    {
        $head = $again ? L("🔁 <b>قرعه دوباره کشیده شد!</b>\n\n", "🔁 <b>The draw was redone!</b>\n\n") : L("🎉 <b>قرعه‌کشی تموم شد!</b>\n\n", "🎉 <b>The giveaway is over!</b>\n\n");
        $body = L('🏆 جایزه: <b>', '🏆 Prize: <b>') . h($g['prize']) . "</b>\n"
            . L('👥 شرکت‌کننده‌ها: <b>', '👥 Participants: <b>') . fa(number_format((int) $g['total'])) . L("</b> نفر\n\n", "</b>\n\n");
        if (!$g['won']) {
            return $head . $body . L('😕 کسی شرکت نکرد، برنده‌ای نداریم.', '😕 Nobody joined, so there are no winners.');
        }
        return $head . $body . L("👑 <b>برنده‌ها:</b>\n", "👑 <b>Winners:</b>\n") . self::winnerList($g['won']) . L("\n\nمبارک باشه 🎊", "\n\nCongratulations 🎊");
    }

    private static function link(array $g): ?string
    {
        if (empty($g['msg'])) {
            return null;
        }
        if (!empty($g['username'])) {
            return 'https://t.me/' . $g['username'] . '/' . $g['msg'];
        }
        $id = (string) $g['chat'];
        return str_starts_with($id, '-100') ? 'https://t.me/c/' . substr($id, 4) . '/' . $g['msg'] : null;
    }

    private static function managerText(array $g): string
    {
        $where = ($g['type'] === 'channel' ? '📢 ' : '👥 ') . h($g['title']);
        if ($g['status'] === 'cancel') {
            return L("❌ <b>قرعه‌کشی لغو شد</b>", "❌ <b>Giveaway cancelled</b>") . "\n\n{$where}\n🏆 " . h($g['prize']);
        }
        if ($g['status'] === 'done') {
            return L("🏁 <b>قرعه‌کشی انجام شد</b>", "🏁 <b>Giveaway drawn</b>") . "\n\n{$where}\n🏆 " . h($g['prize']) . "\n"
                . L('👥 شرکت‌کننده‌ها: ', '👥 Participants: ') . fa(number_format((int) $g['total'])) . "\n\n"
                . ($g['won'] ? L("👑 <b>برنده‌ها:</b>\n", "👑 <b>Winners:</b>\n") . self::winnerList($g['won']) : L('😕 کسی شرکت نکرد.', '😕 Nobody joined.'));
        }
        return L("✅ <b>قرعه‌کشی فعاله</b>", "✅ <b>Giveaway is active</b>") . "\n\n{$where}\n🏆 " . h($g['prize']) . "\n"
            . '👑 ' . fa($g['winners']) . L(' برنده · ⏳ ', ' winners · ⏳ ') . self::when((int) $g['ends']) . "\n"
            . L('👥 شرکت‌کننده‌ها: <b>', '👥 Participants: <b>') . fa(number_format(self::count($g['id']))) . L('</b> نفر', '</b>');
    }

    private static function managerKeyboard(array $g, string $ask = ''): array
    {
        $id   = $g['id'];
        $rows = [];
        if ($ask !== '') {
            $q = ['d' => L('✅ آره، قرعه بکش', '✅ Yes, draw'), 'c' => L('✅ آره، لغوش کن', '✅ Yes, cancel it'), 'r' => L('✅ آره، دوباره بکش', '✅ Yes, redraw')][$ask];
            $rows[] = [btn($q, "gw:{$ask}{$ask}:{$id}", 'danger'), btn(L('🔙 نه', '🔙 No'), "gw:s:{$id}", null)];
            return ikb($rows);
        }
        if ($g['status'] === 'live') {
            $rows[] = [btn(L('🔄 آمار', '🔄 Stats'), "gw:s:{$id}", null), btn(L('🏁 الان قرعه بکش', '🏁 Draw now'), "gw:d:{$id}", 'success')];
            $rows[] = [btn(L('❌ لغو قرعه‌کشی', '❌ Cancel giveaway'), "gw:c:{$id}", null)];
        } elseif ($g['status'] === 'done' && (int) $g['total'] > 0) {
            $rows[] = [btn(L('🔁 دوباره قرعه بکش', '🔁 Draw again'), "gw:r:{$id}", null)];
        }
        if (($l = self::link($g)) !== null && $g['status'] !== 'cancel') {
            $rows[] = [urlBtn(L('🔗 دیدن پیام قرعه‌کشی', '🔗 View giveaway post'), $l, null)];
        }
        return ikb($rows);
    }

    public static function count(string $id): int
    {
        return (int) Db::value('SELECT COUNT(*) FROM gw_entries WHERE gid = ?', [$id]);
    }

    public static function onCallback(array $cq, string $data): void
    {
        $tg   = App::tg();
        $uid  = (int) $cq['from']['id'];
        $chat = $cq['message']['chat']['id'] ?? null;
        $mid  = (int) ($cq['message']['message_id'] ?? 0);

        if ($data === 'gw:new') {
            $tg->answer($cq);
            if ($chat !== null) {
                self::start(['from' => $cq['from'], 'chat' => ['id' => $uid]]);
            }
            return;
        }
        if ($data === 'gw:x') {
            $w = Store::getToday('gw_wiz', (string) $uid);
            self::clear($uid);
            if ($chat !== null) {
                self::dropKeyboard($chat, $w);
                $r = $tg->call('deleteMessage', ['chat_id' => $chat, 'message_id' => $mid], 8);
                if (empty($r['ok'])) {
                    Rich::editMsg($chat, $mid, L('👌 ساخت قرعه‌کشی لغو شد.', '👌 Giveaway creation cancelled.'));
                }
            }
            $tg->answer($cq, L('👌 ساخت قرعه‌کشی لغو شد', '👌 Giveaway creation cancelled'));
            return;
        }
        if (preg_match('/^gw:w:(\d{1,2})$/', $data, $m)) {
            self::setWinners($uid, $chat, $mid, max(1, min(self::MAX_WIN, (int) $m[1])));
            $tg->answer($cq);
            return;
        }
        if (preg_match('/^gw:t:(\d+)$/', $data, $m)) {
            $w = Store::getToday('gw_wiz', (string) $uid);
            if (!is_array($w) || $w['step'] !== 'time') {
                $tg->alert($cq, L("⌛️ این مرحله منقضی شده؛\nدوباره /giveaway بزن.", "⌛️ This step has expired;\nsend /giveaway again."));
                return;
            }
            $s = (int) $m[1];
            $w['g']['dur'] = isset(self::DURATIONS[$s]) ? $s : 0;
            $w['step']     = 'confirm';
            Store::set('gw_wiz', (string) $uid, $w);
            $g = $w['g'] + ['ends' => $w['g']['dur'] ? time() + $w['g']['dur'] : 0];
            Rich::editMsg($chat, $mid, L("👀 <b>پیش‌نمایش:</b>\n\n", "👀 <b>Preview:</b>\n\n") . self::publicText($g, 0) . "\n\n" . divider() . "\n"
                . L('منتشرش کنم تو «<b>' . h($g['chat']['title']) . '</b>»؟', 'Publish it in “<b>' . h($g['chat']['title']) . '</b>”?'), ikb([
                    [btn(L('✅ انتشار', '✅ Publish'), 'gw:go', 'success'), btn(L('❌ لغو', '❌ Cancel'), 'gw:x', null)],
                ]));
            $tg->answer($cq);
            return;
        }
        if ($data === 'gw:go') {
            self::publish($cq, $uid, $chat, $mid);
            return;
        }
        if (!preg_match('/^gw:(j|s|d|dd|c|cc|r|rr):([a-f0-9]{10})$/', $data, $m)) {
            $tg->answer($cq);
            return;
        }
        [, $act, $id] = $m;
        $imid = $cq['inline_message_id'] ?? null;
        $g    = Store::get('gw', $id);
        if (!is_array($g) && $imid) {
            $d = Store::getToday('gw_draft', $id);
            $g = is_array($d) ? self::fromDraft($id, $imid, ['id' => $d['owner']]) : null;
        }
        if (!is_array($g)) {
            $tg->alert($cq, L('🤷 این قرعه‌کشی دیگه وجود نداره.', '🤷 This giveaway no longer exists.'));
            return;
        }
        if ($act === 'j') {
            self::join($cq, $g);
            return;
        }
        if ($uid !== (int) $g['owner'] && !App::isAdmin($uid)) {
            $tg->alert($cq, L("🔒 فقط سازنده‌ی قرعه‌کشی\nبه این دکمه دسترسی داره.", "🔒 Only the giveaway’s creator\ncan use this button."));
            return;
        }
        if (in_array($act, ['d', 'c', 'r'], true)) {
            $ok = ($act === 'r') ? $g['status'] === 'done' : $g['status'] === 'live';
            if ($ok && $imid) {
                Rich::editInline($imid, self::publicText($g, self::count($g['id'])), self::managerKeyboard($g, $act));
            } elseif ($ok) {
                Rich::editMsg($chat, $mid, self::managerText($g), self::managerKeyboard($g, $act));
            }
            $tg->answer($cq);
            return;
        }
        if ($act === 'dd') {
            $tg->answer($cq, L('🎲 دارم قرعه می‌کشم...', '🎲 Drawing...'));
            self::draw($id, false);
            return;
        }
        if ($act === 'rr') {
            $tg->answer($cq, L('🎲 دارم دوباره قرعه می‌کشم...', '🎲 Redrawing...'));
            self::draw($id, true);
            return;
        }
        if ($act === 'cc') {
            $g = Store::update('gw', $id, static fn($x) => (is_array($x) && $x['status'] === 'live') ? ['status' => 'cancel'] + $x : null);
            if ($g !== null) {
                Store::delete('gw_due', $id);
                Store::delete('gw_dirty', $id);
                self::editPublic($g, L("❌ <b>این قرعه‌کشی لغو شد.</b>", "❌ <b>This giveaway was cancelled.</b>") . "\n\n🏆 " . h($g['prize']));
                $tg->answer($cq, L('👌 لغو شد', '👌 Cancelled'));
                if ($imid) {
                    return;
                }
            } else {
                $tg->answer($cq);
                $g = Store::get('gw', $id);
            }
        } else {
            $tg->answer($cq, $imid ? null : L('🔄 به‌روز شد', '🔄 Updated'));
        }
        if ($imid) {
            if ($g['status'] === 'live') {
                self::refreshPublic($g);
            } else {
                Rich::editInline($imid, self::publicText($g, self::count($g['id'])), self::inlineKeyboard($g));
            }
            return;
        }
        Rich::editMsg($chat, $mid, self::managerText($g), self::managerKeyboard($g));
    }

    private static function publish(array $cq, int $uid, int|string $chat, int $mid): void
    {
        $tg = App::tg();
        $w  = Store::getToday('gw_wiz', (string) $uid);
        if (!is_array($w) || $w['step'] !== 'confirm') {
            $tg->alert($cq, L("⌛️ منقضی شده؛\nدوباره /giveaway بزن.", "⌛️ Expired;\nsend /giveaway again."));
            return;
        }
        $t  = $w['g']['chat'];
        $id = bin2hex(random_bytes(5));
        $g  = [
            'id'       => $id,
            'owner'    => $uid,
            'chat'     => $t['id'],
            'title'    => $t['title'],
            'type'     => $t['type'],
            'username' => $t['username'],
            'prize'    => $w['g']['prize'],
            'winners'  => (int) $w['g']['winners'],
            'ends'     => $w['g']['dur'] ? time() + (int) $w['g']['dur'] : 0,
            'status'   => 'live',
            'created'  => time(),
            'msg'      => 0,
            'mgr'      => [$chat, $mid],
            'won'      => [],
            'total'    => 0,
            'bot'      => App::botIndex(),
        ];
        $r = Rich::send($t['id'], self::publicText($g, 0), self::joinKeyboard($id));
        if (empty($r['ok'])) {
            $tg->alert($cq, L('❌ نتونستم تو «' . $t['title'] . "» پیام بفرستم؛\nدسترسی‌هام رو چک کن.", '❌ I couldn’t send a message in “' . $t['title'] . "”;\ncheck my permissions."));
            return;
        }
        $g['msg'] = (int) $r['result']['message_id'];
        Store::set('gw', $id, $g);
        if ($g['ends']) {
            Store::set('gw_due', $id, $g['ends']);
        }
        self::clear($uid);
        Users::hit('gw:create');
        $tg->answer($cq, L('🎉 منتشر شد!', '🎉 Published!'));
        Rich::editMsg($chat, $mid, self::managerText($g), self::managerKeyboard($g));
    }

    private static function join(array $cq, array $g): void
    {
        $tg   = App::tg();
        $from = $cq['from'];
        $uid  = (int) $from['id'];
        if ($g['status'] !== 'live') {
            $tg->alert($cq, L('⌛️ این قرعه‌کشی تموم شده.', '⌛️ This giveaway has ended.'));
            return;
        }
        if ($g['type'] !== 'inline' && !self::isMember($g['chat'], $uid)) {
            $tg->alert($cq, $g['type'] === 'channel' ? L('📢 اول عضو کانال شو، بعد دکمه رو بزن!', '📢 Join the channel first, then tap the button!') : L('👥 اول عضو گروه شو، بعد دکمه رو بزن!', '👥 Join the group first, then tap the button!'));
            return;
        }
        $new = Db::run('INSERT INTO gw_entries (gid, uid, name, at) VALUES (?, ?, ?, ?) ON CONFLICT (gid, uid) DO NOTHING',
            [$g['id'], $uid, mb_substr(userName($from), 0, 64), time()])->rowCount() > 0;
        if (!$new) {
            $tg->alert($cq, L("👌 قبلاً ثبت‌نام کردی؛\nمنتظر نتیجه باش!", "👌 You’re already registered;\nwait for the result!"));
            return;
        }
        Store::set('gw_dirty', $g['id'], time());
        Users::hit('gw:join');
        $tg->alert($cq, L("✅ ثبت‌نام شدی!\nنفر " . fa(number_format(self::count($g['id']))) . "ام هستی، موفق باشی 🍀", "✅ You’re in!\nYou’re participant #" . number_format(self::count($g['id'])) . ', good luck 🍀'));
    }

    private static function rows(string $ns): array
    {
        $out = [];
        foreach (Db::run('SELECT k, v FROM kv WHERE ns = ?', [$ns])->fetchAll() as $r) {
            $out[(string) $r['k']] = (int) json_decode((string) $r['v'], true);
        }
        return $out;
    }

    public static function pending(): bool
    {
        if (self::rows('gw_dirty')) {
            return true;
        }
        foreach (self::rows('gw_due') as $at) {
            if ($at <= time()) {
                return true;
            }
        }
        return false;
    }

    public static function tick(): void
    {
        foreach (self::rows('gw_due') as $id => $at) {
            if ($at <= time()) {
                self::draw($id, false);
            }
        }
        foreach (array_keys(self::rows('gw_dirty')) as $id) {
            Store::delete('gw_dirty', $id);
            $g = Store::get('gw', $id);
            if (is_array($g) && $g['status'] === 'live') {
                App::useBot((int) ($g['bot'] ?? 0));
                self::refreshPublic($g);
            }
        }
        App::useBot(0);
    }

    private static function shuffle(array $a): array
    {
        for ($i = count($a) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$a[$i], $a[$j]] = [$a[$j], $a[$i]];
        }
        return $a;
    }

    public static function draw(string $id, bool $again): bool
    {
        $g  = Store::update('gw', $id, static fn($x) => (is_array($x) && ($x['status'] === 'live' || ($again && $x['status'] === 'done'))) ? ['status' => 'drawing'] + $x : null);
        if ($g === null) {
            return false;
        }
        App::useBot((int) ($g['bot'] ?? 0));
        $tg = App::tg();
        Store::delete('gw_due', $id);
        Store::delete('gw_dirty', $id);
        $won = [];
        try {
            $all  = Db::run('SELECT uid, name FROM gw_entries WHERE gid = ?', [$id])->fetchAll();
            $prev = $again ? array_flip(array_map(static fn($p) => (int) $p['uid'], (array) $g['won'])) : [];
            $pool = array_values(array_filter($all, static fn($p) => !isset($prev[(int) $p['uid']])));
            if (!$pool) {
                $pool = $all;
            }
            $won  = [];
            $need = (int) $g['winners'];
            $left = self::shuffle($pool);
            while (count($won) < $need && $left) {
                $batch = array_splice($left, 0, $g['type'] === 'inline' ? $need : min(25, max(5, ($need - count($won)) * 3)));
                $ok    = $g['type'] === 'inline' ? null : self::members($g['chat'], array_map(static fn($p) => (int) $p['uid'], $batch));
                foreach ($batch as $p) {
                    if (count($won) >= $need) {
                        break;
                    }
                    if ($ok === null || !empty($ok[(int) $p['uid']])) {
                        $won[] = ['uid' => (int) $p['uid'], 'name' => (string) $p['name']];
                    }
                }
            }
            $g['won']    = $won;
            $g['total']  = count($all);
            $g['drawn']  = time();
        } finally {
            $g['status'] = 'done';
            Store::set('gw', $id, $g);
        }

        $inline = !empty($g['imid']);
        self::editPublic($g, self::resultText($g, $again), $inline ? self::inlineKeyboard($g) : null);
        if ($won && !$inline) {
            $tg->send($g['chat'], L("🎊 <b>برنده‌های قرعه‌کشی «" . h($g['prize']) . "»</b>\n\n", "🎊 <b>Winners of the “" . h($g['prize']) . "” giveaway</b>\n\n") . self::winnerList($won) . L("\n\nتبریک! 🥳", "\n\nCongratulations! 🥳"), [
                'reply_parameters' => ['message_id' => (int) $g['msg'], 'allow_sending_without_reply' => true],
            ]);
        }
        if ($won) {
            foreach ($won as $p) {
                $tg->call('sendMessage', [
                    'chat_id'    => $p['uid'],
                    'text'       => L("🎉 <b>تبریک!</b>\n\nتو قرعه‌کشی «" . h($g['prize']) . '»' . ($inline ? '' : ' تو <b>' . h($g['title']) . '</b>') . " برنده شدی 🥳\nمنتظر پیام برگزارکننده باش.",
                        "🎉 <b>Congratulations!</b>\n\nYou won the “" . h($g['prize']) . '” giveaway' . ($inline ? '' : ' in <b>' . h($g['title']) . '</b>') . " 🥳\nWait for a message from the organizer."),
                    'parse_mode' => 'HTML',
                ], 10);
            }
        }
        [$mc, $mm] = $g['mgr'] + [0, 0];
        if ($mc && $mm) {
            Rich::editMsg($mc, (int) $mm, self::managerText($g), self::managerKeyboard($g));
        } elseif ($inline) {
            $tg->call('sendMessage', ['chat_id' => (int) $g['owner'], 'text' => self::resultText($g, $again), 'parse_mode' => 'HTML'], 10);
        }
        logInfo('gw', "{$id} drawn: " . count($won) . '/' . $g['total']);
        return true;
    }

    public static function janitor(): void
    {
        Db::run('DELETE FROM gw_entries WHERE at < ?', [time() - 120 * 86400]);
    }
}
