<?php

final class Ui
{
    private const ICON_BASE = 'https://raw.githubusercontent.com/microsoft/fluentui-emoji/main/assets/';

    private const ICONS = [
        'info'     => ['Bust in silhouette', 'bust_in_silhouette'],
        'calc'     => ['Abacus', 'abacus'],
        'date'     => ['Calendar', 'calendar'],
        'rates'    => ['Currency exchange', 'currency_exchange'],
        'pass'     => ['Key', 'key'],
        'coin'     => ['Coin', 'coin'],
        'fal'      => ['Crystal ball', 'crystal_ball'],
        'rps'      => ['Rock', 'rock'],
        'ttt'      => ['Cross mark', 'cross_mark'],
        'c4'       => ['Red circle', 'red_circle'],
        'math'     => ['Input numbers', 'input_numbers'],
        'chess'    => ['Chess pawn', 'chess_pawn'],
        'whisper'  => ['Shushing face', 'shushing_face'],
        'chat'     => ['Speech balloon', 'speech_balloon'],
        'weather'  => ['Sun behind rain cloud', 'sun_behind_rain_cloud'],
        'nid'      => ['Identification card', 'identification_card'],
        'funstat'  => ['Magnifying glass tilted left', 'magnifying_glass_tilted_left'],
        'ai'       => ['Brain', 'brain'],
        'love'     => ['Heart with arrow', 'heart_with_arrow'],
        'roast'    => ['Fire', 'fire'],
        'translate' => ['Globe with meridians', 'globe_with_meridians'],
        'qr'       => ['Framed picture', 'framed_picture'],
        'image'    => ['Artist palette', 'artist_palette'],
        'dice'     => ['Game die', 'game_die'],
        'warn'     => ['Warning', 'warning'],
        'fail'     => ['Cross mark', 'cross_mark'],
        'ok'       => ['Check mark button', 'check_mark_button'],
        'stop'     => ['Stop sign', 'stop_sign'],
        'lock'     => ['Locked with key', 'locked_with_key'],
        'robot'    => ['Robot', 'robot'],
        'search'   => ['Magnifying glass tilted right', 'magnifying_glass_tilted_right'],
        'font'     => ['Input latin letters', 'input_latin_letters'],
        'download' => ['Inbox tray', 'inbox_tray'],
        'gift'     => ['Wrapped gift', 'wrapped_gift'],
        'wiki'     => ['Books', 'books'],
        'group'    => ['Busts in silhouette', 'busts_in_silhouette'],
        'linkcheck' => ['Shield', 'shield'],
        'short'    => ['Link', 'link'],
        'like'     => ['Red heart', 'red_heart'],
        'unit'     => ['Straight ruler', 'straight_ruler'],
    ];

    public static function icon(string $key): string
    {
        [$dir, $file] = self::ICONS[$key] ?? self::ICONS['robot'];
        return self::ICON_BASE . rawurlencode($dir) . '/3D/' . $file . '_3d.png';
    }

    public static function article(string $id, string $icon, string $title, string $desc, string $text, ?array $markup = null, bool $html = true, ?string $img = null): array
    {
        $clean = preg_replace('/^[\x{1F000}-\x{1FAFF}\x{2190}-\x{21FF}\x{2300}-\x{23FF}\x{2600}-\x{27BF}\x{2B00}-\x{2BFF}\x{FE0F}\x{200D}\s]+/u', '', $title);
        $r = [
            'type'                  => 'article',
            'id'                    => mb_substr($id, 0, 64),
            'title'                 => ($clean !== null && $clean !== '') ? $clean : $title,
            'description'           => $desc,
            'thumbnail_url'         => str_starts_with($icon, 'http') ? $icon : self::icon($icon),
            'thumbnail_width'       => 256,
            'thumbnail_height'      => 256,
            'input_message_content' => [
                'message_text'         => $text,
                'parse_mode'           => $html ? 'HTML' : null,
                'link_preview_options' => ['is_disabled' => true],
            ],
        ];
        if (!$html) {
            unset($r['input_message_content']['parse_mode']);
        }
        if (Rich::usable($markup)) {
            $keep = preg_match('/^(?:(?:fal|unit|calc|coin|pass|chess|ttt|c4|rps|math)$|(?:ai|tr)q_|rs_|st_|lv_|gwi_|wk_|pc_)/', $id) === 1;
            $r['input_message_content'] = Rich::inlineContent($html ? $text : h($text), $markup, $img);
            if ($keep) {
                $r['reply_markup'] = ikb([[btn('Nullin', 'noop', null)]]);
            }
        } elseif ($markup !== null) {
            $r['reply_markup'] = $markup;
        }
        return $r;
    }

    public static function startText(array $from): string
    {
        $bot = App::botUsername();
        return L("سلام ", "Hi ") . safeName($from) . " 👋\n"
            . L("من <b>نالین</b> ام؛\n", "I'm <b>Nullin</b>;\n")
            . L("یه ربات اینلاین و همه‌کاره با هوش مصنوعی 👾\n\n", "an inline, do-it-all bot powered by AI 👾\n\n")
            . L("✨ تو <b>هر چتی</b> بنویس\n", "✨ In <b>any chat</b> just type\n")
            . "<code>@{$bot}</code>\n"
            . L("و از منو انتخاب کن،\n", "and pick from the menu,\n")
            . L("یا همین‌جا مستقیم باهام حرف بزن؛\n\n", "or talk to me right here;\n\n")
            . L("🚀 دکمه‌ی پایین رو بزن و ببین چه کارایی ازم برمیاد!", "🚀 Tap the button below and see what I can do!");
    }

    public static function sendMain(int|string $chatId, array $from): void
    {
        $r   = Rich::sendHtml($chatId, self::startText($from), null, self::startKeyboard());
        $mid = (int) ($r['result']['message_id'] ?? 0);
        if ($mid > 0) {
            Store::set('startmenu', (string) $chatId, $mid);
        }
    }

    public static function show(int|string $chatId, int $mid, string $text, ?array $kb = null): void
    {
        Rich::editHtml($chatId, $mid, $text, $kb);
    }

    public static function groupText(): string
    {
        return L("سلام به همگی 👋 من <b>نالین</b>‌ام!\n\n", "Hi everyone 👋 I'm <b>Nullin</b>!\n\n")
            . L("🧠 هر وقت سؤالی داشتین\n", "🧠 Whenever you have a question\n")
            . L("پیامتون رو با «نالین» شروع کنین.\n\n", "start your message with \"Nullin\".\n\n")
            . L("🎮 بازی و ابزارها هم\n", "🎮 Games and tools are\n")
            . L("با نوشتن <code>@", "available by typing <code>@") . App::botUsername() . L("</code> در دسترسه.\n\n", "</code>.\n\n")
            . L("<i>💡 اگه جواب ندادم، منو ادمین گروه کنین\n", "<i>💡 If I don't answer, make me a group admin\n")
            . L("تا پیام‌هاتون به دستم برسه.</i>", "so I can receive your messages.</i>");
    }

