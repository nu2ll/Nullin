<?php

final class Lang
{
    private static string $cur = 'fa';
    private static array $memo = [];
    private static ?string $code = null;      // Telegram language_code of the current user (base code, e.g. "ru")
    private static ?string $explicit = null;  // language the user picked with /lang, if any
    private static ?string $aiPick = null;    // AI language the user picked with /ailang, if any

    private const NAMES = [
        'fa' => 'Persian', 'en' => 'English', 'ru' => 'Russian', 'ar' => 'Arabic', 'tr' => 'Turkish', 'de' => 'German',
        'fr' => 'French', 'es' => 'Spanish', 'it' => 'Italian', 'pt' => 'Portuguese', 'uk' => 'Ukrainian', 'hi' => 'Hindi',
        'id' => 'Indonesian', 'zh' => 'Chinese', 'ja' => 'Japanese', 'ko' => 'Korean', 'nl' => 'Dutch', 'pl' => 'Polish',
        'ku' => 'Kurdish', 'az' => 'Azerbaijani', 'uz' => 'Uzbek', 'he' => 'Hebrew', 'ur' => 'Urdu', 'bn' => 'Bengali',
        'vi' => 'Vietnamese', 'th' => 'Thai', 'kk' => 'Kazakh', 'ps' => 'Pashto', 'sv' => 'Swedish', 'cs' => 'Czech',
        'ro' => 'Romanian', 'el' => 'Greek', 'hu' => 'Hungarian', 'ms' => 'Malay', 'tg' => 'Tajik', 'ka' => 'Georgian',
        'hy' => 'Armenian', 'be' => 'Belarusian', 'bg' => 'Bulgarian', 'sr' => 'Serbian', 'fi' => 'Finnish', 'da' => 'Danish',
        'no' => 'Norwegian', 'nb' => 'Norwegian', 'ta' => 'Tamil', 'te' => 'Telugu', 'fil' => 'Filipino',
    ];

