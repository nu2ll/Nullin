<?php

final class Http
{
    private static function impersonated(string $method, string $url, array $opt): ?array
    {
        $script = (defined('NULLINI_ROOT') ? NULLINI_ROOT : dirname(__DIR__, 2)) . '/bin/impersonate.py';
        if (!is_file($script) || !function_exists('proc_open')) {
            return null;
        }
        $timeout = (int) ($opt['timeout'] ?? 15);
        $payload = json_encode([
            'method'      => $method,
            'url'         => $url,
            'headers'     => $opt['headers'] ?? [],
            'body'        => isset($opt['body']) ? (string) $opt['body'] : null,
            'follow'      => $opt['follow'] ?? true,
            'timeout'     => $timeout,
            'max_bytes'   => $opt['max_bytes'] ?? 2000000,
            'impersonate' => is_string($opt['impersonate']) ? $opt['impersonate'] : 'chrome',
        ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        $proc = @proc_open(['timeout', (string) ($timeout + 5), 'python3', $script], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($proc)) {
            return null;
        }
        fwrite($pipes[0], (string) $payload);
        fclose($pipes[0]);
        $out = (string) stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($proc);
        $j = json_decode($out, true);
        if (!is_array($j) || stripos((string) ($j['error'] ?? ''), 'No module named') !== false) {
            return null;
        }
        $body = base64_decode((string) ($j['body'] ?? ''), true);
        return [
            'code'     => (int) ($j['code'] ?? 0),
            'body'     => (int) ($j['code'] ?? 0) === 0 ? null : (string) $body,
            'error'    => (string) ($j['error'] ?? ''),
            'redirect' => (string) ($j['redirect'] ?? ''),
            'url'      => (string) ($j['url'] ?? $url),
        ];
    }

    public static $tick = null;

    private static function progress(): array
    {
        return [
            CURLOPT_NOPROGRESS       => false,
            CURLOPT_PROGRESSFUNCTION => static function (): int {
                if (self::$tick !== null) {
                    (self::$tick)();
                }
                return 0;
            },
        ];
    }

    public static function request(string $method, string $url, array $opt = []): array
    {
        if (!empty($opt['impersonate']) && ($viaPy = self::impersonated($method, $url, $opt)) !== null) {
            return $viaPy;
        }
        $ch = curl_init($url);
        $set = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => $opt['follow'] ?? true,
            CURLOPT_MAXREDIRS      => 4,
            CURLOPT_TIMEOUT        => $opt['timeout'] ?? 15,
            CURLOPT_CONNECTTIMEOUT => $opt['connect_timeout'] ?? 8,
            CURLOPT_ENCODING       => '',
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; NulliniBot/3.0)',
            CURLOPT_HTTPHEADER     => $opt['headers'] ?? [],
            CURLOPT_SSL_VERIFYPEER => true,
        ];
        if ($method !== 'GET') {
            $set[CURLOPT_CUSTOMREQUEST] = $method;
            $set[CURLOPT_POSTFIELDS]    = $opt['body'] ?? '';
        }
        $buf = null;
        if (isset($opt['max_bytes'])) {
            $buf = '';
            $max = (int) $opt['max_bytes'];
            $set[CURLOPT_RETURNTRANSFER] = false;
            $set[CURLOPT_WRITEFUNCTION]  = static function ($ch, string $data) use (&$buf, $max): int {
                $buf .= $data;
                return strlen($buf) > $max ? 0 : strlen($data);
            };
        }
        if (self::$tick !== null) {
            $set += self::progress();
        }
        curl_setopt_array($ch, $set);

        $body     = curl_exec($ch);
        $code     = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $redirect = (string) curl_getinfo($ch, CURLINFO_REDIRECT_URL);
        $effective = (string) curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        $error    = curl_error($ch);
        curl_close($ch);
        if ($buf !== null) {
            $body = $buf;
        }

        return ['code' => $code, 'body' => $body === false ? null : (string) $body, 'error' => $error, 'redirect' => $redirect, 'url' => $effective];
    }

    public static function getJson(string $url, array $headers = [], int $timeout = 12): ?array
    {
        $r = self::request('GET', $url, ['headers' => array_merge(['Accept: application/json'], $headers), 'timeout' => $timeout]);
        if ($r['body'] === null || $r['code'] >= 400) {
            return null;
        }
        $data = json_decode($r['body'], true);
        return is_array($data) ? $data : null;
    }

    public static function postJson(string $url, array $payload, array $headers = [], int $timeout = 20): array
    {
        $r = self::request('POST', $url, [
            'headers' => array_merge(['Content-Type: application/json', 'Accept: application/json'], $headers),
            'body'    => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
            'timeout' => $timeout,
        ]);
        $data = $r['body'] !== null ? json_decode($r['body'], true) : null;
        return ['code' => $r['code'], 'data' => is_array($data) ? $data : null, 'raw' => $r['body'] ?? $r['error']];
    }

    public static function stream(string $url, array $payload, array $headers, int $timeout, callable $onChunk): array
    {
        $err = '';
        $ch  = curl_init($url);
        $set = self::$tick !== null ? self::progress() : [];
        curl_setopt_array($ch, $set + [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
            CURLOPT_HTTPHEADER     => array_merge(['Content-Type: application/json', 'Accept: text/event-stream'], $headers),
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; NulliniBot/3.0)',
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_WRITEFUNCTION  => static function ($ch, string $data) use (&$err, $onChunk): int {
                if ((int) curl_getinfo($ch, CURLINFO_HTTP_CODE) === 200) {
                    $onChunk($data);
                } elseif (strlen($err) < 20000) {
                    $err .= $data;
                }
                return strlen($data);
            },
        ]);
        curl_exec($ch);
        $code  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        return ['code' => $code, 'raw' => $err, 'error' => $error];
    }

    public static function sseData(string &$buf, string $chunk): array
    {
        $buf .= $chunk;
        $out  = [];
        while (($pos = strpos($buf, "\n")) !== false) {
            $line = rtrim(substr($buf, 0, $pos), "\r");
            $buf  = substr($buf, $pos + 1);
            if (str_starts_with($line, 'data:')) {
                $out[] = ltrim(substr($line, 5));
            }
        }
        return $out;
    }
}
