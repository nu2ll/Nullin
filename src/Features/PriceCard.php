<?php

final class PriceCard
{
    private const ASSETS = [
        'usd'  => ['price_dollar_rl', ['دلار', 'دلار آمریکا', 'دلار امریکا', 'usd', 'dollar', '$'], 'دلار آمریکا', 'واحد', '$', 'irr', 'US Dollar', ''],
        'eur'  => ['price_eur', ['یورو', 'eur', 'euro'], 'یورو', 'واحد', '€', 'irr', 'Euro', ''],
        'gbp'  => ['price_gbp', ['پوند', 'پوند انگلیس', 'gbp'], 'پوند انگلیس', 'واحد', '£', 'irr', 'British Pound', ''],
        'aed'  => ['price_aed', ['درهم', 'درهم امارات', 'aed'], 'درهم امارات', 'واحد', 'AED', 'irr', 'UAE Dirham', ''],
        'try'  => ['price_try', ['لیر', 'لیر ترکیه', 'try'], 'لیر ترکیه', 'واحد', '₺', 'irr', 'Turkish Lira', ''],
        'chf'  => ['price_chf', ['فرانک', 'فرانک سوئیس', 'فرانک سویس', 'chf'], 'فرانک سوئیس', 'واحد', 'CHF', 'irr', 'Swiss Franc', ''],
        'cad'  => ['price_cad', ['دلار کانادا', 'cad'], 'دلار کانادا', 'واحد', 'C$', 'irr', 'Canadian Dollar', ''],
        'cny'  => ['price_cny', ['یوان', 'یوان چین', 'cny'], 'یوان چین', 'واحد', '¥', 'irr', 'Chinese Yuan', ''],
        'rub'  => ['price_rub', ['روبل', 'روبل روسیه', 'rub'], 'روبل روسیه', 'واحد', '₽', 'irr', 'Russian Ruble', ''],
        'inr'  => ['price_inr', ['روپیه', 'روپیه هند', 'inr'], 'روپیه هند', 'واحد', '₹', 'irr', 'Indian Rupee', ''],
        'iqd'  => ['price_iqd', ['دینار', 'دینار عراق', 'iqd'], 'دینار عراق', 'واحد', 'IQD', 'irr', 'Iraqi Dinar', ''],
        'afn'  => ['price_afn', ['افغانی', 'afn'], 'افغانی', 'واحد', 'AFN', 'irr', 'Afghan Afghani', ''],
        'usdt' => ['crypto-tether-irr', ['تتر', 'usdt', 'tether'], 'تتر', 'واحد', '₮', 'irr', 'Tether', ''],
        'coin' => ['sekee', ['سکه', 'سکه امامی'], 'سکه امامی', 'عدد', '◎', 'irr', 'Imami coin', ''],
        'gold' => ['geram18', ['طلا', 'گرم طلا', 'طلا ۱۸', 'طلای ۱۸', 'طلا 18', 'طلای 18', 'طلای18', 'طلا18', 'گرم 18', 'گرم ۱۸'], 'طلای ۱۸ عیار', 'گرم', 'Au', 'irr', '18k gold', 'gram'],
        'mesh' => ['geram18', ['مثقال', 'مثقال طلا', 'مثقال ۱۸', 'مثقال 18'], 'مثقال طلا', 'عدد', 'Au', 'mesh', 'Gold mithqal', ''],
        'ons'  => ['ons', ['انس', 'انس طلا', 'انس جهانی', 'اونس', 'اونس طلا', 'ons', 'ounce'], 'انس جهانی طلا', 'واحد', 'Au', 'usd', 'Global gold ounce', ''],
        'btc'  => ['crypto-bitcoin', ['بیت', 'بیت کوین', 'بیتکوین', 'btc', 'bitcoin'], 'بیت‌کوین', 'واحد', '₿', 'usd', 'Bitcoin', ''],
        'eth'  => ['crypto-ethereum', ['اتر', 'اتریوم', 'eth', 'ethereum'], 'اتریوم', 'واحد', 'Ξ', 'usd', 'Ethereum', ''],
        'bnb'  => ['crypto-binance-coin', ['بایننس', 'بایننس کوین', 'bnb', 'binance'], 'بایننس کوین', 'واحد', 'BNB', 'usd', 'Binance Coin', ''],
        'sol'  => ['crypto-solana', ['سول', 'سولانا', 'sol', 'solana'], 'سولانا', 'واحد', 'SOL', 'usd', 'Solana', ''],
        'ton'  => ['crypto-gram', ['گرام', 'gram', 'تون', 'تون کوین', 'تونکوین', 'ton', 'toncoin'], 'گرام', 'واحد', 'GRAM', 'usd', 'Gram', ''],
        'xrp'  => ['crypto-ripple', ['ریپل', 'xrp', 'ripple'], 'ریپل', 'واحد', 'XRP', 'usd', 'Ripple', ''],
        'trx'  => ['crypto-tron', ['ترون', 'trx', 'tron'], 'ترون', 'واحد', 'TRX', 'usd', 'Tron', ''],
        'doge' => ['crypto-dogecoin', ['دوج', 'دوج کوین', 'دوجکوین', 'doge', 'dogecoin'], 'دوج‌کوین', 'واحد', 'Ð', 'usd', 'Dogecoin', ''],
        'ltc'  => ['crypto-litecoin', ['لایت', 'لایت کوین', 'لایتکوین', 'ltc', 'litecoin'], 'لایت‌کوین', 'واحد', 'Ł', 'usd', 'Litecoin', ''],
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

    public static function match(string $text, bool $loose, bool $trailing = false): ?array
    {
        $t = en(mbTrim($text));
        $t = strtr($t, ['٬' => ',', '،' => ',', '٫' => '.']);
        $t = mb_strtolower($t);
        $t = preg_replace('/^(?:قیمت|price(?:\s+of)?)\s+/u', '', $t) ?? $t;
        $t = preg_replace('/\s*(?:چنده|چقدره|چقدر|چند|how much)?[؟?!.\s]*$/u', '', $t) ?? $t;
        if ($t === '' || mb_strlen($t) > 40) {
            return null;
        }
        $amount = null;
        $name   = $t;
        if (preg_match('/^(\d[\d,]*(?:\.\d+)?)\s*(هزار|میلیون|میلیارد|(?:thousand|million|billion|k)(?![a-z]))?\s*(.+)$/u', $t, $m)) {
            $amount = (float) str_replace(',', '', $m[1]);
            $amount *= match ($m[2]) {
                'هزار', 'thousand', 'k' => 1e3,
                'میلیون', 'million' => 1e6,
                'میلیارد', 'billion' => 1e9,
                default  => 1,
            };
            $name = $m[3];
        } elseif ($trailing && self::find($t) === null && preg_match('/^(.+?)\s*(\d[\d,]*(?:\.\d+)?)\s*(هزار|میلیون|میلیارد|thousand|million|billion|k)?$/u', $t, $m)) {
            $amount = (float) str_replace(',', '', $m[2]);
            $amount *= match ($m[3] ?? '') {
                'هزار', 'thousand', 'k' => 1e3,
                'میلیون', 'million' => 1e6,
                'میلیارد', 'billion' => 1e9,
                default  => 1,
            };
            $name = mbTrim($m[1]);
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

    private static function feedQuote(string $k, $row): ?array
    {
        $a = self::ASSETS[$k];
        $p = is_array($row) ? self::n($row['p'] ?? null) : null;
        if ($p === null) {
            return null;
        }
        $div   = $a[5] === 'usd' ? 1.0 : 10.0;
        $mult  = $a[5] === 'mesh' ? 4.3318 : 1.0;
        $f     = $mult / $div;
        $dpRaw = (string) ($row['dp'] ?? '');
        $neg   = ($row['dt'] ?? '') === 'low' || str_contains($dpRaw, '-');
        $d     = self::n($row['d'] ?? null) ?? 0.0;
        $h     = self::n($row['h'] ?? null);
        $l     = self::n($row['l'] ?? null);
        $dp    = preg_match('/\d+(?:\.\d+)?/', str_replace(['٫', ','], ['.', ''], $dpRaw), $mm) === 1 ? (float) $mm[0] : null;
        return [
            'dp'  => $dp !== null ? ($neg ? -abs($dp) : abs($dp)) : null,
            'p'   => $p * $f,
            'h'   => $h !== null ? $h * $f : null,
            'l'   => $l !== null ? $l * $f : null,
            'd'   => ($neg ? -abs($d) : abs($d)) * $f,
            't'   => (string) ($row['t'] ?? ''),
            'f'   => $f,
            'pts' => null,
        ];
    }

    public static function changeByFeedKey(string $feedKey, ?array $feed): ?float
    {
        foreach (self::ASSETS as $k => $a) {
            if ($a[0] === $feedKey) {
                return self::quote($k, $feed)['dp'] ?? null;
            }
        }
        return null;
    }

    private static function quote(string $k, ?array $feed): ?array
    {
        return $feed !== null ? self::feedQuote($k, $feed[self::ASSETS[$k][0]] ?? null) : null;
    }

    private static function path(array $c): array
    {
        $out = [$c[0][1]];
        foreach ($c as $x) {
            $seq = $x[4] >= $x[1] ? [$x[3], $x[2], $x[4]] : [$x[2], $x[3], $x[4]];
            foreach ($seq as $v) {
                if (abs($v - $out[count($out) - 1]) > 1e-12) {
                    $out[] = $v;
                }
            }
        }
        if (count($out) < 2) {
            $out[] = $out[0];
        }
        return $out;
    }

    private static function points(string $k, array $q): array
    {
        if (is_array($q['pts'] ?? null) && count($q['pts']) >= 2) {
            return $q['pts'];
        }
        $pts = self::history((string) self::ASSETS[$k][0], (float) ($q['f'] ?? 1.0), (float) $q['p']);
        if (count($pts) < 3) {
            return [$q['p'] - $q['d'], $q['p']];
        }
        return $pts;
    }

    private static function history(string $feedKey, float $f, float $cur): array
    {
        try {
            $j    = Http::getJson('https://api.tgju.org/v1/market/indicator/summary-table-data/' . rawurlencode($feedKey) . '?start=0&length=40', [], 8);
            $rows = is_array($j) ? ($j['data'] ?? null) : null;
            if (!is_array($rows)) {
                return [];
            }
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
            $vals = array_reverse($vals);
            if ($newest === date('Y/m/d')) {
                $vals[count($vals) - 1] = $cur;
            } else {
                $vals[] = $cur;
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

    private static function parts(array $m, ?array $feed, bool $chart): ?array
    {
        $q = self::quote($m['asset'], $feed);
        if ($q === null) {
            return null;
        }
        $asset  = self::ASSETS[$m['asset']];
        $noun   = L($asset[2], $asset[6]);
        $unit   = L($asset[3], $asset[7]);
        $sym    = $asset[4];
        $mode   = $asset[5];
        $isU    = $mode === 'usd';
        $money  = static fn(float $v): string => $isU ? self::fmtU($v) : self::fmt($v);
        $cur    = $isU ? L('دلار', 'USD') : L('تومان', 'Toman');
        $where  = $isU ? L('بازار جهانی', 'global market') : L('تهران', 'Tehran');
        $amount = (float) $m['amount'];
        $amt    = self::amountText($amount);
        if ($q['h'] !== null && $q['l'] !== null) {
            $q['h'] *= $amount;
            $q['l'] *= $amount;
        }
        $total  = $q['p'] * $amount;
        $time   = preg_match('/^\d{1,2}:\d{2}(?::\d{2})?$/', $q['t']) === 1 ? $q['t'] : date('H:i:s');
        $title  = L("ارزش {$amt} {$unit} {$noun}", "Value of {$amt} " . ($unit !== '' ? "{$unit} of " : '') . $noun);
        $pct    = $q['dp'] ?? (($q['p'] - $q['d']) > 0 ? $q['d'] / ($q['p'] - $q['d']) * 100 : 0.0);
        $usdQ   = $isU ? (self::quote('usd', $feed)['p'] ?? null) : null;

        $head = '💵 ' . h(fa($title)) . " ({$where})";
        $g1   = [];
        $g1[] = '💰 ' . fa($money($total)) . " {$cur}";
        if ($usdQ !== null) {
            $g1[] = '🇮🇷 ' . L('معادل', 'Equivalent') . ': ' . fa(self::fmt($total * $usdQ)) . ' ' . L('تومان', 'Toman');
        }
        $g2   = [];
        if ($q['h'] !== null && $q['l'] !== null) {
            $g2[] = '🟢 ' . L('سقف', 'High') . ': ' . fa($money($q['h'])) . '   🔴 ' . L('کف', 'Low') . ': ' . fa($money($q['l']));
        }

        $rows = [[copyBtn('⌚ ' . L('ساعت', 'Time') . ': ' . fa($time), $time, null)]];
        if ($q['h'] !== null && $q['l'] !== null) {
            $rows[] = [
                copyBtn('🟢 ' . L('سقف', 'High') . ': ' . fa($money($q['h'])), $money($q['h']), 'success'),
                copyBtn('🔴 ' . L('کف', 'Low') . ': ' . fa($money($q['l'])), $money($q['l']), 'danger'),
            ];
        }
        $rows[] = [copyBtn('💰 ' . L('کپی کل', 'Copy total') . ': ' . fa($money($total)) . " {$cur}", $money($total), 'primary')];

        return [
            'title'  => fa($title),
            'head'   => $head,
            'g1'     => $g1,
            'g2'     => $g2,
            'kb'     => ikb($rows),
            'render' => [
                'title'  => fa($title) . " — {$where}",
                'unit'   => $cur,
                'value'  => $money($total),
                'pct'    => round($pct, 2),
                'points' => $chart ? array_map(static fn($v) => $v * $amount, self::points($m['asset'], $q)) : [],
                'dec'    => $isU ? (abs($total) >= 1000 ? 0 : (abs($total) >= 1 ? 2 : 5)) : 0,
                'sym'    => $sym,
                'icon'   => $m['asset'],
                'tag'    => $isU ? 'USD' : 'IRT',
                'footer' => '@' . App::botUsername(),
            ],
        ];
    }

    public static function send(array $msg, array $m): void
    {
        $tg     = App::tg();
        $chatId = $msg['chat']['id'];
        $tg->action($chatId, 'upload_photo');

        $p = self::parts($m, Currency::feed(), true);
        if ($p === null) {
            $tg->reply($msg, Currency::unavailable());
            return;
        }
        // positive reaction (thumbs up) on the user's price request
        if (!empty($msg['message_id'])) {
            $tg->react($chatId, (int) $msg['message_id'], '👍');
        }
        $rich = '<img src="tg://photo?id=card"/>'
              . '<blockquote><p>' . $p['head'] . '</p>'
              . '<blockquote><p>' . implode('<br>', $p['g1']) . '</p>'
              . ($p['g2'] ? '<blockquote><p>' . implode('<br>', $p['g2']) . '</p></blockquote>' : '')
              . '</blockquote></blockquote>'
              . Rich::buttonsHtml($p['kb']);

        $png = self::render($p['render']);
        if ($png !== null) {
            $tg->sendRichCard($chatId, $png, $rich, (int) $msg['message_id']);
        }
    }

    private static function inlineText(array $p): string
    {
        return $p['head'] . "\n" . implode("\n", $p['g1']) . ($p['g2'] ? "\n" . implode("\n", $p['g2']) : '');
    }

    public static function inlineArticle(array $m): ?array
    {
        $p = self::parts($m, Currency::feed(), false);
        if ($p === null) {
            return null;
        }
        $key = substr(md5($m['asset'] . '|' . $m['amount']), 0, 12);
        Store::set('pc_pending', $key, ['asset' => $m['asset'], 'amount' => (float) $m['amount']]);
        return Ui::article('pc_' . $key, 'rates', '💵 ' . $p['title'], implode(' · ', $p['g1']), self::inlineText($p), $p['kb']);
    }

    public static function onChosen(string $key, string $imid): void
    {
        $m = Store::get('pc_pending', $key);
        if (!is_array($m) || !isset($m['asset'], $m['amount']) || !isset(self::ASSETS[$m['asset']])) {
            return;
        }
        $p = self::parts($m, Currency::feed(), true);
        if ($p === null) {
            return;
        }
        $text = self::inlineText($p);
        $fid  = null;
        $png  = self::render($p['render']);
        $chat = (string) App::cfg('storage_chat', '');
        $chat = $chat !== '' ? $chat : (string) (App::cfg('admins', [])[0] ?? '');
        if ($png !== null && $chat !== '') {
            $fid = App::tg()->photoFileId($chat, $png);
        }
        if ($fid === null) {
            logWarn('pricecard', 'inline card: no file_id (check STORAGE_CHAT_ID)');
        }
        $r = $fid !== null
            ? Rich::editInlinePhoto($imid, $text, $fid, $p['kb'])
            : Rich::editInline($imid, $text, $p['kb']);
        if (empty($r['ok'])) {
            logWarn('pricecard', 'inline edit failed: ' . (string) ($r['description'] ?? ''));
        }
    }
}
