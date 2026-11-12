<?php

final class UnitConvert
{
    private const CATS = [
        'length' => ['📏', [
            'km' => ['کیلومتر', 1000.0],
            'm'  => ['متر', 1.0],
            'cm' => ['سانتی‌متر', 0.01],
            'mm' => ['میلی‌متر', 0.001],
            'mi' => ['مایل', 1609.344],
            'yd' => ['یارد', 0.9144],
            'ft' => ['فوت', 0.3048],
            'in' => ['اینچ', 0.0254],
        ]],
        'mass' => ['⚖️', [
            't'   => ['تن', 1000.0],
            'kg'  => ['کیلوگرم', 1.0],
            'g'   => ['گرم', 0.001],
            'mg'  => ['میلی‌گرم', 0.000001],
            'lb'  => ['پوند', 0.45359237],
            'oz'  => ['اونس', 0.028349523125],
            'msq' => ['مثقال', 0.0046083],
        ]],
        'volume' => ['🧪', [
            'm3'  => ['متر مکعب', 1000.0],
            'l'   => ['لیتر', 1.0],
            'ml'  => ['میلی‌لیتر', 0.001],
            'gal' => ['گالن', 3.785411784],
        ]],
        'speed' => ['🚀', [
            'kmh' => ['کیلومتر بر ساعت', 0.2777777778],
            'ms'  => ['متر بر ثانیه', 1.0],
            'mph' => ['مایل بر ساعت', 0.44704],
            'kn'  => ['گره دریایی', 0.5144444444],
        ]],
        'area' => ['📐', [
            'km2'  => ['کیلومتر مربع', 1000000.0],
            'ha'   => ['هکتار', 10000.0],
            'm2'   => ['متر مربع', 1.0],
            'ft2'  => ['فوت مربع', 0.09290304],
            'acre' => ['ایکر', 4046.8564224],
        ]],
        'data' => ['💾', [
            'tb' => ['ترابایت', 1099511627776.0],
            'gb' => ['گیگابایت', 1073741824.0],
            'mb' => ['مگابایت', 1048576.0],
            'kb' => ['کیلوبایت', 1024.0],
            'b'  => ['بایت', 1.0],
        ]],
        'temp' => ['🌡', [
            'c' => ['سانتی‌گراد', 0.0],
            'f' => ['فارنهایت', 0.0],
            'k' => ['کلوین', 0.0],
        ]],
    ];

    private const EN = [
        'km' => 'Kilometer', 'm' => 'Meter', 'cm' => 'Centimeter', 'mm' => 'Millimeter', 'mi' => 'Mile', 'yd' => 'Yard', 'ft' => 'Foot', 'in' => 'Inch',
        't' => 'Metric ton', 'kg' => 'Kilogram', 'g' => 'Gram', 'mg' => 'Milligram', 'lb' => 'Pound', 'oz' => 'Ounce', 'msq' => 'Mithqal',
        'm3' => 'Cubic meter', 'l' => 'Liter', 'ml' => 'Milliliter', 'gal' => 'Gallon',
        'kmh' => 'Kilometers per hour', 'ms' => 'Meters per second', 'mph' => 'Miles per hour', 'kn' => 'Knot',
        'km2' => 'Square kilometer', 'ha' => 'Hectare', 'm2' => 'Square meter', 'ft2' => 'Square foot', 'acre' => 'Acre',
        'tb' => 'Terabyte', 'gb' => 'Gigabyte', 'mb' => 'Megabyte', 'kb' => 'Kilobyte', 'b' => 'Byte',
        'c' => 'Celsius', 'f' => 'Fahrenheit', 'k' => 'Kelvin',
    ];

    private const DEFAULTS = [
        'length' => ['km', 'mi'],
        'mass'   => ['kg', 'lb'],
        'volume' => ['l', 'gal'],
        'speed'  => ['kmh', 'mph'],
        'area'   => ['m2', 'ft2'],
        'data'   => ['gb', 'mb'],
        'temp'   => ['c', 'f'],
    ];

    private static function defaults(string $cat): array
    {
        return ['cat' => $cat, 'from' => self::DEFAULTS[$cat][0], 'to' => self::DEFAULTS[$cat][1]];
    }

    public static function initial(): array
    {
        return self::defaults('length') + ['expr' => '0', 'pick' => null];
    }

    public static function convert(float $v, string $cat, string $from, string $to): float
    {
        if ($cat === 'temp') {
            $c = match ($from) {
                'f'     => ($v - 32) * 5 / 9,
                'k'     => $v - 273.15,
                default => $v,
            };
            return match ($to) {
                'f'     => $c * 9 / 5 + 32,
                'k'     => $c + 273.15,
                default => $c,
            };
        }
        $units = self::CATS[$cat][1];
        return $v * $units[$from][1] / $units[$to][1];
    }

    private static function num(float $n): string
    {
        if ($n == 0.0) {
            return '0';
        }
        $abs = abs($n);
        if ($abs >= 1e15 || $abs < 1e-6) {
            return sprintf('%.4e', $n);
        }
        $d = max(0, min(10, 5 - (int) floor(log10($abs))));
        $s = number_format(round($n, $d), $d, '.', ',');
        if (str_contains($s, '.')) {
            $s = rtrim(rtrim($s, '0'), '.');
        }
        return $s === '-0' ? '0' : $s;
    }