    private const AI_LANGS = [
        'fa' => ['🇮🇷', 'فارسی', 'Persian'],
        'en' => ['🇬🇧', 'English', 'English'],
        'ar' => ['🇸🇦', 'العربية', 'Arabic'],
        'tr' => ['🇹🇷', 'Türkçe', 'Turkish'],
        'ru' => ['🇷🇺', 'Русский', 'Russian'],
        'uk' => ['🇺🇦', 'Українська', 'Ukrainian'],
        'de' => ['🇩🇪', 'Deutsch', 'German'],
        'fr' => ['🇫🇷', 'Français', 'French'],
        'es' => ['🇪🇸', 'Español', 'Spanish'],
        'pt' => ['🇧🇷', 'Português', 'Portuguese'],
        'it' => ['🇮🇹', 'Italiano', 'Italian'],
        'nl' => ['🇳🇱', 'Nederlands', 'Dutch'],
        'pl' => ['🇵🇱', 'Polski', 'Polish'],
        'cs' => ['🇨🇿', 'Čeština', 'Czech'],
        'sk' => ['🇸🇰', 'Slovenčina', 'Slovak'],
        'hu' => ['🇭🇺', 'Magyar', 'Hungarian'],
        'ro' => ['🇷🇴', 'Română', 'Romanian'],
        'bg' => ['🇧🇬', 'Български', 'Bulgarian'],
        'el' => ['🇬🇷', 'Ελληνικά', 'Greek'],
        'sr' => ['🇷🇸', 'Српски', 'Serbian'],
        'hr' => ['🇭🇷', 'Hrvatski', 'Croatian'],
        'bs' => ['🇧🇦', 'Bosanski', 'Bosnian'],
        'sl' => ['🇸🇮', 'Slovenščina', 'Slovenian'],
        'mk' => ['🇲🇰', 'Македонски', 'Macedonian'],
        'sq' => ['🇦🇱', 'Shqip', 'Albanian'],
        'be' => ['🇧🇾', 'Беларуская', 'Belarusian'],
        'lt' => ['🇱🇹', 'Lietuvių', 'Lithuanian'],
        'lv' => ['🇱🇻', 'Latviešu', 'Latvian'],
        'et' => ['🇪🇪', 'Eesti', 'Estonian'],
        'fi' => ['🇫🇮', 'Suomi', 'Finnish'],
        'sv' => ['🇸🇪', 'Svenska', 'Swedish'],
        'no' => ['🇳🇴', 'Norsk', 'Norwegian'],
        'da' => ['🇩🇰', 'Dansk', 'Danish'],
        'is' => ['🇮🇸', 'Íslenska', 'Icelandic'],
        'ga' => ['🇮🇪', 'Gaeilge', 'Irish'],
        'cy' => ['🏴󠁧󠁢󠁷󠁬󠁳󠁿', 'Cymraeg', 'Welsh'],
        'mt' => ['🇲🇹', 'Malti', 'Maltese'],
        'ca' => ['🇦🇩', 'Català', 'Catalan'],
        'eu' => ['🇪🇸', 'Euskara', 'Basque'],
        'gl' => ['🇪🇸', 'Galego', 'Galician'],
        'lb' => ['🇱🇺', 'Lëtzebuergesch', 'Luxembourgish'],
        'he' => ['🇮🇱', 'עברית', 'Hebrew'],
        'ku' => ['☀️', 'Kurdî', 'Kurdish'],
        'ckb' => ['☀️', 'کوردی', 'Central Kurdish'],
        'ps' => ['🇦🇫', 'پښتو', 'Pashto'],
        'ur' => ['🇵🇰', 'اردو', 'Urdu'],
        'sd' => ['🇵🇰', 'سنڌي', 'Sindhi'],
        'hi' => ['🇮🇳', 'हिन्दी', 'Hindi'],
        'bn' => ['🇧🇩', 'বাংলা', 'Bengali'],
        'pa' => ['🇮🇳', 'ਪੰਜਾਬੀ', 'Punjabi'],
        'gu' => ['🇮🇳', 'ગુજરાતી', 'Gujarati'],
        'mr' => ['🇮🇳', 'मराठी', 'Marathi'],
        'ta' => ['🇮🇳', 'தமிழ்', 'Tamil'],
        'te' => ['🇮🇳', 'తెలుగు', 'Telugu'],
        'kn' => ['🇮🇳', 'ಕನ್ನಡ', 'Kannada'],
        'ml' => ['🇮🇳', 'മലയാളം', 'Malayalam'],
        'or' => ['🇮🇳', 'ଓଡ଼ିଆ', 'Odia'],
        'as' => ['🇮🇳', 'অসমীয়া', 'Assamese'],
        'ne' => ['🇳🇵', 'नेपाली', 'Nepali'],
        'si' => ['🇱🇰', 'සිංහල', 'Sinhala'],
        'my' => ['🇲🇲', 'မြန်မာ', 'Burmese'],
        'th' => ['🇹🇭', 'ไทย', 'Thai'],
        'lo' => ['🇱🇦', 'ລາວ', 'Lao'],
        'km' => ['🇰🇭', 'ខ្មែរ', 'Khmer'],
        'vi' => ['🇻🇳', 'Tiếng Việt', 'Vietnamese'],
        'id' => ['🇮🇩', 'Bahasa Indonesia', 'Indonesian'],
        'ms' => ['🇲🇾', 'Bahasa Melayu', 'Malay'],
        'fil' => ['🇵🇭', 'Filipino', 'Filipino'],
        'jv' => ['🇮🇩', 'Basa Jawa', 'Javanese'],
        'su' => ['🇮🇩', 'Basa Sunda', 'Sundanese'],
        'zh' => ['🇨🇳', '简体中文', 'Chinese (Simplified)'],
        'zh-TW' => ['🇹🇼', '繁體中文', 'Chinese (Traditional)'],
        'ja' => ['🇯🇵', '日本語', 'Japanese'],
        'ko' => ['🇰🇷', '한국어', 'Korean'],
        'mn' => ['🇲🇳', 'Монгол', 'Mongolian'],
        'bo' => ['🏔', 'བོད་སྐད་', 'Tibetan'],
        'ug' => ['☀️', 'ئۇيغۇرچە', 'Uyghur'],
        'kk' => ['🇰🇿', 'Қазақша', 'Kazakh'],
        'uz' => ['🇺🇿', 'Oʻzbekcha', 'Uzbek'],
        'ky' => ['🇰🇬', 'Кыргызча', 'Kyrgyz'],
        'tk' => ['🇹🇲', 'Türkmençe', 'Turkmen'],
        'tg' => ['🇹🇯', 'Тоҷикӣ', 'Tajik'],
        'az' => ['🇦🇿', 'Azərbaycanca', 'Azerbaijani'],
        'tt' => ['🇷🇺', 'Татарча', 'Tatar'],
        'ka' => ['🇬🇪', 'ქართული', 'Georgian'],
        'hy' => ['🇦🇲', 'Հայերեն', 'Armenian'],
        'sw' => ['🇰🇪', 'Kiswahili', 'Swahili'],
        'am' => ['🇪🇹', 'አማርኛ', 'Amharic'],
        'ti' => ['🇪🇷', 'ትግርኛ', 'Tigrinya'],
        'so' => ['🇸🇴', 'Soomaali', 'Somali'],
        'om' => ['🇪🇹', 'Oromoo', 'Oromo'],
        'ha' => ['🇳🇬', 'Hausa', 'Hausa'],
        'yo' => ['🇳🇬', 'Yorùbá', 'Yoruba'],
        'ig' => ['🇳🇬', 'Igbo', 'Igbo'],
        'zu' => ['🇿🇦', 'isiZulu', 'Zulu'],
        'xh' => ['🇿🇦', 'isiXhosa', 'Xhosa'],
        'af' => ['🇿🇦', 'Afrikaans', 'Afrikaans'],
        'st' => ['🇱🇸', 'Sesotho', 'Sesotho'],
        'sn' => ['🇿🇼', 'chiShona', 'Shona'],
        'rw' => ['🇷🇼', 'Kinyarwanda', 'Kinyarwanda'],
        'mg' => ['🇲🇬', 'Malagasy', 'Malagasy'],
        'wo' => ['🇸🇳', 'Wolof', 'Wolof'],
        'ln' => ['🇨🇩', 'Lingála', 'Lingala'],
        'ny' => ['🇲🇼', 'Chichewa', 'Chichewa'],
        'ht' => ['🇭🇹', 'Kreyòl ayisyen', 'Haitian Creole'],
        'la' => ['🏛', 'Latina', 'Latin'],
        'eo' => ['🟢', 'Esperanto', 'Esperanto'],
        'mi' => ['🇳🇿', 'Te Reo Māori', 'Maori'],
        'sm' => ['🇼🇸', 'Gagana Samoa', 'Samoan'],
        'haw' => ['🌺', 'ʻŌlelo Hawaiʻi', 'Hawaiian'],
        'fy' => ['🇳🇱', 'Frysk', 'Frisian'],
        'yi' => ['✡️', 'ייִדיש', 'Yiddish'],
    ];

