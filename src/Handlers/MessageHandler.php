<?php

final class MessageHandler
{
    private const TRIGGER = '(?:نالینی|نالین|nullini|nullin)';
    private const TIDY_SKIP = ['cookies', 'broadcast', 'giveaway', 'gw'];
    private const TIDY_SEND = '/^send(Message|RichMessage|Photo|Video|Animation|Document|Audio|Voice|Sticker|Poll|Dice|MediaGroup)$/';

    private static ?array $tidy = null;

    public static function handle(array $msg): void
    {
        if (!empty($msg['via_bot'])) {
            return;
        }

        $from     = $msg['from'] ?? [];
        $userId   = (int) ($from['id'] ?? 0);
        $chatType = (string) ($msg['chat']['type'] ?? '');
        $text     = trim((string) ($msg['text'] ?? $msg['caption'] ?? ''));
        $private  = $chatType === 'private';
        $group    = in_array($chatType, ['group', 'supergroup'], true);

        if (!$userId || (!$private && !$group)) {
            return;
        }

        if ($private) {
            Users::touch($from, str_starts_with($text, '/start'));
        } else {
            Roast::see($from);
        }
        if (isset($msg['reply_to_message']['from'])) {
            Roast::see($msg['reply_to_message']['from']);
        }
        if ($group && $text !== '' && $text[0] === '/') {
            Ui::syncMemberCommands(App::tg(), (int) $msg['chat']['id'], $userId);
        }

        if ($text !== '' && $text[0] === '/' && self::tidy($msg, $text, $private, static fn() => self::command($msg, $text, $private))) {
            return;
        }

        if ($private && Cookies::input($msg)) {
            return;
        }
        if ($private && Broadcast::input($msg)) {
            return;
        }
        if ($private && Giveaway::input($msg)) {
            return;
        }
        if ($private && Business::input($msg)) {
            return;
        }

        $hasMedia = Assistant::hasMedia($msg);
        if ($text === '' && !$hasMedia) {
            return;
        }

        if ($text !== '' && Downloader::isIgStory($text)) {
            if (Downloader::rejected($text) && Switches::on('download') && ($private || Downloader::groupsEnabled())) {
                Downloader::dislike($msg);
            }
            return;
        }

        $startsWithName = preg_match('/^' . self::TRIGGER . '(?:[\s،,:!؟?.]|$)/iu', $text) === 1;
        $mentioned      = self::mentionsBot($text);
        $link = null;
        if ($text !== '' && empty($msg['is_automatic_forward']) && ($private || Downloader::groupsEnabled() || $mentioned || $startsWithName)) {
            $bare = preg_replace('/@' . preg_quote(App::botUsername(), '/') . '\b/iu', '', $text) ?? $text;
            $bare = preg_replace('/^' . self::TRIGGER . '[\s،,:]*/iu', '', mbTrim($bare)) ?? $bare;
            $link = Downloader::match($bare);
            if ($link === null && Downloader::rejected($bare)) {
                if (Switches::on('download')) {
                    Downloader::dislike($msg);
                }
                return;
            }
        }
        $toolReply = self::isReplyToBot($msg)
            && !AI::isAiMsg($msg['chat']['id'], (int) ($msg['reply_to_message']['message_id'] ?? 0))
            && !$mentioned && !$startsWithName;
        if ($link !== null && !Switches::on('download')) {
            return;
        }
        $price = null;
        if ($text !== '' && empty($msg['is_automatic_forward']) && Switches::on('rates')) {
            $pq    = preg_replace('/@' . preg_quote(App::botUsername(), '/') . '\b/iu', '', $text) ?? $text;
            $pq    = preg_replace('/^' . self::TRIGGER . '[\s،,:]*/iu', '', mbTrim($pq)) ?? $pq;
            $price = PriceCard::match($pq, true);
        }
        $shouldRespond  = $link !== null
            || $price !== null
            || (!$toolReply && (
                $private
                || ($group && !empty($msg['is_automatic_forward']))
                || ($group && self::isReplyToBot($msg))
                || ($group && $mentioned)
                || ($group && is_array($msg['dice'] ?? null))
                || ($group && preg_match('/' . self::TRIGGER . '/iu', $text) === 1)
            ));

        if (!$shouldRespond) {
            if ($group && $toolReply) {
                logInfo('reply', 'ignored reply to non-AI bot message ' . $msg['chat']['id'] . ':' . (int) ($msg['reply_to_message']['message_id'] ?? 0));
            }
            return;
        }

        if ($private) {
            $missing = Membership::missing($userId);
            if ($missing) {
                Membership::prompt($msg['chat']['id'], $userId, $missing, null, '', (int) ($msg['message_id'] ?? 0));
                return;
            }
        } elseif ($group && !is_array($msg['dice'] ?? null) && empty($msg['is_automatic_forward']) && Membership::gateGroup($msg, $link !== null ? 'download' : '')) {
            return;
        }

        if ($price !== null && $link === null) {
            PriceCard::send($msg, $price);
            return;
        }

        if ($link !== null) {
            Downloader::offer($msg, $link);
            return;
        }

        if (is_array($msg['dice'] ?? null)) {
            Rich::replyWithButton($msg, Ui::diceHello((string) ($msg['dice']['emoji'] ?? '🎲'), (int) ($msg['dice']['value'] ?? 0)), 'Nullin', 'noop', null);
            return;
        }

        $question = $text;
        if ($mentioned) {
            $question = mbTrim(preg_replace('/@' . preg_quote(App::botUsername(), '/') . '\b/iu', '', $question) ?? $question, " \t\n،,:");
        }
        if ($startsWithName) {
            $question = mbTrim(preg_replace('/^' . self::TRIGGER . '/iu', '', $question) ?? $question, " \t\n،,:");
        }

        if ($group && $question === '' && !$hasMedia && !isset($msg['reply_to_message']) && ($mentioned || $startsWithName)) {
            $r = Rich::replyTo($msg, Ui::hello(), Ui::groupKeyboard());
            AI::markMsg($msg['chat']['id'], $r['result']['message_id'] ?? 0);
            return;
        }

        if ($question === '' && !$hasMedia) {
            $question = $text;
        }
        Assistant::handle($msg, $question);
    }

