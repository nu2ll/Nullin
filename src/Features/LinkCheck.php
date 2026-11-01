<?php

final class LinkCheck
{
    private const PLATFORMS = [
        ['telegram', '~^https?://(?:www\.)?t\.me/(?:joinchat/|\+)[A-Za-z0-9_-]+~i'],
        ['telegram', '~^https?://(?:www\.)?t\.me/c/\d+(?:/\d+)?~i'],
        ['telegram', '~^https?://(?:www\.)?t\.me/(?!joinchat/|c/|\+|s/)[A-Za-z0-9_]{5,32}(?:/\d+)?/?(?:[?#].*)?$~i'],
        ['discord_channels', '~^https?://(?:www\.)?discord\.com/channels/(?:@me|\d+)/\d+(?:/\d+)?/?(?:[?#].*)?$~i'],
        ['discord', '~^https?://(?:(?:www\.)?discord\.com/invite|discord\.gg)/[A-Za-z0-9_-]+~i'],
        ['microsoft', '~^https?://[^/]+\.sharepoint\.com/:[a-z]:/g/personal/[^/]+/~i'],
        ['gdoc', '~^https?://(?:docs\.google\.com/(?:document|spreadsheets|presentation|drawings|forms)/d/|drive\.google\.com/file/d/|drive\.google\.com/drive/(?:mobile/|u/\d+/)?folders/|script\.google\.com/d/|jamboard\.google\.com/d/|(?:www\.)?google\.com/maps/d/)~i'],
        ['github', '~^https?://(?:www\.)?github\.com/[^/]+/[^/]+/(?:commit|pull/\d+/commits)/[0-9a-f]{7,40}~i'],
        ['github', '~^https?://(?:www\.)?github\.com/[A-Za-z0-9](?:[A-Za-z0-9-]{0,37}[A-Za-z0-9])?(?:/[A-Za-z0-9._-]+)?/?(?:\?.*)?$~i'],
        ['gitlab', '~^https?://(?:www\.)?gitlab\.com/(?:[^/]+/)+[^/]+/-/commit/[0-9a-f]{7,40}~i'],
        ['gitlab', '~^https?://(?:www\.)?gitlab\.com/[A-Za-z0-9][A-Za-z0-9._-]{0,254}/?$~i'],
        ['huggingface', '~^https?://(?:www\.)?huggingface\.co/[A-Za-z0-9][A-Za-z0-9._-]{0,94}(?:/[^/?#]+)?/?(?:[?#].*)?$~i'],
        ['tiktok', '~^https?://(?:vm\.tiktok\.com|vt\.tiktok\.com|(?:www\.)?tiktok\.com/t)/[A-Za-z0-9]+~i'],
        ['tiktok', '~^https?://(?:www\.|m\.)?tiktok\.com/@[A-Za-z0-9._]+(?:/video/\d+)?/?~i'],
        ['chatgpt', '~^https?://(?:www\.)?chatgpt\.com/share/[a-f0-9-]+~i'],
        ['claude', '~^https?://(?:www\.)?claude\.ai/share/[a-f0-9-]+~i'],
        ['instagram', '~^https?://(?:www\.)?instagram\.com/(?:reel|p)/[A-Za-z0-9_-]+~i'],
        ['perplexity', '~^https?://(?:www\.)?perplexity\.ai/search/[A-Za-z0-9._-]+~i'],
        ['pinterest', '~^https?://pin\.it/[A-Za-z0-9]+~i'],
        ['reddit', '~^https?://(?:www\.|old\.|new\.)?reddit\.com/r/[A-Za-z0-9_]+/s/[A-Za-z0-9]+~i'],
        ['substack', '~^https?://(?:www\.)?substack\.com/@[^/]+/note/~i'],
        ['suno', '~^https?://(?:www\.)?suno\.com/s/[A-Za-z0-9]+~i'],
        ['notion', '~^https?://(?:www\.)?notion\.so/~i'],
        ['notion', '~^https?://[A-Za-z0-9-]+\.notion\.site(?:/|$)~i'],
        ['linkedin', '~^https?://(?:www\.)?linkedin\.com/(?:in|posts|pulse)/[A-Za-z0-9_%-]+~i'],
        ['youtube', '~^https?://(?:youtu\.be/[A-Za-z0-9_-]{11}|(?:www\.|m\.)?youtube\.com/(?:watch\?[^ ]*v=[A-Za-z0-9_-]{11}|(?:shorts|live|embed)/[A-Za-z0-9_-]{11}|@[A-Za-z0-9._-]+|channel/UC[A-Za-z0-9_-]{22}))~i'],
    ];