    public static function hello(): string
    {
        $lines = [
            L('جانم؟ 😄 بگو چی کار داری', 'Yes? 😄 What do you need'),
            L('بله؟ اینجام 👋 سؤالت رو بپرس', 'Yep? I\'m here 👋 Ask away'),
            L('در خدمتم 😎 چی لازم داری؟', 'At your service 😎 What do you need?'),
            L('صدام کردی؟ 👀 بگو ببینم', 'Did you call me? 👀 Tell me'),
            L('حاضرم! 🫡 امرتون؟', 'Ready! 🫡 What\'s the order?'),
            L('هوم؟ 🤔 گوشم با توئه', 'Hmm? 🤔 I\'m all ears'),
            L('سلام سلام! 🙌 چه کمکی از دستم برمیاد؟', 'Hey hey! 🙌 How can I help?'),
            L('نالین گزارش می‌ده: آماده‌ام 🚀', 'Nullin reporting: ready 🚀'),
            L('جون دلم؟ 😁 بپرس', 'Yes dear? 😁 Ask'),
            L('بگو، سراپا گوشم 🎧', 'Go on, I\'m all ears 🎧'),
            L('اومدم! ⚡️ چی شده؟', 'I\'m here! ⚡️ What\'s up?'),
            L('یه سؤال بپرس، یه جواب تحویل بگیر 😉', 'Ask a question, get an answer 😉'),
            L('آماده‌ی خدمتم 🫡 بفرمایید', 'Ready to serve 🫡 Go ahead'),
            L('بله بله؟ 😃 چی شده؟', 'Yes yes? 😃 What\'s up?'),
            L('من اینجام، تو بگو 🙌', 'I\'m here, you talk 🙌'),
            L('جانم عزیز؟ 😍 چی می‌خوای؟', 'Yes friend? 😍 What do you want?'),
            L('هوی! 👋 صدام کردی؟', 'Hey! 👋 Did you call me?'),
            L('بفرما، منتظرم ⏳', 'Go ahead, I\'m waiting ⏳'),
            L('سلام! 😄 بگو ببینم چه خبره', 'Hi! 😄 Tell me what\'s going on'),
            L('نالین در دسترسه ✅ بپرس', 'Nullin is online ✅ Ask away'),
        ];
        return $lines[array_rand($lines)] . L("\n<i>یا از منو یکی رو انتخاب کن 👇</i>", "\n<i>or pick one from the menu 👇</i>");
    }

    private const PING_LINES = [
        'پونگ! 🏓 زنده‌م، نگران نباش',
        'اینجام 😎 هنوز نمردم',
        'بیدارم! 👀 چی شده؟',
        'پونگ 🏓 سریع‌تر از چیزی که فکر می‌کنی',
        'آنلاینم 🟢 دستور بده قهرمان',
        'صدات رو شنیدم 🫡 سر پستم',
        'نالین حاضر! ✋',
        'پینگ زدی؟ 😏 پونگ تحویل بگیر',
        'چرا هی چک می‌کنی؟ 😂 زنده‌م بابا',
        'نه خوابم، نه رفتم 😅 اینجام',
        'سرور سالمه 💪 دل‌نگران نباش',
        'دارم چای می‌خورم ☕ ولی جواب می‌دم، پونگ!',
        'پونگ! 🏓 توپ برگشت تو زمین تو',
        'همیشه آماده‌م 🔥 فقط بگو چی می‌خوای',
        'بفرمایید 🙌 نالین پای کاره',
        'هنوز اینجام 🥹 ولم نکردی که!',
        'یه پینگ تازه، یه پونگ تازه 🏓✨',
        'نترس 🛡 قطع نشدم',
        'ای جان 😍 یه پینگ دیگه؟ پونگ!',
        'سلام سلام 👋 زنده و سرحال',
    ];
    const PING_LINES_EN = [
        'Pong! 🏓 I\'m alive, don\'t worry',
        'I\'m here 😎 not dead yet',
        'I\'m awake! 👀 What\'s up?',
        'Pong 🏓 faster than you think',
        'Online 🟢 give the order, champ',
        'Heard you 🫡 at my post',
        'Nullin present! ✋',
        'You pinged? 😏 here\'s your pong',
        'Why do you keep checking? 😂 I\'m alive, man',
        'Not asleep, not gone 😅 I\'m here',
        'Server is healthy 💪 don\'t worry',
        'Having some tea ☕ but I\'m answering, pong!',
        'Pong! 🏓 the ball is back in your court',
        'Always ready 🔥 just say what you want',
        'Here you go 🙌 Nullin is on the job',
        'Still here 🥹 you haven\'t left me!',
        'A fresh ping, a fresh pong 🏓✨',
        'Fear not 🛡 I haven\'t dropped',
        'Aww 😍 another ping? Pong!',
        'Hello hello 👋 alive and kicking',
    ];

