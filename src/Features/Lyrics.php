<?php

final class Lyrics
{
    public static function find(string $artist, string $title, int $dur = 0): ?string
    {
        $key = 'ly:' . md5(mb_strtolower($artist . '|' . $title));
        $hit = Store::get('cache', $key);
        if (is_string($hit) && $hit !== '') {
            return $hit;
        }
        $text = self::lrclib($artist, $title, $dur) ?? self::ovh($artist, $title);
        if ($text !== null) {
            Store::set('cache', $key, $text);
        }
        return $text;
    }

    private static function lrclib(string $artist, string $title, int $dur): ?string
    {
        $j = Http::getJson('https://lrclib.net/api/get?' . http_build_query(array_filter(['artist_name' => $artist, 'track_name' => $title, 'duration' => $dur > 0 ? $dur : null])), [], 10);
        $t = is_array($j) ? self::clean((string) ($j['plainLyrics'] ?? '')) : '';
        if ($t !== '') {
            return $t;
        }
        $list = Http::getJson('https://lrclib.net/api/search?' . http_build_query(['q' => trim($artist . ' ' . $title)]), [], 10);
        foreach ((array) $list as $e) {
            $t = is_array($e) ? self::clean((string) ($e['plainLyrics'] ?? '')) : '';
            if ($t !== '') {
                return $t;
            }
        }
        return null;
    }

    private static function ovh(string $artist, string $title): ?string
    {
        if ($artist === '' || $title === '') {
            return null;
        }
        $j = Http::getJson('https://api.lyrics.ovh/v1/' . rawurlencode($artist) . '/' . rawurlencode($title), [], 10);
        $t = is_array($j) ? self::clean((string) ($j['lyrics'] ?? '')) : '';
        return $t !== '' ? $t : null;
    }

    private static function clean(string $t): string
    {
        $t = preg_replace("/\r\n?/", "\n", $t) ?? $t;
        return trim(preg_replace("/\n{3,}/", "\n\n", $t) ?? $t);
    }
}
