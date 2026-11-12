<?php

final class Weather
{
    private const GEO      = 'https://geocoding-api.open-meteo.com/v1/search';
    private const OSM      = 'https://nominatim.openstreetmap.org/search';
    private const FORECAST = 'https://api.open-meteo.com/v1/forecast';
    private const AIR      = 'https://air-quality-api.open-meteo.com/v1/air-quality';

    private const CODES = [
        0  => ['صاف', '☀️', '🌙', 'Clear'],
        1  => ['تقریباً صاف', '🌤', '🌙', 'Mostly clear'],
        2  => ['نیمه‌ابری', '⛅', '☁️', 'Partly cloudy'],
        3  => ['ابری', '☁️', '☁️', 'Overcast'],
        45 => ['مه', '🌫', '🌫', 'Fog'],
        48 => ['مه یخ‌زده', '🌫', '🌫', 'Freezing fog'],
        51 => ['نم‌نم باران', '🌦', '🌧', 'Light drizzle'],
        53 => ['نم‌نم باران', '🌦', '🌧', 'Drizzle'],
        55 => ['نم‌نم باران شدید', '🌧', '🌧', 'Heavy drizzle'],
        56 => ['نم‌نم یخ‌زده', '🌧', '🌧', 'Freezing drizzle'],
        57 => ['نم‌نم یخ‌زده', '🌧', '🌧', 'Freezing drizzle'],
        61 => ['باران سبک', '🌦', '🌧', 'Light rain'],
        63 => ['باران', '🌧', '🌧', 'Rain'],
        65 => ['باران شدید', '🌧', '🌧', 'Heavy rain'],
        66 => ['باران یخ‌زده', '🌧', '🌧', 'Freezing rain'],
        67 => ['باران یخ‌زده‌ی شدید', '🌧', '🌧', 'Heavy freezing rain'],
        71 => ['برف سبک', '🌨', '🌨', 'Light snow'],
        73 => ['برف', '🌨', '🌨', 'Snow'],
        75 => ['برف سنگین', '❄️', '❄️', 'Heavy snow'],
        77 => ['دونه‌های برف', '🌨', '🌨', 'Snow grains'],
        80 => ['رگبار سبک', '🌦', '🌧', 'Light showers'],
        81 => ['رگبار', '🌧', '🌧', 'Showers'],
        82 => ['رگبار شدید', '⛈', '⛈', 'Heavy showers'],
        85 => ['بارش برف', '🌨', '🌨', 'Snow showers'],
        86 => ['بارش برف شدید', '❄️', '❄️', 'Heavy snow showers'],
        95 => ['رعدوبرق', '⛈', '⛈', 'Thunderstorm'],
        96 => ['رعدوبرق و تگرگ', '⛈', '⛈', 'Thunderstorm with hail'],
        99 => ['رعدوبرق و تگرگ شدید', '⛈', '⛈', 'Thunderstorm with heavy hail'],
    ];

    private static function norm(string $city): string
    {
        $c = str_replace(['ي', 'ك', 'ة'], ['ی', 'ک', 'ه'], $city);
        $c = preg_replace('/^(شهر|استان)\s+/u', '', mbTrim($c)) ?? $c;
        return mbTrim(preg_replace('/\s+/u', ' ', $c) ?? $c);
    }

    public static function zoneOf(string $city): ?array
    {
        $city = self::norm($city);
        if ($city === '') {
            return null;
        }
        $lang = Lang::get();
        return Store::remember('cache', 'tz_' . $lang . '_' . md5(mb_strtolower($city)), 86400 * 30, static function () use ($city, $lang) {
            $g = Http::getJson(self::GEO . '?' . http_build_query(['name' => $city, 'count' => 1, 'language' => $lang, 'format' => 'json']), [], 8);
            $r = $g['results'][0] ?? null;
            if (!is_array($r) || empty($r['timezone'])) {
                return null;
            }
            return ['zone' => (string) $r['timezone'], 'name' => (string) ($r['name'] ?? $city), 'country' => (string) ($r['country'] ?? '')];
        });
    }