    private const DICE_LINES = [
        '🎲' => [
            'one' => [
                'یه تک اومد 😂 بدشانسی محضه',
                'ای وای ۱! 🥲 تاس باهات قهره',
                'تک؟ 😬 فکر کنم تاس امروز خوابه',
                'نمی‌خوای بدونی چی اومد... یه ۱ 😅',
                'یک اومد 💀 دفعه‌ی بعد جبران می‌کنی',
            ],
            'low' => [
                'عدد کم اومد 😕 ولی ناامید نشو',
                'تاس امروز دست و دل‌باز نیست 😅',
                'نه بد، نه خوب 🫤 یه بار دیگه؟',
                'عدد کمیه 🤏 دوباره بنداز',
                'شانس امروز یه کم کمه 😬 ولی ادامه بده',
            ],
            'high' => [
                'عدد خوبیه 😎 داری شانس میاری',
                'بد نیست! 👌 یه ذره مونده به ۶',
                'نزدیک شش بود 😏 ولی همین هم خوبه',
                'عدد بالایی اومد 🔥 دمت گرم',
                'تاس بهت لبخند زد 😁 عدد خوبیه',
            ],
            'six' => [
                'شش اومد! 🔥 به‌به شانس',
                '۶ شد 😎 تاس‌باز حرفه‌ای رسید',
                'جک‌پات! شش آورد 🎉',
                'تاس بهت ارادت داره 🙌 شش اومد',
                '۶ شد 😏 دلم گواهی خوب می‌داد',
            ],
        ],
        '🎯' => [
            'miss' => [
                'خطا رفت 😂 اصلاً به تخته نخورد',
                'تیر پرید تو هوا 🙈 هدف سالم موند',
                'نخورد به تخته 😅 دیوار شاکی شد',
                'دارتت گم شد 😬 چشمات رو باز کن',
                'هدف اون‌ور بود قهرمان 😅',
                'یه خطای تمیز 💀 دفعه‌ی بعد دقیق‌تر',
            ],
            'hit' => [
                'خورد به تخته 👌 ولی وسط نیست',
                'نزدیک وسط نشست 😏 یه کم دیگه',
                'بد نزدی، دارت نشست 🎯',
                'به هدف خورد ولی نه وسطش 🤏',
                'دستت درست کار می‌کنه 😎 یه ذره مونده تا وسط',
                'یه دارت معمولی؛ نه خطا، نه عالی 😌',
                'تو حلقه‌ها نشست 🎯 بد نبود',
                'نشست رو تخته 👍 ولی وسط رو خالی گذاشتی',
            ],
            'top' => [
                'وسط هدف! 🎯 دمت گرم',
                'دقیقاً وسط نشست 🔥 تیرانداز حرفه‌ای',
                'شش! 🎯 همون وسطِ وسط',
                'جون! 😍 دقیق زدی به هدف',
                'تو ارباب دارتی؟ 😎 وسط نشست',
                'هدف‌گیری عالی! 🏆 اینو بگیر',
            ],
        ],
        '🏀' => [
            'miss' => [
                'توپ نرفت تو حلقه 😅 خورد به لبه',
                'خورد به حلقه و برگشت 😬',
                'پرتابت کم‌دقت بود 🙈 نرفت تو',
                'حلقه جا خالی داد؛ نرفت 😂',
                'ای بابا! 🏀 نشد',
                'توپ دور حلقه چرخید و بیرون افتاد 😮‍💨',
                'یه پرتاب خیلی نزدیک بود... ولی نشد 🫠',
                'امتیاز نداشت 😕 دفعه‌ی بعد',
                'حلقه امروز نمی‌خواد توپ بگیره 🙅',
                'نخورد به سبد 😅 یه بار دیگه بزن',
            ],
            'win' => [
                'رفت تو! 🏀 سه‌امتیازی',
                'سوییش تمیز 🔥 رفت تو حلقه',
                'امتیاز! 😎 توپ نشست تو سبد',
                'پرتاب عالی بود 👏 رفت تو',
                'اینم از امتیاز 🏆 دمت گرم',
                'توپ بی‌رحمانه رفت تو حلقه 😏',
                'شوت حرفه‌ای! 🔥 امتیاز مال توئه',
                'مستقیم تو سبد نشست 🙌',
                'ستاره‌ی NBA رسید 😎 رفت تو',
                'حلقه رو ترکوندی 💥 امتیاز',
            ],
        ],
        '⚽' => [
            'miss' => [
                'دروازه‌بان گرفت 🧤 گل نشد',
                'توپ رفت بیرون 😬 اوه اوه',
                'خورد به تیرک و برگشت 😭',
                'گل نشد 😂 دروازه‌بان خندید',
                'شوتت بالای دروازه رفت 🙈',
                'پنالتی از دست رفت 😮‍💨',
                'توپ کج رفت ⚽ ببخشید ولی گل نشد',
                'دروازه‌بان آماده بود 🧤 نشد',
            ],
            'win' => [
                'گل! ⚽ دروازه ترکید',
                'گل شد 🔥 دمت گرم',
                'گوشه‌ی دروازه نشست 😎 گل',
                'دروازه‌بان رو گیج کردی 😏 گل شد',
                'تماشاگرا از جا پریدن 🙌 گل',
                'یه گل تمیز و بی‌رحم 🥅',
                'رفت تو تور! ⚽ مبارکه',
                'پنالتی‌زن حرفه‌ای رسید 🏆 گل شد',
                'گل گل گل! 🎉',
                'دروازه‌بان فقط نگاه کرد 😂 گل شد',
                'مستقیم رفت تو دروازه ⚽🔥',
                'رونالدو هم بهت غبطه می‌خوره 😎 گل',
            ],
        ],
        '🎳' => [
            'miss' => [
                'توپ افتاد تو ناودونی 😂 هیچی نیفتاد',
                'ای وای ناودونی! 😭 هیچ پینی نیفتاد',
                'توپ راه رو گم کرد 🙈 صفر پین',
                'خطا شد 💀 پین‌ها راحت خوابیدن',
                'توپ از کنار پین‌ها رد شد 😅',
                'یه صفر تمیز 😬',
            ],
            'hit' => [
                'چندتا پین افتاد 🎳 ولی همه نه',
                'نزدیک بود استرایک بشه 😏 چندتا مونده',
                'بد نیست 👌 چندتا پین ایستاده موند',
                'نصف پین‌ها نفله شدن 😄 بقیه جون سالم به در بردن',
                'توپ خوب رفت ولی کامل نبود 🎳',
                'یه ضربه‌ی معمولی؛ نه ناودونی، نه استرایک 😌',
                'پین‌ها لرزیدن ولی همه نیفتادن 😬',
                'نصفه‌نیمه زدی 🤏 دوباره امتحان کن',
            ],
            'top' => [
                'استرایک! 🎳 همه‌ی پین‌ها افتادن',
                'همه رو خوابوندی 😎 استرایک',
                'پین‌ها قتل‌عام شدن 💥 استرایک!',
                'یه استرایک تمیز 🔥 دمت گرم',
                'بولینگ‌باز حرفه‌ای رسید 🏆 همه افتادن',
                'یه ضربه، ده پین 😏 استرایک',
            ],
        ],
        '🎰' => [
            'jackpot' => [
                'جک‌پات! 🎰 سه تا ۷ اومد',
                '۷ ۷ ۷ 😱 ترکوندی',
                'سه تا هفت؟! 🤑 برو لاتاری بخر',
                'دستگاه رو بردی 🎉 جک‌پات',
                'باورم نمی‌شه 🙌 سه تا هفت اومد',
            ],
            'triple' => [
                'سه تا مثل هم اومد 🎉 بردی',
                'قرقره‌ها هماهنگ شدن 😎 برنده‌ای',
                'سه تا یکی! 🍀 شانس بهت رو کرد',
                'برد خوردی 🔥 دمت گرم',
                'سه تا یکسان؛ اسلات تسلیم شد 🏆',
            ],
            'pair' => [
                'دوتا مثل هم اومد 😏 یه ذره مونده',
                'نزدیک بود جک‌پات بشه 😭 دو تا یکی',
                'یه قرقره خراب کرد 😅 دو تا مثل هم',
                'خیلی نزدیک بود 🤏 دوباره بچرخون',
                'دو تا اومد، سومی نیومد 😬',
            ],
            'lose' => [
                'هیچ‌کدوم مثل هم نبودن 😂 باختی',
                'اسلات امروز پولت رو می‌خوره 😅 نبردی',
                'قرقره‌ها هر کدوم یه طرف 🙈 باخت',
                'بدشانسی 💀 هیچی نیومد',
                'نشد که نشد 🎰 دوباره بچرخون',
            ],
        ],
    ];
    const DICE_LINES_EN = [
        '🎲' => [
            'one' => [
                'Rolled a one 😂 pure bad luck',
                'Oh no, a 1! 🥲 the die is mad at you',
                'One? 😬 I think the die is asleep today',
                'You don\'t want to know what came up... a 1 😅',
                'A one 💀 you\'ll make up for it next time',
            ],
            'low' => [
                'Low number 😕 but don\'t give up',
                'The die isn\'t generous today 😅',
                'Not bad, not good 🫤 one more try?',
                'Low-ish 🤏 roll again',
                'Luck is a bit low today 😬 but keep going',
            ],
            'high' => [
                'Nice number 😎 you\'re getting lucky',
                'Not bad! 👌 almost a 6',
                'Almost six 😏 but that\'s still good',
                'High number 🔥 nice one',
                'The die smiled at you 😁 good number',
            ],
            'six' => [
                'Six! 🔥 wow, lucky you',
                'A 6 😎 a pro dice roller has arrived',
                'Jackpot! Rolled a six 🎉',
                'The die respects you 🙌 a six',
                'A 6 😏 I had a feeling',
            ],
        ],
        '🎯' => [
            'miss' => [
                'Miss 😂 didn\'t even touch the board',
                'The dart flew into the air 🙈 the target is safe',
                'Missed the board 😅 the wall is angry',
                'Your dart got lost 😬 open your eyes',
                'The target was over there, champ 😅',
                'A clean miss 💀 be more precise next time',
            ],
            'hit' => [
                'Hit the board 👌 but not the center',
                'Landed near the center 😏 a bit more',
                'Not bad, the dart landed 🎯',
                'Hit the target but not the middle 🤏',
                'Your hand works fine 😎 just a little short of the center',
                'An ordinary dart; not a miss, not great 😌',
                'Landed in the rings 🎯 not bad',
                'Landed on the board 👍 but missed the center',
            ],
            'top' => [
                'Bullseye! 🎯 nice one',
                'Right in the center 🔥 pro archer',
                'Six! 🎯 dead center',
                'Wow! 😍 you hit the target exactly',
                'Are you the dart master? 😎 bullseye',
                'Great aim! 🏆 take this',
            ],
        ],
        '🏀' => [
            'miss' => [
                'The ball missed the hoop 😅 hit the rim',
                'Hit the rim and bounced back 😬',
                'Your throw was sloppy 🙈 didn\'t go in',
                'The hoop dodged it; no basket 😂',
                'Oh man! 🏀 no luck',
                'The ball circled the rim and fell out 😮‍💨',
                'That throw was so close... but no 🫠',
                'No points 😕 next time',
                'The hoop doesn\'t want the ball today 🙅',
                'Missed the basket 😅 try once more',
            ],
            'win' => [
                'It\'s in! 🏀 three-pointer',
                'Clean swish 🔥 it went in',
                'Score! 😎 the ball landed in the basket',
                'Great shot 👏 it went in',
                'There\'s your point 🏆 nice one',
                'The ball went in ruthlessly 😏',
                'Pro shot! 🔥 the point is yours',
                'Straight into the basket 🙌',
                'An NBA star has arrived 😎 it\'s in',
                'You smashed the hoop 💥 score',
            ],
        ],
        '⚽' => [
            'miss' => [
                'The goalkeeper caught it 🧤 no goal',
                'The ball went out 😬 oh oh',
                'Hit the post and bounced back 😭',
                'No goal 😂 the goalkeeper laughed',
                'Your shot went over the bar 🙈',
                'Penalty missed 😮‍💨',
                'The ball went wide ⚽ sorry, no goal',
                'The goalkeeper was ready 🧤 no luck',
            ],
            'win' => [
                'Goal! ⚽ the net exploded',
                'It\'s a goal 🔥 nice one',
                'Landed in the corner 😎 goal',
                'You confused the goalkeeper 😏 goal',
                'The crowd jumped up 🙌 goal',
                'A clean, ruthless goal 🥅',
                'Into the net! ⚽ congrats',
                'A pro penalty taker has arrived 🏆 goal',
                'Goal goal goal! 🎉',
                'The goalkeeper just watched 😂 goal',
                'Straight into the goal ⚽🔥',
                'Even Ronaldo envies you 😎 goal',
            ],
        ],
        '🎳' => [
            'miss' => [
                'The ball went into the gutter 😂 nothing fell',
                'Oh no, gutter! 😭 no pins fell',
                'The ball lost its way 🙈 zero pins',
                'Miss 💀 the pins slept peacefully',
                'The ball passed by the pins 😅',
                'A clean zero 😬',
            ],
            'hit' => [
                'A few pins fell 🎳 but not all',
                'Almost a strike 😏 a few left',
                'Not bad 👌 a few pins still standing',
                'Half the pins are gone 😄 the rest survived',
                'Good roll but not complete 🎳',
                'An ordinary roll; no gutter, no strike 😌',
                'The pins wobbled but didn\'t all fall 😬',
                'Half-hearted hit 🤏 try again',
            ],
            'top' => [
                'Strike! 🎳 all the pins fell',
                'You knocked them all down 😎 strike',
                'The pins got massacred 💥 strike!',
                'A clean strike 🔥 nice one',
                'A pro bowler has arrived 🏆 all down',
                'One roll, ten pins 😏 strike',
            ],
        ],
        '🎰' => [
            'jackpot' => [
                'Jackpot! 🎰 three 7s',
                '7 7 7 😱 you smashed it',
                'Three sevens?! 🤑 go buy a lottery ticket',
                'You beat the machine 🎉 jackpot',
                'Can\'t believe it 🙌 three sevens',
            ],
            'triple' => [
                'Three of a kind 🎉 you win',
                'The reels aligned 😎 you\'re a winner',
                'Triple! 🍀 luck is on your side',
                'You won 🔥 nice one',
                'Three matching; the slot surrendered 🏆',
            ],
            'pair' => [
                'Two of a kind 😏 almost there',
                'Almost a jackpot 😭 two matching',
                'One reel ruined it 😅 two matching',
                'So close 🤏 spin again',
                'Two came up, the third didn\'t 😬',
            ],
            'lose' => [
                'None matched 😂 you lost',
                'The slot eats your money today 😅 no win',
                'Each reel went its own way 🙈 lose',
                'Bad luck 💀 nothing came up',
                'It just didn\'t work 🎰 spin again',
            ],
        ],
    ];

