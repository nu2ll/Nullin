<?php

final class MusicSearch
{
    public static function find(string $q, int $limit = 50): array
    {
        $q = trim($q);
        if ($q === '') {
            return [];
        }
        $key = 'ms5:' . md5(mb_strtolower($q));
        $hit = Store::get('cache', $key);
        if (is_array($hit) && $hit) {
            return $hit;
        }
        $dz   = self::deezer($q, 50);
        $itn  = self::itunes($q, $limit);
        $list = self::originalCovers(self::unique(array_merge($dz, self::youtube($q, 50), $itn)), array_merge($dz, $itn));
        if ($list) {
            Store::set('cache', $key, $list);
        }
        return $list;
    }

    private static function deezer(string $q, int $limit): array
    {
        $j = Http::getJson('https://api.deezer.com/search?' . http_build_query(['q' => $q, 'limit' => $limit]), [], 10);
        $out = [];
        foreach ((array) ($j['data'] ?? []) as $e) {
            if (!is_array($e) || empty($e['title'])) {
                continue;
            }
            $out[] = [
                'title'  => (string) $e['title'],
                'artist' => (string) ($e['artist']['name'] ?? ''),
                'album'  => (string) ($e['album']['title'] ?? ''),
                'cover'  => (string) ($e['album']['cover_xl'] ?? $e['album']['cover_big'] ?? ''),
                'dur'    => (int) ($e['duration'] ?? 0),
            ];
        }
        return self::unique($out);
    }

    private static function youtube(string $q, int $limit): array
    {
        $out = [];
        foreach (Downloader::ytSearch($q, $limit) as $e) {
            $out[] = [
                'title'  => $e['title'],
                'artist' => trim(preg_replace('/\s+-\s+Topic$/u', '', $e['by']) ?? $e['by']),
                'album'  => '',
                'cover'  => '',
                'dur'    => $e['d'],
                'yt'     => $e['id'],
            ];
        }
        return $out;
    }

    private static function itunes(string $q, int $limit): array
    {
        $j = Http::getJson('https://itunes.apple.com/search?' . http_build_query(['term' => $q, 'entity' => 'song', 'limit' => $limit]), [], 10);
        $out = [];
        foreach ((array) ($j['results'] ?? []) as $e) {
            if (!is_array($e) || empty($e['trackName'])) {
                continue;
            }
            $out[] = [
                'title'  => (string) $e['trackName'],
                'artist' => (string) ($e['artistName'] ?? ''),
                'album'  => (string) ($e['collectionName'] ?? ''),
                'cover'  => str_replace('100x100bb', '600x600bb', (string) ($e['artworkUrl100'] ?? '')),
                'dur'    => (int) round(((int) ($e['trackTimeMillis'] ?? 0)) / 1000),
            ];
        }
        return self::unique($out);
    }

    private static function norm(string $t): string
    {
        $t = mb_strtolower($t);
        $t = preg_replace('/[\(\[][^\)\]]*[\)\]]/u', ' ', $t) ?? $t;
        $t = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $t) ?? $t;
        return trim($t);
    }

    // YouTube results only have a video frame; swap it for the real album cover when Deezer/iTunes know the track
    private static function originalCovers(array $list, array $refs): array
    {
        $idx = [];
        foreach ($refs as $r) {
            if (preg_match('#^https://#', (string) $r['cover']) !== 1) {
                continue;
            }
            $idx[] = ['t' => self::norm($r['title']), 'a' => self::norm($r['artist']), 'r' => $r];
        }
        foreach ($list as $i => $t) {
            if (empty($t['yt'])) {
                continue;
            }
            $yt = self::norm($t['title']);
            $ya = self::norm($t['artist']);
            foreach ($idx as $c) {
                if ($c['t'] === '' || !str_contains(' ' . $yt . ' ', ' ' . $c['t'] . ' ')) {
                    continue;
                }
                if ($c['a'] !== '' && !str_contains(' ' . $ya . ' ' . $yt . ' ', ' ' . $c['a'] . ' ')) {
                    continue;
                }
                $list[$i]['cover'] = $c['r']['cover'];
                if ($t['album'] === '') {
                    $list[$i]['album'] = $c['r']['album'];
                }
                break;
            }
        }
        return $list;
    }

    private static function unique(array $list): array
    {
        $seen = [];
        $out  = [];
        foreach ($list as $t) {
            $k = mb_strtolower($t['artist'] . '|' . $t['title']);
            if (isset($seen[$k])) {
                continue;
            }
            $seen[$k] = true;
            $out[]    = $t;
        }
        return $out;
    }
}
