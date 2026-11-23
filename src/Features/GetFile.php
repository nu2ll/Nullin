<?php

/**
 * دریافت فایل از اینترنت با درخواست زبان طبیعی:
 *   «v2rayNG نسخه اندروید رو بفرست» / «ریپوی فلان رو بده» / «عکس فلان» / لینک مستقیم فایل
 *
 * مراحل: Intent::run → start() → (پروسه‌ی پس‌زمینه) work() → resolve() → fetch() → send()
 */
final class GetFile
{
    private const TMP   = '/tmp/nullini-gf';
    private const DAILY = 10;
    private const UA    = 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36';

    private const FILE_EXT = 'pdf|zip|rar|7z|tar|gz|tgz|xz|apk|xapk|apks|aab|exe|msi|dmg|pkg|deb|rpm|appimage|ipa|iso|mp3|m4a|flac|ogg|wav|mp4|mkv|webm|mov|avi|epub|mobi|docx?|xlsx?|pptx?|csv|jpe?g|png|gif|webp|bmp|ttf|otf|torrent';
    private const EXEC_EXT = ['apk', 'xapk', 'apks', 'aab', 'exe', 'msi', 'dmg', 'pkg', 'deb', 'rpm', 'appimage', 'ipa', 'zip', 'rar', '7z', 'iso'];
    private const SOFT_HINTS = ['', 'apk', 'win', 'mac', 'linux', 'ios', 'source', 'zip', 'exe', 'msi', 'dmg', 'deb', 'rpm', 'appimage', 'ipa', 'tar', 'gz'];

    private const MIME_EXT = [
        'application/pdf' => 'pdf', 'application/zip' => 'zip', 'application/vnd.android.package-archive' => 'apk',
        'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif',
        'audio/mpeg' => 'mp3', 'video/mp4' => 'mp4', 'application/x-msdownload' => 'exe', 'application/x-7z-compressed' => '7z',
        'application/gzip' => 'gz', 'application/vnd.rar' => 'rar', 'application/x-rar-compressed' => 'rar', 'application/epub+zip' => 'epub',
    ];

    // ───────────────────────── ورودی ─────────────────────────

    public static function start(array $msg, string $arg): bool
    {
        if (($msg['chat']['type'] ?? '') !== 'private') {
            return false;
        }
        $uid  = (int) ($msg['from']['id'] ?? 0);
        $chat = $msg['chat']['id'];
        $req  = self::parseArg($arg);
        $isAdmin = App::isAdmin($uid);

        if ($req['q'] === '') {
            Rich::notice($msg, L('چه فایلی می‌خوای؟ اسم برنامه یا لینکش رو بگو 🙂', 'Which file do you want? Tell me the app name or the link 🙂'));
            return true;
        }
        if (!$isAdmin && self::used($uid) >= self::DAILY) {
            Rich::notice($msg, L('فردا دوباره بیا 🙂', 'Come back tomorrow 🙂') . "\n" . L('✋ امروز سهمیه‌ی دانلود فایلت (', '✋ You’ve used up today’s file quota (') . fa(self::DAILY) . L(' تا) تموم شده؛', ' files).'));
            return true;
        }
        if (!$isAdmin && (int) Store::get('gf_busy', (string) $uid, 0) > time() - 1800) {
            Rich::notice($msg, L('⏳ فایل قبلیت هنوز در حال دانلوده؛ صبر کن تموم بشه 🙂', '⏳ Your previous file is still downloading; wait for it to finish 🙂'));
            return true;
        }

        $sent = Rich::sendHtml($chat, L('🔎 دارم دنبال فایل می‌گردم...', '🔎 Looking for the file...'), (int) $msg['message_id']);
        $mid  = (int) ($sent['result']['message_id'] ?? 0);
        if ($mid <= 0) {
            return true;
        }
        $token = bin2hex(random_bytes(6));
        $job   = [
            'owner'    => $uid,
            'chat'     => $chat,
            'mid'      => $mid,
            'reply_to' => (int) $msg['message_id'],
            'q'        => $req['q'],
            'hint'     => $req['hint'],
            'ver'      => $req['ver'],
            'cands'    => [],
        ];
        Store::set('gf', $token, $job);
        Store::set('gf_busy', (string) $uid, time());
        if (!self::spawn($token, -1)) {
            Store::delete('gf_busy', (string) $uid);
            self::fail($job, L('😕 الان نمی‌تونم این کار رو انجام بدم؛ بعداً دوباره امتحان کن.', '😕 I can’t do this right now; please try again later.'));
        }
        return true;
    }

