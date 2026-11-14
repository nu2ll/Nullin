<?php

final class Intent
{
    public const NAMES = ['translate', 'download', 'wiki', 'search', 'weather', 'rates', 'font', 'qr', 'short', 'fal', 'password', 'date', 'coin', 'calc', 'nid', 'love', 'roast', 'userinfo', 'giveaway', 'gif'];

    public static function prompt(): string
    {
        return 'You have access to Nullin’s tools. Work out what the user means from their whole message, not from individual words. If they really are asking for one of these things, then instead of answering, write a single line that sits entirely inside double square brackets: “[[<name> | <input>]]”, and write nothing else before or after it:'
            . "\n- translate | <target language in English, or auto> | <the text to translate; leave empty if they mean the message they replied to>: translation."
            . "\n- wiki | <topic>: an encyclopedia summary from Wikipedia."
            . "\n- weather | <city>: current weather or forecast."
            . "\n- rates: live prices for currencies, gold, coins or crypto."
            . "\n- font | <text>: write text in fancy fonts."
            . "\n- qr | <text or link>: make a QR code."
            . "\n- short | <link>: shorten a link."
            . "\n- fal: a Hafez fortune (fal)."
            . "\n- password: generate a strong password."
            . "\n- date | <optional city or country>: the current date or time (leave empty for the user's own time; give the place only if they asked about another city or country)."
            . "\n- coin: heads or tails."
            . "\n- giveaway: create a giveaway."
            . "\n- gif | <search query in English, one to four words>: send a GIF that matches what they want to see or express."
            . "\n- download | <full link; leave empty if the link is in the message they replied to>: download the video, photo or audio of a link."
            . "\n- calc | <math expression with + - * / ^ and parentheses>: numeric calculation."
            . "\n- nid | <national ID code>: check whether an Iranian national ID is valid."
            . "\n- love | <first person> | <second person>: love percentage for two people."
            . "\n- roast | <me, reply, @username or numeric id>: roast a person; me means the user themselves, reply means the author of the message they replied to. Only for a new roast; commenting on a previous roast is not a roast request."
            . "\n- userinfo: the user’s own Telegram account info."
            . "\nIf they only talked about these topics or asked a general question without asking you to actually do the task, answer yourself and don’t write a “[[ ]]” command.";
    }

    public static function parse(string $text): ?array
    {
        $text = trim(str_replace(['**', '`', '«', '»', '“', '”'], '', $text));
        if (!preg_match('/^\s*\[\[\s*(' . implode('|', self::NAMES) . ')\s*(?:[|｜:：]\s*(.*?))?\s*\]\]/isu', $text, $m)) {
            return null;
        }
        $name = strtolower($m[1]);
        $arg = (string) ($m[2] ?? '');
        if ($name !== 'translate') {
            $arg = (string) strtok($arg . "\n", "\n");
        }
        return [$name, mb_substr(mbTrim($arg, " \t\n\"'«»<>"), 0, $name === 'translate' ? 3000 : 300)];
    }

    public static function run(array $msg, string $name, string $arg): bool
    {
        $tg      = App::tg();
        $chat    = $msg['chat']['id'];
        $private = ($msg['chat']['type'] ?? '') === 'private';
        if (!Switches::on($name)) {
            return true;
        }
        Users::hit('intent:' . $name);

        if ($name === 'download') {
            $link = Downloader::match($arg) ?? Downloader::find($arg . ' ' . (string) ($msg['reply_to_message']['text'] ?? $msg['reply_to_message']['caption'] ?? ''));
            if ($link === null) {
                Downloader::dislike($msg);
                return true;
            }
            Downloader::offer($msg, $link);
            return true;
        }
        if ($name === 'giveaway') {
            $private ? Giveaway::start($msg) : Giveaway::fromGroup($msg);
            return true;
        }
        if ($name === 'gif') {
            return Gif::send($msg, $arg);
        }
        if (in_array($name, ['translate', 'rates', 'weather', 'wiki', 'search', 'love', 'roast'], true)) {
            $tg->action($chat, 'typing');
        }

        $o = self::output($msg, $name, $arg);
        if ($o === null) {
            return false;
        }
        if (!empty($o['rich'])) {
            $r = Rich::sendHtml($chat, $o['text'], (int) $msg['message_id']);
            return !empty($r['ok']);
        }
        if (!empty($o['md'])) {
            $r = Rich::sendMd($chat, $o['md'], $o['kb'] ?? null, (int) $msg['message_id']);
            return !empty($r['ok']);
        }
        if (!empty($o['live']) && $o['live']->finishHtml($o['text'])) {
            return true;
        }
        $reply = ['message_id' => $msg['message_id'], 'allow_sending_without_reply' => true];
        if (!empty($o['photo']) || !empty($o['anim'])) {
            $kb = $o['kb'] ?? null;
            if (!empty($o['photo']) && is_string($o['photo']) && preg_match('#^(?:https://|[A-Za-z0-9_-]{20,}$)#', $o['photo']) === 1 && trim((string) $o['text']) !== '' && (Rich::usable($kb) || !Rich::hasButtons($kb))) {
                $r = Rich::send($chat, $o['text'], $kb, (int) $msg['message_id'], $o['photo']);
                return !empty($r['ok']);
            }
            $r = $tg->call(!empty($o['photo']) ? 'sendPhoto' : 'sendAnimation', [
                'chat_id' => $chat, !empty($o['photo']) ? 'photo' : 'animation' => $o['photo'] ?? $o['anim'],
                'caption' => $o['text'], 'parse_mode' => 'HTML', 'reply_markup' => $kb, 'reply_parameters' => $reply,
            ], 30);
            return !empty($r['ok']);
        }
        if (!empty($o['retry']) && ($o['retry']['k'] ?? '') === 'tr' && $o['text'] === (Lang::is('en') ? AI::FAIL_TEXT_EN : AI::FAIL_TEXT)) {
            AnswerButtons::sendFail($msg, (Lang::is('en') ? AI::FAIL_TEXT_EN : AI::FAIL_TEXT), array_diff_key($o['retry'], ['a' => 1, 'f' => 1]) + ['o' => (int) $msg['message_id']]);
            return true;
        }
        $sent = Rich::replyTo($msg, $o['text'], $o['kb'] ?? null, $o['img'] ?? null, array_filter([
            'link_preview_options' => $o['preview'] ?? null,
        ]));
        if (!empty($o['retry']) && ($rmid = (int) ($sent['result']['message_id'] ?? 0)) > 0) {
            AnswerButtons::attach((int) $chat, $rmid, $o['retry'] + ['o' => (int) $msg['message_id']], false);
        }
        foreach ($o['more'] ?? [] as $part) {
            $tg->reply($msg, $part);
        }
        return true;
    }

