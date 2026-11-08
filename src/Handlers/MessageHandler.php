<?php

final class MessageHandler
{
    private const TRIGGER = '(?:نالینی|نالین|nullini|nullin)';

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

        if ($text !== '' && $text[0] === '/' && self::command($msg, $text, $private)) {
            return;
        }

        if ($private && Cookies::input($msg)) {
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
            return;
        }

        $startsWithName = preg_match('/^' . self::TRIGGER . '(?:[\s،,:!؟?.]|$)/iu', $text) === 1;
        $mentioned      = self::mentionsBot($text);
        $link = null;
        if ($text !== '' && empty($msg['is_automatic_forward']) && ($private || Downloader::groupsEnabled() || $mentioned || $startsWithName)) {
            $bare = preg_replace('/@' . preg_quote(App::botUsername(), '/') . '\b/iu', '', $text) ?? $text;
            $bare = preg_replace('/^' . self::TRIGGER . '[\s،,:]*/iu', '', mbTrim($bare)) ?? $bare;
            $link = Downloader::match($bare);
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
                Rich::sendHtml($msg['chat']['id'], Membership::text(), null, Membership::keyboard($missing));
                return;
            }
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
        Users::hit("cmd:{$cmd}");

        $gate = ['wk' => 'wiki', 'wiki' => 'wiki', 'giveaway' => 'giveaway', 'gw' => 'giveaway', 'image' => 'image', 'img' => 'image', 'font' => 'font', 'ft' => 'font', 'tr' => 'translate', 'translate' => 'translate', 'st' => 'linkcheck', 'monshi' => 'business'][$cmd] ?? null;
        if ($gate !== null && !Switches::guard($gate)) {
            return true;
        }