    public static function onCallback(array $cq, string $data): void
    {
        $tg = App::tg();
        if (!preg_match('/^gf:([a-f0-9]{12}):(x|\d{1,2})$/', $data, $m)) {
            $tg->answer($cq);
            return;
        }
        $token = $m[1];
        $uid   = (int) ($cq['from']['id'] ?? 0);
        $job   = Store::get('gf', $token);
        if (!is_array($job)) {
            $tg->alert($cq, L('⌛️ این درخواست منقضی شده؛', '⌛️ This request has expired;') . "\n" . L('دوباره بخواهش.', 'ask again.'));
            return;
        }
        if ($uid !== (int) $job['owner'] && !App::isAdmin($uid)) {
            $tg->alert($cq, L('🙅 این دکمه مال کسیه که فایل رو خواسته.', '🙅 This button belongs to whoever asked for the file.'));
            return;
        }
        if ($m[2] === 'x') {
            $tg->call('deleteMessage', ['chat_id' => $job['chat'], 'message_id' => (int) $job['mid']], 8);
            Store::delete('gf', $token);
            $tg->answer($cq, L('باشه 👌', 'OK 👌'));
            return;
        }
        $i = (int) $m[2];
        if (empty($job['cands'][$i]) || !empty($job['picked'])) {
            $tg->answer($cq);
            return;
        }
        if (!App::isAdmin($uid) && self::used((int) $job['owner']) >= self::DAILY) {
            $tg->alert($cq, L('✋ امروز سهمیه‌ات تموم شده؛ فردا دوباره بیا.', '✋ Today’s quota is used up; come back tomorrow.'));
            return;
        }
        $job['picked'] = true;
        Store::set('gf', $token, $job);
        Store::set('gf_busy', (string) $job['owner'], time());
        $tg->answer($cq, L('باشه 👌', 'OK 👌'));
        self::show($job, L('⏳ آماده‌سازی دانلود...', '⏳ Preparing the download...'));
        if (!self::spawn($token, $i)) {
            Store::delete('gf_busy', (string) $job['owner']);
            self::fail($job, L('😕 الان نمی‌تونم این کار رو انجام بدم؛ بعداً دوباره امتحان کن.', '😕 I can’t do this right now; please try again later.'));
        }
    }

    // ───────────────────────── پروسه‌ی پس‌زمینه ─────────────────────────

    public static function work(string $token, int $pick): void
    {
        $job = Store::get('gf', $token);
        if (!is_array($job)) {
            return;
        }
        Lang::useFrom(['id' => (int) ($job['owner'] ?? 0)]);
        $dir = self::TMP . '/' . $token;
        @mkdir($dir, 0700, true);
        try {
            $res = $pick >= 0 ? ['urls' => [$job['cands'][$pick] ?? []]] : self::resolve($job);
            if (!empty($res['error'])) {
                self::fail($job, (string) $res['error']);
                return;
            }
            if (!empty($res['choose'])) {
                $job['cands'] = $res['choose'];
                Store::set('gf', $token, $job);
                self::askChoice($job, $token, $res['choose']);
                return;
            }
            self::download($job, $token, $dir, (array) ($res['urls'] ?? []));
        } catch (\Throwable $e) {
            logError('gf', $e->getMessage());
            self::fail($job, L('😕 یه مشکل پیش اومد؛ دوباره امتحان کن.', '😕 Something went wrong; please try again.'));
        } finally {
            Store::delete('gf_busy', (string) ($job['owner'] ?? 0));
            self::rmrf($dir);
        }
    }

    private static function download(array $job, string $token, string $dir, array $urls): void
    {
        $max  = self::maxBytes();
        $last = 'notfound';
        $hint = (string) $job['hint'];
        foreach (array_slice($urls, 0, 4) as $c) {
            if (empty($c['url'])) {
                continue;
            }
            $file  = $dir . '/download.bin';
            @unlink($file);
            $label = (string) ($c['name'] ?? '') !== '' ? (string) $c['name'] : self::hostOf((string) $c['url']);
            $next  = 0.0;
            $tick  = function (int $now, int $total) use ($job, $label, &$next): void {
                if (microtime(true) < $next) {
                    return;
                }
                $next = microtime(true) + 3.5;
                $pct  = $total > 0 ? (int) floor($now / $total * 100) : 0;
                $line = $total > 0
                    ? Downloader::bar($pct) . ' ' . fa((string) $pct) . L('٪', '%') . "\n📦 " . Downloader::mb((float) $now) . L(' از ', ' of ') . Downloader::mb((float) $total)
                    : '📦 ' . Downloader::mb((float) $now);
                self::show($job, '⬇️ <b>' . h(mb_substr($label, 0, 80)) . "</b>\n" . $line);
            };
            self::show($job, '⬇️ ' . L('دارم دانلود می‌کنم: ', 'Downloading: ') . '<b>' . h(mb_substr($label, 0, 80)) . '</b>' . (!empty($c['size']) ? ' · ' . Downloader::mb((float) $c['size']) : ''));

            $r = self::fetch((string) $c['url'], $file, $max, $tick, $hint);
            if (empty($r['ok'])) {
                $last = (string) ($r['err'] ?? 'net');
                logWarn('gf', "fetch failed ({$last}): " . mb_substr((string) $c['url'], 0, 200));
                continue;
            }

            $name = self::fileName((string) ($c['name'] ?? ''), (string) $r['disp'], (string) $r['final'], (string) $r['mime']);
            $path = $dir . '/' . $name;
            if (!@rename($file, $path)) {
                $path = $file;
            }
            $size = (int) @filesize($path);
            $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            $cap  = self::caption($c, $name, $size, in_array($ext, self::EXEC_EXT, true));

            if (!self::send($job, $path, $name, $cap, $size)) {
                self::fail($job, L('😕 فایل دانلود شد ولی ارسالش به تلگرام نشد.', '😕 The file was downloaded but sending it to Telegram failed.'));
                return;
            }
            App::tg()->call('deleteMessage', ['chat_id' => $job['chat'], 'message_id' => (int) $job['mid']], 8);
            if (!App::isAdmin((int) $job['owner'])) {
                Store::update('gf_quota', self::quotaKey((int) $job['owner']), static fn($n) => (int) $n + 1);
            }
            Store::delete('gf', $token);
            logInfo('gf', 'sent ' . $name . ' (' . $size . ' bytes) to ' . $job['owner']);
            return;
        }
        self::fail($job, self::errText($last));
    }

