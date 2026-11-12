<?php

final class LinkSites
{
    private const UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36';

    private const MOBILE_UA = 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Mobile Safari/537.36';

    private const HTML_ACCEPT = 'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8';

    private const COUNTRIES = [
        'AF' => 'Afghanistan',
        'AL' => 'Albania',
        'DZ' => 'Algeria',
        'AD' => 'Andorra',
        'AO' => 'Angola',
        'AG' => 'Antigua and Barbuda',
        'AR' => 'Argentina',
        'AM' => 'Armenia',
        'AU' => 'Australia',
        'AT' => 'Austria',
        'AZ' => 'Azerbaijan',
        'BS' => 'Bahamas',
        'BH' => 'Bahrain',
        'BD' => 'Bangladesh',
        'BB' => 'Barbados',
        'BY' => 'Belarus',
        'BE' => 'Belgium',
        'BZ' => 'Belize',
        'BJ' => 'Benin',
        'BT' => 'Bhutan',
        'BO' => 'Bolivia',
        'BA' => 'Bosnia and Herzegovina',
        'BW' => 'Botswana',
        'BR' => 'Brazil',
        'BN' => 'Brunei',
        'BG' => 'Bulgaria',
        'BF' => 'Burkina Faso',
        'BI' => 'Burundi',
        'CV' => 'Cabo Verde',
        'KH' => 'Cambodia',
        'CM' => 'Cameroon',
        'CA' => 'Canada',
        'CF' => 'Central African Republic',
        'TD' => 'Chad',
        'CL' => 'Chile',
        'CN' => 'China',
        'CO' => 'Colombia',
        'KM' => 'Comoros',
        'CD' => 'Congo Democratic Republic',
        'CG' => 'Congo Republic',
        'CR' => 'Costa Rica',
        'CI' => 'Côte d\'Ivoire',
        'HR' => 'Croatia',
        'CU' => 'Cuba',
        'CY' => 'Cyprus',
        'CZ' => 'Czechia',
        'DK' => 'Denmark',
        'DJ' => 'Djibouti',
        'DM' => 'Dominica',
        'DO' => 'Dominican Republic',
        'EC' => 'Ecuador',
        'EG' => 'Egypt',
        'SV' => 'El Salvador',
        'GQ' => 'Equatorial Guinea',
        'ER' => 'Eritrea',
        'EE' => 'Estonia',
        'SZ' => 'Eswatini',
        'ET' => 'Ethiopia',
        'FJ' => 'Fiji',
        'FI' => 'Finland',
        'FR' => 'France',
        'GA' => 'Gabon',
        'GM' => 'Gambia',
        'GE' => 'Georgia',
        'DE' => 'Germany',
        'GH' => 'Ghana',
        'GR' => 'Greece',
        'GD' => 'Grenada',
        'GT' => 'Guatemala',
        'GN' => 'Guinea',
        'GW' => 'Guinea-Bissau',
        'GY' => 'Guyana',
        'HT' => 'Haiti',
        'HN' => 'Honduras',
        'HU' => 'Hungary',
        'IS' => 'Iceland',
        'IN' => 'India',
        'ID' => 'Indonesia',
        'IR' => 'Iran',
        'IQ' => 'Iraq',
        'IE' => 'Ireland',
        'IL' => 'Israel',
        'IT' => 'Italy',
        'JM' => 'Jamaica',
        'JP' => 'Japan',
        'JO' => 'Jordan',
        'KZ' => 'Kazakhstan',
        'KE' => 'Kenya',
        'KI' => 'Kiribati',
        'KP' => 'North Korea',
        'KR' => 'South Korea',
        'KW' => 'Kuwait',
        'KG' => 'Kyrgyzstan',
        'LA' => 'Laos',
        'LV' => 'Latvia',
        'LB' => 'Lebanon',
        'LS' => 'Lesotho',
        'LR' => 'Liberia',
        'LY' => 'Libya',
        'LI' => 'Liechtenstein',
        'LT' => 'Lithuania',
        'LU' => 'Luxembourg',
        'MG' => 'Madagascar',
        'MW' => 'Malawi',
        'MY' => 'Malaysia',
        'MV' => 'Maldives',
        'ML' => 'Mali',
        'MT' => 'Malta',
        'MH' => 'Marshall Islands',
        'MR' => 'Mauritania',
        'MU' => 'Mauritius',
        'MX' => 'Mexico',
        'FM' => 'Micronesia',
        'MD' => 'Moldova',
        'MC' => 'Monaco',
        'MN' => 'Mongolia',
        'ME' => 'Montenegro',
        'MA' => 'Morocco',
        'MZ' => 'Mozambique',
        'MM' => 'Myanmar',
        'NA' => 'Namibia',
        'NR' => 'Nauru',
        'NP' => 'Nepal',
        'NL' => 'Netherlands',
        'NZ' => 'New Zealand',
        'NI' => 'Nicaragua',
        'NE' => 'Niger',
        'NG' => 'Nigeria',
        'MK' => 'North Macedonia',
        'NO' => 'Norway',
        'OM' => 'Oman',
        'PK' => 'Pakistan',
        'PW' => 'Palau',
        'PA' => 'Panama',
        'PG' => 'Papua New Guinea',
        'PY' => 'Paraguay',
        'PE' => 'Peru',
        'PH' => 'Philippines',
        'PL' => 'Poland',
        'PT' => 'Portugal',
        'QA' => 'Qatar',
        'RO' => 'Romania',
        'RU' => 'Russia',
        'RW' => 'Rwanda',
        'KN' => 'Saint Kitts and Nevis',
        'LC' => 'Saint Lucia',
        'VC' => 'Saint Vincent and the Grenadines',
        'WS' => 'Samoa',
        'SM' => 'San Marino',
        'ST' => 'Sao Tome and Principe',
        'SA' => 'Saudi Arabia',
        'SN' => 'Senegal',
        'RS' => 'Serbia',
        'SC' => 'Seychelles',
        'SL' => 'Sierra Leone',
        'SG' => 'Singapore',
        'SK' => 'Slovakia',
        'SI' => 'Slovenia',
        'SB' => 'Solomon Islands',
        'SO' => 'Somalia',
        'ZA' => 'South Africa',
        'SS' => 'South Sudan',
        'ES' => 'Spain',
        'LK' => 'Sri Lanka',
        'SD' => 'Sudan',
        'SR' => 'Suriname',
        'SE' => 'Sweden',
        'CH' => 'Switzerland',
        'SY' => 'Syria',
        'TJ' => 'Tajikistan',
        'TZ' => 'Tanzania',
        'TH' => 'Thailand',
        'TL' => 'Timor-Leste',
        'TG' => 'Togo',
        'TO' => 'Tonga',
        'TT' => 'Trinidad and Tobago',
        'TN' => 'Tunisia',
        'TR' => 'Turkey',
        'TM' => 'Turkmenistan',
        'TV' => 'Tuvalu',
        'UG' => 'Uganda',
        'UA' => 'Ukraine',
        'AE' => 'United Arab Emirates',
        'GB' => 'United Kingdom',
        'US' => 'United States',
        'UY' => 'Uruguay',
        'UZ' => 'Uzbekistan',
        'VU' => 'Vanuatu',
        'VA' => 'Vatican City',
        'VE' => 'Venezuela',
        'VN' => 'Vietnam',
        'YE' => 'Yemen',
        'ZM' => 'Zambia',
        'ZW' => 'Zimbabwe',
    ];

