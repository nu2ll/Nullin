<?php

final class PriceCard
{
    private const ASSETS = [
        'usd'  => ['price_dollar_rl', ['دلار', 'دلار آمریکا', 'دلار امریکا', 'usd', 'dollar', '$'], 'دلار آمریکا', 'واحد', '$'],
        'eur'  => ['price_eur', ['یورو', 'eur', 'euro'], 'یورو', 'واحد', '€'],
        'gbp'  => ['price_gbp', ['پوند', 'پوند انگلیس', 'gbp'], 'پوند انگلیس', 'واحد', '£'],
        'aed'  => ['price_aed', ['درهم', 'درهم امارات', 'aed'], 'درهم امارات', 'واحد', 'AED'],
        'try'  => ['price_try', ['لیر', 'لیر ترکیه', 'try'], 'لیر ترکیه', 'واحد', '₺'],
        'cad'  => ['price_cad', ['دلار کانادا', 'cad'], 'دلار کانادا', 'واحد', 'C$'],
        'cny'  => ['price_cny', ['یوان', 'یوان چین', 'cny'], 'یوان چین', 'واحد', '¥'],
        'usdt' => ['crypto-tether-irr', ['تتر', 'usdt', 'tether'], 'تتر', 'واحد', '₮'],
        'coin' => ['sekee', ['سکه', 'سکه امامی'], 'سکه امامی', 'عدد', '◎'],
        'gold' => ['geram18', ['طلا', 'گرم طلا', 'طلا ۱۸', 'طلای ۱۸', 'طلا 18', 'طلای 18', 'طلای18', 'طلا18'], 'طلای ۱۸ عیار', 'گرم', 'Au'],
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
                $d   = self::n($row['d'] ?? null) ?? 0.0;
                $neg = ($row['dt'] ?? '') === 'low' || str_starts_with(trim((string) ($row['dp'] ?? '')), '-');
                $h   = self::n($row['h'] ?? null);
                $l   = self::n($row['l'] ?? null);
                $out[$k] = [
                    'p' => $p / 10,
                    'h' => $h !== null ? $h / 10 : null,
                    'l' => $l !== null ? $l / 10 : null,
                    'd' => ($neg ? -abs($d) : abs($d)) / 10,
                    't' => (string) ($row['t'] ?? ''),
                ];
                self::record($k, $p / 10);
            }
            return $out ?: null;
        });
    }

    private static function record(string $k, float $p): void
    {
        Store::update('pricehist', $k, static function ($h) use ($p) {
            $h   = is_array($h) ? $h : [];
            $now = time();
            if ($h && $now - (int) end($h)[0] < 240) {
                return null;
            }
            $h[] = [$now, $p];
            $h   = array_values(array_filter($h, static fn($r) => $now - (int) $r[0] <= 86400));
            return array_slice($h, -400);
        });
    }

    public static function tick(): void
    {
        static $last = 0;
        if (time() - $last < 300 || !Switches::on('rates')) {
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
        [, , $noun, $unit, $sym] = self::ASSETS[$m['asset']];
        $amount = (float) $m['amount'];
        $amt    = self::amountText($amount);
        $total  = $q['p'] * $amount;
        $time   = preg_match('/^\d{1,2}:\d{2}(?::\d{2})?$/', $q['t']) === 1 ? $q['t'] : date('H:i:s');
        $title  = "ارزش {$amt} {$unit} {$noun}";
        $pct    = ($q['p'] - $q['d']) > 0 ? $q['d'] / ($q['p'] - $q['d']) * 100 : 0.0;

        $l   = [];
        $l[] = '💵 ' . h(fa($title)) . ' (تهران)';
        $l[] = '';
        $l[] = '💰 <b>' . fa(self::fmt($total)) . ' تومان</b>';
        if ($amount != 1.0) {
            $l[] = '🏷 هر واحد: ' . fa(self::fmt($q['p'])) . ' تومان';
        }
        if ($q['h'] !== null && $q['l'] !== null) {
            $l[] = '';
            $l[] = '🟢 سقف: ' . fa(self::fmt($q['h'])) . '   🔴 کف: ' . fa(self::fmt($q['l']));
        }
        $l[] = '⌚ ساعت: ' . fa($time);
        $caption = '<blockquote>' . implode("\n", $l) . '</blockquote>';

        $row = [];
        if ($q['h'] !== null && $q['l'] !== null) {
            $row = [
                copyBtn('🟢 سقف: ' . fa(self::fmt($q['h'])), self::fmt($q['h']), 'success'),
                copyBtn('🔴 کف: ' . fa(self::fmt($q['l'])), self::fmt($q['l']), 'danger'),
            ];
        }
        $kb = ikb([$row, [copyBtn('💰 کپی کل: ' . fa(self::fmt($total)) . ' تومان', self::fmt($total), 'primary')]]);

        $png = self::render([
            'title'  => fa($title) . ' — تهران',
            'unit'   => 'تومان',
            'value'  => self::fmt($total),
            'pct'    => round($pct, 2),
            'points' => array_map(static fn($v) => $v * $amount, self::points($m['asset'], $q)),
            'sym'    => $sym,
            'footer' => '@' . App::botUsername(),
        ]);
        if ($png !== null && $tg->sendPhotoCard($chatId, $png, $caption, (int) $msg['message_id'], $kb) !== null) {
            return;
        }
        $tg->reply($msg, $caption, ['reply_markup' => $kb]);
    }
}
