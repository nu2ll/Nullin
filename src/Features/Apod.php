<?php

final class Apod
{
    public const FIRST = '1995-06-16';

    public static function today(): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone('America/New_York')))->format('Y-m-d');
    }

    // ۱۳۷۵/۴/۱۲ یا 1996-07-02 → [سال، ماه، روز] میلادی. سال زیر ۱۷۰۰ یعنی شمسی.
    public static function parseDate(string $q): ?array
    {
        if (!preg_match('#^(\d{4})\s*[/\-.]\s*(\d{1,2})\s*[/\-.]\s*(\d{1,2})$#', en(mbTrim($q)), $m)) {
            return null;
        }
        [$y, $mo, $d] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        if ($mo < 1 || $mo > 12 || $d < 1 || $d > 31) {
            return null;
        }
        if ($y < 1700) {
            [$gy, $gm, $gd] = Jalali::toGregorian($y, $mo, $d);
            if (Jalali::fromGregorian($gy, $gm, $gd) !== [$y, $mo, $d]) {
                return null;
            }
            [$y, $mo, $d] = [$gy, $gm, $gd];
        }
        return checkdate($mo, $d, $y) ? [$y, $mo, $d] : null;
    }

    // date = null یعنی عکس امروز. نتیجه تو Store با کلید تاریخ می‌مونه؛ شکست هیچ‌وقت کش نمی‌شه.
    public static function load(?string $date = null): ?array
    {
        $today = self::today();
        $date ??= $today;
        $hit = Store::get('apod', $date);
        if (is_array($hit) && !empty($hit['title'])) {
            return $hit;
        }
        $r = self::fetch($date === $today ? null : $date);
        if ($r === null) {
            return null;
        }
        if (!is_array(Store::get('apod', $r['date']))) {
            Store::set('apod', $r['date'], $r);
        }
        return $r;
    }

    private static function fetch(?string $date): ?array
    {
        $q = ['api_key' => (string) App::cfg('nasa_key', 'DEMO_KEY'), 'thumbs' => 'true'] + ($date !== null ? ['date' => $date] : []);
        $r = Http::request('GET', 'https://api.nasa.gov/planetary/apod?' . http_build_query($q), [
            'timeout' => 15,
            'headers' => ['Accept: application/json', 'User-Agent: NulliniBot/3.0 (https://t.me/NullinBot)'],
        ]);
        $j = ($r['code'] ?? 0) === 200 ? json_decode((string) ($r['body'] ?? ''), true) : null;
        if (!is_array($j) || trim((string) ($j['title'] ?? '')) === '' || trim((string) ($j['explanation'] ?? '')) === ''
            || !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($j['date'] ?? ''))) {
            logWarn('apod', 'nasa http ' . (int) ($r['code'] ?? 0) . ' ' . ($date ?? 'today') . ' ' . (string) ($r['error'] ?? ''));
            return null;
        }
        $tr = self::translate(trim((string) $j['title']), (string) $j['explanation']);
        if ($tr === null) {
            logWarn('apod', 'translate failed ' . (string) $j['date']);
            return null;
        }
        $image = ($j['media_type'] ?? '') === 'image';
        $url   = (string) ($j['url'] ?? '');
        return [
            'date'   => (string) $j['date'],
            'title'  => $tr[0],
            'text'   => $tr[1],
            'credit' => mbTrim((string) preg_replace('/\s+/u', ' ', (string) ($j['copyright'] ?? ''))),
            'media'  => $image ? 'image' : 'video',
            'url'    => $url,
            'hd'     => (string) ($j['hdurl'] ?? ''),
            'thumb'  => $image ? $url : (string) ($j['thumbnail_url'] ?? ''),
        ];
    }

    private static function translate(string $title, string $text): ?array
    {
        $text = mbTrim((string) preg_replace('/\s+/u', ' ', $text));
        $text = mbTrim((string) preg_replace('/\s*(?:Tomorrow\'s picture|Follow APOD on)\b.*$/isu', '', $text));
        $out  = AI::translateFast($title . "\n\n" . $text, 'Persian');
        if ($out !== null) {
            $p = preg_split('/\n\s*\n/u', mbTrim($out), 2);
            if (is_array($p) && count($p) === 2 && mbTrim($p[0]) !== '' && mbTrim($p[1]) !== '') {
                return [mbTrim($p[0]), mbTrim($p[1])];
            }
        }
        $t = AI::translateFast($title, 'Persian');
        $x = AI::translateFast($text, 'Persian');
        return ($t !== null && $x !== null) ? [mbTrim($t), mbTrim($x)] : null;
    }

    public static function page(array $r): string
    {
        return 'https://apod.nasa.gov/apod/ap' . date('ymd', strtotime($r['date'] . ' 12:00:00 UTC')) . '.html';
    }

    public static function text(array $r, bool $birthday = false): string
    {
        $ts = strtotime($r['date'] . ' 12:00:00 UTC');
        return '🌌 <b>' . h($r['title']) . "</b>\n"
            . '<i>' . ($birthday ? '📅 عکسِ همون روز · ' : '') . Jalali::fullDate($ts) . '</i>'
            . "\n\n" . h($r['text'])
            . ($r['credit'] !== '' ? "\n\n© " . h($r['credit']) : '');
    }

    public static function keyboard(array $r): array
    {
        if ($r['media'] === 'image') {
            $row = [];
            if ($r['hd'] !== '' && $r['hd'] !== $r['url']) {
                $row[] = urlBtn('🖼 کیفیت کامل', $r['hd'], null);
            }
            $row[] = urlBtn('🌌 صفحه‌ی ناسا', self::page($r), null);
            return ikb([$row]);
        }
        return ikb([[urlBtn('▶️ دیدن ویدیو', $r['url'], null)], [urlBtn('🌌 صفحه‌ی ناسا', self::page($r), null)]]);
    }

    public static function inline(array $r, bool $birthday = false): array
    {
        // عکس: بعد از انتخاب onChosen عکس (file_id) رو اضافه می‌کنه؛ ویدیو: همون مقاله‌ی متنی با دکمه‌ی لینک می‌مونه
        $id = ($r['media'] === 'image' ? 'ap_' : 'apv_') . str_replace('-', '', $r['date']) . ($birthday ? 'b' : '');
        return Ui::article(
            $id,
            str_starts_with((string) $r['thumb'], 'https://') ? $r['thumb'] : 'info',
            '🌌 ' . $r['title'],
            mb_substr($r['text'], 0, 90),
            self::text($r, $birthday),
            self::keyboard($r)
        );
    }

    public static function onChosen(string $id, string $imid): void
    {
        $birthday = false;
        if ($id === 'apt_today') {
            // از منوی اینلاین: عکس امروز؛ پیام «صبر کن» از قبل فرستاده شده
            $r = self::load(null);
            if ($r === null) {
                Rich::editInline($imid, '🌌 الان عکس نجومی نیومد؛ یه کم بعد دوباره بزن.');
                return;
            }
        } elseif (preg_match('/^ap_(\d{4})(\d{2})(\d{2})(b?)$/', $id, $m)) {
            $r        = Store::get('apod', "{$m[1]}-{$m[2]}-{$m[3]}");
            $birthday = $m[4] === 'b';
        } else {
            return;
        }
        if (!is_array($r)) {
            return;
        }
        if (($r['media'] ?? '') !== 'image') {
            Rich::editInline($imid, self::text($r, $birthday), self::keyboard($r));
            return;
        }
        $date = (string) $r['date'];
        $bot  = (string) App::botIndex();
        $fid  = $r['fid'][$bot] ?? null;
        if (!is_string($fid) || $fid === '') {
            $fid = Wiki::photoId((string) $r['url'], 25);
            if ($fid === null) {
                logWarn('apod', 'photo upload failed ' . $date);
                if ($id === 'apt_today') {
                    Rich::editInline($imid, self::text($r, $birthday), self::keyboard($r));
                }
                return;
            }
            Store::update('apod', $date, static function ($x) use ($fid, $bot) {
                if (!is_array($x)) {
                    return null;
                }
                $x['fid'][$bot] = $fid;
                return $x;
            });
        }
        $res = Rich::editInlinePhoto($imid, self::text($r, $birthday), $fid, self::keyboard($r));
        if (empty($res['ok'])) {
            logWarn('apod', 'edit failed: ' . (string) ($res['description'] ?? ''));
        }
    }
}
