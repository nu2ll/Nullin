<?php

final class PriceCard
{
    // [کلید فید، نام‌های قابل‌قبول، اسم، واحد، نماد، نوع]
    // نوع: irr = ریال در فید (تقسیم بر ۱۰ می‌شه تومان)، usd = قیمت دلاری، mesh = مثقال (از گرم ۱۸ عیار)
    private const ASSETS = [
        'usd'  => ['price_dollar_rl', ['دلار', 'دلار آمریکا', 'دلار امریکا', 'usd', 'dollar', '$'], 'دلار آمریکا', 'واحد', '$', 'irr'],
        'eur'  => ['price_eur', ['یورو', 'eur', 'euro'], 'یورو', 'واحد', '€', 'irr'],
        'gbp'  => ['price_gbp', ['پوند', 'پوند انگلیس', 'gbp'], 'پوند انگلیس', 'واحد', '£', 'irr'],
        'aed'  => ['price_aed', ['درهم', 'درهم امارات', 'aed'], 'درهم امارات', 'واحد', 'AED', 'irr'],
        'try'  => ['price_try', ['لیر', 'لیر ترکیه', 'try'], 'لیر ترکیه', 'واحد', '₺', 'irr'],
        'chf'  => ['price_chf', ['فرانک', 'فرانک سوئیس', 'فرانک سویس', 'chf'], 'فرانک سوئیس', 'واحد', 'CHF', 'irr'],
        'cad'  => ['price_cad', ['دلار کانادا', 'cad'], 'دلار کانادا', 'واحد', 'C$', 'irr'],
        'cny'  => ['price_cny', ['یوان', 'یوان چین', 'cny'], 'یوان چین', 'واحد', '¥', 'irr'],
        'rub'  => ['price_rub', ['روبل', 'روبل روسیه', 'rub'], 'روبل روسیه', 'واحد', '₽', 'irr'],
        'inr'  => ['price_inr', ['روپیه', 'روپیه هند', 'inr'], 'روپیه هند', 'واحد', '₹', 'irr'],
        'iqd'  => ['price_iqd', ['دینار', 'دینار عراق', 'iqd'], 'دینار عراق', 'واحد', 'IQD', 'irr'],
        'afn'  => ['price_afn', ['افغانی', 'afn'], 'افغانی', 'واحد', 'AFN', 'irr'],
        'usdt' => ['crypto-tether-irr', ['تتر', 'usdt', 'tether'], 'تتر', 'واحد', '₮', 'irr'],
        'coin' => ['sekee', ['سکه', 'سکه امامی'], 'سکه امامی', 'عدد', '◎', 'irr'],
        'gold' => ['geram18', ['طلا', 'گرم طلا', 'طلا ۱۸', 'طلای ۱۸', 'طلا 18', 'طلای 18', 'طلای18', 'طلا18', 'گرم 18', 'گرم ۱۸'], 'طلای ۱۸ عیار', 'گرم', 'Au', 'irr'],
        'mesh' => ['geram18', ['مثقال', 'مثقال طلا', 'مثقال ۱۸', 'مثقال 18'], 'مثقال طلا', 'عدد', 'Au', 'mesh'],
        'ons'  => ['ons', ['انس طلا', 'انس جهانی', 'اونس', 'اونس طلا', 'ons', 'ounce'], 'انس جهانی طلا', 'واحد', 'Au', 'usd'],
        'btc'  => ['crypto-bitcoin', ['بیت کوین', 'بیتکوین', 'btc', 'bitcoin'], 'بیت‌کوین', 'واحد', 'BTC', 'usd'],
        'eth'  => ['crypto-ethereum', ['اتریوم', 'eth', 'ethereum'], 'اتریوم', 'واحد', 'ETH', 'usd'],
        'bnb'  => ['crypto-binance-coin', ['بایننس', 'بایننس کوین', 'bnb', 'binance'], 'بایننس کوین', 'واحد', 'BNB', 'usd'],
        'sol'  => ['crypto-solana', ['سولانا', 'sol', 'solana'], 'سولانا', 'واحد', 'SOL', 'usd'],
        'ton'  => ['crypto-toncoin', ['تون کوین', 'تونکوین', 'toncoin'], 'تون‌کوین', 'واحد', 'TON', 'usd'],
        'xrp'  => ['crypto-ripple', ['ریپل', 'xrp', 'ripple'], 'ریپل', 'واحد', 'XRP', 'usd'],
        'trx'  => ['crypto-tron', ['ترون', 'trx', 'tron'], 'ترون', 'واحد', 'TRX', 'usd'],
        'doge' => ['crypto-dogecoin', ['دوج', 'دوج کوین', 'دوجکوین', 'doge', 'dogecoin'], 'دوج‌کوین', 'واحد', 'DOGE', 'usd'],
        'ltc'  => ['crypto-litecoin', ['لایت کوین', 'لایتکوین', 'ltc', 'litecoin'], 'لایت‌کوین', 'واحد', 'LTC', 'usd'],
    ];