    private static function mentionsBot(string $text): bool
    {
        $bot = App::botUsername();
        return $bot !== '' && preg_match('/@' . preg_quote($bot, '/') . '\b/iu', $text) === 1;
    }

    private static function isReplyToBot(array $msg): bool
    {
        $r = $msg['reply_to_message']['from'] ?? null;
        return $r && !empty($r['is_bot']) && strcasecmp((string) ($r['username'] ?? ''), App::botUsername()) === 0;
    }

    private static function tidy(array $msg, string $text, bool $private, callable $fn): bool
    {
        self::$tidy = null;
        try {
            if (preg_match('/^\/([a-zA-Z_]+)(?:@(\w+))?(?:\s+(.*))?$/su', $text, $m) === 1) {
                $cmd  = strtolower($m[1]);
                $to   = $m[2] ?? '';
                $args = trim($m[3] ?? '');
                $uid  = (int) ($msg['from']['id'] ?? 0);
                $skip = in_array($cmd, self::TIDY_SKIP, true) || ($cmd === 'start' && $args !== '');
                if ($uid && !$skip && ($to === '' || strcasecmp($to, App::botUsername()) === 0)) {
                    self::$tidy = ['chat' => (string) $msg['chat']['id'], 'key' => "{$msg['chat']['id']}:{$uid}:{$cmd}", 'new' => []];
                }
            }
            $handled = $fn();
            if ($handled && self::$tidy !== null) {
                self::tidyFinish($msg, $private);
            }
            return $handled;
        } finally {
            self::$tidy = null;
        }
    }

    private static function tidyFinish(array $msg, bool $private): void
    {
        $t          = self::$tidy;
        self::$tidy = null;
        $chat       = $msg['chat']['id'];
        if (!$private && !self::tidyCanDelete($chat)) {
            return;
        }
        $old = Store::get('tidy', $t['key']);
        if (is_array($old)) {
            $ids = array_merge([(int) ($old['c'] ?? 0)], array_map('intval', (array) ($old['m'] ?? [])));
            foreach (array_unique(array_filter($ids)) as $id) {
                App::tg()->call('deleteMessage', ['chat_id' => $chat, 'message_id' => $id], 8);
            }
        }
        Store::set('tidy', $t['key'], ['c' => (int) ($msg['message_id'] ?? 0), 'm' => array_slice($t['new'], -10)]);
    }

    private static function tidyCanDelete(int|string $chat): bool
    {
        $r = App::tg()->call('getChatMember', ['chat_id' => $chat, 'user_id' => (int) strstr(App::tg()->token(), ':', true)], 10);
        if (empty($r['ok'])) {
            return false;
        }
        $st = (string) ($r['result']['status'] ?? '');
        return $st === 'creator' || ($st === 'administrator' && !empty($r['result']['can_delete_messages']));
    }