    private static function diceBucket(string $emoji, int $value): string
    {
        switch ($emoji) {
            case '🎯':
                return $value <= 1 ? 'miss' : ($value >= 6 ? 'top' : 'hit');
            case '🏀':
                return $value >= 4 ? 'win' : 'miss';
            case '⚽':
                return $value >= 3 ? 'win' : 'miss';
            case '🎳':
                return $value >= 6 ? 'top' : ($value <= 1 ? 'miss' : 'hit');
            case '🎰':
                if ($value === 64) {
                    return 'jackpot';
                }
                $l = ($value - 1) % 4;
                $m = intdiv($value - 1, 4) % 4;
                $r = intdiv($value - 1, 16);
                return $l === $m && $m === $r ? 'triple' : ($l === $m || $m === $r || $l === $r ? 'pair' : 'lose');
            default:
                return $value <= 1 ? 'one' : ($value <= 3 ? 'low' : ($value <= 5 ? 'high' : 'six'));
        }
    }

    public static function ping(int $ms): string
    {
        return L('پینگ: ', 'Ping: ') . fa((string) $ms) . L(" میلی‌ثانیه\n", " ms\n") . (Lang::is('en') ? self::PING_LINES_EN : self::PING_LINES)[array_rand((Lang::is('en') ? self::PING_LINES_EN : self::PING_LINES))];
    }

    public static function diceHello(string $emoji, int $value): string
    {
        $emoji = str_replace("\u{FE0F}", '', $emoji);
        if (!isset((Lang::is('en') ? self::DICE_LINES_EN : self::DICE_LINES)[$emoji])) {
            $emoji = '🎲';
        }
        $lines = (Lang::is('en') ? self::DICE_LINES_EN : self::DICE_LINES)[$emoji][self::diceBucket($emoji, $value)];
        return $lines[array_rand($lines)];
    }

    public static function groupKeyboard(): array
    {
        return ikb([[['text' => L('🚀 منوی نالین', '🚀 Nullin menu'), 'switch_inline_query_current_chat' => '', 'style' => 'success']]]);
    }

    public static function pushCommands($tg, string $kind, array $scope): void
    {
        $prev = Lang::get();
        Lang::set('en');
        $tg->call('setMyCommands', ['commands' => self::commands($kind), 'scope' => $scope], 10);
        Lang::set('fa');
        $tg->call('setMyCommands', ['commands' => self::commands($kind), 'scope' => $scope, 'language_code' => 'fa'], 10);
        Lang::set($prev);
    }

    /** Re-publish the command menu for one private chat in the language the user just picked. */
    public static function pushUserCommands($tg, int $uid): void
    {
        if ($uid <= 0) {
            return;
        }
        try {
            $cmds  = self::commands(App::isAdmin($uid) ? 'admin' : 'private');
            $scope = ['type' => 'chat', 'chat_id' => $uid];
            $tg->call('setMyCommands', ['commands' => $cmds, 'scope' => $scope], 10);
            $tg->call('setMyCommands', ['commands' => $cmds, 'scope' => $scope, 'language_code' => 'fa'], 10);
        } catch (\Throwable $e) {
            logError('lang', 'pushUserCommands: ' . $e->getMessage());
        }
    }

    public static function syncMemberCommands($tg, int $chatId, int $uid, bool $force = false): void
    {
        if ($chatId === 0 || $uid <= 0) {
            return;
        }
        $lang = Lang::get();
        $key  = $chatId . ':' . $uid;
        $prev = Store::get('cmdsync', $key);
        if (!$force && is_array($prev)) {
            if (($prev['l'] ?? '') === $lang && !empty($prev['ok'])) {
                return;
            }
        }
        try {
            $r  = $tg->call('setMyCommands', [
                'commands' => self::commands('group'),
                'scope'    => ['type' => 'chat_member', 'chat_id' => $chatId, 'user_id' => $uid],
            ], 10);
            if (!empty($r['ok'])) {
                Store::set('cmdsync', $key, ['l' => $lang, 'ok' => true]);
            }
        } catch (\Throwable $e) {
            logError('lang', 'syncMemberCommands: ' . $e->getMessage());
        }
    }