    public static function run(string $platform, string $url): array
    {
        return match ($platform) {
            'tiktok'     => self::tiktok($url),
            'instagram'  => self::instagram($url),
            'chatgpt'    => self::chatgpt($url),
            'claude'     => self::claude($url),
            'perplexity' => self::perplexity($url),
            'pinterest'  => self::pinterest($url),
            'reddit'     => self::reddit($url),
            'substack'   => self::substack($url),
            'suno'       => self::suno($url),
            'linkedin'   => self::linkedin($url),
            'notion'     => self::notion($url),
            'youtube'    => self::youtube($url),
            default      => ['error' => L('این نوع لینک رو هنوز بلد نیستم', 'I don’t support this type of link yet')],
        };
    }

    private static function req(string $method, string $url, array $headers = [], ?string $body = null, int $max = 2000000, bool $follow = true): array
    {
        $hasUa = false;
        foreach ($headers as $h) {
            if (stripos($h, 'user-agent:') === 0) {
                $hasUa = true;
            }
        }
        $opt = [
            'headers'         => array_merge($hasUa ? [] : ['User-Agent: ' . self::UA], ['Accept-Language: en-US,en;q=0.9'], $headers),
            'timeout'         => 15,
            'connect_timeout' => 8,
            'max_bytes'       => $max,
            'follow'          => $follow,
            'impersonate'     => 'chrome',
        ];
        if ($body !== null) {
            $opt['body'] = $body;
        }
        return Http::request($method, $url, $opt);
    }

    private static function json(array $r): ?array
    {
        $d = ($r['body'] ?? '') !== '' ? json_decode((string) $r['body'], true) : null;
        return is_array($d) ? $d : null;
    }

    private static function fail(string $site, array $r): array
    {
        $code = (int) ($r['code'] ?? 0);
        if ($code === 0) {
            return ['error' => L('نتونستم به ' . $site . ' وصل بشم', 'I couldn’t connect to ' . $site)];
        }
        if (in_array($code, [401, 403, 429, 999], true)) {
            return ['error' => L($site . ' جلوی درخواستم رو گرفت (HTTP ' . $code . ")؛\nاین سایت‌ها معمولاً جلوی ربات‌ها رو می‌گیرن", $site . ' blocked my request (HTTP ' . $code . ");\nthese sites usually block bots")];
        }
        return ['error' => L($site . ' جواب نداد (HTTP ' . $code . ')', $site . ' didn’t respond (HTTP ' . $code . ')')];
    }

