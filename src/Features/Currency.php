<?php

final class Currency
{
    private const FIAT = [
        'price_dollar_rl' => ['🇺🇸', 'دلار', 'US Dollar'],
        'price_eur'       => ['🇪🇺', 'یورو', 'Euro'],
        'price_gbp'       => ['🇬🇧', 'پوند', 'British Pound'],
        'price_aed'       => ['🇦🇪', 'درهم', 'UAE Dirham'],
        'price_try'       => ['🇹🇷', 'لیر', 'Turkish Lira'],
        'price_chf'       => ['🇨🇭', 'فرانک', 'Swiss Franc'],
        'price_cad'       => ['🇨🇦', 'دلار کانادا', 'Canadian Dollar'],
        'price_cny'       => ['🇨🇳', 'یوان', 'Chinese Yuan'],
        'price_rub'       => ['🇷🇺', 'روبل', 'Russian Ruble'],
        'price_inr'       => ['🇮🇳', 'روپیه', 'Indian Rupee'],
        'price_iqd'       => ['🇮🇶', 'دینار', 'Iraqi Dinar'],
        'price_afn'       => ['🇦🇫', 'افغانی', 'Afghan Afghani'],
    ];

    private const CRYPTO = [
        'crypto-bitcoin'      => ['₿', 'بیت‌کوین', 'Bitcoin'],
        'crypto-ethereum'     => ['💠', 'اتریوم', 'Ethereum'],
        'crypto-binance-coin' => ['🟡', 'بایننس', 'Binance'],
        'crypto-solana'       => ['🟣', 'سولانا', 'Solana'],
        'crypto-toncoin'      => ['💎', 'گرام', 'Gram'],
        'crypto-ripple'       => ['⚪', 'ریپل', 'Ripple'],
        'crypto-tron'         => ['🔴', 'ترون', 'Tron'],
        'crypto-dogecoin'     => ['🐕', 'دوج‌کوین', 'Dogecoin'],
        'crypto-litecoin'     => ['🥈', 'لایت‌کوین', 'Litecoin'],
    ];

    private static function label(array $e): string
    {
        return $e[0] . ' ' . L($e[1], $e[2]);
    }

    public static function unavailable(): string
    {
        return L("⚠️ الان به منبع قیمت‌ها دسترسی ندارم؛\nچند لحظه دیگه دوباره بزن.", "⚠️ I can’t reach the price source right now;\nplease try again in a moment.");
    }

    private static function headText(): string
    {
        return L('نرخ لحظه‌ای بازار', 'Live market rates');
    }

    private static function sourceNote(): string
    {
        return L('ت = تومان · منبع: بازار آزاد', 'T = Toman · Source: open market');
    }

    public static function feed(): ?array
    {
        return null; // prices now come from ezrate only (see PriceCard::ezByFeedKey)
    }

    public static function rates(): ?array
    {
        return Store::remember('cache', 'rates3', 60, static function () {
            $out = ['fiat' => [], 'crypto' => [], 'gold' => [], 'chg' => ['fiat' => [], 'crypto' => [], 'gold' => [], 'tether' => null]];
            $any = false;
            $get = static function (string $key, bool $mesh = false) use (&$any): array {
                $q = PriceCard::ezByFeedKey($key, $mesh);
                if ($q !== null) {
                    $any = true;
                }
                return [$q['p'] ?? null, $q['dp'] ?? null];
            };
            foreach (self::FIAT as $key => $e) {
                [$out['fiat'][$key], $out['chg']['fiat'][$key]] = $get($key);
            }
            foreach (self::CRYPTO as $key => $e) {
                [$out['crypto'][$key], $out['chg']['crypto'][$key]] = $get($key);
            }
            [$out['tether'], $out['chg']['tether']] = $get('crypto-tether-irr');
            [$coin, $coinC]   = $get('sekee');
            [$gram, $gramC]   = $get('geram18');
            [$mesh, $meshC]   = $get('geram18', true);
            [$ons, $onsC]     = $get('ons');
            $out['gold'] = ['coin' => $coin, 'gram' => $gram, 'mesghal' => $mesh, 'ounce' => $ons];
            $out['chg']['gold'] = ['coin' => $coinC, 'gram' => $gramC, 'mesghal' => $meshC, 'ounce' => $onsC];
            return $any ? $out : null;
        });
    }

    private static function toman(?float $v): string
    {
        return $v === null ? '—' : number_format($v) . L(' ت', ' T');
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
        $head = '<b>💱 ' . self::headText() . "</b>\n🕒 " . Clock::stamp($now);

        if ($r === null) {
            return $head . "\n\n" . self::unavailable();
        }

        $l   = [$head, '', '<b>💵 ' . L('ارزها', 'Currencies') . '</b>'];
        foreach ($r['fiat'] as $key => $v) {
            $l[] = self::label(self::FIAT[$key]) . ': <code>' . self::toman($v) . '</code>';
        }
        $g   = $r['gold'];
        $l[] = '';
        $l[] = '<b>🥇 ' . L('طلا و سکه', 'Gold & coins') . '</b>';
        $l[] = '🪙 ' . self::goldName('coin') . ': <code>' . self::toman($g['coin']) . '</code>';
        $l[] = '🔸 ' . self::goldName('mesghal') . ': <code>' . self::toman($g['mesghal']) . '</code>';
        $l[] = '🔹 ' . self::goldName('gram') . ': <code>' . self::toman($g['gram']) . '</code>';
        $l[] = '🌕 ' . self::goldName('ounce') . ': <code>' . self::usd($g['ounce']) . '</code>';
        $l[] = '';
        $l[] = '<b>🪙 ' . L('ارز دیجیتال', 'Crypto') . '</b>';
        $l[] = '💵 ' . L('تتر', 'Tether') . ': <code>' . self::toman($r['tether']) . '</code>';
        foreach ($r['crypto'] as $key => $v) {
            $l[] = self::label(self::CRYPTO[$key]) . ': <code>' . self::usd($v) . '</code>';
        }
        $l[] = '';
        $l[] = '<i>' . self::sourceNote() . '</i>';
        return implode("\n", $l);
    }

