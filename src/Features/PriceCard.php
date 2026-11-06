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

    // بدون هیچ کشی: هر درخواست مستقیم از API می‌خونه
    private static function quotes(): ?array
    {
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
            $dpRaw = (string) ($row['dp'] ?? '');
            $neg  = ($row['dt'] ?? '') === 'low' || str_contains($dpRaw, '-');
            $d    = self::n($row['d'] ?? null) ?? 0.0;
            $h    = self::n($row['h'] ?? null);
            $l    = self::n($row['l'] ?? null);
            $dp   = preg_match('/\d+(?:\.\d+)?/', str_replace(['٫', ','], ['.', ''], $dpRaw), $mm) === 1 ? (float) $mm[0] : null;
            $out[$k] = [
                'dp' => $dp !== null ? ($neg ? -abs($dp) : abs($dp)) : null,
                'p'  => $p * $f,
                'h'  => $h !== null ? $h * $f : null,
                'l'  => $l !== null ? $l * $f : null,
                'd'  => ($neg ? -abs($d) : abs($d)) * $f,
                't'  => (string) ($row['t'] ?? ''),
                'f'  => $f,
            ];
        }
        return $out ?: null;
    }

    // نمودار: اول کندل‌های ساعتی EZRate (۲۴ ساعت اخیر)؛ اگه کلید نبود یا نیومد، نمودار ۳۰ روزه‌ی tgju؛
    // اگه اون هم نیومد، خط ساده‌ی «قبلی ← الان»
    private static function points(string $k, array $q): array
    {
        $pts = self::ezrate($k, (float) $q['p']);
        if (count($pts) < 6) {
            $pts = self::history((string) self::ASSETS[$k][0], (float) ($q['f'] ?? 1.0), (float) $q['p']);
        }
        if (count($pts) < 3) {
            return [$q['p'] - $q['d'], $q['p']];
        }
        return $pts;
    }

    // نام دارایی تو EZRate (دارایی‌هایی که اینجا نیستن مستقیم می‌رن سراغ نمودار ۳۰ روزه)
    private const EZ = [
        'usd' => 'usd', 'eur' => 'eur', 'gbp' => 'gbp', 'aed' => 'aed', 'try' => 'try', 'cny' => 'cny',
        'cad' => 'cad', 'rub' => 'rub', 'iqd' => 'iqd', 'afn' => 'afn', 'usdt' => 'usdt', 'inr' => 'inr',
        'coin' => 'emami', 'gold' => 'g18', 'mesh' => 'mesghal', 'ons' => 'ounce',
        'btc' => 'btc', 'eth' => 'eth', 'ton' => 'ton', 'trx' => 'trx',
        'bnb' => 'bnb', 'sol' => 'sol', 'xrp' => 'xrp', 'doge' => 'doge', 'ltc' => 'ltc',
    ];

    // کندل ساعتی EZRate؛ کلید از متغیر محیطی EZRATE_KEY. خروجی: حداکثر ۲۴ نقطه، قدیمی ← جدید، یا [] اگه نشد.
    // واحد (تومان یا دلار) هرچی باشه، نمودار طوری مقیاس می‌شه که آخرین نقطه با قیمت زنده‌ی کارت یکی باشه.
    private static function ezrate(string $k, float $cur): array
    {
        $sym = self::EZ[$k] ?? null;
        $key = trim((string) env('EZRATE_KEY', ''));
        if ($sym === null || $key === '' || $cur <= 0) {
            return [];
        }
        try {
            $url  = 'https://api.ezrate.me/v1/history/' . $sym . '?interval=1h&from=' . gmdate('Y-m-d', time() - 2 * 86400)
                  . '&to=' . gmdate('Y-m-d', time() + 86400);
            $j    = Http::getJson($url, ['X-API-Key: ' . $key], 8);
            $rows = is_array($j) ? ($j['data'] ?? null) : null;
            if (!is_array($rows)) {
                return [];
            }
            $c = [];
            foreach ($rows as $r) {
                $v = is_array($r) ? self::n($r['c'] ?? null) : null;
                if ($v !== null && $v > 0) {
                    $c[] = [(string) ($r['t'] ?? ''), $v];
                }
            }
            usort($c, static fn($a, $b) => strcmp($a[0], $b[0]));
            $c = array_slice($c, -24);
            if (count($c) < 6) {
                return [];
            }
            $scale = $cur / $c[count($c) - 1][1];
            if (self::ASSETS[$k][5] !== 'usd' && ($scale < 0.8 || $scale > 1.25)) {
                return [];                       // قیمت EZRate با قیمت کارت جور نیست؛ به نمودار ۳۰ روزه برگرد
            }
            $out = array_map(static fn($x) => $x[1] * $scale, $c);
            $out[count($out) - 1] = $cur;
            return $out;
        } catch (\Throwable $e) {
            return [];
        }
    }

    private static function history(string $feedKey, float $f, float $cur): array
    {
        try {
            $j    = Http::getJson('https://api.tgju.org/v1/market/indicator/summary-table-data/' . rawurlencode($feedKey) . '?start=0&length=40', [], 8);
            $rows = is_array($j) ? ($j['data'] ?? null) : null;
            if (!is_array($rows)) {
                return [];
            }
            // هر ردیف: [باز، کمترین، بیشترین، پایانی، تغییر، درصد، تاریخ میلادی، تاریخ شمسی] و جدیدترین اول
            $vals  = [];
            $newest = '';
            foreach (array_slice($rows, 0, 40) as $r) {
                $c = is_array($r) ? self::n($r[3] ?? null) : null;
                if ($c === null || $c <= 0) {
                    continue;
                }
                if ($vals === []) {
                    $newest = (string) ($r[6] ?? '');
                }
                $vals[] = $c * $f;
            }
            if (count($vals) < 3 || $cur <= 0) {
                return [];
            }
            $vals = array_reverse($vals);            // قدیمی ← جدید
            if ($newest === date('Y/m/d')) {
                $vals[count($vals) - 1] = $cur;      // ردیف امروز هست؛ با قیمت زنده جایگزین
            } else {
                $vals[] = $cur;                      // ردیف امروز نیست؛ قیمت زنده رو آخر اضافه کن
            }
            foreach ($vals as $v) {
                if ($v < $cur * 0.2 || $v > $cur * 5) {
                    return [];
                }
            }
            return array_slice($vals, -30);
        } catch (\Throwable $e) {
            return [];
        }
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

        $all = self::quotes() ?? [];
        $q   = $all[$m['asset']] ?? null;
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
        $usdQ   = $isU ? ($all['usd']['p'] ?? null) : null;

        $head = '💵 ' . h(fa($title)) . " ({$where})";
        $g1   = [];
        $g1[] = '💰 ' . fa($money($total)) . " {$cur}";
        if ($usdQ !== null) {
            $g1[] = '🇮🇷 معادل: ' . fa(self::fmt($total * $usdQ)) . ' تومان';
        }
        $g2 = [];
        if ($q['h'] !== null && $q['l'] !== null) {
            $g2[] = '🟢 سقف: ' . fa($money($q['h'])) . '   🔴 کف: ' . fa($money($q['l']));
        }
        $g2[] = '⌚ ساعت: ' . fa($time);
        // کپشن تو در تو (نقل قول داخل نقل قول)؛ اگه تلگرام قبول نکرد، نسخه‌ی ساده می‌ره
        $nested  = '<blockquote>' . $head . "\n<blockquote>" . implode("\n", $g1) . "</blockquote>\n<blockquote>"
                 . implode("\n", $g2) . '</blockquote></blockquote>';
        $caption = '<blockquote>' . $head . "\n\n" . implode("\n", $g1) . "\n\n" . implode("\n", $g2) . '</blockquote>';

        $row = [];
        if ($q['h'] !== null && $q['l'] !== null) {
            $row = [
                copyBtn('🟢 سقف: ' . fa($money($q['h'])), $money($q['h']), 'success'),
                copyBtn('🔴 کف: ' . fa($money($q['l'])), $money($q['l']), 'danger'),
            ];
        }
        $kb = ikb([$row, [copyBtn('💰 کپی کل: ' . fa($money($total)) . " {$cur}", $money($total), 'primary')]]);

        // نسخه‌ی Rich: نقل‌قول سه‌لایه + دکمه‌های داخل پیام (مثل ربات مایکل)
        $btn = static fn(string $label, string $copy, string $style): string
            => '<tg-button type="copy_text" text="' . h($copy) . '" style="' . $style . '">' . h($label) . '</tg-button>';
        $rich = '<img src="tg://photo?id=card"/>'
              . '<blockquote><p>' . $head . '</p>'
              . '<blockquote><p>' . implode('<br>', $g1) . '</p>'
              . '<blockquote><p>' . implode('<br>', $g2) . '</p></blockquote>'
              . '</blockquote></blockquote>';
        if ($q['h'] !== null && $q['l'] !== null) {
            $rich .= '<tg-button-row>'
                   . $btn('🟢 سقف: ' . fa($money($q['h'])), $money($q['h']), 'success')
                   . $btn('🔴 کف: ' . fa($money($q['l'])), $money($q['l']), 'danger')
                   . '</tg-button-row>';
        }
        $rich .= '<tg-button-row>' . $btn('💰 کپی کل: ' . fa($money($total)) . " {$cur}", $money($total), 'primary') . '</tg-button-row>';

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
        if ($png !== null) {
            if ($tg->sendRichCard($chatId, $png, $rich, (int) $msg['message_id']) !== null) {
                return;
            }
            if ($tg->sendPhotoCard($chatId, $png, $nested, (int) $msg['message_id'], $kb) !== null
                || $tg->sendPhotoCard($chatId, $png, $caption, (int) $msg['message_id'], $kb) !== null) {
                return;
            }
        }
        $tg->reply($msg, $caption, ['reply_markup' => $kb]);
    }
}
