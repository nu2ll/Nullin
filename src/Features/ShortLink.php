<?php

final class ShortLink
{
    public static function extract(string $raw): ?string
    {
        $raw = trim($raw);
        if ($raw === '' || mb_strlen($raw) > 2000) {
            return null;
        }
        if (preg_match('~https?://[^\s]+~iu', $raw, $m)) {
            $url = $m[0];
        } elseif (preg_match('~^((?:[a-z0-9-]+\.)+[a-z]{2,}(?:[/?#]\S*)?)$~iu', $raw, $m)) {
            $url = 'https://' . $m[1];
        } else {
            return null;
        }
        $host = parse_url($url, PHP_URL_HOST);
        return (is_string($host) && str_contains($host, '.')) ? $url : null;
    }

    public static function shorten(string $url): ?string
    {
        foreach (['https://is.gd/create.php', 'https://v.gd/create.php'] as $api) {
            $r = Http::request('GET', $api . '?' . http_build_query(['format' => 'json', 'url' => $url]), ['timeout' => 6, 'connect_timeout' => 4]);
            $j = $r['body'] !== null ? json_decode($r['body'], true) : null;
            $s = is_array($j) ? (string) ($j['shorturl'] ?? '') : '';
            if ($s !== '' && str_starts_with($s, 'http')) {
                return $s;
            }
        }
        $r = Http::request('GET', 'https://tinyurl.com/api-create.php?' . http_build_query(['url' => $url]), ['timeout' => 6, 'connect_timeout' => 4]);
        $s = trim((string) ($r['body'] ?? ''));
        return ($r['code'] === 200 && preg_match('~^https?://\S+$~', $s)) ? $s : null;
    }

    public static function text(string $short, string $url): string
    {
        return "🔗 <b>لینک کوتاه</b>\n\n<code>" . h($short) . "</code>\n\n"
            . '🔸 لینک اصلی: ' . h(mb_strlen($url) > 90 ? mb_substr($url, 0, 90) . '…' : $url);
    }

    public static function keyboard(string $short): array
    {
        return ikb([
            [['text' => '📋 کپی', 'copy_text' => ['text' => mb_substr($short, 0, 256)]]],
            [urlBtn('🌐 باز کردن', $short, null)],
        ]);
    }
}