    // ───────────────────────── پیدا کردن لینک ─────────────────────────

    private static function resolve(array $job): array
    {
        $q    = (string) $job['q'];
        $hint = (string) $job['hint'];
        $ver  = (string) $job['ver'];

        if (preg_match('#^https?://#i', $q)) {
            if (preg_match('#^https?://(?:www\.)?github\.com/([\w.-]+)/([\w.-]+?)(?:\.git)?(?:/(.*))?$#i', $q, $m)
                && !preg_match('#^(releases/download|raw|archive|blob|raw\.githubusercontent)#i', (string) ($m[3] ?? ''))) {
                if (preg_match('#^releases/tag/(.+)$#i', (string) ($m[3] ?? ''), $tm) && $ver === '') {
                    $ver = rawurldecode($tm[1]);
                }
                return self::fromGithub($m[1], $m[2], $hint, $ver);
            }
            return ['urls' => [['url' => $q, 'name' => '', 'size' => 0, 'src' => self::hostOf($q), 'srcUrl' => $q]]];
        }

        if (in_array($hint, self::SOFT_HINTS, true) && preg_match('/[A-Za-z0-9]/', $q) && !preg_match('/\s{3,}/', $q)) {
            $repo = self::findRepo($q);
            if ($repo !== null) {
                return self::fromGithub($repo[0], $repo[1], $hint, $ver);
            }
        }
        return self::fromWeb($job);
    }

    private static function gh(string $path): ?array
    {
        $h = [];
        if (($t = trim((string) env('GITHUB_TOKEN', ''))) !== '') {
            $h[] = 'Authorization: Bearer ' . $t;
        }
        return Http::getJson('https://api.github.com' . $path, $h, 12);
    }

    private static function norm(string $s): string
    {
        return (string) preg_replace('/[^a-z0-9]/', '', strtolower($s));
    }

    private static function findRepo(string $q): ?array
    {
        $nq = self::norm($q);
        if (strlen($nq) < 3) {
            return null;
        }
        $r = self::gh('/search/repositories?q=' . rawurlencode($q) . '&sort=stars&order=desc&per_page=6');
        foreach ((array) ($r['items'] ?? []) as $it) {
            $nr = self::norm((string) ($it['name'] ?? ''));
            if ($nr === '') {
                continue;
            }
            if ($nr === $nq || (strlen($nr) >= 3 && str_contains($nq, $nr)) || (strlen($nq) >= 4 && str_contains($nr, $nq))) {
                return [(string) $it['owner']['login'], (string) $it['name']];
            }
        }
        return null;
    }

    private static function fromGithub(string $o, string $r, string $hint, string $ver): array
    {
        $src    = 'GitHub: ' . $o . '/' . $r;
        $srcUrl = 'https://github.com/' . $o . '/' . $r;

        if ($hint === 'source') {
            return self::sourceZip($o, $r, $src, $srcUrl);
        }
        $rel = self::release($o, $r, $ver);
        if ($rel === null || empty($rel['assets'])) {
            if ($hint === '' && $ver === '') {
                return self::sourceZip($o, $r, $src, $srcUrl);
            }
            return ['error' => L('😕 برای این پروژه فایل آماده‌ای پیدا نکردم.', '😕 I couldn’t find a ready-made file for this project.')];
        }
        $tag = (string) ($rel['tag_name'] ?? '');
        $re  = self::hintRegex($hint);
        $all = [];
        foreach ((array) $rel['assets'] as $a) {
            $n = (string) ($a['name'] ?? '');
            if ($n === '' || empty($a['browser_download_url']) || preg_match('/\.(sha\d+|md5|sig|asc|minisig|pem|sbom|blockmap|ya?ml|txt|json)$/i', $n)) {
                continue;
            }
            if ($re !== null && !preg_match($re, $n)) {
                continue;
            }
            $all[] = ['url' => (string) $a['browser_download_url'], 'name' => $n, 'size' => (int) ($a['size'] ?? 0),
                      'src' => $src . ($tag !== '' ? ' · ' . $tag : ''), 'srcUrl' => 'https://github.com/' . $o . '/' . $r . '/releases' . ($tag !== '' ? '/tag/' . rawurlencode($tag) : ''), 'score' => self::score($n, $hint)];
        }
        if (!$all) {
            return ['error' => L('😕 فایلی با این نوع تو آخرین نسخه‌ی این پروژه نبود.', '😕 This project’s release has no file of that type.')];
        }
        usort($all, static fn($a, $b) => [$b['score'], $a['size']] <=> [$a['score'], $b['size']]);
        if (count($all) === 1) {
            return ['urls' => $all];
        }
        if (in_array($hint, ['apk', 'win'], true) && $all[0]['score'] > $all[1]['score']) {
            return ['urls' => $all];
        }
        return ['choose' => array_slice($all, 0, 8)];
    }