    private const ALIASES = [
        'newyork' => 'New York City', 'nyc' => 'New York City', 'losangeles' => 'Los Angeles',
        'la' => 'Los Angeles', 'sanfrancisco' => 'San Francisco', 'lasvegas' => 'Las Vegas',
        'hongkong' => 'Hong Kong', 'kualalumpur' => 'Kuala Lumpur', 'saopaulo' => 'Sao Paulo',
        'buenosaires' => 'Buenos Aires', 'riodejaneiro' => 'Rio de Janeiro', 'mexicocity' => 'Mexico City',
        'telaviv' => 'Tel Aviv', 'abudhabi' => 'Abu Dhabi', 'capetown' => 'Cape Town',
        'newdelhi' => 'New Delhi', 'saltlakecity' => 'Salt Lake City', 'washingtondc' => 'Washington',
    ];

    private static function locate(string $city): ?array
    {
        $city = self::norm($city);
        $lang = Lang::get();
        return Store::remember('cache', 'geo2_' . $lang . '_' . md5(mb_strtolower($city)), 86400 * 30, static function () use ($city, $lang) {
            $key   = preg_replace('/[\s\-_.]+/u', '', mb_strtolower($city)) ?? '';
            $query = self::ALIASES[$key] ?? $city;
            $g = Http::getJson(self::GEO . '?' . http_build_query(['name' => $query, 'count' => 10, 'language' => $lang, 'format' => 'json']), [], 8);
            $list = array_values(array_filter($g['results'] ?? [], static fn ($x) => is_array($x) && isset($x['latitude'], $x['longitude'])));
            if ($list) {
                usort($list, static fn ($a, $b) => ((int) ($b['population'] ?? 0)) <=> ((int) ($a['population'] ?? 0)));
                $r = $list[0];
                return ['name' => $r['name'] ?? $city, 'country' => $r['country'] ?? '', 'lat' => (float) $r['latitude'], 'lon' => (float) $r['longitude']];
            }
            if (mb_strlen($city) < 3) {
                return null;
            }
            $o = Http::getJson(self::OSM . '?' . http_build_query(['q' => $query, 'format' => 'jsonv2', 'limit' => 1, 'addressdetails' => 1, 'accept-language' => $lang]),
                ['User-Agent: NulliniBot/3.8 (Telegram bot)'], 8);
            if (!empty($o[0]['lat'])) {
                $r = $o[0];
                $n = $r['name'] ?? '';
                return [
                    'name'    => $n !== '' ? $n : $city,
                    'country' => $r['address']['country'] ?? '',
                    'lat'     => (float) $r['lat'],
                    'lon'     => (float) $r['lon'],
                ];
            }
            return null;
        });
    }

    public static function fetch(string $city): ?array
    {
        if (mbTrim($city) === '') {
            return null;
        }
        $loc = self::locate($city);
        if ($loc === null) {
            return null;
        }
        return (static function () use ($loc) {
            $w = Http::getJson(self::FORECAST . '?' . http_build_query([
                'latitude'      => $loc['lat'],
                'longitude'     => $loc['lon'],
                'current'       => 'temperature_2m,apparent_temperature,relative_humidity_2m,weather_code,cloud_cover,wind_speed_10m,wind_direction_10m,surface_pressure,is_day',
                'daily'         => 'weather_code,temperature_2m_max,temperature_2m_min,precipitation_probability_max,sunrise,sunset',
                'timezone'      => 'auto',
                'forecast_days' => 3,
            ]), [], 10);
            if (empty($w['current']) || empty($w['daily']['time'])) {
                return null;
            }
            $a   = Http::getJson(self::AIR . '?' . http_build_query(['latitude' => $loc['lat'], 'longitude' => $loc['lon'], 'current' => 'us_aqi,pm2_5']), [], 6);
            $cur = $w['current'];
            return [
                'location' => ['name' => $loc['name'], 'country' => $loc['country']],
                'current'  => [
                    'temp_c'    => $cur['temperature_2m'] ?? 0,
                    'condition' => ['text' => self::codeText((int) ($cur['weather_code'] ?? 0))],
                ],
                'raw'      => $w,
                'air'      => $a['current'] ?? null,
            ];
        })();
    }

    private static function codeText(int $code): string
    {
        $c = self::CODES[$code] ?? null;
        return $c === null ? '—' : L($c[0], $c[3]);
    }