    public static function commands(string $scope): array
    {
        $c = static fn(string $cmd, string $d) => ['command' => $cmd, 'description' => $d];
        $base = [
            $c('start', L('🏠 شروع', '🏠 Start')),
            $c('help', L('📘 راهنما', '📘 Help')),
            $c('voice', L('🎙 انتخاب صدای نالین', '🎙 Choose Nullin\'s voice')),
            $c('forget', L('🧹 پاک کردن حافظه‌ی نالین', '🧹 Clear Nullin\'s memory')),
            $c('ping', L('🏓 پینگ', '🏓 Ping')),
            $c('lang', '🌐 زبان / Language'),
        ];
        foreach ([['img', L('🎨 ساخت عکس', '🎨 Create image')], ['tr', L('🌐 ترجمه', '🌐 Translate')], ['ft', L('✨ فونت‌ساز', '✨ Font maker')], ['gw', L('🎁 قرعه‌کشی', '🎁 Giveaway')], ['wk', L('📖 ویکی‌پدیا', '📖 Wikipedia')], ['st', L('🛡 ردیاب اشتراک', '🛡 Invite link tracker')]] as [$cmd, $desc]) {
            if (Switches::on(Switches::COMMAND_SWITCH[$cmd])) {
                $base[] = $c($cmd, $desc);
            }
        }
        if ($scope !== 'group' && Switches::on(Switches::COMMAND_SWITCH['monshi'])) {
            $base[] = $c('monshi', L('🤝 منشی هوشمند', '🤝 Smart secretary'));
        }
        if ($scope === 'admin') {
            return array_merge($base, [
                $c('stats', L('📊 آمار ربات', '📊 Bot stats')),
                $c('ai', L('🤖 پنل هوش مصنوعی', '🤖 AI panel')),
                $c('quota', L('🔋 سهمیه‌ی هوش مصنوعی', '🔋 AI quota')),
                $c('backup', L('💾 بکاپ دستی دیتابیس', '💾 Manual DB backup')),
                $c('cookies', L('🍪 آپدیت فایل کوکی دانلودر', '🍪 Update downloader cookie file')),
                $c('broadcast', L('📣 پیام همگانی', '📣 Broadcast')),
                $c('tools', L('🧰 روشن/خاموش کردن ابزارها', '🧰 Toggle tools on/off')),
                $c('ban', L('⛔ بن کاربر (ریپلای یا آیدی)', '⛔ Ban user (reply or ID)')),
                $c('unban', L('✅ آنبن کاربر (ریپلای یا آیدی)', '✅ Unban user (reply or ID)')),
                $c('banned', L('📋 لیست بن‌شده‌ها', '📋 Banned list')),
            ]);
        }
        return $base;
    }

    public static function startKeyboard(): array
    {
        $bot = App::botUsername();
        $mid = Switches::on('business') ? [btn(L('🤝 منشی', '🤝 Secretary'), 'bz:menu:h')] : [];
        return ikb([
            [['text' => L('🚀 امتحانش کن', '🚀 Try it'), 'switch_inline_query_current_chat' => '', 'style' => 'primary']],
            array_merge([btn(L('📘 راهنما', '📘 Help'), 'help:menu')], $mid, [btn(L('ℹ️ درباره', 'ℹ️ About'), 'about')]),
            [urlBtn(L('➕ افزودن به گروه', '➕ Add to group'), "https://t.me/{$bot}?startgroup=true", 'primary')],
            [btn('🌐 زبان / Language', 'lang:menu', null)],
        ]);
    }

    public static function aboutText(): string
    {
        $c = App::cfg('creator_name', 'Arown');
        $v = App::cfg('bot_version', '3.0.0');
        return L("<b>ℹ️ درباره‌ی نالین</b>\n\n", "<b>ℹ️ About Nullin</b>\n\n")
            . L("اسمم از <code>null</code> (یعنی «خالی») میاد، ولی دستم پره 😉\n", "My name comes from <code>null</code> (meaning \"empty\"), but my hands are full 😉\n")
            . L("یه دستیار فارسیِ اینلاین\n", "An inline assistant\n")
            . L("با هوش مصنوعی، ابزار و بازی؛\n", "with AI, tools and games;\n")
            . L("تو هر چتی، بدون این‌که عضوش باشم.\n\n", "in any chat, without being a member.\n\n")
            . L("👨‍💻 سازنده: <b>", "👨‍💻 Creator: <b>") . h($c) . "</b>\n"
            . L("🔖 نسخه: <code>", "🔖 Version: <code>") . h($v) . "</code>";
    }

    public static function aboutKeyboard(bool $withBack): array
    {
        $rows = [];
        $row  = [];
        if (App::cfg('creator_username')) {
            $row[] = urlBtn(L('👤 سازنده', '👤 Creator'), 'https://t.me/' . App::cfg('creator_username'));
        }
        if (App::cfg('channel_url')) {
            $row[] = urlBtn(L('📢 کانال', '📢 Channel'), App::cfg('channel_url'));
        }
        $rows[] = $row;
        if ($withBack) {
            $rows[] = [btn(L('🔙 برگشت', '🔙 Back'), 'home', null)];
        }
        return ikb(array_values(array_filter($rows)));
    }

    public const GUIDES = [
        'ai'        => '🧠 هوش مصنوعی',
        'image'     => '🎨 ساخت عکس',
        'translate' => '🌐 ترجمه',
        'search'    => '🔍 سرچ گوگل',
        'font'      => '✨ فونت‌ساز',
        'download'  => '📥 دانلودر',
        'giveaway'  => '🎁 قرعه‌کشی',
        'like'      => '❤️ لایک‌ساز',
        'wiki'      => '📖 ویکی‌پدیا',
        'whisper'   => '🤫 نجوا',
        'weather'   => '🌦 آب‌وهوا',
        'calc'      => '🧮 محاسبه سریع',
        'qr'        => '🔳 QR کد',
        'short'     => '🔗 کوتاه‌کننده لینک',
        'love'      => '💘 تطابق عشقی',
        'roast'     => '🔥 روست',
        'nid'       => '🪪 کد ملی',
        'unit'      => '⚖️ تبدیل واحد',
        'funstat'   => '🔍 استعلام کاربر',
        'linkcheck' => '🛡 ردیاب اشتراک',
        'games'     => '🎮 بازی‌ها',
        'group'     => '👥 در گروه',
    ];
    const GUIDES_EN = [
        'ai'        => '🧠 AI',
        'image'     => '🎨 Create image',
        'translate' => '🌐 Translate',
        'search'    => '🔍 Google search',
        'font'      => '✨ Font maker',
        'download'  => '📥 Downloader',
        'giveaway'  => '🎁 Giveaway',
        'like'      => '❤️ Like maker',
        'wiki'      => '📖 Wikipedia',
        'whisper'   => '🤫 Whisper',
        'weather'   => '🌦 Weather',
        'calc'      => '🧮 Quick calc',
        'qr'        => '🔳 QR code',
        'short'     => '🔗 Link shortener',
        'love'      => '💘 Love match',
        'roast'     => '🔥 Roast',
        'nid'       => '🪪 National ID',
        'unit'      => '⚖️ Unit converter',
        'funstat'   => '🔍 User lookup',
        'linkcheck' => '🛡 Invite link tracker',
        'games'     => '🎮 Games',
        'group'     => '👥 In groups',
    ];