    private const PLATFORM_NAMES = [
        'telegram'         => 'تلگرام',
        'discord'          => 'دیسکورد',
        'discord_channels' => 'دیسکورد',
        'microsoft'        => 'مایکروسافت (SharePoint / OneDrive)',
        'gdoc'             => 'گوگل داکس / درایو',
        'github'           => 'گیت‌هاب',
        'gitlab'           => 'گیت‌لب',
        'huggingface'      => 'هاگینگ‌فیس',
        'tiktok'           => 'تیک‌تاک',
        'chatgpt'          => 'چت‌جی‌پی‌تی',
        'claude'           => 'کلود',
        'instagram'        => 'اینستاگرام',
        'perplexity'       => 'پرپلکسیتی',
        'pinterest'        => 'پینترست',
        'reddit'           => 'ردیت',
        'substack'         => 'سابستک',
        'suno'             => 'سونو',
        'notion'           => 'نوشن',
        'linkedin'         => 'لینکدین',
        'youtube'          => 'یوتیوب',
    ];

    private const LABELS = [
        'user_id'             => 'آیدی عددی',
        'username'            => 'یوزرنیم',
        'name'                => 'نام',
        'email'               => 'ایمیل',
        'emails'              => 'ایمیل‌ها',
        'noreply_emails'      => 'ایمیل‌های مخفی‌شده (noreply)',
        'is_noreply'          => 'ایمیل مخفی‌شده (noreply)',
        'public_email'        => 'ایمیل عمومی',
        'avatar_url'          => 'عکس پروفایل',
        'profile_url'         => 'پروفایل',
        'created_at'          => 'تاریخ ساخت',
        'modified_at'         => 'آخرین ویرایش',
        'bio'                 => 'بیو',
        'follower_count'      => 'تعداد فالوئر',
        'num_followers'       => 'تعداد فالوئر',
        'commit_sha'          => 'کامیت',
        'repo'                => 'ریپو',
        'project'             => 'پروژه',
        'display_name'        => 'نام نمایشی',
        'fullname'            => 'نام کامل',
        'account_type'        => 'نوع حساب',
        'orgs'                => 'سازمان‌ها',
        'url_type'            => 'نوع لینک',
        'channel_id'          => 'آیدی کانال',
        'channel_internal_id' => 'آیدی داخلی کانال',
        'message_id'          => 'آیدی پیام',
        'guild_id'            => 'آیدی سرور',
        'guild_created_at'    => 'تاریخ ساخت سرور',
        'channel_created_at'  => 'تاریخ ساخت کانال',
        'message_created_at'  => 'زمان ارسال پیام',
        'doc_id'              => 'آیدی سند',
        'google_id'           => 'آیدی گوگل',
        'public_permissions'  => 'دسترسی عمومی',
        'nickname'            => 'نیک‌نیم',
        'country'             => 'کشور',
        'signature'           => 'بیو',
        'device'              => 'دستگاه',
        'share_method'        => 'روش اشتراک‌گذاری',
        'shared_at'           => 'زمان اشتراک‌گذاری',
        'following_count'     => 'تعداد فالو',
        'video_count'         => 'تعداد ویدیو',
        'heart_count'         => 'تعداد لایک',
        'private_account'     => 'اکانت خصوصی',
        'dm_available'        => 'دایرکت باز',
        'handle'              => 'هندل',
        'previous_name'       => 'نام قبلی',
        'photo_url'           => 'عکس پروفایل',
        'profile_set_up_at'   => 'ساخت پروفایل',
        'reader_installed_at' => 'نصب اپ',
        'subreddit'           => 'ساب‌ردیت',
        'post_url'            => 'آدرس پست',
        'post_title'          => 'عنوان پست',
        'headline'            => 'عنوان شغلی',
        'published_at'        => 'تاریخ انتشار',
        'space_name'          => 'ورک‌اسپیس',
        'space_domain'        => 'دامنه‌ی ورک‌اسپیس',
        'other_editors'       => 'ویرایشگرهای دیگه',
        'video_id'            => 'آیدی ویدیو',
        'video_title'         => 'عنوان ویدیو',
        'channel_url'         => 'آدرس کانال',
        'view_count'          => 'تعداد بازدید',
        'video_country'       => 'کشور ویدیو',
        'joined_date'         => 'تاریخ عضویت',
        'subscriber_count'    => 'تعداد مشترک',
        'external_links'      => 'لینک‌های بیرونی',
    ];