    private const AI_ROWS = 8;   // rows of language buttons per page
    private const AI_COLS = 2;   // language buttons per row (names are long; 2 fits every phone screen)

    public static function get(): string
    {
        return self::$cur;
    }

    public static function is(string $lang): bool
    {
        return self::$cur === $lang;
    }

    public static function set(string $lang): void
    {
        self::$cur      = $lang === 'en' ? 'en' : 'fa';
        self::$code     = null;
        self::$explicit = null;
    }

    public static function default(): string
    {
        return strtolower((string) env('DEFAULT_LANG', 'fa')) === 'en' ? 'en' : 'fa';
    }

    public static function fromCode(?string $code): ?string
    {
        $code = strtolower(trim((string) $code));
        if ($code === '') {
            return null;
        }
        return str_starts_with($code, 'fa') ? 'fa' : 'en';
    }

    public static function stored(int $userId): ?string
    {
        if ($userId <= 0) {
            return null;
        }
        if (array_key_exists($userId, self::$memo)) {
            return self::$memo[$userId];
        }
        $v = Store::get('lang', (string) $userId);
        $v = is_array($v) ? ($v['l'] ?? null) : null;
        return self::$memo[$userId] = ($v === 'en' || $v === 'fa') ? $v : null;
    }

    public static function save(int $userId, string $lang): void
    {
        if ($userId <= 0) {
            return;
        }
        $lang = $lang === 'en' ? 'en' : 'fa';
        self::$memo[$userId] = $lang;
        Store::set('lang', (string) $userId, ['l' => $lang]);
        self::$cur = $lang;
    }

    public static function forUser(array $from): string
    {
        $id = (int) ($from['id'] ?? 0);
        return self::stored($id) ?? self::fromCode($from['language_code'] ?? null) ?? self::default();
    }

    public static function useFrom(?array $from): void
    {
        self::$cur      = is_array($from) ? self::forUser($from) : self::default();
        self::$code     = is_array($from) ? self::baseCode($from['language_code'] ?? null) : null;
        self::$explicit = is_array($from) ? self::stored((int) ($from['id'] ?? 0)) : null;
        self::$aiPick   = is_array($from) ? self::aiStored((int) ($from['id'] ?? 0)) : null;
    }

    private static function baseCode(?string $code): ?string
    {
        $code = strtolower(trim((string) $code));
        $code = (string) preg_split('/[-_]/', $code)[0];
        return preg_match('/^[a-z]{2,3}$/', $code) === 1 ? $code : null;
    }

