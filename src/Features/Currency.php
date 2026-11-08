<?php

final class Currency
{
    private const FEEDS = [
        'https://call4.tgju.org/ajax.json',
        'https://call1.tgju.org/ajax.json',
        'https://call5.tgju.org/ajax.json',
    ];

    private const FIAT = [
        '🇺🇸 دلار'       => 'price_dollar_rl',
        '🇪🇺 یورو'       => 'price_eur',
        '🇬🇧 پوند'       => 'price_gbp',
        '🇦🇪 درهم'       => 'price_aed',
        '🇹🇷 لیر'        => 'price_try',
        '🇨🇭 فرانک'      => 'price_chf',
        '🇨🇦 دلار کانادا' => 'price_cad',
        '🇨🇳 یوان'       => 'price_cny',
        '🇷🇺 روبل'       => 'price_rub',
        '🇮🇳 روپیه'      => 'price_inr',
        '🇮🇶 دینار'      => 'price_iqd',
        '🇦🇫 افغانی'     => 'price_afn',
    ];

    private const CRYPTO = [
        '₿ بیت‌کوین'   => 'crypto-bitcoin',
        '💠 اتریوم'    => 'crypto-ethereum',
        '🟡 بایننس'    => 'crypto-binance-coin',
        '🟣 سولانا'    => 'crypto-solana',
        '💎 گرام'       => 'crypto-toncoin',
        '⚪ ریپل'      => 'crypto-ripple',
        '🔴 ترون'      => 'crypto-tron',
        '🐕 دوج‌کوین'  => 'crypto-dogecoin',
        '🥈 لایت‌کوین' => 'crypto-litecoin',
    ];

    private const MESGHAL_GRAMS = 4.3318;

    private static function fetchFeed(): ?array
    {
        foreach (self::FEEDS as $url) {
            $data = Http::getJson($url, [], 8);
            if (isset($data['current']) && is_array($data['current'])) {
                return $data['current'];
            }
        }
        return null;
    }

    public static function feed(): ?array
    {
        return self::fetchFeed();
    }

    private static function num(array $feed, string $key): ?float
    {
        $raw = $feed[$key]['p'] ?? null;
        if (!is_scalar($raw)) {
            return null;
        }
        $clean = str_replace([',', ' '], '', (string) $raw);
        return is_numeric($clean) ? (float) $clean : null;
    }

    private static function chg(array $feed, string $key): ?float
    {
        $row = $feed[$key] ?? null;
        $v   = null;
        if (is_array($row)) {
            $raw = (string) ($row['dp'] ?? '');
            if (preg_match('/\d+(?:\.\d+)?/', str_replace(['٫', ','], ['.', ''], $raw), $m) === 1) {
                $v = (float) $m[0];
                $v = (($row['dt'] ?? '') === 'low' || str_contains($raw, '-')) ? -$v : $v;
            }
        }
        if ($v === null || abs($v) < 0.005) {
            return PriceCard::changeByFeedKey($key, $feed) ?? $v;
        }
        return $v;
    }

    public static function rates(): ?array
    {
        return Store::remember('cache', 'rates', 120, static function () {
            $feed = self::fetchFeed();
            if ($feed === null) {
                return null;
            }
            $out = ['fiat' => [], 'crypto' => [], 'gold' => [], 'chg' => ['fiat' => [], 'crypto' => [], 'gold' => [], 'tether' => null]];
            foreach (self::FIAT as $label => $key) {
                $rial                        = self::num($feed, $key);
                $out['fiat'][$label]         = $rial !== null ? $rial / 10 : null;
                $out['chg']['fiat'][$label]  = self::chg($feed, $key);
            }
            foreach (self::CRYPTO as $label => $key) {
                $out['crypto'][$label]        = self::num($feed, $key);
                $out['chg']['crypto'][$label] = self::chg($feed, $key);
            }
            $tether          = self::num($feed, 'crypto-tether-irr');
            $gram            = self::num($feed, 'geram18');
            $coin            = self::num($feed, 'sekee');
            $out['tether']   = $tether !== null ? $tether / 10 : null;
            $out['gold']     = [
                'coin'    => $coin !== null ? $coin / 10 : null,
                'gram'    => $gram !== null ? $gram / 10 : null,
                'mesghal' => $gram !== null ? $gram / 10 * self::MESGHAL_GRAMS : null,
                'ounce'   => self::num($feed, 'ons'),
            ];
            $out['chg']['tether'] = self::chg($feed, 'crypto-tether-irr');
            $out['chg']['gold']   = [
                'coin'    => self::chg($feed, 'sekee'),
                'gram'    => self::chg($feed, 'geram18'),
                'mesghal' => self::chg($feed, 'geram18'),
                'ounce'   => self::chg($feed, 'ons'),
            ];
            return $out;
        });
    }

    private static function toman(?float $v): string
    {
        return $v === null ? '—' : number_format($v) . ' ت';
    }

    private static function usd(?float $v): string
    {
        if ($v === null) {
            return '—';
        }
        return '$' . ($v >= 1 ? number_format($v, 2) : rtrim(rtrim(number_format($v, 6, '.', ''), '0'), '.'));
    }