    private static function label(string $cat, string $key): string
    {
        return Lang::is('en') ? (self::EN[$key] ?? $key) : self::CATS[$cat][1][$key][0];
    }

    private static function amount(string $shown, string $cat, string $key): string
    {
        return '<bdi>' . (Lang::is('en') ? $shown : fa(strtr($shown, ['.' => '٫', ',' => '٬']))) . ' ' . self::label($cat, $key) . '</bdi>';
    }

    private static function edit(string $e, string $key): string
    {
        switch ($key) {
            case 'C':
                return '0';
            case 'back':
                $n = substr($e, 0, -1);
                return ($n === '' || $n === '-') ? '0' : $n;
            case 'neg':
                return $e === '0' ? '0' : ($e[0] === '-' ? substr($e, 1) : '-' . $e);
            case '.':
                return str_contains($e, '.') ? $e : $e . '.';
        }
        if (strlen($key) === 1 && ctype_digit($key) && strlen($e) < 15) {
            return $e === '0' ? $key : $e . $key;
        }
        return $e;
    }

    public static function press(array $s, string $key): array
    {
        if (str_starts_with($key, 'cat_')) {
            $cat = substr($key, 4);
            return isset(self::CATS[$cat]) ? self::defaults($cat) + ['expr' => $s['expr'], 'pick' => null] : $s;
        }
        if (str_starts_with($key, 'set_')) {
            $unit = substr($key, 4);
            if (!isset(self::CATS[$s['cat']][1][$unit]) || !in_array($s['pick'], ['from', 'to'], true)) {
                return $s;
            }
            $other = $s['pick'] === 'from' ? 'to' : 'from';
            if ($s[$other] === $unit) {
                $s[$other] = $s[$s['pick']];
            }
            $s[$s['pick']] = $unit;
            $s['pick']     = null;
            return $s;
        }
        switch ($key) {
            case 'pick_from':
            case 'pick_to':
                $s['pick'] = substr($key, 5);
                return $s;
            case 'ret':
                $s['pick'] = null;
                return $s;
            case 'swap':
                [$s['from'], $s['to']] = [$s['to'], $s['from']];
                return $s;
        }
        $s['expr'] = self::edit((string) $s['expr'], $key);
        return $s;
    }

    public static function render(array $s): string
    {
        $head = '<b>' . self::CATS[$s['cat']][0] . L(' تبدیل واحد', ' Unit converter') . "</b>\n\n";
        if ($s['pick'] !== null) {
            return $head . ($s['pick'] === 'from' ? L('واحد مبدأ رو انتخاب کن 👇', 'Pick the source unit 👇') : L('واحد مقصد رو انتخاب کن 👇', 'Pick the target unit 👇'));
        }
        $res = self::convert((float) $s['expr'], $s['cat'], $s['from'], $s['to']);
        return $head . self::amount((string) $s['expr'], $s['cat'], $s['from'])
            . "\n= <b>" . self::amount(self::num($res), $s['cat'], $s['to']) . '</b>';
    }

    public static function keyboard(array $s): array
    {
        if ($s['pick'] !== null) {
            $current = $s[$s['pick']];
            $rows    = [];
            $row     = [];
            foreach (self::CATS[$s['cat']][1] as $key => $unit) {
                $row[] = btn(($key === $current ? '✅ ' : '') . self::label($s['cat'], $key), "unit:set_{$key}", $key === $current ? 'success' : 'primary');
                if (count($row) === 2) {
                    $rows[] = $row;
                    $row    = [];
                }
            }
            if ($row) {
                $rows[] = $row;
            }
            $rows[] = [btn(L('🔙 برگشت', '🔙 Back'), 'unit:ret', 'danger')];
            return ikb($rows);
        }
        $k    = static fn(string $label, string $key, string $style = 'primary') => btn($label, "unit:{$key}", $style);
        $cats = [];
        foreach (self::CATS as $cat => [$icon]) {
            $cats[] = $k($icon, "cat_{$cat}", $cat === $s['cat'] ? 'success' : 'primary');
        }
        return ikb([
            $cats,
            [$k(self::label($s['cat'], $s['from']), 'pick_from', 'success'), $k('⇄', 'swap'), $k(self::label($s['cat'], $s['to']), 'pick_to', 'success')],
            [$k('7', '7'), $k('8', '8'), $k('9', '9')],
            [$k('4', '4'), $k('5', '5'), $k('6', '6')],
            [$k('1', '1'), $k('2', '2'), $k('3', '3')],
            [$k('±', 'neg'), $k('0', '0'), $k('.', '.')],
            [$k('⌫', 'back'), $k('C', 'C', 'danger')],
        ]);
    }

    public static function onCallback(array $cq, string $key, string $imid): void
    {
        $tg  = App::tg();
        $old = Store::get('unit', $imid, null);
        $s   = is_array($old) ? $old : self::initial();
        $new = self::press($s, $key);
        if ($new === $s) {
            $tg->answer($cq);
            return;
        }
        Store::set('unit', $imid, $new);
        Rich::editInline($imid, self::render($new), self::keyboard($new));
        $tg->answer($cq);
    }
}