    private static function dec(string $s): string
    {
        return html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    private static function prune(array $d): array
    {
        return array_filter($d, static fn($v) => $v !== null && $v !== '' && $v !== []);
    }

    public static function tiktok(string $url): array
    {
        $h  = ['User-Agent: ' . self::MOBILE_UA, self::HTML_ACCEPT];
        $r1 = self::req('GET', $url, $h, null, 3000000);
        if ($r1['code'] === 0) {
            return self::fail(L('تیک‌تاک', 'TikTok'), $r1);
        }
        $final = ($r1['url'] ?? '') !== '' ? (string) $r1['url'] : $url;
        $r     = $final === $url ? $r1 : self::req('GET', $final, $h, null, 3000000);
        $html  = (string) ($r['body'] ?? '');
        if ($html === '') {
            return self::fail(L('تیک‌تاک', 'TikTok'), $r);
        }
        if (preg_match('~"webapp\.reflow\.global\.shareUser":\s*(\{[^}]+\{[^}]+\}[^}]*\})~', $html, $m)) {
            $j  = json_decode(str_replace('\u002F', '/', $m[1]), true);
            $su = is_array($j) ? ($j['shareUser'] ?? []) : [];
            if ($su) {
                $country = null;
                if (preg_match('~"share_region"\s*:\s*"([A-Z]{2})"~', $html, $x)) {
                    $country = self::COUNTRIES[$x[1]] ?? $x[1];
                }
                $at = null;
                if (preg_match('~"timestamp"\s*:\s*"(\d+)"~', $html, $x)) {
                    $at = gmdate('Y-m-d H:i:s', (int) $x[1]) . ' UTC';
                }
                $uname = $su['uniqueId'] ?? null;
                return ['data' => [
                    'profile_url'     => $uname !== null ? 'https://www.tiktok.com/@' . $uname : null,
                    'user_id'         => $su['id'] ?? null,
                    'username'        => $uname,
                    'nickname'        => $su['nickname'] ?? null,
                    'country'         => $country,
                    'avatar_url'      => isset($su['avatarLarger']) ? str_replace('\u002F', '/', (string) $su['avatarLarger']) : null,
                    'signature'       => $su['signature'] ?? null,
                    'device'          => preg_match('~"utm_medium"\s*:\s*"([^"]+)"~', $html, $x) ? $x[1] : null,
                    'share_method'    => preg_match('~"utm_source"\s*:\s*"([^"]+)"~', $html, $x) ? $x[1] : null,
                    'shared_at'       => $at,
                    'follower_count'  => $su['followerCount'] ?? null,
                    'following_count' => $su['followingCount'] ?? null,
                    'video_count'     => $su['videoCount'] ?? null,
                    'heart_count'     => $su['heartCount'] ?? null,
                    'private_account' => $su['privateAccount'] ?? null,
                    'dm_available'    => $su['dmAvailable'] ?? null,
                ]];
            }
        }
        if (preg_match('~<script id="__UNIVERSAL_DATA_FOR_REHYDRATION__"[^>]*>(.*?)</script>~s', $html, $m)) {
            $blob  = json_decode($m[1], true);
            $scope = is_array($blob) ? ($blob['__DEFAULT_SCOPE__'] ?? []) : [];
            $info  = $scope['webapp.user-detail']['userInfo'] ?? null;
            if (is_array($info) && !empty($info['user']['uniqueId'])) {
                return ['data' => self::tiktokProfile($info['user'], (array) ($info['stats'] ?? []))];
            }
            $item = $scope['webapp.video-detail']['itemInfo']['itemStruct'] ?? null;
            if (is_array($item) && !empty($item['author']['uniqueId'])) {
                return ['data' => self::tiktokProfile($item['author'], (array) ($item['authorStats'] ?? []))];
            }
        }
        return ['error' => L('اطلاعات اشتراک‌گذار رو تو صفحه‌ی تیک‌تاک پیدا نکردم', 'I couldn’t find the sharer’s info on the TikTok page')];
    }

    private static function tiktokProfile(array $u, array $s): array
    {
        $uname  = $u['uniqueId'] ?? null;
        $region = (string) ($u['region'] ?? '');
        return [
            'profile_url'     => $uname !== null ? 'https://www.tiktok.com/@' . $uname : null,
            'user_id'         => $u['id'] ?? null,
            'username'        => $uname,
            'nickname'        => $u['nickname'] ?? null,
            'country'         => $region !== '' ? (self::COUNTRIES[$region] ?? $region) : null,
            'avatar_url'      => $u['avatarLarger'] ?? null,
            'signature'       => $u['signature'] ?? null,
            'follower_count'  => $s['followerCount'] ?? null,
            'following_count' => $s['followingCount'] ?? null,
            'video_count'     => $s['videoCount'] ?? null,
            'heart_count'     => $s['heartCount'] ?? $s['heart'] ?? null,
            'private_account' => $u['privateAccount'] ?? null,
        ];
    }

    public static function instagram(string $url): array
    {
        $r = self::req('GET', $url, [
            'User-Agent: ' . self::MOBILE_UA, self::HTML_ACCEPT, 'Sec-Fetch-Site: none', 'Sec-Fetch-Mode: navigate', 'Sec-Fetch-Dest: document', 'Upgrade-Insecure-Requests: 1',
        ], null, 3000000);
        $html = (string) ($r['body'] ?? '');
        if ($html === '') {
            return self::fail(L('اینستاگرام', 'Instagram'), $r);
        }
        $u = null;
        foreach (['user_for_shid_logged_out', 'sharer'] as $key) {
            if (preg_match('~"' . $key . '"\s*:\s*(\{[^{}]*\})~', $html, $m) && is_array($j = json_decode($m[1], true))) {
                $u = $j;
                break;
            }
        }
        if ($u === null) {
            return ['error' => L("اطلاعات اشتراک‌گذار تو صفحه نبود\n(فقط لینک‌های تازه این اطلاعات رو دارن)", "The sharer’s info wasn’t on the page\n(only recent links have it)")];
        }
        $uname = $u['username'] ?? null;
        return ['data' => [
            'username'    => $uname,
            'user_id'     => $u['id'] ?? null,
            'name'        => $u['full_name'] ?? null,
            'profile_url' => $uname ? 'https://www.instagram.com/' . $uname . '/' : null,
            'avatar_url'  => isset($u['profile_pic_url']) ? urldecode((string) $u['profile_pic_url']) : null,
        ]];
    }

    public static function chatgpt(string $url): array
    {
        $r    = self::req('GET', $url, [self::HTML_ACCEPT], null, 1500000);
        $html = (string) ($r['body'] ?? '');
        if ($html === '' || $r['code'] >= 400) {
            return self::fail(L('چت‌جی‌پی‌تی', 'ChatGPT'), $r);
        }
        if (!preg_match('~<meta property="og:description" content="Shared by (.+?) via ChatGPT"~', $html, $m)) {
            return ['error' => L('اسم اشتراک‌گذار تو صفحه نبود', 'The sharer’s name wasn’t on the page')];
        }
        return ['data' => ['name' => self::dec($m[1])]];
    }

    public static function claude(string $url): array
    {
        if (!preg_match('~claude\.ai/share/([a-f0-9-]+)~i', $url, $m)) {
            return ['error' => L('این لینک اشتراک کلود درست نیست', 'This Claude share link isn’t valid')];
        }
        $r = self::req('GET', 'https://claude.ai/api/chat_snapshots/' . $m[1], ['Accept: application/json'], null, 1500000);
        $j = self::json($r);
        if ($j === null || $r['code'] >= 400) {
            return self::fail(L('کلود', 'Claude'), $r);
        }
        if (empty($j['creator']['full_name'])) {
            return ['error' => L('کلود اطلاعات سازنده‌ی چت رو نداد', 'Claude didn’t return the chat creator’s info')];
        }
        return ['data' => ['name' => $j['creator']['full_name'], 'user_id' => $j['creator']['uuid'] ?? null]];
    }

    public static function perplexity(string $url): array
    {
        if (!preg_match('~perplexity\.ai/search/([A-Za-z0-9._-]+)~i', $url, $m)) {
            return ['error' => L('این لینک اشتراک پرپلکسیتی درست نیست', 'This Perplexity share link isn’t valid')];
        }
        $r = self::req('GET', 'https://www.perplexity.ai/rest/thread/' . $m[1], ['Accept: application/json'], null, 1500000);
        if ($r['code'] === 403) {
            return ['error' => L("پرپلکسیتی جلوی درخواستم رو گرفت؛\nسایتش محافظت‌شده‌ست", "Perplexity blocked my request;\nits site is protected")];
        }
        $j = self::json($r);
        if ($j === null || ($j['status'] ?? '') !== 'success') {
            return self::fail(L('پرپلکسیتی', 'Perplexity'), $r);
        }
        $e = $j['entries'][0] ?? null;
        if (!is_array($e)) {
            return ['error' => L('اطلاعاتی تو جواب پرپلکسیتی نبود', 'There was no info in Perplexity’s response')];
        }
        return ['data' => ['username' => $e['author_username'] ?? null, 'avatar_url' => $e['author_image'] ?? null, 'user_id' => $e['author_id'] ?? null]];
    }

    public static function pinterest(string $url): array
    {
        if (!preg_match('~pin\.it/([A-Za-z0-9]+)~i', $url, $m)) {
            return ['error' => L('این لینک کوتاه پینترست درست نیست', 'This Pinterest short link isn’t valid')];
        }
        $r   = self::req('GET', 'https://api.pinterest.com/url_shortener/' . $m[1] . '/redirect/', [self::HTML_ACCEPT], null, 20000, false);
        $loc = (string) ($r['redirect'] ?? '');
        if (!preg_match('~invite_code=([a-f0-9]+)~', $loc, $x)) {
            return ['error' => L('کد دعوت تو لینک پینترست نبود', 'There was no invite code in the Pinterest link')];
        }
        $invite = $x[1];
        $pin    = preg_match('~/pin/(\d+)/~', $loc, $y) ? $y[1] : '0';
        $src    = "/pin/{$pin}/sent/?invite_code={$invite}";
        $q      = http_build_query([
            'source_url' => $src,
            'data'       => json_encode(['options' => ['invite_code' => $invite, 'field_set_key' => 'default'], 'context' => (object) []]),
            '_'          => (string) (int) (microtime(true) * 1000),
        ]);
        $r2 = self::req('GET', 'https://www.pinterest.com/resource/InviteCodeMetadataResource/get/?' . $q, [
            'Accept: application/json, text/javascript, */*, q=0.01', 'Referer: https://www.pinterest.com/', 'X-Requested-With: XMLHttpRequest',
            'X-Pinterest-AppState: active', 'X-Pinterest-PWS-Handler: www/pin/[id]/sent.js', 'X-Pinterest-Source-Url: ' . $src,
        ], null, 500000);
        $j      = self::json($r2);
        $sender = $j['resource_response']['data']['sender'] ?? null;
        if (!is_array($sender) || !$sender) {
            return $j === null ? self::fail(L('پینترست', 'Pinterest'), $r2) : ['error' => L('اطلاعات فرستنده رو پیدا نکردم', 'I couldn’t find the sender’s info')];
        }
        $uname = $sender['username'] ?? null;
        return ['data' => [
            'profile_url' => $uname ? 'https://www.pinterest.com/' . $uname . '/' : null,
            'username'    => $uname,
            'user_id'     => $sender['id'] ?? null,
            'name'        => $sender['full_name'] ?? null,
            'avatar_url'  => $sender['image_large_url'] ?? null,
        ]];
    }

    public static function reddit(string $url): array
    {
        $cur  = $url;
        $post = null;
        for ($i = 0; $i < 5; $i++) {
            $r = self::req('GET', $cur, [], null, 20000, false);
            if ($r['code'] < 300 || $r['code'] >= 400 || ($r['redirect'] ?? '') === '') {
                break;
            }
            $loc  = (string) $r['redirect'];
            $host = strtolower((string) parse_url($loc, PHP_URL_HOST));
            if ($host !== 'reddit.com' && !str_ends_with($host, '.reddit.com')) {
                break;
            }
            $cur = $loc;
            if (preg_match('~reddit\.com/r/[A-Za-z0-9_]+/comments/[A-Za-z0-9]+~', $cur)) {
                $p    = parse_url($cur);
                $post = 'https://' . $p['host'] . rtrim((string) ($p['path'] ?? ''), '/');
                break;
            }
        }
        if ($post === null) {
            return ['error' => L('این لینک اشتراک به پست ردیت نرسید', 'This share link didn’t lead to a Reddit post')];
        }
        $r = self::req('GET', $post . '.json', ['Accept: application/json'], null, 1500000);
        $j = self::json($r);
        if ($j === null || $r['code'] >= 400) {
            return self::fail(L('ردیت', 'Reddit'), $r);
        }
        $d = $j[0]['data']['children'][0]['data'] ?? null;
        if (!is_array($d)) {
            return ['error' => L('جواب ردیت یه چیز عجیب بود و نتونستم بخونمش', 'Reddit’s response was odd and I couldn’t read it')];
        }
        $author = (string) ($d['author'] ?? '');
        if ($author === '' || $author === '[deleted]') {
            return ['error' => L('نویسنده‌ی پست حذف شده یا در دسترس نیست', 'The post’s author is deleted or unavailable')];
        }
        return ['data' => [
            'username'    => $author,
            'profile_url' => 'https://www.reddit.com/user/' . $author,
            'subreddit'   => $d['subreddit'] ?? null,
            'post_url'    => $post,
            'post_title'  => $d['title'] ?? null,
        ]];
    }

    public static function substack(string $url): array
    {
        $r    = self::req('GET', $url, [self::HTML_ACCEPT], null, 3000000);
        $html = (string) ($r['body'] ?? '');
        if ($html === '' || $r['code'] >= 400) {
            return self::fail(L('سابستک', 'Substack'), $r);
        }
        if (!preg_match('~window\._preloads\s*=\s*JSON\.parse\("(.+?)"\)~s', $html, $m)) {
            return ['error' => L('اطلاعات صفحه‌ی سابستک رو پیدا نکردم', 'I couldn’t find the Substack page’s info')];
        }
        $str = json_decode('"' . $m[1] . '"');
        $j   = is_string($str) ? json_decode($str, true) : null;
        if (!is_array($j)) {
            return ['error' => L('نتونستم اطلاعات صفحه‌ی سابستک رو بخونم', 'I couldn’t read the Substack page’s info')];
        }
        $u = $j['referringUser'] ?? null;
        if (!is_array($u) || !$u) {
            return ['error' => L("تو این لینک کاربر ارجاع‌دهنده نیست\n(باید لینک اشتراک با ?r= باشه)", "This link has no referring user\n(it must be a share link with ?r=)")];
        }
        $handle = $u['handle'] ?? null;
        return ['data' => [
            'user_id'             => $u['id'] ?? null,
            'name'                => $u['name'] ?? null,
            'handle'              => $handle,
            'previous_name'       => $u['previous_name'] ?? null,
            'photo_url'           => $u['photo_url'] ?? null,
            'bio'                 => $u['bio'] ?? null,
            'profile_set_up_at'   => $u['profile_set_up_at'] ?? null,
            'reader_installed_at' => $u['reader_installed_at'] ?? null,
            'profile_url'         => $handle ? 'https://substack.com/@' . $handle : null,
        ]];
    }

    public static function suno(string $url): array
    {
        if (!preg_match('~suno\.com/s/([A-Za-z0-9]+)~i', $url, $m)) {
            return ['error' => L('این لینک اشتراک سونو درست نیست', 'This Suno share link isn’t valid')];
        }
        $tok = json_encode(['token' => base64_encode(json_encode(['timestamp' => (int) (microtime(true) * 1000)]))]);
        $r   = self::req('GET', 'https://studio-api.prod.suno.com/api/share/code/' . $m[1], [
            'Accept: */*', 'Browser-Token: ' . $tok, 'Origin: https://suno.com', 'Referer: https://suno.com/',
        ], null, 500000);
        $j = self::json($r);
        if ($j === null || empty($j['success'])) {
            return $j === null ? self::fail(L('سونو', 'Suno'), $r) : ['error' => L('نتونستم اطلاعات اشتراک سونو رو بگیرم', 'I couldn’t get the Suno share info')];
        }
        $handle = $j['sharer_handle'] ?? null;
        return ['data' => [
            'profile_url' => $handle ? 'https://suno.com/@' . $handle . '/' : null,
            'username'    => $handle,
            'name'        => $j['sharer_display_name'] ?? null,
            'avatar_url'  => $j['sharer_avatar_url'] ?? null,
        ]];
    }

    public static function linkedin(string $url): array
    {
        if (!preg_match('~linkedin\.com/(in|posts|pulse)/([A-Za-z0-9_%-]+)~i', $url, $m)) {
            return ['error' => L('این لینک لینکدین درست نیست', 'This LinkedIn link isn’t valid')];
        }
        $type      = ['in' => L('پروفایل', 'Profile'), 'posts' => L('پست', 'Post'), 'pulse' => L('مقاله', 'Article')][strtolower($m[1])] ?? $m[1];
        $canonical = 'https://www.linkedin.com/' . strtolower($m[1]) . '/' . $m[2];
        $r         = self::req('GET', $canonical, [
            'User-Agent: Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
            self::HTML_ACCEPT, 'Sec-Fetch-Dest: document', 'Sec-Fetch-Mode: navigate', 'Sec-Fetch-Site: none', 'Upgrade-Insecure-Requests: 1',
        ], null, 1500000);
        $blocked = ['error' => L('لینکدین جلوی درخواستم رو گرفت (محافظ ربات)', 'LinkedIn blocked my request (bot protection)')];
        if (in_array((int) $r['code'], [999, 403, 429], true)) {
            return $blocked;
        }
        $body = (string) ($r['body'] ?? '');
        if ($body === '') {
            return self::fail(L('لینکدین', 'LinkedIn'), $r);
        }
        if (stripos($body, 'authwall') !== false) {
            return $blocked;
        }
        if (preg_match('~<script[^>]*type=["\']application/ld\+json["\'][^>]*>(.+?)</script>~is', $body, $x)) {
            $ld     = json_decode($x[1], true);
            $author = is_array($ld) ? ($ld['author'] ?? $ld['creator'] ?? null) : null;
            if (is_array($author) && isset($author[0])) {
                $author = $author[0];
            }
            if (is_array($author) && !empty($author['name'])) {
                $img = $author['image'] ?? null;
                return ['data' => self::prune([
                    'display_name'   => $author['name'],
                    'profile_url'    => $author['url'] ?? $canonical,
                    'avatar_url'     => is_array($img) ? ($img['url'] ?? null) : $img,
                    'follower_count' => $author['interactionStatistic']['userInteractionCount'] ?? null,
                    'headline'       => $ld['headline'] ?? null,
                    'published_at'   => $ld['datePublished'] ?? null,
                    'url_type'       => $type,
                ])];
            }
        }
        $hasTitle = preg_match('~<meta\s+property=["\']og:title["\']\s+content=["\'](.*?)["\']~is', $body, $t) === 1;
        if (strlen($body) < 5000 && !$hasTitle) {
            return $blocked;
        }
        if (!$hasTitle) {
            return ['error' => L('اسم صاحب پروفایل تو صفحه‌ی لینکدین نبود', 'The profile owner’s name wasn’t on the LinkedIn page')];
        }
        $title = trim(preg_replace('~\s*\|\s*LinkedIn\s*$~i', '', trim(self::dec($t[1]))) ?? '');
        $name  = $title;
        $head  = null;
        if (str_contains($title, ' - ')) {
            [$name, $head] = array_map('trim', explode(' - ', $title, 2));
        }
        $avatar = preg_match('~<meta\s+property=["\']og:image["\']\s+content=["\'](.*?)["\']~is', $body, $im) ? self::dec(trim($im[1])) : null;
        return ['data' => self::prune(['display_name' => $name, 'headline' => $head, 'profile_url' => $canonical, 'avatar_url' => $avatar, 'url_type' => $type])];
    }

    public static function notionPageId(string $url): ?string
    {
        $path = rtrim((string) preg_replace('~[?#].*$~', '', $url), '/');
        $last = str_replace('-', '', (string) substr($path, (int) strrpos($path, '/') + 1));
        if (!preg_match('~([a-f0-9]{32})$~i', $last, $m)) {
            return null;
        }
        $h = strtolower($m[1]);
        return substr($h, 0, 8) . '-' . substr($h, 8, 4) . '-' . substr($h, 12, 4) . '-' . substr($h, 16, 4) . '-' . substr($h, 20);
    }

    private static function notionPost(string $endpoint, array $payload): array
    {
        $last = ['code' => 0, 'body' => null];
        for ($i = 0; $i < 4; $i++) {
            $last = self::req('POST', 'https://www.notion.so/api/v3/' . $endpoint, ['Content-Type: application/json', 'Accept: application/json'], json_encode($payload), 3000000);
            $j    = self::json($last);
            if ($j !== null && empty($j['errorId'])) {
                return $last;
            }
        }
        return $last;
    }

    public static function notion(string $url): array
    {
        $id = self::notionPageId($url);
        if ($id === null && preg_match('~^https?://[^/]+\.notion\.site(?:/|$)~i', $url) && preg_match('/(?:^|\.)notion\.site$/i', (string) parse_url($url, PHP_URL_HOST))) {
            $r = self::req('GET', $url, [self::HTML_ACCEPT], null, 2000000);
            if (preg_match('~"pageId"\s*:\s*"([a-f0-9-]{36})"~', (string) ($r['body'] ?? ''), $m)) {
                $id = $m[1];
            }
        }
        if ($id === null) {
            return ['error' => L('آیدی صفحه رو از لینک درنیاوردم', 'I couldn’t extract the page ID from the link')];
        }
        $pd = self::json(self::notionPost('getPublicPageData', ['blockId' => $id]));
        if ($pd === null || !empty($pd['errorId']) || empty($pd['spaceId'])) {
            return ['error' => L('این صفحه دسترسی باز نداره یا وجود نداره', 'This page isn’t publicly accessible or doesn’t exist')];
        }
        $space = self::prune(['space_name' => $pd['spaceName'] ?? null, 'space_domain' => $pd['spaceDomain'] ?? null]);
        $chunk = (string) (self::notionPost('loadPageChunk', ['pageId' => $id, 'limit' => 100, 'cursor' => ['stack' => []], 'chunkNumber' => 0, 'verticalColumns' => false])['body'] ?? '');
        $uuids = [];
        foreach (['created_by_id', 'last_edited_by_id', 'block_locked_by', 'user_id'] as $key) {
            if (preg_match_all('~"' . $key . '"\s*:\s*"([a-f0-9-]{36})"~', $chunk, $mm)) {
                foreach ($mm[1] as $u) {
                    $uuids[$u] = true;
                }
            }
        }
        if (!$uuids) {
            return ['data' => $space];
        }
        $reqs = [];
        foreach (array_keys($uuids) as $u) {
            $reqs[] = ['pointer' => ['table' => 'notion_user', 'id' => $u, 'spaceId' => ''], 'version' => -1];
        }
        $sync  = self::json(self::notionPost('syncRecordValuesMain', ['requests' => $reqs]));
        $users = [];
        foreach ((array) ($sync['recordMap']['notion_user'] ?? []) as $rec) {
            $val = $rec['value']['value'] ?? null;
            if (!is_array($val)) {
                continue;
            }
            $name = $val['name'] ?? trim(($val['given_name'] ?? '') . ' ' . ($val['family_name'] ?? ''));
            $u    = ['user_id' => $val['id'] ?? null, 'name' => $name !== '' ? $name : null, 'email' => $val['email'] ?? null, 'avatar_url' => $val['profile_photo'] ?? null];
            if ($u['name'] !== null || $u['email'] !== null) {
                $users[] = $u;
            }
        }
        if (!$users) {
            return ['data' => $space];
        }
        $p = $users[0];
        $d = ['name' => $p['name'], 'email' => $p['email'], 'user_id' => $p['user_id'], 'avatar_url' => $p['avatar_url']] + $space;
        if (count($users) > 1) {
            $d['other_editors'] = array_map(static fn($u) => ['name' => $u['name'], 'email' => $u['email']], array_slice($users, 1));
        }
        return ['data' => $d];
    }

    public static function extractBlob(string $html, string $marker): ?array
    {
        $i = strpos($html, $marker);
        if ($i === false) {
            return null;
        }
        $s = strpos($html, '{', $i);
        if ($s === false) {
            return null;
        }
        $depth = 0;
        $in    = false;
        $esc   = false;
        $n     = strlen($html);
        for ($k = $s; $k < $n; $k++) {
            $ch = $html[$k];
            if ($in) {
                if ($esc) {
                    $esc = false;
                } elseif ($ch === '\\') {
                    $esc = true;
                } elseif ($ch === '"') {
                    $in = false;
                }
                continue;
            }
            if ($ch === '"') {
                $in = true;
            } elseif ($ch === '{') {
                $depth++;
            } elseif ($ch === '}') {
                $depth--;
                if ($depth === 0) {
                    $j = json_decode(substr($html, $s, $k - $s + 1), true);
                    return is_array($j) ? $j : null;
                }
            }
        }
        return null;
    }

    public static function findKey($obj, string $key)
    {
        if (!is_array($obj)) {
            return null;
        }
        if (array_key_exists($key, $obj)) {
            return $obj[$key];
        }
        foreach ($obj as $v) {
            $hit = self::findKey($v, $key);
            if ($hit !== null) {
                return $hit;
            }
        }
        return null;
    }

    private static function firstUcid($obj): ?string
    {
        $stack = [$obj];
        while ($stack) {
            $cur = array_pop($stack);
            if (!is_array($cur)) {
                continue;
            }
            foreach ($cur as $k => $v) {
                if ($k === 'browseId' && is_string($v) && str_starts_with($v, 'UC') && strlen($v) === 24) {
                    return $v;
                }
                $stack[] = $v;
            }
        }
        return null;
    }

    private static function intOrNull($v): ?int
    {
        if ($v === null) {
            return null;
        }
        if (is_int($v)) {
            return $v;
        }
        $d = preg_replace('/\D/', '', (string) $v);
        return $d !== '' ? (int) $d : null;
    }

    private static function shareMethod(string $url): ?string
    {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $q);
        foreach (['si', 'sl', 'is'] as $k) {
            if (!empty($q[$k])) {
                return "youtube_share_button ({$k}=…)";
            }
        }
        return null;
    }