    private static function norm(string $s): string
    {
        $s = strtr($s, ['ي' => 'ی', 'ى' => 'ی', 'ك' => 'ک', "\u{200C}" => ' ', 'ۀ' => 'ه', 'ة' => 'ه']);
        $s = preg_replace('/\s+/u', ' ', $s) ?? $s;
        return mb_strtolower(mbTrim($s));
    }

    private static function find(string $name): ?string
    {
        static $map = null;
        if ($map === null) {
            $map = [];
            foreach (self::ASSETS as $k => $a) {
                foreach ($a[1] as $alias) {
                    $map[self::norm($alias)] = $k;
                }
            }
        }
        return $map[self::norm($name)] ?? null;
    }

    public static function match(string $text, bool $loose): ?array
    {
        $t = en(mbTrim($text));
        $t = strtr($t, ['٬' => ',', '،' => ',', '٫' => '.']);
        $t = preg_replace('/^قیمت\s+/u', '', $t) ?? $t;
        $t = preg_replace('/\s*(?:چنده|چقدره|چقدر|چند)?[؟?!.\s]*$/u', '', $t) ?? $t;
        if ($t === '' || mb_strlen($t) > 40) {
            return null;
        }
        $amount = null;
        $name   = $t;
        if (preg_match('/^(\d[\d,]*(?:\.\d+)?)\s*(هزار|میلیون|میلیارد)?\s*(.+)$/u', $t, $m)) {
            $amount = (float) str_replace(',', '', $m[1]);
            $amount *= match ($m[2]) {
                'هزار'   => 1e3,
                'میلیون' => 1e6,
                'میلیارد' => 1e9,
                default  => 1,
            };
            $name = $m[3];
        } elseif (!$loose) {
            return null;
        }
        $asset = self::find($name);
        if ($asset === null) {
            return null;
        }
        $amount ??= 1.0;
        if ($amount <= 0 || $amount > 1e12) {
            return null;
        }
        return ['asset' => $asset, 'amount' => $amount];
    }

    private static function n($raw): ?float
    {
        if (!is_scalar($raw)) {
            return null;
        }
        $c = str_replace([',', ' '], '', (string) $raw);
        return is_numeric($c) ? (float) $c : null;
    }

    private static function quotes(): ?array
    {
        return Store::remember('cache', 'pc_quotes', 90, static function () {
            $feed = Currency::feed();
            if ($feed === null) {
                return null;
            }
            $out = [];
            foreach (self::ASSETS as $k => $a) {
                $row = $feed[$a[0]] ?? null;
                $p   = is_array($row) ? self::n($row['p'] ?? null) : null;
                if ($p === null) {
                    continue;
                }
                $div  = $a[5] === 'usd' ? 1.0 : 10.0;
                $mult = $a[5] === 'mesh' ? 4.3318 : 1.0;
                $f    = $mult / $div;
                $d    = self::n($row['d'] ?? null) ?? 0.0;
                $neg  = ($row['dt'] ?? '') === 'low' || str_starts_with(trim((string) ($row['dp'] ?? '')), '-');
                $h    = self::n($row['h'] ?? null);
                $l    = self::n($row['l'] ?? null);
                $dp      = self::n(str_replace(['%', '+', '-'], '', (string) ($row['dp'] ?? '')));
                $out[$k] = [
                    'dp' => $dp !== null ? ($neg ? -abs($dp) : abs($dp)) : null,
                    'p' => $p * $f,
                    'h' => $h !== null ? $h * $f : null,
                    'l' => $l !== null ? $l * $f : null,
                    'd' => ($neg ? -abs($d) : abs($d)) * $f,
                    't' => (string) ($row['t'] ?? ''),
                ];
                self::record($k, $p * $f);
            }
            return $out ?: null;
        });
    }

    private static function record(string $k, float $p): void
    {
        Store::update('pricehist', $k, static function ($h) use ($p) {
            $h   = is_array($h) ? $h : [];
            $now = time();
            if ($h && $now - (int) end($h)[0] < 100) {
                return null;
            }
            $h[] = [$now, $p];
            $h   = array_values(array_filter($h, static fn($r) => $now - (int) $r[0] <= 86400));
            return array_slice($h, -800);
        });
    }

    public static function tick(): void
    {
        static $last = 0;
        if (time() - $last < 120 || !Switches::on('rates')) {
            return;
        }
        $last = time();
        self::quotes();
    }

    private static function points(string $k, array $q): array
    {
        $now = time();
        $h   = Store::get('pricehist', $k, []);
        $pts = [];
        foreach (is_array($h) ? $h : [] as $r) {
            if ($now - (int) $r[0] <= 86400) {
                $pts[] = (float) $r[1];
            }
        }
        if (count($pts) < 3) {
            $pts = [$q['p'] - $q['d'], $q['p']];
        } else {
            $pts[count($pts) - 1] = $q['p'];
        }
        return $pts;
    }

    private static function fmt(float $v): string
    {
        return number_format(round($v));
    }