    public static function guide(string $key): string
    {
        $b = '@' . App::botUsername();
        $c = static fn(string $s) => "<code>{$b} {$s}</code>";

        return match ($key) {
            'ai' => L("<b>🧠 هوش مصنوعی</b>\n", "<b>🧠 AI</b>\n")
                . L("هر سؤالی داری بپرس؛\n", "Ask anything;\n")
                . L("از درس و کد تا مشاوره و گپ.\n\n", "from homework and code to advice and chat.\n\n")
                . L("• اینلاین:\n", "• Inline:\n") . $c(L('ai سلام نالین', 'ai hello Nullin')) . "\n"
                . L("• پیوی ربات:\n", "• Private chat with the bot:\n")
                . L("بدون هیچ دستور خاصی،\n", "No special command needed,\n")
                . L("هر چی خواستی همون‌جا برام بفرست.\n", "just send me whatever you want there.\n")
                . L("• گروه:\n", "• Group:\n")
                . L("اول یا جایی در پیامت بنویس «نالین»،\n", "Write \"Nullin\" at the start or anywhere in your message,\n")
                . L("منو تگ کن یا روی پیامم ریپلای کن\n\n", "tag me or reply to my message\n\n")
                . L("💡 ۲۰ پیام آخر هر چت یادم می‌مونه؛\n", "💡 I remember the last 20 messages of each chat;\n")
                . L("هر شب ساعت ۱۲ همه‌چی از نو شروع می‌شه.\n\n", "every night at 12 everything starts fresh.\n\n")
                . L("<b>🧠 حافظه‌ی نالین</b>\n", "<b>🧠 Nullin's memory</b>\n")
                . L("چیزای مهم درباره‌ات رو یادم می‌مونه؛\n", "I remember important things about you;\n")
                . L("تو پیوی، گروه و هر چتی که صدام کنی.\n\n", "in private, groups and any chat you call me in.\n\n")
                . L("• بگو «یادت بمونه ...» تا ذخیره کنم\n", "• Say \"remember that ...\" and I'll save it\n")
                . L("• بگو «فلان رو فراموش کن» تا اون مورد پاک بشه\n", "• Say \"forget such-and-such\" to remove that item\n")
                . L("• دستور:\n", "• Command:\n")
                . "/forget\n"
                . L("💡 همه‌ی چیزایی که ازت یادمه رو پاک می‌کنه.", "💡 It erases everything I remember about you."),

            'image' => L("<b>🎨 ساخت عکس</b>\n", "<b>🎨 Create image</b>\n")
                . L("لازم نیست دستور خاصی بلد باشی؛\n", "No special command needed;\n")
                . L("عادی بگو چی می‌خوای،\n", "just say what you want normally,\n")
                . L("خودم می‌فهمم و می‌کشم.\n\n", "and I'll understand and draw it.\n\n")
                . L("• پیوی:\n<code>یه گربه‌ی فضانورد برام بکش</code>\n", "• Private:\n<code>draw me an astronaut cat</code>\n")
                . L("• گروه:\n<code>نالین یه والپیپر از جنگل بارونی می‌خوام</code>\n", "• Group:\n<code>Nullin I want a rainforest wallpaper</code>\n")
                . L("• یا دستور:\n<code>/img شهر آینده‌نگر در شب</code>", "• Or command:\n<code>/img a futuristic city at night</code>"),

            'translate' => L("<b>🌐 ترجمه</b>\n", "<b>🌐 Translate</b>\n")
                . L("فارسی ⇄ انگلیسی خودکار،\n", "Automatic Persian ⇄ English,\n")
                . L("با هوش مصنوعی.\n\n", "powered by AI.\n\n")
                . L("• اینلاین:\n", "• Inline:\n") . $c(L('tr سلام، حالت چطوره؟', 'tr hello, how are you?')) . "\n"
                . L("• پیوی یا گروه:\n", "• Private or group:\n")
                . L("روی یه پیام ریپلای کن و بنویس\n", "Reply to a message and write\n")
                . L("<code>نالین ترجمه کن</code>", "<code>Nullin translate this</code>"),

            'search' => L("<b>🔍 سرچ گوگل</b>\n", "<b>🔍 Google search</b>\n")
                . L("تو هر چتی سرچ کن و نتیجه رو بفرست؛\n", "Search in any chat and send the result;\n")
                . L("دکمه‌ها مستقیم به گوگل وصل می‌شن.\n\n", "the buttons link directly to Google.\n\n")
                . "• " . $c(L('g هوای تهران فردا', 'g weather in London tomorrow')) . "\n"
                . "• " . $c('g best php framework') . "\n\n"
                . L("💡 علاوه بر دکمه‌ی گوگل،\n", "💡 Besides the Google button,\n")
                . L("چندتا نتیجه‌ی آماده هم زیرش میاد\n", "a few ready results appear below it\n")
                . L("که می‌تونی مستقیم بفرستی.", "that you can send directly."),

            'download' => L("<b>📥 دانلودر</b>\n", "<b>📥 Downloader</b>\n")
                . L("لینک رو بفرست،\n", "Send a link,\n")
                . L("ویدیو یا آهنگش رو تحویل بگیر!\n\n", "get the video or song!\n\n")
                . L("• پیوی ربات: فقط لینک رو بفرست\n", "• Private chat with the bot: just send the link\n")
                . L("• گروه: لینک رو تنها بفرست یا ربات رو تگ کن\n", "• Group: send the link alone or tag the bot\n")
                . L("• هر چتی (حتی بدون عضویت): ربات رو تگ کن + لینک\n\n", "• Any chat (even without membership): tag the bot + link\n\n")
                . L("🌐 یوتیوب، اینستاگرام، تیک‌تاک،\n", "🌐 YouTube, Instagram, TikTok,\n")
                . L("توییتر، فیسبوک، ردیت، پینترست، اسنپ‌چت،\n", "Twitter, Facebook, Reddit, Pinterest, Snapchat,\n")
                . L("ساندکلاد و اسپاتیفای\n\n", "SoundCloud and Spotify\n\n")
                . L("🖼 پستای عکس‌دار\n", "🖼 Posts with photos\n")
                . L("اینستا، پینترست، توییتر و ردیت هم عکسشون رو می‌گیرم\n\n", "I grab the images from Instagram, Pinterest, Twitter and Reddit too\n\n")
                . L("💡 تا ", "💡 Up to ") . Downloader::limitText() . L("؛\n", ";\n")
                . L("برای ویدیوی بلند\n", "for long videos\n")
                . L("«کم‌حجم» یا «فقط صدا» رو بزن.", "tap \"Low size\" or \"Audio only\"."),

            'giveaway' => L("<b>🎁 قرعه‌کشی</b>\n", "<b>🎁 Giveaway</b>\n")
                . L("برای کانال، گروه یا هر چتی قرعه‌کشی بساز؛\n", "Create a giveaway for a channel, group or any chat;\n")
                . L("بقیه با یه دکمه شرکت می‌کنن\n", "others join with one button\n")
                . L("و خودم سر وقت برنده‌ها رو انتخاب و اعلام می‌کنم 🎉\n\n", "and I pick and announce the winners on time 🎉\n\n")
                . L("• پیوی: /gw بزن یا بگو «یه قرعه‌کشی بساز»\n", "• Private: send /gw or say \"create a giveaway\"\n")
                . L("• گروه: ادمین /gw بزنه\n", "• Group: an admin sends /gw\n")
                . L("• اینلاین تو هر چتی:\n", "• Inline in any chat:\n") . $c(L('gw 2 1h آیفون', 'gw 2 1h iPhone')) . "\n"
                . L("(۲ برنده، ۱ ساعت دیگه، جایزه آیفون)\n\n", "(2 winners, in 1 hour, prize iPhone)\n\n")
                . L("💡 تو کانال باید ادمین باشم؛\n", "💡 In a channel I must be an admin;\n")
                . L("اون‌جا فقط اعضا می‌تونن شرکت کنن.", "only members can join there."),

            'font' => L("<b>✨ فونت‌ساز</b>\n", "<b>✨ Font maker</b>\n")
                . L("اسم یا متنت رو\n", "Write your name or text\n")
                . L("با کلی فونت و استایل خوشگل بنویس؛\n", "in lots of beautiful fonts and styles;\n")
                . L("هم انگلیسی، هم فارسی.\n\n", "both English and Persian.\n\n")
                . "• " . $c('ft Arown') . "\n"
                . "• " . $c(L('ft نالین', 'ft Nullin')) . "\n"
                . L("• پیوی ربات:\n<code>فونت Arown</code>\n\n", "• Private chat with the bot:\n<code>font Arown</code>\n\n")
                . L("💡 هر نتیجه یه دکمه‌ی «📋 کپی» داره.", "💡 Every result has a \"📋 Copy\" button."),

            'wiki' => L("<b>📖 ویکی‌پدیا</b>\n", "<b>📖 Wikipedia</b>\n")
                . L("خلاصه‌ی هر موضوعی\n", "A summary of any topic\n")
                . L("از ویکی‌پدیای فارسی و انگلیسی.\n\n", "from Persian and English Wikipedia.\n\n")
                . "• " . $c(L('wk تخت جمشید', 'wk Eiffel Tower')) . "\n"
                . "• " . $c('wk Albert Einstein') . "\n"
                . L("• پیوی یا گروه:\n", "• Private or group:\n")
                . L("<code>نالین ویکی‌پدیا درباره‌ی حافظ چی میگه</code>\n", "<code>Nullin what does Wikipedia say about Einstein</code>\n")
                . L("• دستور:\n<code>/wk حافظ</code>", "• Command:\n<code>/wk Einstein</code>"),

            'whisper' => L("<b>🤫 نجوا</b>\n", "<b>🤫 Whisper</b>\n")
                . L("پیام مخفی وسط هر چتی\n", "A secret message in the middle of any chat\n")
                . L("که فقط گیرنده می‌تونه بخونه.\n\n", "that only the recipient can read.\n\n")
                . "• " . $c(L('nj متن پیام @username', 'nj message text @username')) . "\n"
                . "• " . $c(L('nj متن پیام 12345678', 'nj message text 12345678')) . "\n\n"
                . L("حداکثر ", "Max ") . fa(Whisper::MAX_LEN) . L(" کاراکتر؛\n", " characters;\n")
                . L("فرستنده می‌تونه پاکش کنه و ببینه کیا فضولی کردن 🕵️\n", "The sender can delete it and see who peeked 🕵️\n")
                . L("برای چند نفر هم می‌شه فرستاد؛\n", "You can send it to several people;\n")
                . L("یوزرنیم یا آیدی‌ها رو پشت هم بنویس.", "write the usernames or IDs one after another."),

            'weather' => L("<b>🌦 آب‌وهوا</b>\n", "<b>🌦 Weather</b>\n")
                . L("وضعیت هوا و پیش‌بینی دو روز آینده‌ی هر شهری.\n\n", "Current weather and a two-day forecast for any city.\n\n")
                . "• " . $c('hv tehran') . "\n"
                . "• " . $c(L('hv رشت', 'hv London')) . "\n\n"
                . L("💡 فارسی یا انگلیسی فرقی نداره؛\n", "💡 Persian or English doesn't matter;\n")
                . L("مستقیم بنویس.", "just type it directly."),

            'calc' => L("<b>🧮 محاسبه سریع</b>\n", "<b>🧮 Quick calc</b>\n")
                . L("یه عبارت ریاضی بنویس،\n", "Type a math expression,\n")
                . L("جوابش رو همون لحظه می‌بینی.\n\n", "and see the answer instantly.\n\n")
                . "• " . $c('(12+8)*3^2') . "\n\n"
                . L("💡 ماشین‌حساب دکمه‌ای هم\n", "💡 There's also a button calculator\n")
                . L("تو منوی اصلی هست.", "in the main menu."),

            'qr' => L("<b>🔳 QR کد</b>\n", "<b>🔳 QR code</b>\n")
                . L("هر متن یا لینکی رو به QR تبدیل کن.\n\n", "Turn any text or link into a QR code.\n\n")
                . "• " . $c('qr https://t.me/NullHubs'),

            'short' => L("<b>🔗 کوتاه‌کننده لینک</b>\n", "<b>🔗 Link shortener</b>\n")
                . L("هر لینک بلندی رو کوتاه کن.\n\n", "Shorten any long link.\n\n")
                . "• " . $c('sh https://t.me/NullHubs'),

            'like' => L("<b>❤️ لایک‌ساز</b>\n", "<b>❤️ Like maker</b>\n")
                . L("هر متنی بنویسی با دکمه‌ی ❤️ و شمارنده می‌فرستمش؛\n", "Write any text and I'll send it with a ❤️ button and a counter;\n")
                . L("هر نفر یه بار می‌تونه لایک کنه و با زدن دوباره برمی‌داره.\n\n", "each person can like once and tapping again removes it.\n\n")
                . "• " . $c(L('like نالین', 'like Nullin')),

            'love' => L("<b>💘 تطابق عشقی</b>\n", "<b>💘 Love match</b>\n")
                . L("درصد عشق بین دو نفر (کاملاً شانسی و فان 😄).\n\n", "Love percentage between two people (purely random and for fun 😄).\n\n")
                . "• " . $c('love @ali @sara') . "\n"
                . "• " . $c('love @ali 12345678'),

            'roast' => L("<b>🔥 روست</b>\n", "<b>🔥 Roast</b>\n")
                . L("با عکس پروفایل، اسم، بیو، یوزرنیم و حرفاش،\n", "Using the profile photo, name, bio, username and messages,\n")
                . L("حسابی می‌شورمش 😈\n\n", "I'll roast them thoroughly 😈\n\n")
                . L("• خودت: <code>نالین منو روست کن</code>\n", "• Yourself: <code>Nullin roast me</code>\n")
                . L("• یه نفر دیگه: <code>نالین اینو روست کن</code>\n", "• Someone else: <code>Nullin roast this person</code>\n")
                . L("• با آیدی: <code>نالین @username رو روست کن</code>\n", "• By username: <code>Nullin roast @username</code>\n")
                . L("• تو هر چتی:\n", "• In any chat:\n") . $c('roast') . "\n" . $c('roast @username') . "\n\n"
                . L("💡 هر جوری بگی می‌فهمم 😉\n", "💡 I understand however you phrase it 😉\n")
                . L("⏱ هر نفر دقیقه‌ای یه بار؛ فقط شوخیه، ناراحت نشی 😄", "⏱ Once a minute per person; it's just a joke, don't be upset 😄"),

            'nid' => L("<b>🪪 کد ملی</b>\n", "<b>🪪 National ID</b>\n")
                . L("بررسی سالم بودن ساختار کد ملی\n", "Checks that the structure of a national ID is valid\n")
                . L("(رقم کنترل).\n\n", "(check digit).\n\n")
                . "• " . $c('meli 0012345678') . "\n\n"
                . L("⚠️ فقط ساختار رو چک می‌کنه، نه ثبت‌احوال رو.", "⚠️ It only checks the structure, not the civil registry."),

            'unit' => L("<b>⚖️ تبدیل واحد</b>\n", "<b>⚖️ Unit converter</b>\n")
                . L("طول، وزن، حجم، سرعت، مساحت، دیتا و دما\n", "Length, weight, volume, speed, area, data and temperature\n")
                . L("• از منوی اصلی:\n", "• From the main menu:\n")
                . "<code>{$b}</code>\n"
                . L("💡 دسته و واحدها رو با دکمه انتخاب کن و عدد رو بزن؛\n", "💡 Pick the category and units with buttons and type the number;\n")
                . L("⇄ جای مبدأ و مقصد رو عوض می‌کنه.", "⇄ swaps source and target."),

            'funstat' => L("<b>🔍 استعلام کاربر</b>\n", "<b>🔍 User lookup</b>\n")
                . L("آمار فعالیت یه کاربر تلگرام\n", "Activity stats of a Telegram user\n")
                . L("با آیدی عددیش.\n\n", "by their numeric ID.\n\n")
                . "• " . $c('id 12345678'),

            'linkcheck' => L("<b>🛡 ردیاب اشتراک</b>\n", "<b>🛡 Invite link tracker</b>\n")
                . L("اطلاعات پشت یه لینک اشتراک رو ردیابی می‌کنه:\n", "Traces the info behind an invite link:\n")
                . L("تلگرام، دیسکورد، گیت‌هاب، گیت‌لب، هاگینگ‌فیس، تیک‌تاک، اینستاگرام، یوتیوب، ردیت، نوشن، لینکدین،\n", "Telegram, Discord, GitHub, GitLab, Hugging Face, TikTok, Instagram, YouTube, Reddit, Notion, LinkedIn,\n")
                . L("چت‌جی‌پی‌تی، کلود، پرپلکسیتی، پینترست، سابستک،\n", "ChatGPT, Claude, Perplexity, Pinterest, Substack,\n")
                . L("سونو، گوگل داکس و شیرپوینت\n\n", "Suno, Google Docs and SharePoint\n\n")
                . L("• دستور: <code>/st https://example.com</code>\n", "• Command: <code>/st https://example.com</code>\n")
                . L("• یا روی پیامی که لینک داره ریپلای کن و <code>/st</code> بزن", "• Or reply to a message that has a link and send <code>/st</code>"),

            'games' => L("<b>🎮 بازی‌ها</b>\n", "<b>🎮 Games</b>\n")
                . L("همه از منوی اصلی در دسترسن؛\n", "All available from the main menu;\n")
                . "<code>{$b}</code>\n"
                . L("با ربات یا با دوستات:\n\n", "with the bot or with your friends:\n\n")
                . L("♟️ شطرنج (۳ سطح سختی)\n", "♟️ Chess (3 difficulty levels)\n")
                . L("⭕ دوز · 🔴 دوز سکه‌ای\n", "⭕ Tic-tac-toe · 🔴 Connect Four\n")
                . L("✊ سنگ‌کاغذقیچی · 🔢 دوئل ریاضی", "✊ Rock-paper-scissors · 🔢 Math duel"),

            'group' => L("<b>👥 نالین در گروه</b>\n", "<b>👥 Nullin in groups</b>\n")
                . L("منو به گروهت اضافه کن و:\n\n", "Add me to your group and:\n\n")
                . L("• اول یا جایی از پیامت «نالین» رو بنویس\n", "• Write \"Nullin\" at the start or anywhere in your message\n")
                . L("• منو تگ کن تا جواب بدم\n", "• Tag me and I'll answer\n")
                . L("• روی پیام‌های من ریپلای کن\n", "• Reply to my messages\n")
                . L("• پست‌های کانال متصل رو خودکار جواب می‌دم\n\n", "• I automatically answer posts from the linked channel\n\n")
                . L("💡 اگه جواب ندادم،\n", "💡 If I don't answer,\n")
                . L("منو ادمین گروه کن تا پیام‌ها به دستم برسه.", "make me a group admin so I can receive messages."),
            default => L("<b>📘 راهنما</b>", "<b>📘 Help</b>"),
        };
    }