    public static function output(array $msg, string $name, string $arg, bool $guest = false, ?string $imid = null): ?array
    {
        $bot   = App::botUsername();
        $reply = (string) ($msg['reply_to_message']['text'] ?? $msg['reply_to_message']['caption'] ?? '');

        switch ($name) {
            case 'translate':
                [$to, $text] = array_map('trim', explode('|', $arg, 2)) + [1 => ''];
                if ($text === '' && $reply === '' && !preg_match('/^[A-Za-z ]{0,20}$/', $to)) {
                    [$to, $text] = ['auto', $to];
                }
                $source = $text !== '' ? $text : trim($reply);
                if ($source === '') {
                    return ['text' => Ui::guide('translate')];
                }
                $target = preg_match('/^[A-Za-z ]{2,20}$/', $to) && strtolower($to) !== 'auto' ? ucfirst(strtolower($to)) : 'auto';
                $out    = AI::translate($source, $target);
                if ($out) {
                    return ['text' => L("🌐 <b>ترجمه:</b>\n\n", "🌐 <b>Translation:</b>\n\n") . h($out)];
                }
                return ['text' => (Lang::is('en') ? AI::FAIL_TEXT_EN : AI::FAIL_TEXT), 'kb' => AnswerButtons::retryKeyboard(), 'retry' => [
                    'k' => 'tr', 'u' => (int) ($msg['from']['id'] ?? 0), 'q' => $source, 't' => $target,
                    'a' => (Lang::is('en') ? AI::FAIL_TEXT_EN : AI::FAIL_TEXT), 'h' => L("🌐 <b>ترجمه:</b>\n\n", "🌐 <b>Translation:</b>\n\n"), 'f' => 1,
                ]];

            case 'wiki':
                if ($arg === '') {
                    return null;
                }
                $p = Wiki::first($arg);
                if ($p === null) {
                    return ['text' => L('📖 تو ویکی‌پدیا چیزی درباره‌ی «' . h(mb_substr($arg, 0, 80)) . '» پیدا نکردم.', '📖 I couldn’t find anything about “' . h(mb_substr($arg, 0, 80)) . '” on Wikipedia.')];
                }
                Users::hit('wiki');
                return ['text' => Wiki::text($p), 'kb' => Wiki::keyboard($p), 'preview' => ['url' => $p['url'], 'prefer_small_media' => true], 'img' => ($p['thumb'] ?? '') !== '' ? $p['thumb'] : null];

            case 'weather':
                $d = $arg !== '' ? Weather::fetch($arg) : null;
                if ($d === null) {
                    return ['text' => $arg === ''
                        ? L("🌦 اسم شهر رو هم بگو؛\nمثلاً «هوای تهران چطوره؟»", "🌦 Tell me the city too;\nfor example “what’s the weather in London?”")
                        : L('❌ جایی به اسم «' . h($arg) . '» پیدا نکردم.', '❌ I couldn’t find a place called “' . h($arg) . '”.')];
                }
                $hash = substr(md5(mb_strtolower($arg)), 0, 16);
                Store::set('weather_pending', $hash, $arg);
                return ['text' => Weather::format($d), 'md' => Weather::richFormat($d), 'kb' => Weather::keyboard($hash)];

            case 'rates':
                return ['text' => Currency::message(), 'md' => Currency::richMessage('fiat'), 'kb' => Currency::keyboard('fiat')];

            case 'font':
                return $arg === '' ? null : ['text' => Fonts::listText($arg)];

            case 'qr':
                return $arg === '' ? null : ['photo' => Tools::qrUrl($arg, 512), 'text' => '🔳 <code>' . h(mb_substr($arg, 0, 1000)) . '</code>'];

            case 'short':
                $url = ShortLink::extract($arg) ?? ShortLink::extract(trim($reply));
                if ($url === null) {
                    return null;
                }
                $short = ShortLink::shorten($url);
                if ($short === null) {
                    return ['text' => L('❌ الان نتونستم این لینک رو کوتاه کنم؛ چند لحظه‌ی دیگه دوباره امتحان کن.', '❌ I couldn’t shorten this link right now; please try again in a moment.')];
                }
                return ['text' => ShortLink::text($short, $url), 'kb' => ShortLink::keyboard($short)];

            case 'fal':
                return ['photo' => Tools::hafezPhoto(), 'text' => Tools::hafezCaption()];

            case 'password':
                return ['text' => Tools::passwordText(16)];

            case 'date':
                return ['text' => Tools::dateText($arg)];

            case 'coin':
                return ['text' => Tools::coinResult()];

            case 'calc':
                if ($arg === '' || !Calculator::looksLikeExpression($arg)) {
                    return null;
                }
                $res = Calculator::evaluate($arg);
                return ['text' => '🧮 <code>' . h(Calculator::normalize($arg)) . '</code>' . "\n= <b>" . h($res) . '</b>'];

            case 'nid':
                $code = Tools::normalizeNid($arg);
                return $code === null ? ['text' => L('🪪 یه کد ملی ۱۰ رقمی بفرست تا چکش کنم.', '🪪 Send a 10-digit national ID code and I’ll check it.')] : ['text' => Tools::nidText($code)];

            case 'search':
                if (mb_strlen($arg) < 2) {
                    return null;
                }
                $list  = Search::results($arg, 10);
                $lines = [];
                foreach ($list as $i => $r) {
                    $lines[] = fa((string) ($i + 1)) . '. <a href="' . h($r['url']) . '">' . h(mb_substr($r['title'], 0, 90)) . '</a>'
                        . ($r['snippet'] !== '' ? "\n<i>" . h(mb_substr($r['snippet'], 0, 160)) . '</i>' : '');
                }
                Users::hit('search');
                return ['text' => '🔍 <b>' . h(mb_substr($arg, 0, 120)) . "</b>\n\n" . ($lines ? implode("\n\n", $lines) : L('نتیجه‌ی آماده‌ای پیدا نکردم؛ از دکمه‌ها برو تو گوگل 👇', 'I didn’t find a ready result; use the buttons to search on Google 👇')), 'kb' => Search::keyboard($arg)];

            case 'love':
                [$a, $b] = array_map('trim', explode('|', $arg, 2)) + [1 => ''];
                if ($a === '' || $b === '') {
                    return ['text' => L("💘 اسم دو نفر رو بگو؛\nمثلاً «درصد عشق علی و سارا چنده؟»", "💘 Give me two names;\nfor example “what’s the love percentage for Ali and Sara?”")];
                }
                $ra = UserInfo::resolve($a);
                $rb = UserInfo::resolve($b);
                $r  = Love::build(mb_substr($ra['name'], 0, Love::NAME_MAX), mb_substr($rb['name'], 0, Love::NAME_MAX), $ra, $rb);
                return ['text' => $r['text'], 'kb' => $r['keyboard']];

            case 'roast':
                $t = Roast::target($msg, $arg);
                if (!empty($t['skip'])) {
                    return null;
                }
                if ($t !== null && Roast::busy((int) ($msg['from']['id'] ?? 0))) {
                    return ['text' => L("یه‌کم دیگه بیا 😅\n⏳ هر دقیقه فقط یه روست!", "Come back in a bit 😅\n⏳ Only one roast per minute!"), 'rich' => true];
                }
                if ($t === null) {
                    return ['text' => L("🤷 این آدم رو پیدا نکردم؛\nآیدی عددی یا یوزرنیمش رو درست بنویس.", "🤷 I couldn’t find this person;\nwrite their numeric id or username correctly.")];
                }
                $t['request'] = trim((string) ($msg['text'] ?? $msg['caption'] ?? ''));
                $live = App::cfg('ai_stream', true) ? new LiveReply($msg, $imid, Roast::head($t), false) : null;
                return ['text' => Roast::text($t, $live), 'live' => $live];

            case 'userinfo':
                [$text, $kb] = UserInfo::card($msg['from'], $guest ? null : ($msg['chat']['type'] ?? null));
                return ['text' => $text, 'kb' => $kb];

            case 'gif':
                return Gif::output($arg, (int) ($msg['from']['id'] ?? 0));

            case 'giveaway':
                return $guest ? ['text' => L("🎁 قرعه‌کشی رو تو پیوی من بساز 👇", "🎁 Create the giveaway in my private chat 👇"), 'kb' => ikb([[urlBtn(L('🎁 ساخت قرعه‌کشی', '🎁 Create giveaway'), "https://t.me/{$bot}?start=giveaway", 'success')]])] : null;
        }
        return null;
    }
}