    /** English name of the language the AI should answer in: the user's /lang choice, else their Telegram language. */
    public static function aiName(): string
    {
        if (self::$aiPick !== null && isset(self::AI_LANGS[self::$aiPick])) {
            return self::AI_LANGS[self::$aiPick][2];
        }
        if (self::$explicit !== null) {
            return self::NAMES[self::$explicit];
        }
        if (self::$code !== null) {
            if (isset(self::NAMES[self::$code])) {
                return self::NAMES[self::$code];
            }
            if (class_exists('Locale')) {
                $n = Locale::getDisplayLanguage(self::$code, 'en');
                if ($n !== '' && $n !== self::$code) {
                    return $n;
                }
            }
            return 'the language with ISO code “' . self::$code . '”';
        }
        return self::NAMES[self::$cur];
    }

    public static function useUpdate(array $update): void
    {
        $from = null;
        foreach (['message', 'edited_message', 'callback_query', 'inline_query', 'chosen_inline_result', 'guest_message', 'business_message', 'edited_business_message', 'my_chat_member'] as $k) {
            if (isset($update[$k]['from']) && is_array($update[$k]['from'])) {
                $from = $update[$k]['from'];
                break;
            }
        }
        self::useFrom($from);
    }

    public static function pick(string $fa, string $en): string
    {
        return self::$cur === 'en' ? $en : $fa;
    }

    public static function toggleKeyboard(): array
    {
        return ikb([[
            btn('🇮🇷 فارسی', 'lang:fa', self::$cur === 'fa' ? 'success' : 'primary'),
            btn('🇬🇧 English', 'lang:en', self::$cur === 'en' ? 'success' : 'primary'),
        ]]);
    }

    // ---------- /ailang : the user picks the language the AI answers in ----------

    public static function aiStored(int $userId): ?string
    {
        if ($userId <= 0) {
            return null;
        }
        $v = Store::get('ailang', (string) $userId);
        $v = is_array($v) ? ($v['c'] ?? null) : null;
        return is_string($v) && isset(self::AI_LANGS[$v]) ? $v : null;
    }

    public static function aiSave(int $userId, ?string $code): void
    {
        if ($userId <= 0) {
            return;
        }
        if ($code === null || !isset(self::AI_LANGS[$code])) {
            Store::delete('ailang', (string) $userId);
            self::$aiPick = null;
            return;
        }
        Store::set('ailang', (string) $userId, ['c' => $code]);
        self::$aiPick = $code;
    }

    /** True when the user explicitly picked an AI language with /ailang (it must then override the language they write in). */
    public static function aiForced(): bool
    {
        return self::$aiPick !== null && isset(self::AI_LANGS[self::$aiPick]);
    }

    public static function aiPages(): int
    {
        return (int) ceil(count(self::AI_LANGS) / (self::AI_ROWS * self::AI_COLS));
    }

    public static function aiPanelText(int $userId): string
    {
        $cur = self::aiStored($userId);
        $now = $cur !== null
            ? self::AI_LANGS[$cur][0] . ' ' . self::AI_LANGS[$cur][1]
            : L('خودکار (زبان تلگرامت)', 'Auto (your Telegram language)');
        return L("🌐 <b>زبان هوش مصنوعی</b>\n\nنالین به چه زبانی جواب بده؟ از لیست پایین زبانت رو انتخاب کن.\n\n✅ الان: <b>", "🌐 <b>AI language</b>\n\nWhich language should Nullin answer in? Pick yours from the list below.\n\n✅ Now: <b>")
            . htmlspecialchars($now, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</b>';
    }

    public static function aiKeyboard(int $userId, int $page = 0): array
    {
        $codes = array_keys(self::AI_LANGS);
        $pages = self::aiPages();
        $page  = max(0, min($page, $pages - 1));
        $cur   = self::aiStored($userId);
        $per   = self::AI_ROWS * self::AI_COLS;

        $rows = [];
        foreach (array_chunk(array_slice($codes, $page * $per, $per), self::AI_COLS) as $chunk) {
            $row = [];
            foreach ($chunk as $c) {
                [$flag, $native] = self::AI_LANGS[$c];
                $row[] = btn(($c === $cur ? '✅ ' : '') . $flag . ' ' . $native, 'ail:s:' . $c . ':' . $page, $c === $cur ? 'success' : null);
            }
            $rows[] = $row;
        }

        $prev = $page > 0 ? $page - 1 : $pages - 1;
        $next = $page < $pages - 1 ? $page + 1 : 0;
        $rows[] = [
            btn('◀️', 'ail:p:' . $prev, null),
            btn(($page + 1) . ' / ' . $pages, 'noop', null),
            btn('▶️', 'ail:p:' . $next, null),
        ];
        $rows[] = [btn(L('🔄 خودکار (زبان تلگرام)', '🔄 Auto (Telegram language)'), 'ail:r:' . $page, $cur === null ? 'success' : 'primary')];
        return ikb($rows);
    }
}