    private static function codeEmoji(int $code, bool $day = true): string
    {
        return self::CODES[$code][$day ? 1 : 2] ?? '🌡';
    }

    private static function deg($v): string
    {
        return fa((int) round((float) $v)) . '°';
    }

    private static function pct($v): string
    {
        return fa((int) $v) . L('٪', '%');
    }

    private static function wind(array $cur): string
    {
        return fa((int) round((float) ($cur['wind_speed_10m'] ?? 0))) . L(' کیلومتر/ساعت', ' km/h')
            . L(' (از ', ' (from ') . self::windDir((float) ($cur['wind_direction_10m'] ?? 0)) . ')';
    }

    private static function pressure(array $cur): string
    {
        return fa((int) round((float) ($cur['surface_pressure'] ?? 0))) . L(' میلی‌بار', ' hPa');
    }

    private static function windDir(float $deg): string
    {
        $dirs = Lang::is('en')
            ? ['north', 'northeast', 'east', 'southeast', 'south', 'southwest', 'west', 'northwest']
            : ['شمال', 'شمال‌شرق', 'شرق', 'جنوب‌شرق', 'جنوب', 'جنوب‌غرب', 'غرب', 'شمال‌غرب'];
        return $dirs[(int) round(fmod($deg + 360, 360) / 45) % 8];
    }

    private static function aqi(int $v): string
    {
        return match (true) {
            $v <= 50  => '🟢 ' . L('پاک', 'Good'),
            $v <= 100 => '🟡 ' . L('قابل قبول', 'Moderate'),
            $v <= 150 => '🟠 ' . L('ناسالم برای حساس‌ها', 'Unhealthy for sensitive groups'),
            $v <= 200 => '🔴 ' . L('ناسالم', 'Unhealthy'),
            $v <= 300 => '🟣 ' . L('خیلی ناسالم', 'Very unhealthy'),
            default   => '🟤 ' . L('خطرناک', 'Hazardous'),
        };
    }

    public static function format(array $d): string
    {
        $w     = $d['raw'];
        $cur   = $w['current'];
        $daily = $w['daily'];
        $code  = (int) ($cur['weather_code'] ?? 0);
        $isDay = !empty($cur['is_day']);
        $local = isset($cur['time']) ? fa(substr((string) $cur['time'], 11, 5)) : Jalali::time(time());
        $place = h($d['location']['name']) . ($d['location']['country'] !== '' ? ' · ' . h($d['location']['country']) : '');

        $l   = [];
        $l[] = self::codeEmoji($code, $isDay) . " <b>" . L('آب‌وهوای', 'Weather in') . " {$place}</b>";
        $l[] = '🕒 ' . Clock::dateLine(time()) . " · " . L('ساعت محلی', 'local time') . " {$local}";
        $l[] = '';
        $l[] = '🌡 ' . L('دما', 'Temperature') . ': <b>' . self::deg($cur['temperature_2m'] ?? 0) . '</b> (' . L('حس می‌شه', 'feels like') . ' ' . self::deg($cur['apparent_temperature'] ?? 0) . ')';
        $l[] = '⬆️ ' . L('بیشینه', 'High') . ' ' . self::deg($daily['temperature_2m_max'][0] ?? 0) . ' · ⬇️ ' . L('کمینه', 'Low') . ' ' . self::deg($daily['temperature_2m_min'][0] ?? 0);
        $l[] = '☁️ ' . L('وضعیت', 'Condition') . ': ' . self::codeText($code);
        $l[] = '💧 ' . L('رطوبت', 'Humidity') . ': ' . self::pct($cur['relative_humidity_2m'] ?? 0) . ' · ' . L('ابر', 'Clouds') . ': ' . self::pct($cur['cloud_cover'] ?? 0);
        $l[] = '💨 ' . L('باد', 'Wind') . ': ' . self::wind($cur);
        $l[] = '🧭 ' . L('فشار', 'Pressure') . ': ' . self::pressure($cur);
        if (!empty($daily['precipitation_probability_max'][0])) {
            $l[] = '☔ ' . L('احتمال بارش امروز', 'Chance of rain today') . ': ' . self::pct($daily['precipitation_probability_max'][0]);
        }
        if (!empty($daily['sunrise'][0]) && !empty($daily['sunset'][0])) {
            $l[] = '🌅 ' . L('طلوع', 'Sunrise') . ' ' . fa(substr((string) $daily['sunrise'][0], 11, 5)) . ' · 🌇 ' . L('غروب', 'Sunset') . ' ' . fa(substr((string) $daily['sunset'][0], 11, 5));
        }
        if (isset($d['air']['us_aqi'])) {
            $pm  = $d['air']['pm2_5'] ?? null;
            $l[] = '🫁 ' . L('کیفیت هوا', 'Air quality') . ': ' . self::aqi((int) $d['air']['us_aqi']) . ($pm !== null ? ' (PM2.5: ' . fa(round((float) $pm, 1)) . ')' : '');
        }

        $future = [];
        foreach ([1 => L('فردا', 'Tomorrow'), 2 => L('پس‌فردا', 'Day after tomorrow')] as $i => $name) {
            if (isset($daily['temperature_2m_max'][$i])) {
                $c        = (int) ($daily['weather_code'][$i] ?? 0);
                $rain     = (int) ($daily['precipitation_probability_max'][$i] ?? 0);
                $future[] = "📆 {$name}: " . self::codeEmoji($c) . ' ' . self::deg($daily['temperature_2m_max'][$i]) . ' / ' . self::deg($daily['temperature_2m_min'][$i])
                    . ' · ' . self::codeText($c) . ($rain > 0 ? ' · ☔ ' . self::pct($rain) : '');
            }
        }
        if ($future) {
            $l[] = '';
            array_push($l, ...$future);
        }
        return implode("\n", $l);
    }