    public static function replyText(array $msg): string
    {
        $r = $msg['reply_to_message'] ?? null;
        if (!is_array($r)) {
            return '';
        }
        $urls = [];
        foreach (array_merge((array) ($r['entities'] ?? []), (array) ($r['caption_entities'] ?? [])) as $e) {
            if (($e['type'] ?? '') === 'text_link' && !empty($e['url'])) {
                $urls[] = (string) $e['url'];
            }
        }
        return trim(implode(' ', $urls) . ' ' . (string) ($r['text'] ?? $r['caption'] ?? ''));
    }

    public static function respond(string $arg, string $reply = ''): string
    {
        $url = self::extract($arg) ?? self::extract($reply);
        if ($url === null) {
            return Ui::guide('linkcheck');
        }
        Users::hit('linkcheck');
        return self::report($url);
    }

    public static function extract(string $text): ?string
    {
        $text = mb_substr($text, 0, 4000);
        $url  = null;
        if (preg_match('~(?:https?://|www\.)[^\s<>"«»]+~iu', $text, $m)) {
            $url = $m[0];
        } else {
            $t = mbTrim($text);
            if (preg_match('~^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,24}(?:[/?#]\S*)?$~i', $t)) {
                $url = $t;
            }
        }
        if ($url === null) {
            return null;
        }
        $url = rtrim($url, ".,;:!?)]}'\"،؛؟»");
        if (!preg_match('~^https?://~i', $url)) {
            $url = 'https://' . $url;
        }
        if (strlen($url) > 2000) {
            return null;
        }
        $host = parse_url($url, PHP_URL_HOST);
        return is_string($host) && $host !== '' ? $url : null;
    }

    public static function detect(string $url): ?string
    {
        foreach (self::PLATFORMS as [$name, $re]) {
            if (preg_match($re, $url)) {
                return $name;
            }
        }
        return null;
    }

    public static function identity(string $platform, string $url): array
    {
        $err = null;
        $res = Store::remember('cache', 'lc:' . md5($platform . '|' . $url), 900, function () use ($platform, $url, &$err) {
            $r = match ($platform) {
                'telegram'         => self::telegram($url),
                'discord'          => self::discord($url),
                'discord_channels' => self::discordChannels($url),
                'microsoft'        => self::microsoft($url),
                'gdoc'             => self::gdoc($url),
                'github'           => self::github($url),
                'gitlab'           => self::gitlab($url),
                'huggingface'      => self::huggingface($url),
                default            => LinkSites::run($platform, $url),
            };
            if (isset($r['data'])) {
                return $r;
            }
            $err = $r;
            return null;
        });
        return $res ?? $err ?? ['error' => 'نتیجه‌ای نگرفتم'];
    }

    private static function get(string $url, array $headers = [], int $max = 400000): array
    {
        return Http::request('GET', $url, [
            'headers'         => array_merge(['User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36'], $headers),
            'timeout'         => 10,
            'connect_timeout' => 6,
            'max_bytes'       => $max,
            'impersonate'     => 'chrome',
        ]);
    }

    public static function snowflake($id): string
    {
        $ms = ((int) $id >> 22) + 1420070400000;
        return gmdate('Y-m-d H:i:s', intdiv($ms, 1000)) . ' UTC';
    }

    public static function decodeInvite(string $hash): array
    {
        $b = strtr($hash, '-_', '+/');
        $b .= str_repeat('=', (4 - strlen($b) % 4) % 4);
        $raw = base64_decode($b, true);
        if ($raw === false) {
            return ['error' => 'هش دعوت‌نامه نامعتبره'];
        }
        if (strlen($raw) >= 16) {
            $u = unpack('Vid', substr($raw, 0, 4));
            return ['data' => ['user_id' => (string) $u['id']]];
        }
        return ['error' => 'این دعوت‌نامه از نوع جدیده و آیدی سازنده‌ش توش نیست'];
    }

