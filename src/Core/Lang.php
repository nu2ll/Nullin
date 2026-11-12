<?php

final class Lang
{
    private static string $cur = 'fa';
    private static array $memo = [];
    private static ?string $code = null;

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
        self::$cur  = $lang === 'en' ? 'en' : 'fa';
        self::$code = null;
    }

    public static function default(): string
    {
        return 'fa';
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
        return self::stored($id) ?? self::default();
    }

    public static function useFrom(?array $from): void
    {
        self::$cur  = is_array($from) ? self::forUser($from) : self::default();
        self::$code = is_array($from) ? self::baseCode($from['language_code'] ?? null) : null;
    }

    private static function baseCode(?string $code): ?string
    {
        $code = strtolower(trim((string) $code));
        $code = (string) preg_split('/[-_]/', $code)[0];
        return preg_match('/^[a-z]{2,3}$/', $code) === 1 ? $code : null;
    }

    public static function code(): ?string
    {
        return self::$code;
    }

    public static function telegramIsFa(): bool
    {
        return self::$code === null || self::$code === 'fa';
    }

    public static function aiName(): string
    {
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
}
