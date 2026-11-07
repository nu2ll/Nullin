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
        $hit = Store::get('apod2', $date);
        if (is_array($hit) && !empty($hit['title'])) {
            return $hit;
        }
        $r = self::fetch($date === $today ? null : $date);
        if ($r === null) {
            return null;
        }
        if (!is_array(Store::get('apod2', $r['date']))) {
            Store::set('apod2', $r['date'], $r);
        }
        return $r;
    }

    private static function fetch(?string $date): ?array
    {
        $base = 'https://science.nasa.gov/wp-json/wp/v2/apod-basic';
        $url  = $date !== null ? $base . '/' . substr($date, 2, 2) . substr($date, 5, 2) . substr($date, 8, 2) : $base . '?per_page=1';
        $r    = Http::request('GET', $url, [
            'timeout' => 15,
            'headers' => ['Accept: application/json', 'User-Agent: NulliniBot/3.0 (https://t.me/NullinBot)'],
        ]);
        $j = ($r['code'] ?? 0) === 200 ? json_decode((string) ($r['body'] ?? ''), true) : null;
        if (is_array($j) && isset($j[0]) && is_array($j[0])) {
            $j = $j[0];
        }
        $title = is_array($j) ? self::clean((string) ($j['title'] ?? '')) : '';
        $expl  = is_array($j) ? self::clean((string) ($j['explanation'] ?? '')) : '';
        $hd    = is_array($j) ? (string) ($j['hdurl'] ?? '') : '';
        if (!is_array($j) || $title === '' || $expl === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($j['date'] ?? '')) || !str_starts_with($hd, 'https://')) {
            logWarn('apod', 'nasa http ' . (int) ($r['code'] ?? 0) . ' ' . ($date ?? 'today') . ' ' . (string) ($r['error'] ?? ''));
            return null;
        }
        $tr = self::translate($title, $expl);
        if ($tr === null) {
            logWarn('apod', 'translate failed ' . (string) $j['date']);
            return null;
        }
        $credit = self::clean((string) ($j['copyright'] ?? ''));
        $credit = mbTrim((string) preg_replace('/^(?:image\s+)?(?:credit|copyright)(?:\s*(?:&|and)\s*(?:credit|copyright))?\s*[:\-]\s*/iu', '', $credit));
        return [
            'date'   => (string) $j['date'],
            'title'  => $tr[0],
            'text'   => $tr[1],
            'credit' => $credit,
            'media'  => ($j['media_type'] ?? '') === 'image' ? 'image' : 'video',
            'url'    => self::sized($hd, 1600),
            'hd'     => str_contains($hd, '/content/dam/') ? (string) strtok($hd, '?') : $hd,
            'thumb'  => self::sized($hd, 320),
            'page'   => (string) ($j['permalink'] ?? ''),
        ];
    }

    // عنوان، توضیح و کپی‌رایت از ناسا HTML میاد
    private static function clean(string $html): string
    {
        $t = preg_replace('#<\s*(?:br\s*/?|/p)\s*>#i', ' ', $html);
        $t = html_entity_decode(strip_tags((string) $t), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return mbTrim((string) preg_replace('/\s+/u', ' ', $t));
    }

    // عکس‌ها از CDN ناسا میان؛ پهنا رو کوچیک می‌کنیم تا تلگرام قبولش کنه
    private static function sized(string $url, int $w): string
    {
        if (!str_contains($url, 'assets.science.nasa.gov/')) {
            return $url;
        }
        return strtok($url, '?') . '?w=' . $w . '&fit=clip';
    }

    private static function translate(string $title, string $text): ?array
    {
        $text = mbTrim((string) preg_replace('/\s+/u', ' ', $text));
        $text = mbTrim((string) preg_replace('/^Explanation:\s*/iu', '', $text));
        $text = mbTrim((string) preg_replace('/\s*(?:APOD News|Tomorrow\'s picture|Follow APOD on)\b.*$/isu', '', $text));
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
        return ($r['page'] ?? '') !== '' ? (string) $r['page'] : 'https://science.nasa.gov/apod/';
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
        return ikb([[urlBtn('▶️ دیدن ویدیو', self::page($r), null)]]);
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
                Rich::editInline($imid, '😕 الان عکس نیومد؛ چند دقیقه‌ی دیگه دوباره بزن.');
                return;
            }
        } elseif (preg_match('/^ap_(\d{4})(\d{2})(\d{2})(b?)$/', $id, $m)) {
            $r        = Store::get('apod2', "{$m[1]}-{$m[2]}-{$m[3]}");
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
            $fid = Wiki::photoId((string) $r['url'], 25) ?? Wiki::photoId((string) $r['hd'], 25);
            if ($fid === null) {
                logWarn('apod', 'photo upload failed ' . $date);
                if ($id === 'apt_today') {
                    Rich::editInline($imid, self::text($r, $birthday), self::keyboard($r));
                }
                return;
            }
            Store::update('apod2', $date, static function ($x) use ($fid, $bot) {
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