    public static function message(): string
    {
        $now  = time();
        $r    = self::rates();
        $head = "<b>💱 نرخ لحظه‌ای بازار</b>\n🕒 " . Jalali::fullDate($now) . ' · ' . Jalali::time($now);

        if ($r === null) {
            return $head . "\n\n⚠️ الان به منبع قیمت‌ها دسترسی ندارم؛\nچند لحظه دیگه دوباره بزن.";
        }

        $l   = [$head, '', '<b>💵 ارزها</b>'];
        foreach ($r['fiat'] as $label => $v) {
            $l[] = "{$label}: <code>" . self::toman($v) . '</code>';
        }
        $g   = $r['gold'];
        $l[] = '';
        $l[] = '<b>🥇 طلا و سکه</b>';
        $l[] = '🪙 سکه امامی: <code>' . self::toman($g['coin']) . '</code>';
        $l[] = '🔸 مثقال طلا: <code>' . self::toman($g['mesghal']) . '</code>';
        $l[] = '🔹 گرم ۱۸ عیار: <code>' . self::toman($g['gram']) . '</code>';
        $l[] = '🌕 انس جهانی: <code>' . self::usd($g['ounce']) . '</code>';
        $l[] = '';
        $l[] = '<b>🪙 ارز دیجیتال</b>';
        $l[] = '💵 تتر: <code>' . self::toman($r['tether']) . '</code>';
        foreach ($r['crypto'] as $label => $v) {
            $l[] = "{$label}: <code>" . self::usd($v) . '</code>';
        }
        $l[] = '';
        $l[] = '<i>ت = تومان · منبع: بازار آزاد</i>';
        return implode("\n", $l);
    }

    private static function faNum(string $n): string
    {
        return strtr(fa($n), [',' => '٬', '.' => '٫']);
    }

    private static function tomanFa(?float $v): string
    {
        return $v === null ? '—' : self::faNum(number_format($v));
    }

    private static function usdFa(?float $v): string
    {
        return $v === null ? '—' : '$' . self::faNum(substr(self::usd($v), 1));
    }

    private static function pct(?float $v): string
    {
        if ($v === null) {
            return '—';
        }
        if (abs($v) < 0.005) {
            return '▫️ ۰٪';
        }
        return ($v > 0 ? '🟢 ▲ ' : '🔴 ▼ ') . self::faNum(number_format(abs($v), 2)) . '٪';
    }

    public static function richMessage(string $view = 'fiat'): string
    {
        $now  = time();
        $r    = self::rates();
        $head = '**💱 نرخ لحظه‌ای بازار**' . "\n🕒 " . Jalali::fullDate($now) . ' · ' . Jalali::time($now);

        if ($r === null) {
            return $head . "\n\n⚠️ الان به منبع قیمت‌ها دسترسی ندارم؛\nچند لحظه دیگه دوباره بزن.";
        }

        $chg = $r['chg'] ?? [];
        $g   = $r['gold'];

        if ($view === 'gold') {
            $title = '🥇 طلا و سکه';
            $rows  = [
                ['🪙 سکه امامی', self::tomanFa($g['coin']) . ' ت', self::pct($chg['gold']['coin'] ?? null)],
                ['🔸 مثقال طلا', self::tomanFa($g['mesghal']) . ' ت', self::pct($chg['gold']['mesghal'] ?? null)],
                ['🔹 گرم ۱۸ عیار', self::tomanFa($g['gram']) . ' ت', self::pct($chg['gold']['gram'] ?? null)],
                ['🌕 انس جهانی', self::usdFa($g['ounce']), self::pct($chg['gold']['ounce'] ?? null)],
            ];
            $table = Rich::mdTable(['طلا', 'قیمت', 'تغییر'], $rows);
        } elseif ($view === 'crypto') {
            $title = '🪙 ارز دیجیتال';
            $rows  = [];
            foreach ($r['crypto'] as $label => $v) {
                $rows[] = [$label, self::usdFa($v), self::pct($chg['crypto'][$label] ?? null)];
            }
            $table = Rich::mdTable(['ارز', 'قیمت', 'تغییر'], $rows);
        } else {
            $title = '💵 ارزها';
            $rows  = [['💵 تتر', self::tomanFa($r['tether']) . ' ت', self::pct($chg['tether'] ?? null)]];
            foreach ($r['fiat'] as $label => $v) {
                $rows[] = [$label, self::tomanFa($v) . ' ت', self::pct($chg['fiat'][$label] ?? null)];
            }
            $table = Rich::mdTable(['ارز', 'قیمت', 'تغییر'], $rows);
        }

        return $head . "\n\n**{$title}**\n\n" . $table . "\n\n_ت = تومان · منبع: بازار آزاد_";
    }

    public static function keyboard(string $view = 'fiat'): array
    {
        $tabs = [];
        foreach (['fiat' => '💵 ارز', 'gold' => '🥇 طلا', 'crypto' => '🪙 کریپتو'] as $k => $label) {
            $tabs[] = $k === $view ? btn($label . ' ✓', 'rates:' . $k, 'success') : btn($label, 'rates:' . $k, 'primary');
        }
        return ikb([$tabs, [btn('🔄 بروزرسانی', 'rates:refresh:' . $view, 'danger')]]);
    }

    public static function pendingKeyboard(): array
    {
        return ikb([[btn('📊 نمایش نرخ‌ها', 'rates:show', 'success')]]);
    }

    public static function onCallback(array $cq, string $data, ?string $imid = null): void
    {
        $tg   = App::tg();
        $view = preg_match('/^rates:(?:refresh:)?(fiat|gold|crypto)$/', $data, $m) === 1 ? $m[1] : 'fiat';
        if (self::rates() === null) {
            $tg->alert($cq, '⚠️ الان نتونستم نرخ‌ها رو بگیرم؛' . "\n" . 'یه‌کم دیگه امتحان کن.');
            return;
        }
        Rich::editCbMd($cq, $imid, self::richMessage($view), self::keyboard($view));
        $tg->answer($cq, str_starts_with($data, 'rates:refresh') ? '🔄 به‌روز شد' : null);
    }
}
