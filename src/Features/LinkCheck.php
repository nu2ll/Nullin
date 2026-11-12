<?php

final class LinkCheck
{
    private const WAIT = 60;

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
        'github'           => 'گیت‌هاب',
        'gitlab'           => 'گیت‌لب',
        'huggingface'      => 'هاگینگ‌فیس',
        'tiktok'           => 'تیک‌تاک',
        'instagram'        => 'اینستاگرام',
        'youtube'          => 'یوتیوب',
        'reddit'           => 'ردیت',
        'notion'           => 'نوشن',
        'linkedin'         => 'لینکدین',
        'chatgpt'          => 'چت‌جی‌پی‌تی',
        'claude'           => 'کلود',
        'perplexity'       => 'پرپلکسیتی',
        'pinterest'        => 'پینترست',
        'substack'         => 'سابستک',
        'suno'             => 'سونو',
        'gdoc'             => 'گوگل داکس',
        'microsoft'        => 'شیرپوینت',
    ];

    private const PLATFORM_NAMES_EN = [
        'telegram'         => 'Telegram',
        'discord'          => 'Discord',
        'discord_channels' => 'Discord',
        'github'           => 'GitHub',
        'gitlab'           => 'GitLab',
        'huggingface'      => 'Hugging Face',
        'tiktok'           => 'TikTok',
        'instagram'        => 'Instagram',
        'youtube'          => 'YouTube',
        'reddit'           => 'Reddit',
        'notion'           => 'Notion',
        'linkedin'         => 'LinkedIn',
        'chatgpt'          => 'ChatGPT',
        'claude'           => 'Claude',
        'perplexity'       => 'Perplexity',
        'pinterest'        => 'Pinterest',
        'substack'         => 'Substack',
        'suno'             => 'Suno',
        'gdoc'             => 'Google Docs',
        'microsoft'        => 'SharePoint',
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
        'following_count'     => 'تعداد فالوینگ',
        'video_count'         => 'تعداد ویدیو',
        'heart_count'         => 'تعداد لایک',
        'private_account'     => 'اکانت خصوصی',
        'dm_available'        => 'امکان پیام خصوصی',
        'handle'              => 'آیدی',
        'previous_name'       => 'نام قبلی',
        'photo_url'           => 'عکس پروفایل',
        'profile_set_up_at'   => 'ساخت پروفایل',
        'reader_installed_at' => 'تاریخ نصب اپ',
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

    private const LABELS_EN = [
        'user_id'             => 'Numeric ID',
        'username'            => 'Username',
        'name'                => 'Name',
        'email'               => 'Email',
        'emails'              => 'Emails',
        'noreply_emails'      => 'Hidden emails (noreply)',
        'is_noreply'          => 'Hidden email (noreply)',
        'public_email'        => 'Public email',
        'avatar_url'          => 'Profile photo',
        'profile_url'         => 'Profile',
        'created_at'          => 'Created at',
        'modified_at'         => 'Last modified',
        'bio'                 => 'Bio',
        'follower_count'      => 'Followers',
        'num_followers'       => 'Followers',
        'commit_sha'          => 'Commit',
        'repo'                => 'Repo',
        'project'             => 'Project',
        'display_name'        => 'Display name',
        'fullname'            => 'Full name',
        'account_type'        => 'Account type',
        'orgs'                => 'Organizations',
        'url_type'            => 'Link type',
        'channel_id'          => 'Channel ID',
        'channel_internal_id' => 'Channel internal ID',
        'message_id'          => 'Message ID',
        'guild_id'            => 'Server ID',
        'guild_created_at'    => 'Server created at',
        'channel_created_at'  => 'Channel created at',
        'message_created_at'  => 'Message sent at',
        'doc_id'              => 'Document ID',
        'google_id'           => 'Google ID',
        'public_permissions'  => 'Public permissions',
        'nickname'            => 'Nickname',
        'country'             => 'Country',
        'signature'           => 'Bio',
        'device'              => 'Device',
        'share_method'        => 'Share method',
        'shared_at'           => 'Shared at',
        'following_count'     => 'Following',
        'video_count'         => 'Videos',
        'heart_count'         => 'Likes',
        'private_account'     => 'Private account',
        'dm_available'        => 'DMs available',
        'handle'              => 'Handle',
        'previous_name'       => 'Previous name',
        'photo_url'           => 'Profile photo',
        'profile_set_up_at'   => 'Profile created',
        'reader_installed_at' => 'App installed at',
        'subreddit'           => 'Subreddit',
        'post_url'            => 'Post URL',
        'post_title'          => 'Post title',
        'headline'            => 'Headline',
        'published_at'        => 'Published at',
        'space_name'          => 'Workspace',
        'space_domain'        => 'Workspace domain',
        'other_editors'       => 'Other editors',
        'video_id'            => 'Video ID',
        'video_title'         => 'Video title',
        'channel_url'         => 'Channel URL',
        'view_count'          => 'Views',
        'video_country'       => 'Video country',
        'joined_date'         => 'Joined',
        'subscriber_count'    => 'Subscribers',
        'external_links'      => 'External links',
    ];

    private static function platformNames(): array
    {
        return Lang::is('en') ? self::PLATFORM_NAMES_EN : self::PLATFORM_NAMES;
    }

    private static function labels(): array
    {
        return Lang::is('en') ? self::LABELS_EN : self::LABELS;
    }

    private static function waitText(): string
    {
        return L("یه‌کم دیگه بیا 😅\n⏳ هر دقیقه فقط یه ردیاب اشتراک!", "Come back in a bit 😅\n⏳ Only one link trace per minute!");
    }

    private static function title(): string
    {
        return '<b>🛡 ' . L('ردیاب اشتراک', 'Invite link tracker') . '</b>';
    }

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

    public static function busy(int $uid): bool
    {
        if ($uid <= 0 || App::isAdmin($uid)) {
            return false;
        }
        return Store::update('rl', 'linkcheck_' . $uid, static fn($at) => (is_int($at) && time() - $at < self::WAIT) ? null : time()) === null;
    }

    public static function respond(string $arg, string $reply = '', int $uid = 0): string
    {
        return self::respondFull($arg, $reply, $uid)['text'];
    }

    public static function respondFull(string $arg, string $reply = '', int $uid = 0): array
    {
        $url = self::extract($arg) ?? self::extract($reply);
        if ($url === null) {
            return ['text' => Ui::guide('linkcheck'), 'avatar' => null];
        }
        if (self::busy($uid)) {
            return ['text' => self::waitText(), 'avatar' => null, 'rich' => true];
        }
        Users::hit('linkcheck');
        return self::build($url);
    }

    public static function pending(array $from, string $url): array
    {
        $token = bin2hex(random_bytes(6));
        Store::set('linkcheck_inline', $token, ['from' => $from, 'url' => $url]);
        return [
            'token' => $token,
            'text'  => self::title() . "\n\n🔗 <a href=\"" . h($url) . '">' . L('لینک', 'Link') . '</a>' . "\n\n" . L('⏳ دارم ردیابی می‌کنم...', '⏳ Tracing...'),
            'kb'    => ikb([[btn(L('⏳ صبر کن...', '⏳ Wait...'), 'noop', null)]]),
        ];
    }

    public static function resolveInline(string $token, string $imid): void
    {
        $p = Store::get('linkcheck_inline', $token);
        if (!is_array($p)) {
            return;
        }
        Store::delete('linkcheck_inline', $token);
        $tg = App::tg();
        if (self::busy((int) ($p['from']['id'] ?? 0))) {
            Rich::editInlineRich($imid, self::waitText());
            return;
        }
        Users::hit('linkcheck');
        $text = self::build((string) $p['url'])['text'];
        if (empty(Rich::editInline($imid, $text)['ok'])) {
            Rich::editInline($imid, strip_tags($text), null, null, false);
        }
    }

    public static function avatarBytes(string $url): ?string
    {
        if (!preg_match('~^https://~i', $url)) {
            return null;
        }
        $r = Http::request('GET', $url, [
            'headers'         => ['User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36'],
            'timeout'         => 12,
            'connect_timeout' => 6,
            'max_bytes'       => 5000000,
        ]);
        $body = $r['body'] ?? null;
        if (!is_string($body) || $body === '' || ($r['code'] ?? 0) >= 400 || @getimagesizefromstring($body) === false) {
            return null;
        }
        return $body;
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
        return isset($r['data']) ? $r : ($r ?: ['error' => L('چیزی پیدا نکردم', 'Found nothing')]);
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
            return ['error' => L('این لینک دعوت درست نیست', 'This invite link isn’t valid')];
        }
        if (strlen($raw) >= 16) {
            $u = unpack('Vid', substr($raw, 0, 4));
            return ['data' => ['user_id' => (string) $u['id']]];
        }
        return ['error' => L("این لینک دعوت از نوع جدیده\nو آیدی سازنده‌ش توش نیست", "This invite link is of a newer type\nand doesn’t contain the creator’s ID")];
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
            $d = ['channel_id' => '-100' . $m[1], 'channel_internal_id' => $m[1], 'url_type' => L('کانال خصوصی (برای دیدن محتوا باید عضو باشی)', 'Private channel (you must be a member to see the content)')];
            if (!empty($m[2])) {
                $d['message_id'] = $m[2];
            }
            return ['data' => $d];
        }
        if (preg_match('~t\.me/(?:joinchat/|\+)([A-Za-z0-9_-]+)~i', $url, $m)) {
            return self::decodeInvite($m[1]);
        }
        if (!preg_match('~t\.me/(?!joinchat/|c/|\+|s/)([A-Za-z0-9_]{5,32})~i', $url, $m)) {
            return ['error' => L('این لینک تلگرام درست نیست', 'This Telegram link isn’t valid')];
        }
        $r = self::get('https://t.me/s/' . $m[1]);
        if ($r['body'] === null || $r['body'] === '' || $r['code'] >= 400) {
            return ['error' => (int) $r['code'] === 0 ? L('نتونستم به تلگرام وصل بشم', 'Couldn’t connect to Telegram') : L('تلگرام جواب نداد (HTTP ', 'Telegram didn’t respond (HTTP ') . $r['code'] . ')'];
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
        return count($d) > 1 ? ['data' => $d] : ['error' => L('چیزی پیدا نکردم', 'Found nothing')];
    }

    private static function discord(string $url): array
    {
        if (!preg_match('~(?:discord\.com/invite|discord\.gg)/([A-Za-z0-9_-]+)~i', $url, $m)) {
            return ['error' => L('این لینک دعوت دیسکورد درست نیست', 'This Discord invite link isn’t valid')];
        }
        $r = self::get('https://discord.com/api/v9/invites/' . $m[1], ['Accept: application/json'], 200000);
        $j = $r['body'] !== null ? json_decode($r['body'], true) : null;
        if ($r['code'] >= 400 || !is_array($j)) {
            return ['error' => L('دیسکورد جواب نداد یا این دعوت‌نامه معتبر نیست', 'Discord didn’t respond or this invite isn’t valid')];
        }
        $inv = $j['inviter'] ?? null;
        if (!is_array($inv) || empty($inv['id'])) {
            return ['error' => L('تو این دعوت‌نامه اطلاعاتی از دعوت‌کننده نیست', 'This invite has no information about the inviter')];
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
            return ['error' => L('این لینک کانال دیسکورد درست نیست', 'This Discord channel link isn’t valid')];
        }
        $d = ['channel_id' => $m[2], 'channel_created_at' => self::snowflake($m[2])];
        if (strtolower($m[1]) === '@me') {
            $d['url_type'] = L('پیام خصوصی (DM)', 'Direct message (DM)');
        } else {
            $d['guild_id']         = $m[1];
            $d['guild_created_at'] = self::snowflake($m[1]);
            $d['url_type']         = !empty($m[3]) ? L('پیام تو سرور', 'Message in a server') : L('کانال تو سرور', 'Channel in a server');
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
        return $email === null ? ['error' => L('این لینک شیرپوینت درست نیست', 'This SharePoint link isn’t valid')] : ['data' => ['email' => $email]];
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
            return ['error' => L('آیدی سند رو از لینک درنیاوردم', 'Couldn’t extract the document ID from the link')];
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
            return ['error' => L('سند پیدا نشد یا دسترسی بازی نداره', 'Document not found or it isn’t publicly accessible')];
        }
        if ($r['code'] === 401 || $r['code'] === 403) {
            return ['error' => L('گوگل داکس جواب درستی نداد', 'Google Docs didn’t return a valid response')];
        }
        $j = $r['body'] !== null ? json_decode($r['body'], true) : null;
        if ($r['code'] >= 400 || !is_array($j)) {
            return ['error' => L('گوگل داکس جواب درستی نداد', 'Google Docs didn’t return a valid response')];
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
            return ['error' => L('سند بازه ولی هویت صاحبش مشخص نیست', 'The document is open but its owner’s identity isn’t shown')];
        }
        return ['data' => $d];
    }

    private static function github(string $url): array
    {
        if (preg_match('~github\.com/([A-Za-z0-9._-]+)/([A-Za-z0-9._-]+)/(?:commit|pull/\d+/commits)/([0-9a-f]{7,40})~i', $url, $m)) {
            $r = self::get("https://github.com/{$m[1]}/{$m[2]}/commit/{$m[3]}.patch", [], 8192);
            if ($r['code'] === 404) {
                return ['error' => L('کامیت پیدا نشد یا ریپو خصوصیه', 'Commit not found or the repo is private')];
            }
            $from = $r['body'] !== null && $r['code'] < 400 ? self::parseFrom($r['body']) : null;
            if ($from === null) {
                return ['error' => L('ایمیل نویسنده‌ی کامیت رو پیدا نکردم', 'Couldn’t find the commit author’s email')];
            }
            $d = ['name' => $from[0], 'email' => $from[1], 'commit_sha' => $m[3], 'repo' => "{$m[1]}/{$m[2]}"];
            if (str_ends_with($from[1], 'users.noreply.github.com')) {
                $d['is_noreply'] = true;
            }
            return ['data' => $d];
        }
        if (!preg_match('~^https?://(?:www\.)?github\.com/([A-Za-z0-9](?:[A-Za-z0-9-]{0,37}[A-Za-z0-9])?)(?:[/?]|$)~i', $url, $m)) {
            return ['error' => L('این لینک گیت‌هاب درست نیست', 'This GitHub link isn’t valid')];
        }
        $r = self::get('https://api.github.com/users/' . $m[1] . '/events/public', ['Accept: application/vnd.github+json'], 600000);
        if ($r['code'] === 404) {
            return ['error' => L('کاربر گیت‌هاب پیدا نشد', 'GitHub user not found')];
        }
        if ($r['code'] === 403 || $r['code'] === 429) {
            return ['error' => L("چند دقیقه دیگه امتحان کن؛\nگیت‌هاب موقتاً درخواست‌های ربات رو محدود کرده", "Try again in a few minutes;\nGitHub has temporarily rate-limited the bot")];
        }
        $events = $r['body'] !== null && $r['code'] < 400 ? json_decode($r['body'], true) : null;
        if (!is_array($events)) {
            return ['error' => L('گیت‌هاب جواب درستی نداد', 'GitHub didn’t return a valid response')];
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
            return ['error' => L('تو فعالیت‌های اخیرش ایمیلی پیدا نکردم', 'Found no email in their recent activity')];
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
                return ['error' => L('کامیت پیدا نشد یا پروژه خصوصیه', 'Commit not found or the project is private')];
            }
            if ($r['code'] === 429) {
                return ['error' => L("چند دقیقه دیگه امتحان کن؛\nگیت‌لب موقتاً درخواست‌های ربات رو محدود کرده", "Try again in a few minutes;\nGitLab has temporarily rate-limited the bot")];
            }
            $from = $r['body'] !== null && $r['code'] < 400 ? self::parseFrom($r['body']) : null;
            if ($from === null) {
                return ['error' => L('ایمیل نویسنده‌ی کامیت رو پیدا نکردم', 'Couldn’t find the commit author’s email')];
            }
            $d = ['name' => $from[0], 'email' => $from[1], 'commit_sha' => $m[2], 'project' => $m[1]];
            if (str_ends_with($from[1], 'users.noreply.gitlab.com')) {
                $d['is_noreply'] = true;
            }
            return ['data' => $d];
        }
        if (!preg_match('~^https?://(?:www\.)?gitlab\.com/([A-Za-z0-9][A-Za-z0-9._-]{0,254})/?$~i', $url, $m)
            || in_array(strtolower($m[1]), ['explore', 'users', 'help', 'admin', 'api', '-', 'dashboard', 'profile', 'groups', 'projects', 'search', 'public', 'snippets', 'assets', 'uploads'], true)) {
            return ['error' => L('این لینک گیت‌لب درست نیست', 'This GitLab link isn’t valid')];
        }
        $r     = self::get('https://gitlab.com/api/v4/users?username=' . rawurlencode($m[1]), ['Accept: application/json'], 200000);
        $users = $r['body'] !== null && $r['code'] < 400 ? json_decode($r['body'], true) : null;
        if (!is_array($users) || !$users) {
            return ['error' => $r['code'] === 429 ? L("چند دقیقه دیگه امتحان کن؛\nگیت‌لب موقتاً درخواست‌های ربات رو محدود کرده", "Try again in a few minutes;\nGitLab has temporarily rate-limited the bot") : L('کاربر گیت‌لب پیدا نشد', 'GitLab user not found')];
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
            return ['error' => L('این لینک هاگینگ‌فیس درست نیست', 'This Hugging Face link isn’t valid')];
        }
        if (in_array(strtolower($m[1]), ['spaces', 'datasets', 'models', 'docs', 'blog', 'api', 'pricing', 'login', 'join', 'settings', 'new', 'tasks', 'chat'], true)) {
            return ['error' => L('این یه صفحه‌ی خود سایته، نه پروفایل کاربر', 'This is a page of the site itself, not a user profile')];
        }
        $r = self::get('https://huggingface.co/api/users/' . rawurlencode($m[1]) . '/overview', ['Accept: application/json'], 200000);
        if ($r['code'] === 404) {
            return ['error' => L('کاربر هاگینگ‌فیس پیدا نشد', 'Hugging Face user not found')];
        }
        $j = $r['body'] !== null && $r['code'] < 400 ? json_decode($r['body'], true) : null;
        if (!is_array($j)) {
            return ['error' => L('هاگینگ‌فیس جواب درستی نداد', 'Hugging Face didn’t return a valid response')];
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

    private static function urlText(string $key, string $url): string
    {
        if ($key !== 'profile_url' && $key !== 'channel_url') {
            return L('باز کردن', 'Open');
        }
        $parts = array_values(array_filter(explode('/', (string) parse_url($url, PHP_URL_PATH)), static fn($p) => $p !== ''));
        $last  = $parts ? ltrim(rawurldecode((string) end($parts)), '@') : '';
        if ($last === '') {
            return (string) preg_replace('~^www\.~i', '', (string) parse_url($url, PHP_URL_HOST));
        }
        return mb_substr($last, 0, 64);
    }

    public static function formatData(array $data): string
    {
        $lines = [];
        foreach ($data as $key => $v) {
            if ($v === null || $v === '' || $v === [] || $key === 'avatar_url' || $key === 'photo_url') {
                continue;
            }
            $label = self::labels()[$key] ?? $key;
            if (is_bool($v)) {
                $val = $v ? L('بله', 'Yes') : L('خیر', 'No');
            } elseif (is_array($v) && is_array(reset($v))) {
                $val = implode("\n", array_map(static fn($e) => '<code>' . h(self::listItem((array) $e)) . '</code>', array_slice($v, 0, 8)));
                $lines[] = "• {$label}:\n{$val}";
                continue;
            } elseif (is_array($v)) {
                $val = '<code>' . h(implode(', ', array_slice(array_map('strval', $v), 0, 8))) . '</code>';
            } elseif (is_string($v) && str_ends_with($key, '_url') && preg_match('~^https?://~i', $v)) {
                $val = '<a href="' . h($v) . '">' . h(self::urlText($key, $v)) . '</a>';
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
        return self::build($url)['text'];
    }

    public static function build(string $url): array
    {
        $url      = self::resolve($url);
        $platform = self::detect($url);
        $pname    = $platform === null ? '' : trim((string) preg_replace('~\s*\(.*\)~u', '', self::platformNames()[$platform]));
        $label    = $platform === null ? L('لینک', 'Link') : L('لینک ' . $pname, $pname . ' link');
        $out      = self::title() . "\n\n🔗 <a href=\"" . h($url) . '">' . h($label) . '</a>';
        if ($platform === null) {
            return ['text' => $out . "\n\n" . L("🤷 این لینک رو نمی‌شناسم؛\nفقط لینک اشتراک این سایت‌ها رو بررسی می‌کنم:", "🤷 I don’t recognize this link;\nI only trace invite/share links of these sites:") . "\n" . implode(L('، ', ', '), array_values(array_unique(self::platformNames()))), 'avatar' => null];
        }
        $res  = self::identity($platform, $url);
        $out .= "\n\n🪪 <b>" . L('اطلاعات پشت این لینک', 'Information behind this link') . '</b> · ' . self::platformNames()[$platform] . "\n";
        if (isset($res['data'])) {
            $avatar = $res['data']['avatar_url'] ?? $res['data']['photo_url'] ?? null;
            return [
                'text'   => $out . self::formatData($res['data']),
                'avatar' => is_string($avatar) && $avatar !== '' ? $avatar : null,
            ];
        }
        return ['text' => $out . '😕 ' . h($res['error'] ?? L('چیزی پیدا نکردم', 'Found nothing')), 'avatar' => null];
    }
}
