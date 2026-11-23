<?php

final class Cobalt
{
    private const MAX_ITEMS = 20;
    private const HOME      = '/opt/cobalt-src/api';
    private const PORT      = 9000;
    private const IMAGE     = ['jpg', 'jpeg', 'png', 'webp', 'heic', 'heif', 'avif'];

    public static function local(): bool
    {
        return trim((string) env('COBALT_URL', '')) === '' && is_file(self::HOME . '/src/cobalt.js');
    }

    public static function ensure(): void
    {
        if (!self::local() || !function_exists('exec')) {
            return;
        }
        $s = @fsockopen('127.0.0.1', self::PORT, $errno, $errstr, 0.3);
        if ($s !== false) {
            fclose($s);
            return;
        }
        exec('cd ' . escapeshellarg(self::HOME) . ' && API_URL=' . escapeshellarg('http://127.0.0.1:' . self::PORT . '/')
            . ' API_PORT=' . self::PORT . ' API_LISTEN_ADDRESS=127.0.0.1 RATELIMIT_MAX=5000'
            . ' nohup node src/cobalt > /tmp/cobalt.log 2>&1 &');
        logInfo('dl', 'cobalt: starting local instance');
    }

    public static function base(): string
    {
        $u = trim((string) env('COBALT_URL', ''));
        if ($u === '' && self::local()) {
            return 'http://127.0.0.1:' . self::PORT;
        }
        if ($u === '') {
            return '';
        }
        if (!preg_match('#^https?://#i', $u)) {
            $u = 'https://' . $u;
        }
        return rtrim($u, '/');
    }

    public static function enabled(string $site): bool
    {
        return $site === 'instagram' && self::base() !== '';
    }

    public static function probe(string $url): ?array
    {
        $items = self::resolve($url);
        if ($items === null) {
            return null;
        }
        $kinds = array_column($items, 'kind');
        $thumb = '';
        foreach ($items as $it) {
            if (preg_match('#^https://#', (string) $it['thumb'])) {
                $thumb = (string) $it['thumb'];
                break;
            }
        }
        $pg = self::page($url);
        if ($thumb === '') {
            $thumb = $pg['thumb'];
        }
        $info = ['webpage_url' => $url, 'thumbnail' => $thumb, 'count' => count($kinds), 'kinds' => $kinds];
        if ($pg['title'] !== '') {
            $info['title'] = $pg['title'];
        }
        if ($pg['uploader'] !== '') {
            $info['uploader'] = $pg['uploader'];
        }
        if (in_array('v', $kinds, true)) {
            $info['vcodec'] = 'h264';
        }
        logInfo('dl', 'cobalt probe: kinds=' . implode('', $kinds));
        return ['kinds' => $kinds, 'info' => $info];
    }

    public static function download(string $url, string $fmt, string $dir, int $limit): bool
    {
        $audio = $fmt === 'mp3';
        $q     = ['sd' => '360', 'hd' => '1080', 'fhd' => '1080'][$fmt] ?? (preg_match('/^v(\d{3,4})$/', $fmt, $m) ? $m[1] : '720');
        $items = self::resolve($url, $audio ? 'audio' : 'auto', $q);
        if ($items === null || count($items) !== 1) {
            return false;
        }
        @mkdir($dir, 0775, true);
        $file = self::save($items[0]['url'], $dir . '/cobalt', $limit, $audio ? 'mp3' : ($items[0]['kind'] === 'p' ? 'jpg' : 'mp4'));
        if ($file === null) {
            self::clear($dir);
            return false;
        }
        logInfo('dl', 'cobalt download ok: ' . basename($file) . ' ' . (int) filesize($file) . ' bytes');
        return true;
    }

    public static function album(string $url, string $dir, int $limit, int $max = self::MAX_ITEMS): bool
    {
        $items = self::resolve($url);
        if ($items === null) {
            return false;
        }
        @mkdir($dir, 0775, true);
        foreach (array_slice($items, 0, $max) as $i => $it) {
            $base = sprintf('%s/%02d_cobalt', $dir, $i + 1);
            if (self::save($it['url'], $base, $limit, $it['kind'] === 'p' ? 'jpg' : 'mp4') === null) {
                self::clear($dir);
                return false;
            }
        }
        logInfo('dl', 'cobalt album ok: files=' . min(count($items), $max));
        return true;
    }