    public static function parseCount(string $text): ?int
    {
        $t = preg_replace('/(\d)\s+(\d)/', '$1$2', trim($text)) ?? $text;
        if (!preg_match('/^([\d.,]+)\s*([KMB])?/i', $t, $m)) {
            return null;
        }
        $num = (float) str_replace(',', '', $m[1]);
        $mul = ['K' => 1000, 'M' => 1000000, 'B' => 1000000000][strtoupper($m[2] ?? '')] ?? 1;
        return (int) ($num * $mul);
    }

    public static function microsoftEmail(string $url): ?string
    {
        if (!preg_match('~sharepoint\.com/:[a-z]:/g/personal/([^/]+)/~i', $url, $m)) {
            return null;
        }
        $parts = explode('_', $m[1]);
        if (count($parts) < 3) {
            return null;
        }
        $tld = $parts[count($parts) - 1];
        $idx = count($parts) - 2;
        while ($idx > 0 && str_contains($parts[$idx], '-')) {
            $idx--;
        }
        if ($idx < 1) {
            return null;
        }
        return implode('.', array_slice($parts, 0, $idx)) . '@' . $parts[$idx] . '.' . $tld;
    }

    public static function parseFrom(string $text): ?array
    {
        if (!preg_match('/^From: (.+)$/m', $text, $m)) {
            return null;
        }
        $v = trim($m[1]);
        if (preg_match('/^"?(.*?)"?\s*<([^<>\s]+@[^<>\s]+)>$/u', $v, $x)) {
            $name = trim($x[1]);
            if (str_contains($name, '=?')) {
                $name = trim((string) mb_decode_mimeheader($name));
            }
            return [$name, trim($x[2])];
        }
        if (preg_match('/^[^<>\s]+@[^<>\s]+$/', $v)) {
            return ['', $v];
        }
        return null;
    }

