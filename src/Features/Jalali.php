<?php

final class Jalali
{
    private const MONTHS   = ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];
    private const WEEKDAYS = ['یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنجشنبه', 'جمعه', 'شنبه'];

    private const MONTHS_EN   = ['Farvardin', 'Ordibehesht', 'Khordad', 'Tir', 'Mordad', 'Shahrivar', 'Mehr', 'Aban', 'Azar', 'Dey', 'Bahman', 'Esfand'];
    private const WEEKDAYS_EN = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

    public static function fromGregorian(int $gy, int $gm, int $gd): array
    {
        $gdm  = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
        $gy2  = $gm > 2 ? $gy + 1 : $gy;
        $days = 355666 + (365 * $gy) + intdiv($gy2 + 3, 4) - intdiv($gy2 + 99, 100) + intdiv($gy2 + 399, 400) + $gd + $gdm[$gm - 1];
        $jy   = -1595 + (33 * intdiv($days, 12053));
        $days %= 12053;
        $jy  += 4 * intdiv($days, 1461);
        $days %= 1461;
        if ($days > 365) {
            $jy  += intdiv($days - 1, 365);
            $days = ($days - 1) % 365;
        }
        if ($days < 186) {
            $jm = 1 + intdiv($days, 31);
            $jd = 1 + ($days % 31);
        } else {
            $jm = 7 + intdiv($days - 186, 30);
            $jd = 1 + (($days - 186) % 30);
        }
        return [$jy, $jm, $jd];
    }

    private static function at(int $ts, ?string $zone): DateTimeImmutable
    {
        $dt = new DateTimeImmutable('@' . $ts);
        return $dt->setTimezone($zone !== null ? new DateTimeZone($zone) : new DateTimeZone(date_default_timezone_get()));
    }

    public static function parts(int $ts, ?string $zone = null): array
    {
        $dt = self::at($ts, $zone);
        return self::fromGregorian((int) $dt->format('Y'), (int) $dt->format('n'), (int) $dt->format('j'));
    }

    public static function fullDate(int $ts, bool $english = false, ?string $zone = null): string
    {
        [$y, $m, $d] = self::parts($ts, $zone);
        $w = (int) self::at($ts, $zone)->format('w');
        if ($english || Lang::is('en')) {
            return self::WEEKDAYS_EN[$w] . ", {$d} " . self::MONTHS_EN[$m - 1] . " {$y}";
        }
        return fa(self::WEEKDAYS[$w] . " {$d} " . self::MONTHS[$m - 1] . " {$y}");
    }

    public static function numericDate(int $ts, ?string $zone = null): string
    {
        [$y, $m, $d] = self::parts($ts, $zone);
        return fa(sprintf('%04d/%02d/%02d', $y, $m, $d));
    }

    public static function time(int $ts, bool $seconds = false, ?string $zone = null): string
    {
        return fa(self::at($ts, $zone)->format($seconds ? 'H:i:s' : 'H:i'));
    }
}