    public static function photos(string $url, int $max): array
    {
        $items = self::resolve($url);
        if ($items === null) {
            return ['files' => [], 'total' => 0];
        }
        $dir   = sys_get_temp_dir() . '/nl_cobalt_' . bin2hex(random_bytes(4));
        @mkdir($dir, 0775, true);
        $files = [];
        foreach ($items as $it) {
            if ($it['kind'] !== 'p' || count($files) >= $max) {
                continue;
            }
            $f = self::save($it['url'], $dir . '/' . count($files), 20 * 1048576, 'jpg');
            if ($f === null) {
                continue;
            }
            $tmp = tempnam(sys_get_temp_dir(), 'nl_ph_');
            if ($tmp !== false && @copy($f, $tmp)) {
                $files[] = $tmp;
            }
        }
        self::clear($dir);
        @rmdir($dir);
        return ['files' => $files, 'total' => count($items)];
    }

    private static function resolve(string $url, string $mode = 'auto', string $quality = '1080'): ?array
    {
        $base    = self::base();
        $headers = ['Accept: application/json', 'Content-Type: application/json'];
        $key     = trim((string) env('COBALT_API_KEY', ''));
        if ($key !== '') {
            $headers[] = 'Authorization: Api-Key ' . $key;
        }
        $r = Http::request('POST', $base . '/', [
            'headers'         => $headers,
            'body'            => json_encode([
                'url'           => $url,
                'videoQuality'  => $quality,
                'downloadMode'  => $mode,
                'audioFormat'   => 'mp3',
                'filenameStyle' => 'basic',
            ], JSON_UNESCAPED_SLASHES),
            'timeout'         => 25,
            'connect_timeout' => 6,
        ]);
        $j = json_decode((string) $r['body'], true);
        if (!is_array($j)) {
            logWarn('dl', 'cobalt: no JSON (http ' . $r['code'] . ') ' . $r['error']);
            return null;
        }
        $status = (string) ($j['status'] ?? '');
        if (($status === 'tunnel' || $status === 'redirect') && !empty($j['url'])) {
            return [['kind' => self::kindOf((string) ($j['filename'] ?? ''), ''), 'url' => self::fix((string) $j['url'], $base), 'thumb' => '']];
        }
        if ($status === 'picker') {
            $out = [];
            foreach ((array) ($j['picker'] ?? []) as $p) {
                if (!is_array($p) || empty($p['url'])) {
                    continue;
                }
                $out[] = ['kind' => self::kindOf('', (string) ($p['type'] ?? '')), 'url' => self::fix((string) $p['url'], $base), 'thumb' => (string) ($p['thumb'] ?? '')];
            }
            $out = array_slice($out, 0, self::MAX_ITEMS);
            if ($out !== []) {
                return $out;
            }
        }
        logWarn('dl', 'cobalt: status=' . $status . ' code=' . (string) ($j['error']['code'] ?? '-') . ' http=' . $r['code']);
        return null;
    }

    private static function page(string $url): array
    {
        $out  = ['thumb' => '', 'title' => '', 'uploader' => ''];
        $r    = Http::request('GET', $url, [
            'timeout'         => 10,
            'connect_timeout' => 5,
            'max_bytes'       => 800000,
            'headers'         => ['User-Agent: facebookexternalhit/1.1 (+http://www.facebook.com/externalhit_uatext.php)', 'Accept: text/html'],
        ]);
        $html = (string) ($r['body'] ?? '');
        if ($r['code'] !== 200 || $html === '') {
            return $out;
        }
        $m = self::metas($html);
        $u = (string) ($m['og:image'] ?? '');
        if (str_starts_with($u, 'https://')) {
            $out['thumb'] = $u;
        }
        $t = trim((string) ($m['og:title'] ?? ''));
        if (preg_match('/^(.+?) on Instagram:\s*["\x{201C}](.*)["\x{201D}]\s*$/us', $t, $x)) {
            $cap = trim((string) preg_replace('/\s+/u', ' ', $x[2]));
            $out['uploader'] = mb_substr(trim($x[1]), 0, 80);
            $out['title']    = mb_strlen($cap) > 200 ? mb_substr($cap, 0, 200) . '…' : $cap;
        }
        if ($out['title'] === '') {
            $d = trim((string) ($m['og:description'] ?? $m['description'] ?? ''));
            if (preg_match('/^.*?\s-\s(.+?)\s+on\s+[^:]{3,40}:\s*[\"\x{201C}](.*)[\"\x{201D}]\.?\s*$/us', $d, $x)) {
                $cap = trim((string) preg_replace('/\s+/u', ' ', $x[2]));
                if ($out['uploader'] === '') {
                    $out['uploader'] = mb_substr(trim($x[1]), 0, 80);
                }
                $out['title'] = mb_strlen($cap) > 200 ? mb_substr($cap, 0, 200) . '…' : $cap;
            }
        }
        if ($out['uploader'] === '' && preg_match('/^(.+?)\s+on\s+Instagram\b/u', $t, $x)) {
            $out['uploader'] = mb_substr(trim($x[1]), 0, 80);
        }
        logInfo('dl', 'cobalt page: cover=' . ($out['thumb'] !== '' ? '1' : '0') . ' title=' . ($out['title'] !== '' ? '1' : '0'));
        return $out;
    }