    public static function youtubeVideoId(string $url): ?string
    {
        if (preg_match('~^https?://(?:youtu\.be/([A-Za-z0-9_-]{11})|(?:www\.|m\.)?youtube\.com/(?:watch\?[^ ]*v=([A-Za-z0-9_-]{11})|(?:shorts|live|embed)/([A-Za-z0-9_-]{11})))~i', $url, $m)) {
            return $m[1] !== '' ? $m[1] : (($m[2] ?? '') !== '' ? $m[2] : ($m[3] ?? null));
        }
        return null;
    }

    public static function youtube(string $url): array
    {
        $h  = ['Cookie: CONSENT=YES+cb.20210328-17-p0.en+FX+000; SOCS=CAI'];
        $id = self::youtubeVideoId($url);
        if ($id !== null) {
            $r = self::req('GET', 'https://www.youtube.com/watch?v=' . $id, $h, null, 4000000);
            if ($r['code'] >= 400 || ($r['body'] ?? '') === '') {
                return self::fail(L('یوتیوب', 'YouTube'), $r);
            }
            $player = self::extractBlob((string) $r['body'], 'ytInitialPlayerResponse');
            if ($player === null) {
                return ['error' => L('نتونستم اطلاعات ویدیو رو از یوتیوب بخونم', 'I couldn’t read the video info from YouTube')];
            }
            $det   = (array) ($player['videoDetails'] ?? []);
            $micro = (array) ($player['microformat']['playerMicroformatRenderer'] ?? []);
            $cid   = $det['channelId'] ?? null;
            if ($cid === null && empty($det['author'])) {
                return ['error' => L('مشخصات کانال تو اطلاعات ویدیو نبود', 'The channel details weren’t in the video info')];
            }
            $countries = $micro['availableCountries'] ?? null;
            return ['data' => self::prune([
                'video_id'      => $id,
                'video_title'   => $det['title'] ?? null,
                'channel_id'    => $cid,
                'name'          => $det['author'] ?? null,
                'channel_url'   => $cid ? 'https://www.youtube.com/channel/' . $cid : null,
                'view_count'    => self::intOrNull($det['viewCount'] ?? null),
                'published_at'  => $micro['publishDate'] ?? null,
                'video_country' => is_array($countries) && count($countries) === 1 ? $countries[0] : null,
                'share_method'  => self::shareMethod($url),
            ])];
        }
        if (!preg_match('~^https?://(?:www\.|m\.)?youtube\.com/(?:@([A-Za-z0-9._-]+)|channel/(UC[A-Za-z0-9_-]{22}))~i', $url, $m)) {
            return ['error' => L('این لینک یوتیوب درست نیست', 'This YouTube link isn’t valid')];
        }
        $about = !empty($m[1]) ? 'https://www.youtube.com/@' . $m[1] . '/about' : 'https://www.youtube.com/channel/' . $m[2] . '/about';
        $r     = self::req('GET', $about, $h, null, 4000000);
        if ($r['code'] >= 400 || ($r['body'] ?? '') === '') {
            return self::fail(L('یوتیوب', 'YouTube'), $r);
        }
        $html    = (string) $r['body'];
        $initial = self::extractBlob($html, 'ytInitialData');
        if ($initial === null) {
            return ['error' => L('نتونستم اطلاعات کانال رو از یوتیوب بخونم', 'I couldn’t read the channel info from YouTube')];
        }
        $header = (array) (self::findKey($initial, 'pageHeaderRenderer') ?? []);
        $meta   = (array) (self::findKey($initial, 'channelMetadataRenderer') ?? []);
        $ab     = (array) (self::findKey($initial, 'aboutChannelViewModel') ?? []);
        $cid    = $meta['externalId'] ?? self::firstUcid($initial);
        if ($cid === null) {
            return ['error' => L('آیدی کانال رو پیدا نکردم', 'I couldn’t find the channel ID')];
        }
        $handle = null;
        foreach (array_merge([(string) ($meta['vanityChannelUrl'] ?? '')], array_map('strval', (array) ($meta['ownerUrls'] ?? []))) as $u) {
            if (preg_match('~/@([A-Za-z0-9._-]+)~', $u, $hm)) {
                $handle = $hm[1];
                break;
            }
        }
        if ($handle === null) {
            $runs   = self::findKey($initial, 'channelHandleText')['runs'] ?? [];
            $handle = isset($runs[0]['text']) ? ltrim((string) $runs[0]['text'], '@') : null;
        }
        if ($handle === null && preg_match('~<link rel="canonical" href="https://www\.youtube\.com/@([A-Za-z0-9._-]+)"~', $html, $hm)) {
            $handle = $hm[1];
        }
        $links = [];
        foreach ((array) ($ab['links'] ?? []) as $l) {
            $vm  = (array) ($l['channelExternalLinkViewModel'] ?? []);
            $lu  = $vm['link']['content'] ?? null;
            $ttl = $vm['title']['content'] ?? null;
            if ($lu) {
                $links[] = $ttl ? ['title' => $ttl, 'url' => $lu] : ['url' => $lu];
            }
        }
        $thumbs = $meta['avatar']['thumbnails'] ?? null;
        $join   = $ab['joinedDateText']['content'] ?? null;
        return ['data' => self::prune([
            'channel_id'       => $cid,
            'username'         => $handle,
            'name'             => $meta['title'] ?? $header['pageTitle'] ?? null,
            'bio'              => $meta['description'] ?? $ab['description'] ?? null,
            'country'          => $ab['country'] ?? null,
            'joined_date'      => is_string($join) ? trim(str_replace('Joined ', '', $join)) : null,
            'subscriber_count' => LinkCheck::parseCount((string) ($ab['subscriberCountText'] ?? '')),
            'view_count'       => self::intOrNull($ab['viewCountText'] ?? null),
            'video_count'      => LinkCheck::parseCount((string) ($ab['videoCountText'] ?? '')),
            'external_links'   => $links,
            'avatar_url'       => is_array($thumbs) && $thumbs ? ($thumbs[array_key_last($thumbs)]['url'] ?? null) : null,
            'share_method'     => self::shareMethod($url),
        ])];
    }
}