    private static function goldName(string $k): string
    {
        return match ($k) {
            'coin'    => L('سکه امامی', 'Imami coin'),
            'mesghal' => L('مثقال طلا', 'Gold mithqal'),
            'gram'    => L('گرم ۱۸ عیار', '18k gold gram'),
            default   => L('انس جهانی', 'Global ounce'),
        };
    }

    private static function faNum(string $n): string
    {
        return Lang::is('en') ? $n : strtr(fa($n), [',' => '٬', '.' => '٫']);
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
            return '▫️ ' . L('۰٪', '0%');
        }
        return ($v > 0 ? '🟢 ▲ ' : '🔴 ▼ ') . self::faNum(number_format(abs($v), 2)) . L('٪', '%');
    }

    public static function richMessage(string $view = 'fiat'): string
    {
        $now  = time();
        $r    = self::rates();
        $head = '**💱 ' . self::headText() . '**' . "\n🕒 " . Clock::stamp($now);

        if ($r === null) {
            return $head . "\n\n" . self::unavailable();
        }

        $chg = $r['chg'] ?? [];
        $g   = $r['gold'];

        if ($view === 'gold') {
            $title = '🥇 ' . L('طلا و سکه', 'Gold & coins');
            $rows  = [
                ['🪙 ' . self::goldName('coin'), self::tomanFa($g['coin']) . L(' ت', ' T'), self::pct($chg['gold']['coin'] ?? null)],
                ['🔸 ' . self::goldName('mesghal'), self::tomanFa($g['mesghal']) . L(' ت', ' T'), self::pct($chg['gold']['mesghal'] ?? null)],
                ['🔹 ' . self::goldName('gram'), self::tomanFa($g['gram']) . L(' ت', ' T'), self::pct($chg['gold']['gram'] ?? null)],
                ['🌕 ' . self::goldName('ounce'), self::usdFa($g['ounce']), self::pct($chg['gold']['ounce'] ?? null)],
            ];
            $table = Rich::mdTable([L('طلا', 'Gold'), L('قیمت', 'Price'), L('تغییر', 'Change')], $rows);
        } elseif ($view === 'crypto') {
            $title = '🪙 ' . L('ارز دیجیتال', 'Crypto');
            $rows  = [];
            foreach ($r['crypto'] as $key => $v) {
                $rows[] = [self::label(self::CRYPTO[$key]), self::usdFa($v), self::pct($chg['crypto'][$key] ?? null)];
            }
            $table = Rich::mdTable([L('ارز', 'Currency'), L('قیمت', 'Price'), L('تغییر', 'Change')], $rows);
        } else {
            $title = '💵 ' . L('ارزها', 'Currencies');
            $rows  = [['💵 ' . L('تتر', 'Tether'), self::tomanFa($r['tether']) . L(' ت', ' T'), self::pct($chg['tether'] ?? null)]];
            foreach ($r['fiat'] as $key => $v) {
                $rows[] = [self::label(self::FIAT[$key]), self::tomanFa($v) . L(' ت', ' T'), self::pct($chg['fiat'][$key] ?? null)];
            }
            $table = Rich::mdTable([L('ارز', 'Currency'), L('قیمت', 'Price'), L('تغییر', 'Change')], $rows);
        }

        return $head . "\n\n**{$title}**\n\n" . $table . "\n\n_" . self::sourceNote() . '_';
    }

    public static function keyboard(string $view = 'fiat'): array
    {
        $tabs = [];
        foreach (['fiat' => '💵 ' . L('ارز', 'Currency'), 'gold' => '🥇 ' . L('طلا', 'Gold'), 'crypto' => '🪙 ' . L('کریپتو', 'Crypto')] as $k => $label) {
            $tabs[] = $k === $view ? btn($label . ' ✓', 'rates:' . $k, 'success') : btn($label, 'rates:' . $k, 'primary');
        }
        return ikb([$tabs, [btn('🔄 ' . L('بروزرسانی', 'Refresh'), 'rates:refresh:' . $view, 'danger')]]);
    }

    public static function pendingKeyboard(): array
    {
        return ikb([[btn('📊 ' . L('نمایش نرخ‌ها', 'Show rates'), 'rates:show', 'success')]]);
    }

    public static function onCallback(array $cq, string $data, ?string $imid = null): void
    {
        $tg   = App::tg();
        $view = preg_match('/^rates:(?:refresh:)?(fiat|gold|crypto)$/', $data, $m) === 1 ? $m[1] : 'fiat';
        if (self::rates() === null) {
            $tg->alert($cq, L('⚠️ الان نتونستم نرخ‌ها رو بگیرم؛', '⚠️ Couldn’t fetch the rates right now;') . "\n" . L('یه‌کم دیگه امتحان کن.', 'please try again in a bit.'));
            return;
        }
        Rich::editCbMd($cq, $imid, self::richMessage($view), self::keyboard($view));
        $tg->answer($cq, str_starts_with($data, 'rates:refresh') ? L('🔄 به‌روز شد', '🔄 Updated') : null);
    }
}