    public static function richFormat(array $d, string $view = 'now'): string
    {
        $w     = $d['raw'];
        $cur   = $w['current'];
        $daily = $w['daily'];
        $code  = (int) ($cur['weather_code'] ?? 0);
        $isDay = !empty($cur['is_day']);
        $local = isset($w['utc_offset_seconds']) ? fa(gmdate('H:i', time() + (int) $w['utc_offset_seconds'])) : Jalali::time(time());
        $place = $d['location']['name'] . ($d['location']['country'] !== '' ? ' · ' . $d['location']['country'] : '');
        $head  = self::codeEmoji($code, $isDay) . " **" . L('آب‌وهوای', 'Weather in') . " {$place}**\n🕒 " . Clock::dateLine(time()) . ' · ' . L('ساعت محلی', 'local time') . " {$local}\n\n";

        if ($view === 'days') {
            $rows = [];
            foreach ([0 => L('امروز', 'Today'), 1 => L('فردا', 'Tomorrow'), 2 => L('پس‌فردا', 'Day after tomorrow')] as $i => $name) {
                if (!isset($daily['temperature_2m_max'][$i])) {
                    continue;
                }
                $c      = (int) ($daily['weather_code'][$i] ?? 0);
                $rain   = (int) ($daily['precipitation_probability_max'][$i] ?? 0);
                $rows[] = [
                    $name,
                    self::codeEmoji($c) . ' ' . self::codeText($c),
                    self::deg($daily['temperature_2m_max'][$i]) . ' / ' . self::deg($daily['temperature_2m_min'][$i]),
                    $rain > 0 ? self::pct($rain) : '—',
                ];
            }
            return $head . Rich::mdTable([L('روز', 'Day'), L('وضعیت', 'Condition'), L('بیشینه / کمینه', 'High / Low'), '☔ ' . L('بارش', 'Rain')], $rows);
        }

        $rows = [
            ['🌡 ' . L('دما', 'Temperature'), '**' . self::deg($cur['temperature_2m'] ?? 0) . '** (' . L('حس', 'feels') . ' ' . self::deg($cur['apparent_temperature'] ?? 0) . ')'],
            ['↕️ ' . L('بیشینه / کمینه', 'High / Low'), self::deg($daily['temperature_2m_max'][0] ?? 0) . ' / ' . self::deg($daily['temperature_2m_min'][0] ?? 0)],
            ['☁️ ' . L('وضعیت', 'Condition'), self::codeText($code)],
            ['💧 ' . L('رطوبت', 'Humidity'), self::pct($cur['relative_humidity_2m'] ?? 0)],
            ['⛅ ' . L('ابر', 'Clouds'), self::pct($cur['cloud_cover'] ?? 0)],
            ['💨 ' . L('باد', 'Wind'), self::wind($cur)],
            ['🧭 ' . L('فشار', 'Pressure'), self::pressure($cur)],
        ];
        if (!empty($daily['precipitation_probability_max'][0])) {
            $rows[] = ['☔ ' . L('احتمال بارش', 'Chance of rain'), self::pct($daily['precipitation_probability_max'][0])];
        }
        if (!empty($daily['sunrise'][0]) && !empty($daily['sunset'][0])) {
            $rows[] = ['🌅 ' . L('طلوع', 'Sunrise') . ' / 🌇 ' . L('غروب', 'Sunset'), fa(substr((string) $daily['sunrise'][0], 11, 5)) . ' / ' . fa(substr((string) $daily['sunset'][0], 11, 5))];
        }
        if (isset($d['air']['us_aqi'])) {
            $pm     = $d['air']['pm2_5'] ?? null;
            $rows[] = ['🫁 ' . L('کیفیت هوا', 'Air quality'), self::aqi((int) $d['air']['us_aqi']) . ($pm !== null ? ' (PM2.5: ' . fa(round((float) $pm, 1)) . ')' : '')];
        }
        return $head . Rich::mdTable([L('مورد', 'Item'), L('مقدار', 'Value')], $rows);
    }