    public static function tidySeen(string $method, array $params, ?array $res): void
    {
        if (self::$tidy === null || empty($res['ok']) || preg_match(self::TIDY_SEND, $method) !== 1) {
            return;
        }
        if ((string) ($params['chat_id'] ?? '') !== self::$tidy['chat']) {
            return;
        }
        $r   = $res['result'] ?? [];
        $ids = isset($r['message_id'])
            ? [(int) $r['message_id']]
            : array_map(static fn($x) => (int) ($x['message_id'] ?? 0), is_array($r) ? array_values($r) : []);
        self::$tidy['new'] = array_merge(self::$tidy['new'], array_values(array_filter($ids)));
    }

    private static function command(array $msg, string $text, bool $private): bool
    {
        $tg     = App::tg();
        $chatId = $msg['chat']['id'];
        $from   = $msg['from'];

        if (!preg_match('/^\/([a-zA-Z_]+)(?:@(\w+))?(?:\s+(.*))?$/su', $text, $m)) {
            return false;
        }
        $cmd  = strtolower($m[1]);
        $to   = $m[2] ?? '';
        $args = trim($m[3] ?? '');
        if ($to !== '' && strcasecmp($to, App::botUsername()) !== 0) {
            return true;
        }
        if (!$private && Membership::gateGroup($msg)) {
            return true;
        }
        if ($private) {
            $missing = Membership::missing((int) $from['id']);
            if ($missing) {
                Membership::prompt($chatId, (int) $from['id'], $missing, null, '', (int) ($msg['message_id'] ?? 0));
                return true;
            }
        }
        Users::hit("cmd:{$cmd}");

        $gate = ['wk' => 'wiki', 'wiki' => 'wiki', 'giveaway' => 'giveaway', 'gw' => 'giveaway', 'image' => 'image', 'img' => 'image', 'font' => 'font', 'ft' => 'font', 'tr' => 'translate', 'translate' => 'translate', 'st' => 'linkcheck', 'monshi' => 'business'][$cmd] ?? null;
        if ($gate !== null && !Switches::guard($gate)) {
            return true;
        }

        switch ($cmd) {
            case 'forget':
                $count = count(UserMemory::all((int) $from['id']));
                if ($count === 0) {
                    $tg->reply($msg, L('هنوز چیزی ازت تو حافظه‌م نیست 🤷', 'I don\'t have anything stored about you yet 🤷'));
                    return true;
                }
                Rich::replyTo($msg, L("🧹 <b>پاک کردن حافظه</b>\n\n", "🧹 <b>Clear memory</b>\n\n")
                    . L("الان <b>", "I currently remember <b>") . fa((string) $count) . L(" مورد</b> درباره‌ات یادمه؛\n", " items</b> about you;\n")
                    . L("اگه پاکشون کنم، از اول باهم آشنا می‌شیم.\n\n", "If I clear them, we'll get to know each other from scratch.\n\n")
                    . L("⚠️ <i>این کار برگشت نداره.</i> مطمئنی؟", "⚠️ <i>This can't be undone.</i> Are you sure?"), ikb([[
                    btn(L('🗑 آره، پاک کن', '🗑 Yes, clear it'), 'mem:' . (int) $from['id'] . ':y', 'danger'),
                    btn(L('↩️ نه، بمونه', '↩️ No, keep it'), 'mem:' . (int) $from['id'] . ':n', 'success'),
                ]]));
                return true;
            case 'start':
                if (str_starts_with($args, 'gw_') || $args === 'giveaway') {
                    if ($private) {
                        Giveaway::start($msg, $args);
                    }
                    return true;
                }
                if ($private) {
                    Giveaway::clear((int) $from['id']);
                    Business::clear((int) $from['id']);
                }
                if ($args === 'help') {
                    Rich::sendHtml($chatId, Ui::helpText(), null, Ui::helpMenu($private));
                    return true;
                }
                if (!$private) {
                    $r = Rich::send($chatId, Ui::groupText(), Ui::groupKeyboard());
                    AI::markMsg($chatId, $r['result']['message_id'] ?? 0);
                    return true;
                }
                Ui::sendMain($chatId, $from);
                return true;

            case 'monshi':
                if ($private) {
                    Store::set('biz_back', (string) $from['id'], 0);
                    [$bt, $bk] = Business::panel((int) $from['id']);
                    Rich::sendHtml($chatId, $bt, null, $bk);
                }
                return true;

            case 'lang':
                $r = Rich::sendHtml($chatId, L('🌐 زبان ربات رو انتخاب کن:', '🌐 Choose the bot language:'), $private ? null : (int) ($msg['message_id'] ?? 0), Lang::toggleKeyboard());
                if (!$private && !empty($r['result']['message_id'])) {
                    Store::set('menuowner', "{$chatId}:{$r['result']['message_id']}", (int) $from['id']);
                }
                return true;

            case 'ping':
                $t0 = microtime(true);
                $tg->call('getMe', [], 10);
                Rich::replyWithButton($msg, Ui::ping((int) round((microtime(true) - $t0) * 1000)), 'Nullin', 'noop', null);
                return true;

            case 'help':
                Rich::sendHtml($chatId, Ui::helpText(), null, Ui::helpMenu($private));
                return true;

            case 'voice':
                [$vt, $vk] = VoicePick::panel((int) $from['id']);
                Rich::sendHtml($chatId, $vt, (int) $msg['message_id'], $vk);
                return true;

            case 'wk':
            case 'wiki':
                $args === '' ? $tg->reply($msg, Ui::guide('wiki')) : Wiki::reply($msg, $args);
                return true;

            case 'giveaway':
            case 'gw':
                $private ? Giveaway::start($msg) : Giveaway::fromGroup($msg);
                return true;

            case 'image':
            case 'img':
                if ($args === '') {
                    $tg->reply($msg, Ui::guide('image'));
                } else {
                    ImageGen::handle($msg, $args);
                }
                return true;

            case 'font':
            case 'ft':
                $tg->reply($msg, $args === '' ? Ui::guide('font') : Fonts::listText($args));
                return true;

            case 'tr':
            case 'translate':
                Assistant::translate($msg, $args);
                return true;

            case 'st':
                $tg->action($chatId, 'typing');
                $res   = LinkCheck::respondFull($args, LinkCheck::replyText($msg), (int) ($from['id'] ?? 0));
                $photo = $res['avatar'] !== null ? LinkCheck::avatarBytes($res['avatar']) : null;
                if ($photo !== null) {
                    $plain = html_entity_decode(strip_tags($res['text']), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    if (mb_strlen($plain) <= 1000 && $tg->sendPhotoBytes($chatId, $photo, $res['text'], (int) $msg['message_id'])) {
                        return true;
                    }
                    $tg->sendPhotoBytes($chatId, $photo, '', (int) $msg['message_id']);
                }
                $tg->reply($msg, $res['text'], ['link_preview_options' => ['is_disabled' => true]]);
                return true;

            case 'stats':
                if (!App::isAdmin((int) $from['id'])) {
                    return false;
                }
                $u   = Users::summary();
                $n   = static fn($v) => fa(number_format((int) $v));
                $top = '';
                foreach (Users::topEvents(1, 8) as $e) {
                    $top .= "\n• <code>" . h($e['event']) . '</code>: ' . $n($e['hits']);
                }
                $tg->reply($msg, L("📊 <b>آمار نالین</b>\n\n", "📊 <b>Nullin stats</b>\n\n")
                    . L('👥 کل کاربرها: ', '👥 Total users: ') . $n($u['total'] ?? 0) . "\n"
                    . L('🆕 جدید امروز: ', '🆕 New today: ') . $n($u['new_today'] ?? 0) . "\n"
                    . L('🔥 فعال ۲۴ ساعت: ', '🔥 Active 24h: ') . $n($u['day'] ?? 0) . L(' · ۷ روز: ', ' · 7 days: ') . $n($u['week'] ?? 0) . "\n"
                    . L('📬 قابل پیام: ', '📬 Reachable: ') . $n($u['reachable'] ?? 0) . L(' · 💎 پریمیوم: ', ' · 💎 Premium: ') . $n($u['premium'] ?? 0) . "\n"
                    . L('🎮 بازی‌های ذخیره‌شده: ', '🎮 Saved games: ') . $n(Store::count('games'))
                    . ($top !== '' ? L("\n\n<b>⭐ پرکاربردهای امروز:</b>{$top}", "\n\n<b>⭐ Most used today:</b>{$top}") : '')
                    . L("\n\n🗄 دیتابیس: <code>", "\n\n🗄 Database: <code>") . (Db::driver() === 'sqlite' ? 'Nullini.db' : 'PostgreSQL') . '</code>'
                    . L("\n💾 آخرین بکاپ: ", "\n💾 Last backup: ") . (($at = Backup::lastPushAt()) ? Jalali::numericDate($at) . ' ' . Jalali::time($at) : (Backup::enabled() ? L('هنوز نه', 'not yet') : L('خاموش', 'off'))));
                return true;

            case 'ai':
                if (!App::isAdmin((int) $from['id'])) {
                    return false;
                }
                Rich::sendHtml($chatId, AiPanel::text(), (int) $msg['message_id'], AiPanel::keyboard());
                return true;

            case 'quota':
                if (!App::isAdmin((int) $from['id'])) {
                    return false;
                }
                $tg->reply($msg, AI::quotaReport());
                return true;

            case 'backup':
                if (!App::isAdmin((int) $from['id'])) {
                    return false;
                }
                $r = Backup::push('manual');
                $tg->reply($msg, str_starts_with($r, 'ok') ? L('✅ بکاپ تو گیت‌هاب ذخیره شد.', '✅ Backup saved to GitHub.') : L("❌ بکاپ نشد.\n<code>", "❌ Backup failed.\n<code>") . h($r) . '</code>');
                return true;

            case 'cookies':
                if (!$private || !App::isAdmin((int) $from['id'])) {
                    return false;
                }
                Cookies::command($msg);
                return true;

            case 'tools':
                if (!App::isAdmin((int) $from['id'])) {
                    return false;
                }
                Rich::sendHtml($chatId, Switches::text(), (int) $msg['message_id'], Switches::keyboard());
                return true;

            case 'ban':
            case 'unban':
            case 'banned':
                if (!App::isAdmin((int) $from['id'])) {
                    return false;
                }
                if ($cmd === 'banned') {
                    $ids = Ban::all();
                    $tg->reply($msg, $ids
                        ? L('⛔ <b>بن‌شده‌ها (', '⛔ <b>Banned (') . fa(count($ids)) . "):</b>\n" . implode("\n", array_map(static fn($i) => '• <code>' . $i . '</code>', array_slice($ids, 0, 50)))
                        : L('هیچ‌کس بن نیست 👌', 'Nobody is banned 👌'));
                    return true;
                }
                $r   = $msg['reply_to_message']['from'] ?? null;
                if (ctype_digit($args)) {
                    $tid = (int) $args;
                } elseif ($args !== '' && $args[0] === '@') {
                    $tid = Ban::byUsername($args);
                    if ($tid <= 0) {
                        $tg->reply($msg, L('این یوزرنیم پیدا نشد 😕', 'Username not found 😕') . "\n" . L('فقط کسایی که ربات رو استارت کرده باشن (یا تو گروه دیده شده باشن) پیدا می‌شن؛ با ریپلای یا آیدی عددی امتحان کن.', 'Only people who started the bot (or were seen in a group) can be found; try a reply or a numeric ID.'));
                        return true;
                    }
                } else {
                    $tid = ($r && empty($r['is_bot'])) ? (int) $r['id'] : 0;
                }
                if ($tid <= 0) {
                    $tg->reply($msg, L("روی پیام کاربر ریپلای کن و /{$cmd} بزن،\nیا آیدی عددی یا یوزرنیمش رو بنویس:", "Reply to the user's message and send /{$cmd},\nor write their numeric ID or username:") . "\n<code>/{$cmd} 123456</code>\n<code>/{$cmd} @username</code>");
                    return true;
                }
                $row = ($r && (int) $r['id'] === $tid) ? ['name' => userName($r), 'username' => $r['username'] ?? null] : Db::row('SELECT name, username FROM users WHERE id = ?', [$tid]);
                $who = userTag($tid, (string) ($row['name'] ?? ''), $row['username'] ?? null);
                if ($cmd === 'ban') {
                    $tg->reply($msg, Ban::add($tid, (int) $from['id']) ? L("کاربر {$who} از ربات نالین بن شد.", "User {$who} was banned from Nullin.") : L('🙅 ادمین رو نمی‌شه بن کرد.', '🙅 Admins can\'t be banned.'));
                } else {
                    $tg->reply($msg, Ban::remove($tid) ? L("کاربر {$who} از ربات نالین آنبن شد.", "User {$who} was unbanned from Nullin.") : L('این کاربر بن نبود.', 'This user wasn\'t banned.'));
                }
                return true;

            case 'broadcast':
                if (!$private || !App::isAdmin((int) $from['id'])) {
                    return false;
                }
                Broadcast::command($msg);
                return true;
        }
        return false;
    }

    public static function onMyChatMember(array $u): void
    {
        $new  = $u['new_chat_member']['status'] ?? '';
        $old  = $u['old_chat_member']['status'] ?? '';
        $type = $u['chat']['type'] ?? '';
        if (!in_array($type, ['group', 'supergroup'], true)) {
            return;
        }
        if (in_array($new, ['member', 'administrator'], true) && in_array($old, ['left', 'kicked'], true)) {
            $r = Rich::send($u['chat']['id'], Ui::groupText(), Ui::groupKeyboard());
            AI::markMsg($u['chat']['id'], $r['result']['message_id'] ?? 0);
        }
    }
}