    private static function sourceZip(string $o, string $r, string $src, string $srcUrl): array
    {
        $info = self::gh("/repos/{$o}/{$r}");
        if (!is_array($info) || empty($info['name'])) {
            return ['error' => L('😕 این ریپو رو پیدا نکردم.', '😕 I couldn’t find this repository.')];
        }
        $bytes = (int) ($info['size'] ?? 0) * 1024;
        if ($bytes > self::maxBytes()) {
            return ['error' => L('📦 این ریپو از سقف حجم مجاز (', '📦 This repository is over the size limit (') . self::limitText() . L(') بزرگ‌تره.', ').')];
        }
        $branch = (string) ($info['default_branch'] ?? 'main');
        return ['urls' => [['url' => "https://api.github.com/repos/{$o}/{$r}/zipball", 'name' => "{$r}-{$branch}.zip", 'size' => $bytes, 'src' => $src . L(' (سورس)', ' (source)'), 'srcUrl' => $srcUrl]]];
    }

    private static function release(string $o, string $r, string $ver): ?array
    {
        if ($ver === '') {
            $x = self::gh("/repos/{$o}/{$r}/releases/latest");
            if (is_array($x) && !empty($x['assets'])) {
                return $x;
            }
            $list = self::gh("/repos/{$o}/{$r}/releases?per_page=8");
        } else {
            $list = self::gh("/repos/{$o}/{$r}/releases?per_page=50");
        }
        $list = array_values(array_filter((array) $list, static fn($x) => is_array($x) && empty($x['draft'])));
        if ($ver === '') {
            foreach ($list as $rel) {
                if (!empty($rel['assets'])) {
                    return $rel;
                }
            }
            return null;
        }
        $v = ltrim(strtolower($ver), 'v');
        foreach ($list as $rel) {
            if (ltrim(strtolower((string) ($rel['tag_name'] ?? '')), 'v') === $v) {
                return $rel;
            }
        }
        foreach ($list as $rel) {
            if (str_contains(strtolower((string) ($rel['tag_name'] ?? '')), $v)) {
                return $rel;
            }
        }
        return null;
    }

    private static function hintRegex(string $h): ?string
    {
        return match ($h) {
            '' => null,
            'apk'    => '/\.(apk|xapk|apks)$/i',
            'win'    => '/(\.(exe|msi)$|win(dows)?[-_. ]|[-_. ]win)/i',
            'mac'    => '/(\.(dmg|pkg)$|mac|darwin|osx)/i',
            'linux'  => '/(\.(appimage|deb|rpm)$|linux|\.tar\.(gz|xz)$)/i',
            'ios'    => '/\.ipa$/i',
            'image'  => '/\.(jpe?g|png|webp|gif|bmp)$/i',
            'source' => null,
            default  => preg_match('/^[a-z0-9]{2,6}$/', $h) ? '/\.' . $h . '$/i' : null,
        };
    }

    private static function score(string $name, string $hint): int
    {
        $n = strtolower($name);
        $s = 0;
        if ($hint === 'apk') {
            if (preg_match('/arm64/', $n)) {
                $s += 4;
            } elseif (str_contains($n, 'universal')) {
                $s += 3;
            } elseif (preg_match('/x86|armeabi/', $n)) {
                $s -= 1;
            }
        } else {
            if (preg_match('/x64|amd64|x86[_-]64/', $n)) {
                $s += 2;
            }
            if (preg_match('/arm64|aarch64/', $n)) {
                $s += 1;
            }
        }
        return $s;
    }

    private static function fromWeb(array $job): array
    {
        $q      = (string) $job['q'];
        $hint   = (string) $job['hint'];
        $images = $hint === 'image';
        $re     = self::hintRegex($hint);
        $words  = $hint !== '' && !$images && $hint !== 'source' ? ' ' . $hint : '';
        $res    = WebSearch::files($q . $words . ($images ? '' : ' download'), $images);
        if ($res === null) {
            return ['error' => L('😕 جستجو الان کار نمی‌کنه؛ لینک مستقیم فایل رو بفرست یا بعداً امتحان کن.', '😕 Search isn’t working right now; send a direct link or try later.')];
        }

        $cands = [];
        $seen  = [];
        $add   = static function (string $u, string $title) use (&$cands, &$seen): void {
            $k = md5($u);
            if (isset($seen[$k]) || count($cands) >= 12) {
                return;
            }
            $seen[$k] = true;
            $cands[]  = ['url' => $u, 'name' => '', 'size' => 0, 'src' => self::hostOf($u), 'srcUrl' => $u, 'title' => $title];
        };

        if ($images) {
            foreach (array_slice((array) ($res['images'] ?? []), 0, 8) as $im) {
                $u = is_array($im) ? (string) ($im['url'] ?? '') : (string) $im;
                if (preg_match('#^https?://#i', $u)) {
                    $add($u, $q);
                }
            }
        } else {
            $pages = [];
            foreach ((array) ($res['results'] ?? []) as $it) {
                $u = (string) ($it['url'] ?? '');
                if (!preg_match('#^https?://#i', $u)) {
                    continue;
                }
                if (self::looksFile($u, $re)) {
                    $add($u, (string) ($it['title'] ?? ''));
                } else {
                    $pages[] = [$u, (string) ($it['title'] ?? '')];
                }
            }
            foreach (array_slice($pages, 0, 4) as [$pu, $pt]) {
                $r = Http::request('GET', $pu, ['timeout' => 8, 'max_bytes' => 1500000, 'headers' => ['Accept: text/html', 'User-Agent: ' . self::UA]]);
                if ($r['body'] === null || $r['code'] >= 400 || !preg_match_all('/(?:href|src)\s*=\s*["\']([^"\'#<>\s]+)["\']/i', (string) $r['body'], $mm)) {
                    continue;
                }
                foreach ($mm[1] as $lnk) {
                    $abs = self::absUrl((string) ($r['url'] ?: $pu), html_entity_decode($lnk));
                    if ($abs !== null && self::looksFile($abs, $re)) {
                        $add($abs, $pt);
                    }
                }
            }
        }

        if (!$cands) {
            return ['error' => L('😕 لینک مستقیم فایل رو پیدا نکردم؛ اسم دقیق‌تر یا لینک مستقیمش رو بفرست.', '😕 I couldn’t find a direct file link; send a more exact name or the direct link.')];
        }
        if (count($cands) > 1 && !$images) {
            $lines = [];
            foreach ($cands as $i => $c) {
                $lines[] = ($i + 1) . '. ' . mb_substr($c['url'], 0, 160) . ' | ' . mb_substr($c['title'], 0, 60);
            }
            $pick = AI::chat([
                ['role' => 'system', 'content' => 'Pick the one direct file link that best matches what the user asked for: the right file, from an official or trustworthy-looking source, not an ad or a dubious mirror. Reply with its number only.'],
                ['role' => 'user', 'content' => 'Request: ' . $q . ($hint !== '' ? " ({$hint})" : '') . "\n" . implode("\n", $lines)],
            ], ['tier' => 'lite', 'temperature' => 0, 'thinking' => null, 'deadline' => microtime(true) + 10]);
            $n = $pick !== null && preg_match('/\d+/', $pick, $pm) ? (int) $pm[0] - 1 : -1;
            if (isset($cands[$n]) && $n > 0) {
                array_unshift($cands, ...array_splice($cands, $n, 1));
            }
        }
        return ['urls' => $cands];
    }

