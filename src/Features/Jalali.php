<?php

final class Jalali
{
    private const MONTHS   = ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];
    private const WEEKDAYS = ['یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنجشنبه', 'جمعه', 'شنبه'];

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

    public static function toGregorian(int $jy, int $jm, int $jd): array
    {
        $jy  += 1595;
        $days = -355668 + (365 * $jy) + (intdiv($jy, 33) * 8) + intdiv(($jy % 33) + 3, 4) + $jd + ($jm < 7 ? ($jm - 1) * 31 : (($jm - 7) * 30) + 186);
        $gy   = 400 * intdiv($days, 146097);
        $days %= 146097;
        if ($days > 36524) {
            $days--;
            $gy  += 100 * intdiv($days, 36524);
            $days %= 36524;
            if ($days >= 365) {
                $days++;
            }
        }
        $gy  += 4 * intdiv($days, 1461);
        $days %= 1461;
        if ($days > 365) {
            $gy  += intdiv($days - 1, 365);
            $days = ($days - 1) % 365;
        }
        $gd   = $days + 1;
        $leap = ($gy % 4 === 0 && $gy % 100 !== 0) || $gy % 400 === 0;
        $dim  = [0, 31, $leap ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
        for ($gm = 0; $gm < 13 && $gd > $dim[$gm]; $gm++) {
            $gd -= $dim[$gm];
        }
        return [$gy, $gm, $gd];
    }

    public static function parts(int $ts): array
    {
        return self::fromGregorian((int) date('Y', $ts), (int) date('n', $ts), (int) date('j', $ts));
    }

    public static function fullDate(int $ts): string
    {
        [$y, $m, $d] = self::parts($ts);
        return fa(self::WEEKDAYS[(int) date('w', $ts)] . " {$d} " . self::MONTHS[$m - 1] . " {$y}");
    }

    public static function numericDate(int $ts): string
    {
        [$y, $m, $d] = self::parts($ts);
        return fa(sprintf('%04d/%02d/%02d', $y, $m, $d));
    }

    public static function time(int $ts, bool $seconds = false): string
    {
        return fa(date($seconds ? 'H:i:s' : 'H:i', $ts));
    }
}