    private static function fmtU(float $v): string
    {
        if (abs($v) >= 1000) {
            return number_format(round($v));
        }
        if (abs($v) >= 1) {
            return number_format($v, 2);
        }
        return rtrim(rtrim(number_format($v, 6, '.', ''), '0'), '.') ?: '0';
    }

    private static function amountText(float $a): string
    {
        return $a == floor($a) ? number_format($a) : rtrim(rtrim(number_format($a, 4), '0'), '.');
    }

    private static function render(array $data): ?string
    {
        $script = (defined('NULLINI_ROOT') ? NULLINI_ROOT : dirname(__DIR__, 2)) . '/bin/pricecard.py';
        if (!is_file($script) || !function_exists('proc_open')) {
            return null;
        }
        $tmp = tempnam(sys_get_temp_dir(), 'nl_pc_');
        if ($tmp === false) {
            return null;
        }
        $data['out'] = $tmp;
        $proc = @proc_open(['timeout', '25', 'python3', $script], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($proc)) {
            @unlink($tmp);
            return null;
        }
        fwrite($pipes[0], (string) json_encode($data, JSON_UNESCAPED_UNICODE));
        fclose($pipes[0]);
        stream_get_contents($pipes[1]);
        $err = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($proc);
        $bytes = is_file($tmp) && filesize($tmp) > 0 ? file_get_contents($tmp) : false;
        @unlink($tmp);
        if ($bytes === false) {
            logError('pricecard', mb_substr(trim($err), -300));
            return null;
        }
        return $bytes;
    }

    public static function send(array $msg, array $m): void
    {
        $tg     = App::tg();
        $chatId = $msg['chat']['id'];
        $tg->action($chatId, 'upload_photo');

        $q = (self::quotes() ?? [])[$m['asset']] ?? null;
        if ($q === null) {
            $tg->reply($msg, "⚠️ الان به منبع قیمت‌ها دسترسی ندارم؛\nچند لحظه دیگه دوباره بزن.");
            return;
        }
        [, , $noun, $unit, $sym, $mode] = self::ASSETS[$m['asset']];
        $isU    = $mode === 'usd';
        $money  = static fn(float $v): string => $isU ? self::fmtU($v) : self::fmt($v);
        $cur    = $isU ? 'دلار' : 'تومان';
        $where  = $isU ? 'بازار جهانی' : 'تهران';
        $amount = (float) $m['amount'];
        $amt    = self::amountText($amount);
        $total  = $q['p'] * $amount;
        $time   = preg_match('/^\d{1,2}:\d{2}(?::\d{2})?$/', $q['t']) === 1 ? $q['t'] : date('H:i:s');
        $title  = "ارزش {$amt} {$unit} {$noun}";
        $pct    = $q['dp'] ?? (($q['p'] - $q['d']) > 0 ? $q['d'] / ($q['p'] - $q['d']) * 100 : 0.0);
        $usdQ   = $isU ? ((self::quotes() ?? [])['usd']['p'] ?? null) : null;

        $l   = [];
        $l[] = '💵 ' . h(fa($title)) . " ({$where})";
        $l[] = '';
        $l[] = '💰 <b>' . fa($money($total)) . " {$cur}</b>";
        if ($usdQ !== null) {
            $l[] = '🇮🇷 معادل: ' . fa(self::fmt($total * $usdQ)) . ' تومان';
        }
        if ($amount != 1.0) {
            $l[] = '🏷 هر واحد: ' . fa($money($q['p'])) . " {$cur}";
        }
        if ($q['h'] !== null && $q['l'] !== null) {
            $l[] = '';
            $l[] = '🟢 سقف: ' . fa($money($q['h'])) . '   🔴 کف: ' . fa($money($q['l']));
        }
        $l[] = '⌚ ساعت: ' . fa($time);
        $caption = '<blockquote>' . implode("\n", $l) . '</blockquote>';

        $row = [];
        if ($q['h'] !== null && $q['l'] !== null) {
            $row = [
                copyBtn('🟢 سقف: ' . fa($money($q['h'])), $money($q['h']), 'success'),
                copyBtn('🔴 کف: ' . fa($money($q['l'])), $money($q['l']), 'danger'),
            ];
        }
        $kb = ikb([$row, [copyBtn('💰 کپی کل: ' . fa($money($total)) . " {$cur}", $money($total), 'primary')]]);

        $png = self::render([
            'title'  => fa($title) . " — {$where}",
            'unit'   => $cur,
            'value'  => $money($total),
            'pct'    => round($pct, 2),
            'points' => array_map(static fn($v) => $v * $amount, self::points($m['asset'], $q)),
            'dec'    => $isU ? (abs($total) >= 1000 ? 0 : (abs($total) >= 1 ? 2 : 5)) : 0,
            'sym'    => $sym,
            'tag'    => $isU ? 'USD' : 'IRT',
            'footer' => '@' . App::botUsername(),
        ]);
        if ($png !== null && $tg->sendPhotoCard($chatId, $png, $caption, (int) $msg['message_id'], $kb) !== null) {
            return;
        }
        $tg->reply($msg, $caption, ['reply_markup' => $kb]);
    }
}
