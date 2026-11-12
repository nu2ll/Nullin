<?php

final class Clock
{
    private const CALENDARS = [
        'ps' => ['persian', 'تقویم هجری شمسی', 'Solar Hijri'],
        'he' => ['hebrew', 'تقویم عبری', 'Hebrew'],
        'th' => ['buddhist', 'تقویم بودایی', 'Buddhist'],
        'ar' => ['islamic-umalqura', 'هجری قمری', 'Hijri'],
        'ur' => ['islamic-civil', 'هجری قمری', 'Hijri'],
    ];

    public static function zone(): string
    {
        return Lang::telegramIsFa() ? 'Asia/Tehran' : 'UTC';
    }

    private static function valid(string $zone): bool
    {
        return in_array($zone, DateTimeZone::listIdentifiers(), true);
    }

    private static function intl(int $ts, string $zone, string $dateStyle, string $timeStyle, ?string $calendar = null): ?string
    {
        if (!class_exists('IntlDateFormatter') || !class_exists('IntlCalendar')) {
            return null;
        }
        $styles = ['none' => IntlDateFormatter::NONE, 'short' => IntlDateFormatter::SHORT, 'medium' => IntlDateFormatter::MEDIUM, 'full' => IntlDateFormatter::FULL];
        $dateStyle = $styles[$dateStyle];
        $timeStyle = $styles[$timeStyle];
        $code   = Lang::code() ?? 'fa';
        $locale = $calendar !== null ? $code . '@calendar=' . $calendar : $code;
        $cal    = IntlCalendar::createInstance($zone, $locale);
        if ($cal === null) {
            return null;
        }
        $f = new IntlDateFormatter($code, $dateStyle, $timeStyle, $zone, $cal);
        $r = $f->format($ts);
        return is_string($r) && $r !== '' ? $r : null;
    }

    public static function gregorian(int $ts, ?string $zone = null): string
    {
        $zone ??= self::zone();
        $r = self::intl($ts, $zone, 'full', 'none');
        if ($r !== null) {
            return $r;
        }
        return (new DateTimeImmutable('@' . $ts))->setTimezone(new DateTimeZone($zone))->format('l, j F Y');
    }

    public static function clock(int $ts, ?string $zone = null, bool $seconds = true): string
    {
        $zone ??= self::zone();
        if (Lang::telegramIsFa()) {
            return Jalali::time($ts, $seconds, $zone);
        }
        $r = self::intl($ts, $zone, 'none', $seconds ? 'medium' : 'short');
        return $r ?? (new DateTimeImmutable('@' . $ts))->setTimezone(new DateTimeZone($zone))->format($seconds ? 'H:i:s' : 'H:i');
    }

    public static function dateLine(int $ts, ?string $zone = null): string
    {
        $zone ??= self::zone();
        return Lang::telegramIsFa() ? Jalali::fullDate($ts, false, $zone) : self::gregorian($ts, $zone);
    }

    public static function stamp(int $ts): string
    {
        return self::dateLine($ts) . ' · ' . self::clock($ts, null, false) . (Lang::telegramIsFa() ? '' : ' UTC');
    }

    public static function resolve(string $place): ?array
    {
        $place = trim($place);
        if ($place === '') {
            return [self::zone(), Lang::telegramIsFa() ? L('ایران', 'Iran') : 'UTC'];
        }
        $z = Weather::zoneOf($place);
        if ($z === null || !self::valid($z['zone'])) {
            return null;
        }
        $label = $z['name'] . ($z['country'] !== '' && $z['country'] !== $z['name'] ? ' · ' . $z['country'] : '');
        return [$z['zone'], $label];
    }

    public static function text(string $place = ''): string
    {
        $r = self::resolve($place);
        if ($r === null) {
            return L('❌ جایی به اسم «', '❌ I couldn’t find a place named “') . h($place) . L('» پیدا نکردم.', '”.');
        }
        [$zone, $label] = $r;
        $now = time();
        $dt  = (new DateTimeImmutable('@' . $now))->setTimezone(new DateTimeZone($zone));
        $l   = [L("<b>📅 تاریخ و ساعت</b> (به وقت " . h($label) . ')', '<b>📅 Date & time</b> (' . h($label) . ' time)'), ''];

        if (Lang::telegramIsFa()) {
            $l[] = L('🗓 شمسی: ', '🗓 Jalali: ') . Jalali::fullDate($now, false, $zone);
            $l[] = L('🔢 عددی: ', '🔢 Numeric: ') . Jalali::numericDate($now, $zone);
            $l[] = L('🌍 میلادی: ', '🌍 Gregorian: ') . $dt->format('l, j F Y');
        } else {
            $l[] = L('🌍 میلادی: ', '🌍 Gregorian: ') . h(self::gregorian($now, $zone));
            $c   = self::CALENDARS[Lang::code() ?? ''] ?? null;
            if ($c !== null) {
                $alt = self::intl($now, $zone, 'full', 'none', $c[0]);
                if ($alt !== null) {
                    $l[] = '🗓 ' . L($c[1], $c[2]) . ': ' . h($alt);
                }
            }
        }
        $l[] = L('🕒 ساعت: <b>', '🕒 Time: <b>') . h(self::clock($now, $zone)) . '</b>';
        $l[] = '🌐 UTC' . $dt->format('P');
        return implode("\n", $l);
    }
}