    public static function keyboard(string $hash = '', string $view = 'now'): array
    {
        $sfx  = $hash === '' ? '' : ':' . $hash;
        $tabs = [
            $view === 'now' ? btn('🌡 ' . L('اکنون', 'Now') . ' ✓', 'weather:now' . $sfx, 'success') : btn('🌡 ' . L('اکنون', 'Now'), 'weather:now' . $sfx, 'primary'),
            $view === 'days' ? btn('📆 ' . L('۳ روز آینده', 'Next 3 days') . ' ✓', 'weather:days' . $sfx, 'success') : btn('📆 ' . L('۳ روز آینده', 'Next 3 days'), 'weather:days' . $sfx, 'primary'),
        ];
        return ikb([$tabs, [btn('🔄 ' . L('بروزرسانی', 'Refresh'), 'weather:refresh:' . $view . $sfx, 'danger')]]);
    }

    public static function onCallback(array $cq, ?string $imid, string $data = ''): void
    {
        $tg      = App::tg();
        $key     = $imid ?? ((int) ($cq['message']['chat']['id'] ?? 0) . ':' . (int) ($cq['message']['message_id'] ?? 0));
        $city    = Store::get('weather', $key);
        $matched = preg_match('/^weather:(now|days|refresh)(?::(now|days))?(?::(\w{16}))?$/', $data, $m) === 1;
        $hash    = $matched ? ($m[3] ?? '') : '';
        $refresh = $matched && $m[1] === 'refresh';
        $view    = $matched ? ($m[1] === 'refresh' ? (($m[2] ?? '') !== '' ? $m[2] : 'now') : $m[1]) : 'now';
        if ((!is_string($city) || $city === '') && $hash !== '') {
            $city = Store::get('weather_pending', $hash);
            if (is_string($city) && $city !== '') {
                Store::set('weather', $key, $city);
            }
        }
        if (!is_string($city) || $city === '') {
            $tg->alert($cq, L('⚠️ اطلاعات این پیام پیدا نشد؛', '⚠️ Couldn’t find the data for this message;') . "\n" . L('دوباره از منو بگیرش.', 'please request it again from the menu.'));
            return;
        }
        $data = self::fetch($city);
        if ($data === null) {
            $tg->alert($cq, L('⚠️ الان نتونستم آب‌وهوا رو بگیرم؛', '⚠️ Couldn’t fetch the weather right now;') . "\n" . L('یه‌کم دیگه امتحان کن.', 'please try again in a bit.'));
            return;
        }
        $kb = self::keyboard($hash !== '' ? $hash : substr(md5(mb_strtolower($city)), 0, 16), $view);
        Rich::editCbMd($cq, $imid, self::richFormat($data, $view), $kb);
        $tg->answer($cq, $refresh ? L('🔄 به‌روز شد', '🔄 Updated') : null);
    }
}