    private const HELP_LAYOUT = [
        1 => [['ai', 'image'], ['translate', 'search'], ['whisper', 'linkcheck'], ['font', 'download'], ['giveaway', 'wiki']],
        2 => [['weather', 'calc'], ['qr', 'short'], ['love', 'group'], ['like', 'unit'], ['roast', 'nid'], ['funstat', 'games']],
    ];

    public static function helpPage(string $key): int
    {
        foreach (self::HELP_LAYOUT[2] as $row) {
            if (in_array($key, $row, true)) {
                return 2;
            }
        }
        return 1;
    }

    public static function navButton(bool $next, string $data): array
    {
        return $next ? btn(L('➡️ صفحه‌ی بعد', '➡️ Next page'), $data, 'primary') : btn(L('صفحه‌ی قبل ⬅️', 'Previous page ⬅️'), $data, 'primary');
    }

    public static function helpMenu(bool $private = true, int $page = 1): array
    {
        $page = $page === 2 ? 2 : 1;
        $rows = [];
        foreach (self::HELP_LAYOUT[$page] as $keys) {
            $row = [];
            foreach ($keys as $key) {
                if (isset((Lang::is('en') ? self::GUIDES_EN : self::GUIDES)[$key])) {
                    $row[] = btn((Lang::is('en') ? self::GUIDES_EN : self::GUIDES)[$key], "help:{$key}", null);
                }
            }
            $rows[] = $row;
        }
        $rows[] = [self::navButton($page === 1, $page === 1 ? 'help:p2' : 'help:p1')];
        $rows[] = [btn(L('🔙 برگشت', '🔙 Back'), 'home', null)];
        return ikb($rows);
    }