    private static function looksFile(string $url, ?string $re): bool
    {
        $path = rawurldecode((string) parse_url($url, PHP_URL_PATH));
        if (!preg_match('/\.(' . self::FILE_EXT . ')$/i', $path)) {
            return false;
        }
        return $re === null || preg_match($re, $path) === 1;
    }

    private static function absUrl(string $base, string $href): ?string
    {
        if (preg_match('#^https?://#i', $href)) {
            return $href;
        }
        $p = parse_url($base);
        if (!is_array($p) || empty($p['host']) || empty($p['scheme'])) {
            return null;
        }
        $origin = $p['scheme'] . '://' . $p['host'] . (!empty($p['port']) ? ':' . $p['port'] : '');
        if (str_starts_with($href, '//')) {
            return $p['scheme'] . ':' . $href;
        }
        if (str_starts_with($href, '/')) {
            return $origin . $href;
        }
        if (preg_match('#^[a-z][a-z0-9+.-]*:#i', $href)) {
            return null;
        }
        $dir = preg_replace('#/[^/]*$#', '/', (string) ($p['path'] ?? '/'));
        return $origin . $dir . $href;
    }

    // ───────────────────────── دانلود امن ─────────────────────────

    /** @return array{ip:string,host:string,port:int}|null */
    private static function safeHost(string $url): ?array
    {
        $p = parse_url($url);
        if (!is_array($p) || empty($p['host']) || !in_array(strtolower((string) ($p['scheme'] ?? '')), ['http', 'https'], true) || isset($p['user']) || isset($p['pass'])) {
            return null;
        }
        $host = strtolower((string) $p['host']);
        $port = (int) ($p['port'] ?? (strtolower($p['scheme']) === 'https' ? 443 : 80));
        if (!in_array($port, [80, 443, 8080, 8443], true)) {
            return null;
        }
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $ips = [$host];
        } else {
            if (!str_contains($host, '.') || str_ends_with($host, '.local') || str_ends_with($host, '.internal') || str_ends_with($host, '.localhost')) {
                return null;
            }
            $ips = gethostbynamel($host) ?: [];
        }
        if (!$ips) {
            return null;
        }
        foreach ($ips as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                return null;
            }
            $l = ip2long($ip);
            if ($l !== false && ($l & 0xFFC00000) === 0x64400000) { // 100.64.0.0/10
                return null;
            }
        }
        return ['ip' => $ips[0], 'host' => $host, 'port' => $port];
    }

    private static function fetch(string $url, string $dest, int $max, callable $tick, string $hint): array
    {
        $allowHtml = in_array($hint, ['html', 'htm'], true);
        for ($hop = 0; $hop < 6; $hop++) {
            $sh = self::safeHost($url);
            if ($sh === null) {
                return ['ok' => false, 'err' => 'blocked'];
            }
            $fh = @fopen($dest, 'wb');
            if (!$fh) {
                return ['ok' => false, 'err' => 'net'];
            }
            $status = 0;
            $loc    = '';
            $mime   = '';
            $len    = -1;
            $disp   = '';
            $got    = 0;
            $abort  = '';
            $ch     = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_FOLLOWLOCATION  => false,
                CURLOPT_RESOLVE         => ["{$sh['host']}:{$sh['port']}:{$sh['ip']}"],
                CURLOPT_USERAGENT       => self::UA,
                CURLOPT_HTTPHEADER      => ['Accept: */*'],
                CURLOPT_CONNECTTIMEOUT  => 10,
                CURLOPT_TIMEOUT         => 1500,
                CURLOPT_LOW_SPEED_LIMIT => 2048,
                CURLOPT_LOW_SPEED_TIME  => 45,
                CURLOPT_SSL_VERIFYPEER  => true,
                CURLOPT_PROTOCOLS       => CURLPROTO_HTTP | CURLPROTO_HTTPS,
                CURLOPT_NOPROGRESS      => false,
                CURLOPT_PROGRESSFUNCTION => static function ($c, $dlT, $dlN) use ($tick, &$status): int {
                    if ($status === 200 || $status === 206) {
                        $tick((int) $dlN, (int) $dlT);
                    }
                    return 0;
                },
                CURLOPT_HEADERFUNCTION  => static function ($c, string $line) use (&$status, &$loc, &$mime, &$len, &$disp): int {
                    $t = trim($line);
                    if (preg_match('#^HTTP/\S+\s+(\d{3})#i', $t, $m)) {
                        $status = (int) $m[1];
                        [$loc, $mime, $len, $disp] = ['', '', -1, ''];
                    } elseif (stripos($t, 'location:') === 0) {
                        $loc = trim(substr($t, 9));
                    } elseif (stripos($t, 'content-type:') === 0) {
                        $mime = strtolower(trim(explode(';', substr($t, 13))[0]));
                    } elseif (stripos($t, 'content-length:') === 0) {
                        $len = (int) trim(substr($t, 15));
                    } elseif (stripos($t, 'content-disposition:') === 0) {
                        $disp = trim(substr($t, 20));
                    }
                    return strlen($line);
                },
                CURLOPT_WRITEFUNCTION   => static function ($c, string $data) use ($fh, &$status, &$mime, &$len, &$got, &$abort, $max, $allowHtml): int {
                    if ($status < 200 || $status >= 300) {
                        return strlen($data);
                    }
                    if ($got === 0) {
                        if ($len > $max) {
                            $abort = 'big';
                            return 0;
                        }
                        if (!$allowHtml && ($mime === 'text/html' || $mime === 'application/xhtml+xml' || preg_match('/^\s*<(!doctype\s+html|html|head|body)/i', substr($data, 0, 512)) === 1)) {
                            $abort = 'html';
                            return 0;
                        }
                    }
                    $got += strlen($data);
                    if ($got > $max) {
                        $abort = 'big';
                        return 0;
                    }
                    return (int) fwrite($fh, $data);
                },
            ]);
            curl_exec($ch);
            $errno = curl_errno($ch);
            curl_close($ch);
            fclose($fh);

            if ($abort !== '') {
                return ['ok' => false, 'err' => $abort];
            }
            if ($status >= 300 && $status < 400 && $loc !== '') {
                $next = self::absUrl($url, $loc);
                if ($next === null) {
                    return ['ok' => false, 'err' => 'net'];
                }
                $url = $next;
                continue;
            }
            if ($errno !== 0) {
                return ['ok' => false, 'err' => 'net'];
            }
            if ($status < 200 || $status >= 300) {
                return ['ok' => false, 'err' => 'http'];
            }
            if ($got <= 0) {
                return ['ok' => false, 'err' => 'net'];
            }
            return ['ok' => true, 'mime' => $mime, 'disp' => $disp, 'final' => $url, 'size' => $got];
        }
        return ['ok' => false, 'err' => 'net'];
    }

    private static function fileName(string $given, string $disp, string $finalUrl, string $mime): string
    {
        $name = $given;
        if ($name === '' && $disp !== '') {
            if (preg_match("/filename\\*\\s*=\\s*(?:UTF-8|utf-8)''([^;]+)/", $disp, $m)) {
                $name = rawurldecode(trim($m[1], " \"'"));
            } elseif (preg_match('/filename\s*=\s*"?([^";]+)"?/i', $disp, $m)) {
                $name = trim($m[1]);
            }
        }
        if ($name === '') {
            $name = rawurldecode(basename((string) parse_url($finalUrl, PHP_URL_PATH)));
        }
        $name = (string) preg_replace('/[\x00-\x1F\x7F\\\\\/:*?"<>|]+/u', '_', $name);
        $name = mb_substr(trim($name, " ._"), 0, 100);
        if ($name === '') {
            $name = 'file';
        }
        if (pathinfo($name, PATHINFO_EXTENSION) === '' && isset(self::MIME_EXT[$mime])) {
            $name .= '.' . self::MIME_EXT[$mime];
        }
        return $name;
    }

    // ───────────────────────── ارسال ─────────────────────────

    private static function send(array $job, string $file, string $name, string $caption, int $size): bool
    {
        $tg    = App::tg();
        $reply = !empty($job['reply_to']) ? ['message_id' => (int) $job['reply_to'], 'allow_sending_without_reply' => true] : null;
        $ext   = strtolower(pathinfo($name, PATHINFO_EXTENSION));

        if ($size <= Downloader::BOT_API_BYTES) {
            self::show($job, L('📤 دارم می‌فرستم...', '📤 Sending...'));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true) && $size <= 10 * 1048576) {
                $mime = $ext === 'png' ? 'image/png' : ($ext === 'webp' ? 'image/webp' : 'image/jpeg');
                $r = $tg->call('sendPhoto', [
                    'chat_id'          => $job['chat'],
                    'photo'            => new CURLFile($file, $mime, $name),
                    'caption'          => $caption,
                    'parse_mode'       => 'HTML',
                    'reply_parameters' => $reply,
                ], 180);
                if (!empty($r['ok'])) {
                    return true;
                }
            }
            $r = $tg->call('sendDocument', [
                'chat_id'          => $job['chat'],
                'document'         => new CURLFile($file, 'application/octet-stream', $name),
                'caption'          => $caption,
                'parse_mode'       => 'HTML',
                'reply_parameters' => $reply,
            ], 900);
            if (empty($r['ok'])) {
                logWarn('gf', 'sendDocument failed: ' . mb_substr((string) ($r['description'] ?? ''), 0, 200));
            }
            return !empty($r['ok']);
        }
        return self::sendMt($job, $file, $name, $caption);
    }

    private static function sendMt(array $job, string $file, string $name, string $caption): bool
    {
        if (!Downloader::mtproto()) {
            return false;
        }
        $tg   = App::tg();
        $job2 = [
            'session_file' => NULLINI_DATA . '/mtproto' . (App::botIndex() === 0 ? '' : (string) App::botIndex()) . '.session',
            'api_id'       => (int) (env('API_ID') ?: env('TG_API_ID', 0)),
            'api_hash'     => trim((string) (env('API_HASH') ?: env('TG_API_HASH', ''))),
            'bot_token'    => $tg->token(),
            'test'         => App::botIndex() === 2 ? ['dc' => (int) env('TEST_DC_ID', 2), 'ip' => (string) env('TEST_DC_IP', '149.154.167.40'), 'port' => (int) env('TEST_DC_PORT', 443)] : null,
            'chat'         => (int) $job['chat'],
            'reply_to'     => (int) $job['reply_to'],
            'file'         => $file,
            'name'         => $name,
            'kind'         => 'document',
            'mime'         => null,
            'thumb'        => '',
            'caption'      => $caption,
            'duration'     => 0,
            'width'        => 0,
            'height'       => 0,
            'title'        => '',
            'performer'    => '',
        ];
        $out  = null;
        $last = 0.0;
        $res  = self::runMt($job2, self::jobTime() - 120, static function (string $line) use ($job, &$out, &$last): void {
            $j = json_decode($line, true);
            if (!is_array($j)) {
                return;
            }
            if (isset($j['ok'])) {
                $out = $j;
                return;
            }
            if (isset($j['p'], $j['t']) && microtime(true) - $last >= 3.5) {
                $last = microtime(true);
                $pct  = $j['t'] > 0 ? (int) floor($j['p'] / $j['t'] * 100) : 0;
                self::show($job, '📤 ' . Downloader::bar($pct) . ' ' . fa((string) $pct) . L('٪', '%') . "\n📦 " . Downloader::mb((float) $j['p']) . L(' از ', ' of ') . Downloader::mb((float) $j['t']));
            }
        });
        if (empty($out['ok']) || empty($out['msg_id'])) {
            logError('gf', 'mtproto upload failed (exit ' . $res['code'] . '): ' . (string) ($out['error'] ?? mb_substr($res['err'], -200)));
            return false;
        }
        return true;
    }

    private static function runMt(array $job2, int $timeout, callable $onLine): array
    {
        $cmd  = ['timeout', '-k', '5', (string) $timeout, 'python3', NULLINI_ROOT . '/bin/mtup.py'];
        $env  = ['NL_JOB' => json_encode($job2, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'HOME' => '/tmp', 'PATH' => '/usr/local/bin:/usr/bin:/bin', 'LANG' => 'C.UTF-8'];
        $proc = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, $env);
        if (!is_resource($proc)) {
            return ['code' => -1, 'out' => '', 'err' => 'proc_open failed'];
        }
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
        $err = $buf = '';
        $end = time() + $timeout + 15;
        while (true) {
            $r = [$pipes[1], $pipes[2]];
            $w = $e = null;
            if (@stream_select($r, $w, $e, 1) === false) {
                break;
            }
            foreach ($r as $p) {
                $chunk = (string) fread($p, 65536);
                if ($p === $pipes[1]) {
                    $buf .= $chunk;
                    while (($nl = strpos($buf, "\n")) !== false) {
                        $onLine(rtrim(substr($buf, 0, $nl), "\r"));
                        $buf = substr($buf, $nl + 1);
                    }
                } else {
                    $err = substr($err . $chunk, -65536);
                }
            }
            if (feof($pipes[1]) && feof($pipes[2])) {
                break;
            }
            if (time() > $end) {
                proc_terminate($proc, 9);
                break;
            }
        }
        fclose($pipes[1]);
        fclose($pipes[2]);
        return ['code' => proc_close($proc), 'out' => '', 'err' => $err];
    }

    // ───────────────────────── رابط ─────────────────────────

    private static function askChoice(array $job, string $token, array $cands): void
    {
        $rows = [];
        foreach ($cands as $i => $c) {
            $label = '📦 ' . mb_substr((string) $c['name'], 0, 40) . (!empty($c['size']) ? ' · ' . Downloader::mb((float) $c['size']) : '');
            $rows[] = [btn($label, "gf:{$token}:{$i}", $i === 0 ? 'success' : 'primary')];
        }
        $rows[] = [btn(L('✖️ بیخیال', '✖️ Cancel'), "gf:{$token}:x", null)];
        self::show($job, L("📥 چند تا فایل پیدا کردم؛ کدوم رو بفرستم؟\n🔗 ", "📥 I found several files; which one should I send?\n🔗 ") . h((string) ($cands[0]['src'] ?? '')), ikb($rows));
    }

    private static function show(array $job, string $html, ?array $kb = null): void
    {
        Rich::editHtml($job['chat'], (int) $job['mid'], $html, $kb);
    }

    private static function fail(array $job, string $html): void
    {
        self::show($job, $html);
        if (!empty($job['owner'])) {
            Store::delete('gf_busy', (string) $job['owner']);
        }
    }

    private static function errText(string $err): string
    {
        return match ($err) {
            'html'    => L('😕 این لینک یه صفحه‌ی وبه، نه خود فایل؛ لینک مستقیم فایل رو بفرست.', '😕 That link is a web page, not the file itself; send the direct file link.'),
            'big'     => L('📦 حجم فایل از سقف مجاز (', '📦 The file is over the size limit (') . self::limitText() . L(') بیشتره.', ').'),
            'blocked' => L('🚫 این آدرس مجاز نیست.', '🚫 This address isn’t allowed.'),
            'http'    => L('😕 سرور فایل اجازه‌ی دانلود نداد یا فایل پیدا نشد.', '😕 The file server refused the download or the file wasn’t found.'),
            default   => L('😕 دانلود نشد؛ سرور فایل جواب نداد.', '😕 The download failed; the file server didn’t respond.'),
        };
    }

    private static function caption(array $c, string $name, int $size, bool $exec): string
    {
        $t   = '📥 <b>' . h($name) . '</b> · ' . Downloader::mb((float) $size);
        $url = (string) ($c['srcUrl'] ?? '');
        if ($url !== '' && strlen($url) <= 400) {
            $label = (string) ($c['src'] ?? '');
            $t .= "\n🔗 " . L('منبع: ', 'Source: ') . '<a href="' . h($url) . '">' . h($label !== '' ? $label : self::hostOf($url)) . '</a>';
        }
        if ($exec) {
            $t .= "\n⚠️ " . L('فایل نصبی/فشرده است؛ فقط اگه به منبعش اعتماد داری بازش کن.', 'Installer/archive file — open it only if you trust the source.');
        }
        return $t;
    }

    // ───────────────────────── کمکی‌ها ─────────────────────────

    private static function parseArg(string $arg): array
    {
        $p    = array_map('trim', explode('|', $arg));
        $ver  = ltrim(trim((string) ($p[2] ?? '')), 'vV');
        $ver  = preg_match('/^[A-Za-z0-9._-]{1,30}$/', $ver) ? $ver : '';
        return ['q' => (string) ($p[0] ?? ''), 'hint' => self::canonHint((string) ($p[1] ?? '')), 'ver' => $ver];
    }

    private static function canonHint(string $h): string
    {
        $h = strtolower(trim($h, " .\t"));
        return match (true) {
            in_array($h, ['apk', 'android', 'اندروید', 'آندروید'], true)                  => 'apk',
            in_array($h, ['windows', 'win', 'ویندوز'], true)                              => 'win',
            in_array($h, ['mac', 'macos', 'osx', 'مک'], true)                             => 'mac',
            in_array($h, ['linux', 'لینوکس'], true)                                       => 'linux',
            in_array($h, ['ios', 'iphone', 'آیفون', 'ایفون'], true)                      => 'ios',
            in_array($h, ['image', 'photo', 'picture', 'img', 'عکس', 'تصویر'], true)     => 'image',
            in_array($h, ['source', 'src', 'repo', 'repository', 'code', 'سورس', 'ریپو'], true) => 'source',
            preg_match('/^[a-z0-9]{2,10}$/', $h) === 1                                    => $h,
            default                                                                       => '',
        };
    }

    private static function hostOf(string $url): string
    {
        return (string) (parse_url($url, PHP_URL_HOST) ?: $url);
    }

    private static function maxBytes(): int
    {
        $mb = max(5, (int) env('GF_MAX_MB', 200));
        return min(Downloader::maxBytes(), $mb * 1048576);
    }

    private static function limitText(): string
    {
        return Downloader::mb((float) self::maxBytes());
    }

    private static function jobTime(): int
    {
        return Downloader::mtproto() ? 3600 : 1200;
    }

    private static function quotaKey(int $uid): string
    {
        return $uid . ':' . date('Ymd');
    }

    private static function used(int $uid): int
    {
        return (int) Store::get('gf_quota', self::quotaKey($uid), 0);
    }

    private static function spawn(string $token, int $pick): bool
    {
        if (!function_exists('exec') || in_array('exec', array_map('trim', explode(',', (string) ini_get('disable_functions'))), true)) {
            return false;
        }
        $cmd = 'timeout -k 10 ' . self::jobTime() . ' ' . escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(NULLINI_ROOT . '/bot.php')
            . ' --getfile ' . escapeshellarg($token) . ' ' . (int) $pick . ' ' . App::botIndex() . ' > /dev/null 2>&1 &';
        exec($cmd, $o, $code);
        return $code === 0;
    }

    private static function rmrf(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach ((array) glob($dir . '/{,.}[!.]*', GLOB_BRACE) as $f) {
            is_dir($f) ? self::rmrf($f) : @unlink($f);
        }
        @rmdir($dir);
    }

    public static function cleanupTmp(): void
    {
        foreach ((array) glob(self::TMP . '/*') as $f) {
            if (is_dir($f) && time() - (int) @filemtime($f) > self::jobTime() + 60) {
                self::rmrf($f);
            }
        }
    }
}