    private static function metas(string $html): array
    {
        $out = [];
        if (!preg_match_all('#<meta\s[^>]*>#i', $html, $tags)) {
            return $out;
        }
        foreach ($tags[0] as $tag) {
            if (!preg_match_all('#([\w:-]+)\s*=\s*(["\'])(.*?)\2#s', $tag, $attrs, PREG_SET_ORDER)) {
                continue;
            }
            $a = [];
            foreach ($attrs as $x) {
                $a[strtolower($x[1])] = $x[3];
            }
            $k = (string) ($a['property'] ?? $a['name'] ?? '');
            if ($k !== '' && isset($a['content']) && !isset($out[$k])) {
                $out[$k] = html_entity_decode($a['content'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }
        }
        return $out;
    }

    private static function kindOf(string $name, string $type): string
    {
        if ($type === 'photo') {
            return 'p';
        }
        if ($type === 'video' || $type === 'gif') {
            return 'v';
        }
        return in_array(strtolower(pathinfo($name, PATHINFO_EXTENSION)), self::IMAGE, true) ? 'p' : 'v';
    }

    private static function fix(string $u, string $base): string
    {
        $p    = parse_url($u);
        $host = strtolower((string) ($p['host'] ?? ''));
        if (!in_array($host, ['localhost', '127.0.0.1', '0.0.0.0', '[::1]'], true)) {
            return $u;
        }
        $b = parse_url($base);
        return ($b['scheme'] ?? 'https') . '://' . ($b['host'] ?? '') . (isset($b['port']) ? ':' . $b['port'] : '')
            . ($p['path'] ?? '/') . (isset($p['query']) ? '?' . $p['query'] : '');
    }

    private static function save(string $url, string $base, int $limit, string $defExt): ?string
    {
        $tmp = $base . '.part';
        $fh  = @fopen($tmp, 'wb');
        if ($fh === false) {
            return null;
        }
        $size = 0;
        $type = '';
        $ch   = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 4,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT        => 600,
            CURLOPT_LOW_SPEED_LIMIT => 1024,
            CURLOPT_LOW_SPEED_TIME => 40,
            CURLOPT_ENCODING       => '',
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36',
            CURLOPT_HEADERFUNCTION => static function ($c, string $line) use (&$type): int {
                if (stripos($line, 'HTTP/') === 0) {
                    $type = '';
                } elseif (stripos($line, 'content-type:') === 0) {
                    $type = strtolower(trim(substr($line, 13)));
                }
                return strlen($line);
            },
            CURLOPT_WRITEFUNCTION  => static function ($c, string $data) use ($fh, &$size, $limit): int {
                $size += strlen($data);
                return $size > $limit ? 0 : (int) fwrite($fh, $data);
            },
        ]);
        curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);
        fclose($fh);
        if ($code >= 400 || $size < 200 || $err !== '' || str_contains($type, 'json') || str_contains($type, 'html') || str_contains($type, 'text/')) {
            logWarn('dl', "cobalt save failed: http={$code} bytes={$size} type={$type} err={$err}");
            @unlink($tmp);
            return null;
        }
        $ext  = match (true) {
            str_contains($type, 'jpeg') => 'jpg',
            str_contains($type, 'png')  => 'png',
            str_contains($type, 'webp') => 'webp',
            str_contains($type, 'heic'), str_contains($type, 'heif') => 'heic',
            str_contains($type, 'mpeg'), str_contains($type, 'mp3') => 'mp3',
            str_contains($type, 'mp4')  => $defExt === 'mp3' ? 'mp3' : 'mp4',
            str_contains($type, 'quicktime') => 'mov',
            default => $defExt,
        };
        $dest = $base . '.' . $ext;
        return @rename($tmp, $dest) ? $dest : null;
    }

    private static function clear(string $dir): void
    {
        foreach ((array) glob($dir . '/*') as $f) {
            if (is_file($f)) {
                @unlink($f);
            }
        }
    }
}