    private const TRY_PREFIX = ['ai' => 'ai ', 'translate' => 'tr ', 'search' => 'g ', 'font' => 'ft ', 'whisper' => 'nj ', 'weather' => 'hv ', 'qr' => 'qr ', 'short' => 'sh ', 'love' => 'love ', 'like' => 'like ', 'roast' => 'roast ',
        'nid' => 'meli ', 'unit' => 'تبدیل', 'funstat' => 'id ', 'giveaway' => 'gw ', 'wiki' => 'wk ', 'calc' => '2+2', 'linkcheck' => 'st '];
    const TRY_PREFIX_EN = ['ai' => 'ai ', 'translate' => 'tr ', 'search' => 'g ', 'font' => 'ft ', 'whisper' => 'nj ', 'weather' => 'hv ', 'qr' => 'qr ', 'short' => 'sh ', 'love' => 'love ', 'like' => 'like ', 'roast' => 'roast ',
        'nid' => 'meli ', 'unit' => 'convert', 'funstat' => 'id ', 'giveaway' => 'gw ', 'wiki' => 'wk ', 'calc' => '2+2', 'linkcheck' => 'st '];

    private static function tryButton(string $key): array
    {
        return ['text' => L('💬 تو چت امتحانش کن', '💬 Try it in chat'), 'switch_inline_query_current_chat' => (Lang::is('en') ? self::TRY_PREFIX_EN : self::TRY_PREFIX)[$key] ?? '', 'style' => 'primary'];
    }

    public static function tryKeyboard(string $key): array
    {
        return ikb([[self::tryButton($key)]]);
    }

    public static function guideKeyboard(string $key, bool $private): array
    {
        $try = self::tryButton($key);
        if ($private && $key === 'giveaway') {
            $try = btn(L('🎁 بساز', '🎁 Create'), 'gw:new', 'primary');
        }
        return ikb([
            [$try],
            [btn(L('🔙 راهنما', '🔙 Help'), self::helpPage($key) === 2 ? 'help:p2' : 'help:menu', null)],
        ]);
    }

    public static function helpText(int $page = 1): string
    {
        return L("<b>📘 راهنمای نالین</b>\n<i>صفحه‌ی ", "<b>📘 Nullin Help</b>\n<i>Page ") . fa((string) $page) . L(" از ۲</i>\n\nکدوم بخش رو می‌خوای یاد بگیری؟ 👇", " of 2</i>\n\nWhich section do you want to learn about? 👇");
    }
}