    private static function telegram(string $url): array
    {
        if (preg_match('~t\.me/c/(\d+)(?:/(\d+))?~i', $url, $m)) {
            $d = ['channel_id' => '-100' . $m[1], 'channel_internal_id' => $m[1], 'url_type' => 'کانال خصوصی (برای دیدن محتوا باید عضو باشی)'];
            if (!empty($m[2])) {
                $d['message_id'] = $m[2];
            }
            return ['data' => $d];
        }
        if (preg_match('~t\.me/(?:joinchat/|\+)([A-Za-z0-9_-]+)~i', $url, $m)) {
            return self::decodeInvite($m[1]);
        }
        if (!preg_match('~t\.me/(?!joinchat/|c/|\+|s/)([A-Za-z0-9_]{5,32})~i', $url, $m)) {
            return ['error' => 'آدرس تلگرام نامعتبره'];
        }
        $r = self::get('https://t.me/s/' . $m[1]);
        if ($r['body'] === null || $r['body'] === '' || $r['code'] >= 400) {
            return ['error' => 'تلگرام جوابی نداد (HTTP ' . $r['code'] . ')'];
        }
        $html = $r['body'];
        $d    = ['username' => $m[1]];
        if (preg_match('~<meta property="og:title" content="([^"]+)"~', $html, $x)) {
            $d['name'] = html_entity_decode($x[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        if (preg_match('~<meta property="og:description" content="([^"]+)"~', $html, $x)) {
            $d['bio'] = html_entity_decode($x[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        if (preg_match('~<meta property="og:image" content="([^"]+)"~', $html, $x) && !str_ends_with($x[1], '/img/t_logo.png')) {
            $d['avatar_url'] = html_entity_decode($x[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        if (preg_match('~<span class="counter_value">([^<]+)</span>\s*<span class="counter_type">subscribers?</span>~i', $html, $x)) {
            $n = self::parseCount($x[1]);
            if ($n !== null) {
                $d['follower_count'] = $n;
            }
        }
        return count($d) > 1 ? ['data' => $d] : ['error' => 'اطلاعات عمومی‌ای پیدا نشد'];
    }

    private static function discord(string $url): array
    {
        if (!preg_match('~(?:discord\.com/invite|discord\.gg)/([A-Za-z0-9_-]+)~i', $url, $m)) {
            return ['error' => 'آدرس دعوت‌نامه‌ی دیسکورد نامعتبره'];
        }
        $r = self::get('https://discord.com/api/v9/invites/' . $m[1], ['Accept: application/json'], 200000);
        $j = $r['body'] !== null ? json_decode($r['body'], true) : null;
        if ($r['code'] >= 400 || !is_array($j)) {
            return ['error' => 'دیسکورد جواب نداد یا دعوت‌نامه معتبر نیست'];
        }
        $inv = $j['inviter'] ?? null;
        if (!is_array($inv) || empty($inv['id'])) {
            return ['error' => 'تو این دعوت‌نامه اطلاعات دعوت‌کننده نیست'];
        }
        $avatar = null;
        if (!empty($inv['avatar'])) {
            $avatar = 'https://cdn.discordapp.com/avatars/' . $inv['id'] . '/' . $inv['avatar'] . '.' . (str_starts_with((string) $inv['avatar'], 'a_') ? 'gif' : 'png');
        }
        return ['data' => [
            'user_id'    => (string) $inv['id'],
            'username'   => $inv['username'] ?? null,
            'name'       => $inv['global_name'] ?? null,
            'avatar_url' => $avatar,
            'created_at' => self::snowflake($inv['id']),
        ]];
    }

    private static function discordChannels(string $url): array
    {
        if (!preg_match('~discord\.com/channels/(@me|\d+)/(\d+)(?:/(\d+))?~i', $url, $m)) {
            return ['error' => 'آدرس کانال دیسکورد نامعتبره'];
        }
        $d = ['channel_id' => $m[2], 'channel_created_at' => self::snowflake($m[2])];
        if (strtolower($m[1]) === '@me') {
            $d['url_type'] = 'پیام خصوصی (DM)';
        } else {
            $d['guild_id']         = $m[1];
            $d['guild_created_at'] = self::snowflake($m[1]);
            $d['url_type']         = !empty($m[3]) ? 'پیام تو سرور' : 'کانال تو سرور';
        }
        if (!empty($m[3])) {
            $d['message_id']         = $m[3];
            $d['message_created_at'] = self::snowflake($m[3]);
        }
        return ['data' => $d];
    }

    private static function microsoft(string $url): array
    {
        $email = self::microsoftEmail($url);
        return $email === null ? ['error' => 'آدرس SharePoint نامعتبره'] : ['data' => ['email' => $email]];
    }

    private static function gdoc(string $url): array
    {
        $id = null;
        foreach (['~/d/([A-Za-z0-9_-]{25,})~', '~/folders/([A-Za-z0-9_-]{25,})~', '~[?&]mid=([A-Za-z0-9_-]{25,})~'] as $re) {
            if (preg_match($re, $url, $m)) {
                $id = $m[1];
                break;
            }
        }
        if ($id === null) {
            return ['error' => 'آیدی سند از آدرس درنیومد'];
        }
        $fields = 'alternateLink,createdDate,modifiedDate,kind,permissions(id,name,emailAddress,domain,role,additionalRoles,photoLink,type,withLink),userPermission(id,role,additionalRoles,type)';
        $api    = 'https://clients6.google.com/drive/v2beta/files/' . $id . '?' . http_build_query([
            'fields' => $fields, 'supportsTeamDrives' => 'true', 'key' => (string) env('GDOC_API_KEY', 'AIzaSyC1eQ1xj69IdTMeii5r7brs3R90eck-m7k'),
        ]);
        $r = self::get($api, ['X-Origin: https://drive.google.com'], 200000);
        if ($r['code'] === 429) {
            usleep(600000);
            $r = self::get($api, ['X-Origin: https://drive.google.com'], 200000);
        }
        if ($r['code'] === 404 || ($r['body'] !== null && str_contains($r['body'], 'File not found'))) {
            return ['error' => 'سند پیدا نشد یا عمومی نیست'];
        }
        if ($r['code'] === 401 || $r['code'] === 403) {
            return ['error' => 'گوگل درخواست رو قبول نکرد (کلید API ممکنه باطل شده باشه)'];
        }
        $j = $r['body'] !== null ? json_decode($r['body'], true) : null;
        if ($r['code'] >= 400 || !is_array($j)) {
            return ['error' => 'جواب نامعتبر از گوگل درایو'];
        }
        $fmt = static fn($v) => is_string($v) && ($t = strtotime($v)) ? gmdate('Y-m-d H:i:s', $t) . ' UTC' : null;
        $d   = ['doc_id' => $id, 'created_at' => $fmt($j['createdDate'] ?? null), 'modified_at' => $fmt($j['modifiedDate'] ?? null)];
        $roles = [];
        $owner = null;
        foreach ((array) ($j['permissions'] ?? []) as $perm) {
            if (in_array($perm['id'] ?? '', ['anyoneWithLink', 'anyone'], true)) {
                if (!empty($perm['role'])) {
                    $roles[] = $perm['role'];
                }
                $roles = array_merge($roles, (array) ($perm['additionalRoles'] ?? []));
            } elseif (($perm['role'] ?? '') === 'owner') {
                $owner = $perm;
            }
        }
        if ($roles) {
            $d['public_permissions'] = implode(', ', array_values(array_unique($roles)));
        }
        if ($owner) {
            $d['name']       = $owner['name'] ?? null;
            $d['email']      = $owner['emailAddress'] ?? null;
            $d['google_id']  = $owner['id'] ?? null;
            $d['avatar_url'] = $owner['photoLink'] ?? null;
        }
        if (empty($d['name']) && empty($d['email']) && empty($d['google_id'])) {
            return ['error' => 'سند عمومیه ولی هویت صاحبش مشخص نیست'];
        }
        return ['data' => $d];
    }

    private static function github(string $url): array
    {
        if (preg_match('~github\.com/([A-Za-z0-9._-]+)/([A-Za-z0-9._-]+)/(?:commit|pull/\d+/commits)/([0-9a-f]{7,40})~i', $url, $m)) {
            $r = self::get("https://github.com/{$m[1]}/{$m[2]}/commit/{$m[3]}.patch", [], 8192);
            if ($r['code'] === 404) {
                return ['error' => 'کامیت پیدا نشد یا ریپو خصوصیه'];
            }
            $from = $r['body'] !== null && $r['code'] < 400 ? self::parseFrom($r['body']) : null;
            if ($from === null) {
                return ['error' => 'ایمیل نویسنده‌ی کامیت پیدا نشد'];
            }
            $d = ['name' => $from[0], 'email' => $from[1], 'commit_sha' => $m[3], 'repo' => "{$m[1]}/{$m[2]}"];
            if (str_ends_with($from[1], 'users.noreply.github.com')) {
                $d['is_noreply'] = true;
            }
            return ['data' => $d];
        }
        if (!preg_match('~^https?://(?:www\.)?github\.com/([A-Za-z0-9](?:[A-Za-z0-9-]{0,37}[A-Za-z0-9])?)(?:[/?]|$)~i', $url, $m)) {
            return ['error' => 'آدرس گیت‌هاب نامعتبره'];
        }
        $r = self::get('https://api.github.com/users/' . $m[1] . '/events/public', ['Accept: application/vnd.github+json'], 600000);
        if ($r['code'] === 404) {
            return ['error' => 'کاربر گیت‌هاب پیدا نشد'];
        }
        if ($r['code'] === 403 || $r['code'] === 429) {
            return ['error' => 'سقف درخواست گیت‌هاب پر شده؛ بعداً امتحان کن'];
        }
        $events = $r['body'] !== null && $r['code'] < 400 ? json_decode($r['body'], true) : null;
        if (!is_array($events)) {
            return ['error' => 'جواب نامعتبر از گیت‌هاب'];
        }
        $found   = [];
        $noreply = [];
        foreach ($events as $ev) {
            if (($ev['type'] ?? '') !== 'PushEvent') {
                continue;
            }
            foreach ((array) ($ev['payload']['commits'] ?? []) as $c) {
                $email = (string) ($c['author']['email'] ?? '');
                $name  = (string) ($c['author']['name'] ?? '');
                if ($email === '') {
                    continue;
                }
                if (str_ends_with($email, 'users.noreply.github.com')) {
                    $noreply[$email] = true;
                } elseif ($name !== '' && !isset($found[$email])) {
                    $found[$email] = $name;
                }
            }
        }
        if (!$found && !$noreply) {
            return ['error' => 'تو فعالیت‌های عمومی اخیر ایمیلی پیدا نشد'];
        }
        $d = ['username' => $m[1], 'emails' => array_map(static fn($e, $n) => ['name' => $n, 'email' => $e], array_keys($found), array_values($found))];
        if ($noreply) {
            $d['noreply_emails'] = array_keys($noreply);
        }
        return ['data' => $d];
    }

    private static function gitlab(string $url): array
    {
        if (preg_match('~gitlab\.com/((?:[A-Za-z0-9._-]+/)+[A-Za-z0-9._-]+)/-/commit/([0-9a-f]{7,40})~i', $url, $m)) {
            $path = implode('/', array_map('rawurlencode', explode('/', $m[1])));
            $r    = self::get("https://gitlab.com/{$path}/-/commit/{$m[2]}.patch", [], 8192);
            if ($r['code'] === 404) {
                return ['error' => 'کامیت پیدا نشد یا پروژه خصوصیه'];
            }
            if ($r['code'] === 429) {
                return ['error' => 'سقف درخواست گیت‌لب پر شده؛ بعداً امتحان کن'];
            }
            $from = $r['body'] !== null && $r['code'] < 400 ? self::parseFrom($r['body']) : null;
            if ($from === null) {
                return ['error' => 'ایمیل نویسنده‌ی کامیت پیدا نشد'];
            }
            $d = ['name' => $from[0], 'email' => $from[1], 'commit_sha' => $m[2], 'project' => $m[1]];
            if (str_ends_with($from[1], 'users.noreply.gitlab.com')) {
                $d['is_noreply'] = true;
            }
            return ['data' => $d];
        }
        if (!preg_match('~^https?://(?:www\.)?gitlab\.com/([A-Za-z0-9][A-Za-z0-9._-]{0,254})/?$~i', $url, $m)
            || in_array(strtolower($m[1]), ['explore', 'users', 'help', 'admin', 'api', '-', 'dashboard', 'profile', 'groups', 'projects', 'search', 'public', 'snippets', 'assets', 'uploads'], true)) {
            return ['error' => 'آدرس گیت‌لب نامعتبره'];
        }
        $r     = self::get('https://gitlab.com/api/v4/users?username=' . rawurlencode($m[1]), ['Accept: application/json'], 200000);
        $users = $r['body'] !== null && $r['code'] < 400 ? json_decode($r['body'], true) : null;
        if (!is_array($users) || !$users) {
            return ['error' => $r['code'] === 429 ? 'سقف درخواست گیت‌لب پر شده؛ بعداً امتحان کن' : 'کاربر گیت‌لب پیدا نشد'];
        }
        $u      = $users[0];
        $detail = $u;
        $r2     = self::get('https://gitlab.com/api/v4/users/' . (int) ($u['id'] ?? 0), ['Accept: application/json'], 200000);
        if ($r2['body'] !== null && $r2['code'] < 400 && is_array($j = json_decode($r2['body'], true))) {
            $detail = $j;
        }
        return ['data' => [
            'username'     => $detail['username'] ?? $u['username'] ?? null,
            'display_name' => $detail['name'] ?? $u['name'] ?? null,
            'user_id'      => $u['id'] ?? null,
            'avatar_url'   => $detail['avatar_url'] ?? $u['avatar_url'] ?? null,
            'public_email' => $detail['public_email'] ?? null,
        ]];
    }

    private static function huggingface(string $url): array
    {
        if (!preg_match('~^https?://(?:www\.)?huggingface\.co/([A-Za-z0-9][A-Za-z0-9._-]{0,94})(?:[/?#]|$)~i', $url, $m)) {
            return ['error' => 'آدرس هاگینگ‌فیس نامعتبره'];
        }
        if (in_array(strtolower($m[1]), ['spaces', 'datasets', 'models', 'docs', 'blog', 'api', 'pricing', 'login', 'join', 'settings', 'new', 'tasks', 'chat'], true)) {
            return ['error' => 'این یه صفحه‌ی عمومی سایته، نه پروفایل کاربر'];
        }
        $r = self::get('https://huggingface.co/api/users/' . rawurlencode($m[1]) . '/overview', ['Accept: application/json'], 200000);
        if ($r['code'] === 404) {
            return ['error' => 'کاربر هاگینگ‌فیس پیدا نشد'];
        }
        $j = $r['body'] !== null && $r['code'] < 400 ? json_decode($r['body'], true) : null;
        if (!is_array($j)) {
            return ['error' => 'جواب نامعتبر از هاگینگ‌فیس'];
        }
        $user = (string) ($j['user'] ?? $m[1]);
        $orgs = [];
        foreach ((array) ($j['orgs'] ?? []) as $o) {
            if (is_array($o) && !empty($o['name'])) {
                $orgs[] = (string) $o['name'];
            }
        }
        return ['data' => [
            'username'      => $user,
            'fullname'      => ($j['fullname'] ?? '') !== '' ? $j['fullname'] : null,
            'avatar_url'    => $j['avatarUrl'] ?? null,
            'account_type'  => $j['type'] ?? 'user',
            'num_followers' => (int) ($j['numFollowers'] ?? 0),
            'orgs'          => $orgs,
            'profile_url'   => 'https://huggingface.co/' . $user,
        ]];
    }

    private static function listItem(array $e): string
    {
        if (isset($e['email'])) {
            return trim(($e['name'] ?? '') . ' <' . $e['email'] . '>');
        }
        if (isset($e['url'])) {
            return isset($e['title']) ? $e['title'] . ': ' . $e['url'] : (string) $e['url'];
        }
        return (string) json_encode($e, JSON_UNESCAPED_UNICODE);
    }

    public static function formatData(array $data): string
    {
        $lines = [];
        foreach ($data as $key => $v) {
            if ($v === null || $v === '' || $v === []) {
                continue;
            }
            $label = self::LABELS[$key] ?? $key;
            if (is_bool($v)) {
                $val = $v ? 'بله' : 'خیر';
            } elseif (is_array($v) && is_array(reset($v))) {
                $val = implode("\n", array_map(static fn($e) => '<code>' . h(self::listItem((array) $e)) . '</code>', array_slice($v, 0, 8)));
                $lines[] = "• {$label}:\n{$val}";
                continue;
            } elseif (is_array($v)) {
                $val = '<code>' . h(implode(', ', array_slice(array_map('strval', $v), 0, 8))) . '</code>';
            } elseif (is_int($v) && str_contains($key, 'count') || $key === 'num_followers') {
                $val = fa(number_format((int) $v));
            } else {
                $val = '<code>' . h(mb_substr((string) $v, 0, 200)) . '</code>';
            }
            $lines[] = "• {$label}: {$val}";
        }
        return implode("\n", $lines);
    }

    public static function resolve(string $url): string
    {
        $cur = $url;
        for ($i = 0; $i < 5; $i++) {
            if (!preg_match('~^https?://(?:share\.google/|(?:www\.)?google\.com/share\.google(?:[/?]|$))~i', $cur)) {
                return $cur;
            }
            $r = Http::request('GET', $cur, ['follow' => false, 'timeout' => 10, 'max_bytes' => 4096, 'impersonate' => 'chrome']);
            if ($r['code'] < 300 || $r['code'] >= 400 || ($r['redirect'] ?? '') === '') {
                return $cur;
            }
            $cur = (string) $r['redirect'];
        }
        return $cur;
    }

    public static function report(string $url): string
    {
        $url      = self::resolve($url);
        $platform = self::detect($url);
        $out      = "<b>🛡 چک لینک</b>\n\n🔗 <code>" . h(mb_substr($url, 0, 300)) . '</code>';
        if ($platform === null) {
            return $out . "\n\nاین لینک جزو پلتفرم‌های پشتیبانی‌شده نیست.\n\n<b>پشتیبانی‌شده‌ها:</b>\n" . implode('، ', array_values(array_unique(self::PLATFORM_NAMES)));
        }
        $res  = self::identity($platform, $url);
        $out .= "\n\n🪪 <b>اطلاعات پشت لینک</b> · " . self::PLATFORM_NAMES[$platform];
        return $out . (isset($res['data']) ? "\n" . self::formatData($res['data']) : "\n• " . h($res['error'] ?? 'نتیجه‌ای نگرفتم'));
    }
}