        switch ($cmd) {
            case 'forget':
                $count = count(UserMemory::all((int) $from['id']));
                if ($count === 0) {
                    $tg->reply($msg, 'هنوز چیزی ازت تو حافظه‌م نیست 🤷');
                    return true;
                }
                Rich::replyTo($msg, "🧹 <b>پاک کردن حافظه</b>\n\n"
                    . "الان <b>" . fa((string) $count) . " مورد</b> درباره‌ات یادمه؛\n"
                    . "اگه پاکشون کنم،\n"
                    . "از اول باهم آشنا می‌شیم.\n\n"
                    . "⚠️ <i>این کار برگشت نداره.</i>\n"
                    . "مطمئنی؟", ikb([[
                    btn('🗑 آره، پاک کن', 'mem:' . (int) $from['id'] . ':y', 'danger'),
                    btn('↩️ نه، بمونه', 'mem:' . (int) $from['id'] . ':n', 'success'),
                ]]));
                return true;
            case 'start':
                if ($private) {
                    $missing = Membership::missing((int) $from['id']);
                    if ($missing) {
                        Rich::sendHtml($chatId, Membership::text(), null, Membership::keyboard($missing));
                        return true;
                    }
                }
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
                $tg->reply($msg, "📊 <b>آمار نالین</b>\n\n"
                    . '👥 کل کاربرها: ' . $n($u['total'] ?? 0) . "\n"
                    . '🆕 جدید امروز: ' . $n($u['new_today'] ?? 0) . "\n"
                    . '🔥 فعال ۲۴ ساعت: ' . $n($u['day'] ?? 0) . ' · ۷ روز: ' . $n($u['week'] ?? 0) . "\n"
                    . '📬 قابل پیام: ' . $n($u['reachable'] ?? 0) . ' · 💎 پریمیوم: ' . $n($u['premium'] ?? 0) . "\n"
                    . '🎮 بازی‌های ذخیره‌شده: ' . $n(Store::count('games'))
                    . ($top !== '' ? "\n\n<b>⭐ پرکاربردهای امروز:</b>{$top}" : '')
                    . "\n\n🗄 دیتابیس: <code>" . (Db::driver() === 'sqlite' ? 'Nullini.db' : 'PostgreSQL') . '</code>'
                    . "\n💾 آخرین بکاپ: " . (($at = Backup::lastPushAt()) ? Jalali::numericDate($at) . ' ' . Jalali::time($at) : (Backup::enabled() ? 'هنوز نه' : 'خاموش')));
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
                $tg->reply($msg, str_starts_with($r, 'ok') ? '✅ بکاپ تو گیت‌هاب ذخیره شد.' : "❌ بکاپ نشد.\n<code>" . h($r) . '</code>');
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
                        ? '⛔ <b>بن‌شده‌ها (' . fa(count($ids)) . "):</b>\n" . implode("\n", array_map(static fn($i) => '• <code>' . $i . '</code>', array_slice($ids, 0, 50)))
                        : 'هیچ‌کس بن نیست 👌');
                    return true;
                }
                $r   = $msg['reply_to_message']['from'] ?? null;
                if (ctype_digit($args)) {
                    $tid = (int) $args;
                } elseif ($args !== '' && $args[0] === '@') {
                    $tid = Ban::byUsername($args);
                    if ($tid <= 0) {
                        $tg->reply($msg, 'این یوزرنیم پیدا نشد 😕' . "\n" . 'فقط کسایی که ربات رو استارت کرده باشن (یا تو گروه دیده شده باشن) پیدا می‌شن؛ با ریپلای یا آیدی عددی امتحان کن.');
                        return true;
                    }
                } else {
                    $tid = ($r && empty($r['is_bot'])) ? (int) $r['id'] : 0;
                }
                if ($tid <= 0) {
                    $tg->reply($msg, "روی پیام کاربر ریپلای کن و /{$cmd} بزن،\nیا آیدی عددی یا یوزرنیمش رو بنویس:\n<code>/{$cmd} 123456</code>\n<code>/{$cmd} @username</code>");
                    return true;
                }
                $row = ($r && (int) $r['id'] === $tid) ? ['name' => userName($r), 'username' => $r['username'] ?? null] : Db::row('SELECT name, username FROM users WHERE id = ?', [$tid]);
                $who = userTag($tid, (string) ($row['name'] ?? ''), $row['username'] ?? null);
                if ($cmd === 'ban') {
                    $tg->reply($msg, Ban::add($tid, (int) $from['id']) ? "کاربر {$who} از ربات نالین بن شد." : '🙅 ادمین رو نمی‌شه بن کرد.');
                } else {
                    $tg->reply($msg, Ban::remove($tid) ? "کاربر {$who} از ربات نالین آنبن شد." : 'این کاربر بن نبود.');
                }
                return true;

            case 'broadcast':
                if (!App::isAdmin((int) $from['id'])) {
                    return false;
                }
                $src = $msg['reply_to_message'] ?? null;
                if (!$src) {
                    $tg->reply($msg, "روی پیامی که می‌خوای برای همه بره ریپلای کن\nو /broadcast بزن.");
                    return true;
                }
                $st   = $tg->reply($msg, '📣 ارسال شروع شد...');
                $stId = (int) ($st['result']['message_id'] ?? 0);
                $ok   = 0;
                $fail = 0;
                Users::eachReachable(static function (int $id) use ($tg, $chatId, $src, &$ok, &$fail) {
                    $r = $tg->call('copyMessage', ['chat_id' => $id, 'from_chat_id' => $chatId, 'message_id' => $src['message_id']], 10);
                    if (!empty($r['ok'])) {
                        $ok++;
                    } else {
                        $fail++;
                        if ((int) ($r['error_code'] ?? 0) === 403) {
                            Users::markBlocked($id);
                        }
                    }
                    usleep(40000);
                });
                $done = '✅ تموم شد. موفق: ' . fa($ok) . ' · ناموفق: ' . fa($fail);
                $stId ? Rich::editMsg($chatId, $stId, $done) : $tg->reply($msg, $done);
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
