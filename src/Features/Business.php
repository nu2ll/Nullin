<?php

final class Business
{
    private const HISTORY = 6;
    private const TYPING_CPS = 18;
    private const TYPING_MIN = 1.2;
    private const TYPING_MAX = 9.0;
    private const TYPING_REFRESH = 4.0;
    private const OWNER_MIN_PAUSE = 1;
    private const CHAT_URL = 'https://api.groq.com/openai/v1/chat/completions';
    private const STT_URL = 'https://api.groq.com/openai/v1/audio/transcriptions';
    private const WHISPER = 'whisper-large-v3-turbo';
    private const RETRY = [0, 401, 402, 403, 408, 429, 500, 502, 503, 504];
    private const VISION = 'qwen/qwen3.8-27b';

    private static function modes(): array
    {
        $m = ['instant' => ['⚡ فوری', 0]];
        for ($i = 5; $i <= 60; $i += 5) {
            $m[(string) $i] = [$i === 60 ? '⏱ ۱ ساعت' : '⏱ ' . fa((string) $i) . ' دقیقه', $i];
        }
        $m['forever'] = ['♾ دائمی', null];
        return $m;
    }

    public static function cfg(int $uid): array
    {
        $c   = Store::get('biz_cfg', (string) $uid);
        $cfg = array_merge(['on' => true, 'keys' => [], 'prompt' => '', 'model' => '', 'pause' => '20', 'del' => false, 'edit' => false, 'think' => 'auto', 'hrs' => false, 'hf' => 0, 'ht' => 10], is_array($c) ? $c : []);
        $cfg['keys'] = self::keyList($cfg);
        return $cfg;
    }

    private static function keyList(array $c): array
    {
        $keys = array_merge((array) ($c['keys'] ?? []), [(string) ($c['key'] ?? '')]);
        return array_values(array_unique(array_filter(array_map('trim', $keys), static fn($k) => $k !== '')));
    }

    private static function rotate(array $keys): array
    {
        if (count($keys) < 2) {
            return $keys;
        }
        $s = random_int(0, count($keys) - 1);
        return array_merge(array_slice($keys, $s), array_slice($keys, 0, $s));
    }

    private static function save(int $uid, array $patch): void
    {
        Store::update('biz_cfg', (string) $uid, static fn($c) => array_merge(is_array($c) ? $c : [], $patch));
    }

    public static function clear(int $uid): void
    {
        Store::delete('biz_wait', (string) $uid);
    }

    public static function onConnection(array $c): void
    {
        $id  = (string) ($c['id'] ?? '');
        $uid = (int) ($c['user']['id'] ?? 0);
        if ($id === '' || !$uid) {
            return;
        }
        $first = Store::get('biz_conn', $id) === null;
        $on    = !empty($c['is_enabled']);
        Store::set('biz_conn', $id, [
            'owner' => $uid,
            'chat'  => (int) ($c['user_chat_id'] ?? $uid),
            'on'    => $on,
            'user'  => array_intersect_key((array) $c['user'], array_flip(['id', 'first_name', 'last_name', 'username'])),
        ]);
        if ($on && $first && Switches::on('business')) {
            App::tg()->send((int) ($c['user_chat_id'] ?? $uid), "🎉 به اکانتت وصل شدم!\nبرای شروع، کلید گروکت رو ثبت کن.", ['reply_markup' => ikb([[btn('⚙️ پنل منشی', 'bz:menu', 'success')]])]);
        }
    }

    private static function owner(string $id): ?array
    {
        $c = Store::get('biz_conn', $id);
        if (is_array($c)) {
            return $c;
        }
        $r = App::tg()->call('getBusinessConnection', ['business_connection_id' => $id], 8);
        $u = $r['result'] ?? null;
        if (empty($r['ok']) || !is_array($u) || empty($u['user']['id'])) {
            return null;
        }
        $c = [
            'owner' => (int) $u['user']['id'],
            'chat'  => (int) ($u['user_chat_id'] ?? $u['user']['id']),
            'on'    => !empty($u['is_enabled']),
            'user'  => array_intersect_key($u['user'], array_flip(['id', 'first_name', 'last_name', 'username'])),
        ];
        Store::set('biz_conn', $id, $c);
        return $c;
    }

    private static function kind(array $m): ?string
    {
        return match (true) {
            isset($m['animation'])                 => '🎞 گیف',
            isset($m['video_note'])                => '⭕ ویدیو مسیج',
            isset($m['video'])                     => '🎬 ویدیو',
            isset($m['voice'])                     => '🎤 پیام صوتی',
            isset($m['audio'])                     => '🎵 آهنگ',
            isset($m['photo'])                     => '🖼 عکس',
            isset($m['sticker'])                   => '🏷 استیکر',
            isset($m['document'])                  => '📎 فایل',
            isset($m['paid_media'])                => '⭐ مدیای پولی',
            isset($m['story'])                     => '📖 استوری',
            isset($m['contact'])                   => '👤 مخاطب',
            isset($m['venue'])                     => '📍 مکان',
            isset($m['location'])                  => '🗺 موقعیت',
            isset($m['poll'])                      => '📊 نظرسنجی',
            isset($m['checklist'])                 => '☑️ چک‌لیست',
            isset($m['dice'])                      => '🎲 تاس',
            isset($m['game'])                      => '🎮 بازی',
            isset($m['invoice'])                   => '🧾 فاکتور',
            isset($m['giveaway']), isset($m['giveaway_winners']) => '🎁 قرعه‌کشی',
            isset($m['gift']), isset($m['unique_gift']) => '🎁 هدیه',
            isset($m['web_app_data'])              => '🧩 وب‌اپ',
            default                                => null,
        };
    }

    private static function detail(array $m): string
    {
        $c = '';
        if (isset($m['sticker'])) {
            $c = (string) ($m['sticker']['emoji'] ?? '');
        } elseif (isset($m['dice'])) {
            $c = ($m['dice']['emoji'] ?? '') . ' ' . ($m['dice']['value'] ?? '');
        } elseif (isset($m['contact'])) {
            $c = trim(($m['contact']['first_name'] ?? '') . ' ' . ($m['contact']['last_name'] ?? '')) . ' ' . ($m['contact']['phone_number'] ?? '');
        } elseif (isset($m['venue'])) {
            $c = trim(($m['venue']['title'] ?? '') . ' - ' . ($m['venue']['address'] ?? ''), ' -');
        } elseif (isset($m['location'])) {
            $c = ($m['location']['latitude'] ?? '') . ', ' . ($m['location']['longitude'] ?? '');
        } elseif (isset($m['poll'])) {
            $opts = array_map(static fn($o) => (string) ($o['text'] ?? ''), (array) ($m['poll']['options'] ?? []));
            $c    = (string) ($m['poll']['question'] ?? '') . ($opts ? ' [' . implode(' | ', $opts) . ']' : '');
        } elseif (isset($m['checklist'])) {
            $tasks = array_map(static fn($t) => (string) ($t['text'] ?? ''), (array) ($m['checklist']['tasks'] ?? []));
            $c     = (string) ($m['checklist']['title'] ?? '') . ($tasks ? ' [' . implode(' | ', $tasks) . ']' : '');
        } elseif (isset($m['game'])) {
            $c = (string) ($m['game']['title'] ?? '');
        } elseif (isset($m['invoice'])) {
            $c = (string) ($m['invoice']['title'] ?? '');
        } elseif (isset($m['document']) && !empty($m['document']['file_name'])) {
            $c = (string) $m['document']['file_name'];
        } elseif (isset($m['audio'])) {
            $c = trim(($m['audio']['performer'] ?? '') . ' - ' . ($m['audio']['title'] ?? ''), ' -');
        }
        return trim($c);
    }

    private static function digest(array $m): string
    {
        $text   = trim((string) ($m['text'] ?? $m['caption'] ?? ''));
        $kind   = self::kind($m);
        if ($kind === null) {
            return $text;
        }
        $detail = self::detail($m);
        $parts  = array_filter([$detail, $text], static fn($x) => $x !== '');
        return trim($kind . ($parts ? ': ' . implode(' — ', $parts) : ''));
    }

    private static function mediaRef(array $m): ?array
    {
        foreach ([
            'animation'  => 'sendAnimation',
            'sticker'    => 'sendSticker',
            'video_note' => 'sendVideoNote',
            'video'      => 'sendVideo',
            'voice'      => 'sendVoice',
            'audio'      => 'sendAudio',
            'document'   => 'sendDocument',
        ] as $field => $method) {
            if (!empty($m[$field]['file_id'])) {
                return ['method' => $method, 'field' => $field, 'id' => (string) $m[$field]['file_id']];
            }
        }
        if (!empty($m['contact']['phone_number'])) {
            $c = $m['contact'];
            return ['method' => 'sendContact', 'params' => array_filter([
                'phone_number' => (string) $c['phone_number'],
                'first_name'   => (string) ($c['first_name'] ?? '') !== '' ? (string) $c['first_name'] : 'Contact',
                'last_name'    => $c['last_name'] ?? null,
                'vcard'        => $c['vcard'] ?? null,
            ], static fn($v) => $v !== null && $v !== '')];
        }
        if (isset($m['venue']['location']['latitude'])) {
            $v = $m['venue'];
            return ['method' => 'sendVenue', 'params' => array_filter([
                'latitude'  => $v['location']['latitude'],
                'longitude' => $v['location']['longitude'],
                'title'     => (string) ($v['title'] ?? '') !== '' ? (string) $v['title'] : 'Venue',
                'address'   => (string) ($v['address'] ?? '') !== '' ? (string) $v['address'] : '-',
            ], static fn($x) => $x !== null && $x !== '')];
        }
        if (isset($m['location']['latitude'], $m['location']['longitude'])) {
            return ['method' => 'sendLocation', 'params' => [
                'latitude'  => $m['location']['latitude'],
                'longitude' => $m['location']['longitude'],
            ]];
        }
        if (!empty($m['photo']) && is_array($m['photo'])) {
            $last = end($m['photo']);
            if (!empty($last['file_id'])) {
                return ['method' => 'sendPhoto', 'field' => 'photo', 'id' => (string) $last['file_id']];
            }
        }
        return null;
    }

    private static function who(string $name, int $uid, string $un): string
    {
        return "\u{200F}👤 نام: <a href=\"tg://user?id={$uid}\">" . bidi(h($name)) . "</a>\n"
            . "\u{200F}🆔 آیدی: <code>{$uid}</code>\n"
            . "\u{200F}🔗 یوزرنیم: " . ($un !== '' ? bidi('@' . h($un)) : 'ندارد');
    }

    private static function note(string $cid, array $msg): void
    {
        $d = self::digest($msg);
        if ($d === '') {
            return;
        }
        $row = [
            'text' => mb_substr($d, 0, 1500),
            'name' => userName((array) ($msg['from'] ?? [])),
            'uid'  => (int) ($msg['from']['id'] ?? 0),
            'un'   => (string) ($msg['from']['username'] ?? ''),
        ];
        if (($ref = self::mediaRef($msg)) !== null) {
            $row['media'] = $ref;
            $row['cap']   = mb_substr(trim((string) ($msg['caption'] ?? '')), 0, 500);
        }
        Store::set('biz_msgs', $cid . '_' . $msg['chat']['id'] . '_' . $msg['message_id'], $row);
    }

    private static function pause(int $owner, int $chat, string $reason): void
    {
        Store::set('biz_pause', "{$owner}_{$chat}", ['at' => time(), 'reason' => $reason]);
    }

    private static function paused(int $owner, int $chat, string $mode): bool
    {
        $r = Store::get('biz_pause', "{$owner}_{$chat}");
        if (!is_array($r)) {
            return false;
        }
        $modes = self::modes();
        $min   = isset($modes[$mode]) ? $modes[$mode][1] : 30;
        if ($min === 0 && ($r['reason'] ?? '') === 'owner') {
            $min = self::OWNER_MIN_PAUSE;
        }
        if ($min === 0) {
            return false;
        }
        if ($min === null) {
            return true;
        }
        return time() - (int) ($r['at'] ?? 0) < $min * 60;
    }

    private static function bio(int $id): string
    {
        $v = Store::remember('cache', 'bizbio_' . $id, 3600, static function () use ($id) {
            $r = App::tg()->call('getChat', ['chat_id' => $id], 8);
            return !empty($r['ok']) ? (string) ($r['result']['bio'] ?? '') : null;
        });
        return is_string($v) ? $v : '';
    }

    private static function person(string $label, array $u, string $bio): string
    {
        return implode(' | ', [
            "{$label}:",
            'نام: ' . userName($u),
            !empty($u['username']) ? 'یوزرنیم: @' . $u['username'] : 'یوزرنیم: ندارد',
            'آیدی عددی: ' . (int) ($u['id'] ?? 0),
            'بیو: ' . ($bio !== '' ? $bio : 'در دسترس نیست'),
        ]);
    }

    private static function typing(string $cid, int $chat, string $reply): void
    {
        $d = mb_strlen($reply) / self::TYPING_CPS + mt_rand(-40, 60) / 100;
        $d = max(self::TYPING_MIN, min(self::TYPING_MAX, $d));
        while ($d > 0) {
            $r = App::tg()->call('sendChatAction', ['chat_id' => $chat, 'action' => 'typing', 'business_connection_id' => $cid], 5);
            if (empty($r['ok'])) {
                break;
            }
            $s = min(self::TYPING_REFRESH, $d);
            usleep((int) ($s * 1000000));
            $d -= $s;
        }
    }

    public static function onMessage(array $msg): void
    {
        $cid = (string) ($msg['business_connection_id'] ?? '');
        if ($cid === '' || !Switches::on('business') || !empty($msg['from']['is_bot'])) {
            return;
        }
        $conn = self::owner($cid);
        if ($conn === null || empty($conn['on'])) {
            return;
        }
        $owner  = (int) $conn['owner'];
        $chat   = (int) $msg['chat']['id'];
        $fromId = (int) ($msg['from']['id'] ?? 0);
        $cfg    = self::cfg($owner);

        if ($fromId === $owner) {
            self::pause($owner, $chat, 'owner');
            return;
        }
        if ($cfg['del'] || $cfg['edit']) {
            self::note($cid, $msg);
        }
        if (!$cfg['on'] || (!isset($msg['text']) && !Assistant::hasMedia($msg))) {
            return;
        }
        if (!self::inHours($cfg)) {
            return;
        }
        if (self::paused($owner, $chat, (string) $cfg['pause'])) {
            return;
        }

        if (!$cfg['keys']) {
            return;
        }

        $tg = App::tg();
        $tg->call('readBusinessMessage', ['business_connection_id' => $cid, 'chat_id' => $chat, 'message_id' => $msg['message_id']], 8);
        $tg->call('sendChatAction', ['chat_id' => $chat, 'action' => 'typing', 'business_connection_id' => $cid], 5);

        $system = ($cfg['prompt'] !== '' ? $cfg['prompt'] . "\n\n" : '')
            . "[اطلاعات پس‌زمینه — صرفاً برای context، مستقیم تکرارش نکن]\n"
            . self::person('اطلاعات صاحب حساب کاربری', (array) ($conn['user'] ?? ['id' => $owner]), self::bio($owner)) . "\n"
            . self::person('اطلاعات کاربر در حال گفتگو', (array) ($msg['from'] ?? []), self::bio($chat));

        $key      = "{$owner}_{$chat}";
        $reply    = null;
        $keys     = self::rotate($cfg['keys']);
        $deadline = microtime(true) + 60;
        $built    = self::build($msg, $keys, $deadline);
        $content  = $built['content'] ?? null;
        $saved    = $built['saved'] ?? '';
        $model    = !empty($built['vision']) ? self::visionModel($cfg['model']) : self::model($cfg['model']);

        if ($content !== null) {
            $messages = [['role' => 'system', 'content' => $system]];
            foreach (self::history($key) as $h) {
                $messages[] = $h;
            }
            $messages[] = ['role' => 'user', 'content' => $content];
            $text       = self::attempt($keys, $deadline, fn(string $k, int $t): array => self::groq($k, $model, $messages, (string) $cfg['think'], $t));
            if ($text !== null) {
                $text = AI::clean($text);
                if ($text !== '') {
                    $reply = $text;
                    self::remember($key, $saved, $reply);
                    self::pause($owner, $chat, 'bot');
                }
            }
        }

        $reply ??= 'الان مشکلی پیش اومد، بعداً دوباره پیام بدید.';
        self::typing($cid, $chat, $reply);
        $tg->call('sendMessage', [
            'chat_id'                => $chat,
            'business_connection_id' => $cid,
            'text'                   => $reply,
            'reply_parameters'       => ['message_id' => $msg['message_id'], 'allow_sending_without_reply' => true],
        ]);
    }

    private static function build(array $msg, array $keys, float $deadline): ?array
    {
        $question = trim((string) ($msg['text'] ?? $msg['caption'] ?? ''));
        $notes    = [];
        $parts    = [];
        $vision   = false;

        if (Assistant::hasMedia($msg)) {
            $kind = Assistant::kind($msg);
            $data = Assistant::dataInfo($msg);
            $info = Assistant::fileInfo($msg);
            if (isset($msg['voice'])) {
                $a     = $msg['voice'];
                $bytes = ($a['file_size'] ?? 0) <= 20000000 ? App::tg()->download((string) $a['file_id']) : null;
                $tr    = $bytes ? self::stt($keys, $bytes, (string) ($a['mime_type'] ?? 'audio/ogg'), $deadline) : null;
                if ($tr === null) {
                    return null;
                }
                $notes[]  = '[مشتری ویس فرستاده؛ متن ویسش همین پایینه]';
                $question = $tr;
            } elseif ($data !== null) {
                $notes[] = "[مشتری یه {$data[0]} فرستاده؛ {$data[1]}]";
            } elseif ($info !== null) {
                $notes[] = "[مشتری یه فایل فرستاده؛ {$info}]";
            } else {
                $media = Assistant::mediaPart($msg);
                $emoji = (string) ($msg['sticker']['emoji'] ?? '');
                if ($media) {
                    $parts[]  = ['type' => 'image_url', 'image_url' => ['url' => 'data:' . $media['mime'] . ';base64,' . $media['data']]];
                    $vision   = true;
                    $notes[]  = '[مشتری ' . $kind . ' فرستاده؛ ' . (Assistant::isFrame($msg) ? 'فقط عکسِ فریم اولش پیوسته' : 'عکسش پیوسته') . ($emoji !== '' ? "؛ ایموجی استیکر: {$emoji}" : '') . '، ببینش و جواب بده]';
                } else {
                    $notes[] = '[مشتری ' . $kind . ' فرستاده ولی محتواش قابل دیدن نیست' . ($emoji !== '' ? "؛ ایموجی استیکر: {$emoji}" : '') . ']';
                }
            }
            $notes[0] = Assistant::withAlbum($notes[0], $msg);
        }

        $text = trim(implode("\n", $notes) . ($question !== '' ? "\n\n{$question}" : ''));
        if ($text === '') {
            return null;
        }
        return [
            'content' => $parts ? array_merge([['type' => 'text', 'text' => $text]], $parts) : $text,
            'saved'   => $text,
            'vision'  => $vision,
        ];
    }

    private static function effort(string $model, string $lv): ?string
    {
        if (!isset(AI::THINK_LEVELS[$lv]) || $lv === 'auto') {
            return null;
        }
        if (preg_match('/gpt-oss/i', $model) === 1) {
            return $lv === 'min' ? 'low' : $lv;
        }
        if (preg_match('/qwen/i', $model) === 1) {
            return $lv === 'min' ? 'none' : $lv;
        }
        return null;
    }

    private static function attempt(array $keys, float $deadline, callable $call): ?string
    {
        foreach ($keys as $k) {
            $left = $deadline - microtime(true);
            if ($left < 2) {
                return null;
            }
            $r = $call($k, (int) min($left, 55));
            AI::noteKey('groq', $k, $r['code']);
            if ($r['text'] !== null) {
                return $r['text'];
            }
            logWarn('biz', 'key…' . substr($k, -4) . " HTTP {$r['code']}: " . $r['err']);
            if (!in_array($r['code'], self::RETRY, true)) {
                return null;
            }
        }
        return null;
    }

    private static function groq(string $key, string $model, array $messages, string $think, int $timeout): array
    {
        $payload = ['model' => $model, 'messages' => $messages];
        if (($effort = self::effort($model, $think)) !== null) {
            $payload['reasoning_effort'] = $effort;
        }
        if (AI::searchOn() && preg_match('/gpt-oss/i', $model) === 1 && !str_contains($model, 'safeguard')) {
            $payload['tools'] = [['type' => 'browser_search']];
        }
        $merged = false;
        for ($try = 0; $try < 4; $try++) {
            $r   = Http::postJson(self::CHAT_URL, $payload, ['Authorization: Bearer ' . $key], $timeout);
            $raw = (string) ($r['raw'] ?? '');
            if ($r['code'] === 400 && isset($payload['reasoning_effort']) && stripos($raw, 'reasoning') !== false) {
                unset($payload['reasoning_effort']);
                continue;
            }
            if (isset($payload['tools']) && in_array($r['code'], [400, 429], true) && preg_match('/tool|search|browser|function/i', $raw) === 1) {
                unset($payload['tools']);
                continue;
            }
            if ($r['code'] === 400 && !$merged && preg_match('/instruction|system/i', $raw) === 1) {
                $payload['messages'] = FreeAI::mergeSystem($payload['messages']);
                $merged              = true;
                continue;
            }
            break;
        }
        $t = $r['code'] === 200 ? ($r['data']['choices'][0]['message']['content'] ?? null) : null;
        return [
            'text' => is_string($t) ? $t : null,
            'code' => (int) $r['code'],
            'err'  => mb_substr((string) ($r['data']['error']['message'] ?? $r['raw'] ?? ''), 0, 160),
        ];
    }

    private static function stt(array $keys, string $bytes, string $mime, float $deadline): ?string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'nbz');
        if ($tmp === false) {
            return null;
        }
        $file = $tmp . '.ogg';
        @rename($tmp, $file);
        if (@file_put_contents($file, $bytes) === false) {
            @unlink($file);
            return null;
        }
        $model = (string) (array_values(array_filter((array) (App::cfg('ai', [])['free_stt']['groq'] ?? [])))[0] ?? self::WHISPER);
        $text  = self::attempt($keys, $deadline, static function (string $key, int $timeout) use ($file, $mime, $model): array {
            $r = Http::request('POST', self::STT_URL, [
                'headers' => ['Authorization: Bearer ' . $key],
                'body'    => ['file' => new CURLFile($file, $mime, 'audio.ogg'), 'model' => $model, 'response_format' => 'json'],
                'timeout' => min(40, $timeout),
            ]);
            $j = $r['body'] !== null ? json_decode($r['body'], true) : null;
            $t = $r['code'] === 200 && is_array($j) ? trim((string) ($j['text'] ?? '')) : '';
            return [
                'text' => $t !== '' ? $t : null,
                'code' => (int) $r['code'],
                'err'  => mb_substr((string) ($r['body'] ?? $r['error'] ?? ''), 0, 160),
            ];
        });
        @unlink($file);
        return $text;
    }

    private static function history(string $key): array
    {
        $h = Store::get('biz_hist', $key);
        return is_array($h) ? $h : [];
    }

    private static function remember(string $key, string $user, string $assistant): void
    {
        Store::update('biz_hist', $key, static function ($h) use ($user, $assistant) {
            $h   = is_array($h) ? $h : [];
            $h[] = ['role' => 'user', 'content' => mb_substr($user, 0, 2000)];
            $h[] = ['role' => 'assistant', 'content' => mb_substr($assistant, 0, 2000)];
            return array_slice($h, -self::HISTORY);
        });
    }

    public static function onEdited(array $msg): void
    {
        $cid = (string) ($msg['business_connection_id'] ?? '');
        if ($cid === '' || !Switches::on('business')) {
            return;
        }
        $conn = self::owner($cid);
        if ($conn === null || empty($conn['on']) || (int) ($msg['from']['id'] ?? 0) === (int) $conn['owner']) {
            return;
        }
        $cfg = self::cfg((int) $conn['owner']);
        if (!$cfg['edit']) {
            $cfg['del'] && self::note($cid, $msg);
            return;
        }
        $key = $cid . '_' . $msg['chat']['id'] . '_' . $msg['message_id'];
        $old = Store::get('biz_msgs', $key);
        $new = self::digest($msg);
        self::note($cid, $msg);
        if (!is_array($old) || $new === '' || $old['text'] === mb_substr($new, 0, 1500)) {
            return;
        }
        $f = (array) ($msg['from'] ?? []);
        App::tg()->send((int) $conn['chat'], "\u{200F}✏️ <b>پیام ویرایش‌شده</b>\n\n" . self::who(userName($f), (int) ($f['id'] ?? 0), (string) ($f['username'] ?? '')) . "\n\n\u{200F}قبل:\n<blockquote>" . h($old['text']) . "</blockquote>\n\u{200F}بعد:\n<blockquote>" . h(mb_substr($new, 0, 1500)) . '</blockquote>');
    }

    public static function onDeleted(array $d): void
    {
        $cid = (string) ($d['business_connection_id'] ?? '');
        if ($cid === '' || !Switches::on('business')) {
            return;
        }
        $conn = self::owner($cid);
        if ($conn === null || empty($conn['on']) || !self::cfg((int) $conn['owner'])['del']) {
            return;
        }
        $tg    = App::tg();
        $chat  = (int) $conn['chat'];
        $lines = [];
        $sent  = 0;
        foreach ((array) ($d['message_ids'] ?? []) as $id) {
            $key = $cid . '_' . ($d['chat']['id'] ?? 0) . '_' . $id;
            $m   = Store::get('biz_msgs', $key);
            if (!is_array($m)) {
                continue;
            }
            Store::delete('biz_msgs', $key);
            if ((int) $m['uid'] === (int) $conn['owner']) {
                continue;
            }
            $media = $m['media'] ?? null;
            if (is_array($media) && isset($media['params'])) {
                if ($sent++ >= 10) {
                    continue;
                }
                $head = "🗑 <b>پیام پاک‌شده</b>\n\n" . self::who((string) $m['name'], (int) $m['uid'], (string) ($m['un'] ?? ''));
                $tg->send($chat, $head);
                $r = $tg->call($media['method'], ['chat_id' => $chat] + $media['params'], 20);
                if (empty($r['ok'])) {
                    $tg->send($chat, '<blockquote>' . h($m['text']) . '</blockquote>');
                }
                continue;
            }
            if (!is_array($media) || empty($media['id'])) {
                $lines[] = self::who((string) $m['name'], (int) $m['uid'], (string) ($m['un'] ?? '')) . "\n<blockquote>" . h($m['text']) . '</blockquote>';
                continue;
            }
            if ($sent++ >= 10) {
                continue;
            }
            $head    = "🗑 <b>پیام پاک‌شده</b>\n\n" . self::who((string) $m['name'], (int) $m['uid'], (string) ($m['un'] ?? ''));
            $cap     = (string) ($m['cap'] ?? '');
            $capable = !in_array($media['method'], ['sendSticker', 'sendVideoNote'], true);
            $caption = $head . ($cap !== '' ? "\n<blockquote>" . h($cap) . '</blockquote>' : '');
            if (!$capable) {
                $tg->send($chat, $head);
            }
            $params = ['chat_id' => $chat, $media['field'] => $media['id']];
            if ($capable) {
                $params += ['caption' => $caption, 'parse_mode' => 'HTML'];
            }
            $r = $tg->call($media['method'], $params, 20);
            if (empty($r['ok'])) {
                $tg->send($chat, $head . "\n<blockquote>" . h($m['text']) . '</blockquote>');
            }
        }
        if ($lines) {
            $tg->send($chat, "🗑 <b>پیام پاک‌شده</b>\n\n" . implode("\n\n", array_slice($lines, 0, 10)));
        }
    }

    private static function model(string $saved): string
    {
        $ids = array_column(FreeAI::models('groq'), 'id');
        return in_array($saved, $ids, true) ? $saved : (string) ($ids[0] ?? $saved);
    }

    private static function visionModel(string $saved): string
    {
        $cur = self::model($saved);
        if (FreeAI::sees($cur)) {
            return $cur;
        }
        foreach (array_column(FreeAI::models('groq'), 'id') as $id) {
            if (FreeAI::sees($id)) {
                return $id;
            }
        }
        return self::VISION;
    }

    private static function hourLabel(int $h): string
    {
        return fa(sprintf('%02d:00', $h));
    }

    private static function inHours(array $c): bool
    {
        if (empty($c['hrs'])) {
            return true;
        }
        $h = (int) date('G');
        $f = (int) $c['hf'];
        $t = (int) $c['ht'];
        if ($f === $t) {
            return true;
        }
        return $f < $t ? ($h >= $f && $h < $t) : ($h >= $f || $h < $t);
    }

    private static function hoursMenu(int $uid): array
    {
        $c    = self::cfg($uid);
        $text = "<b>🕐 ساعت فعالیت</b>\nمنشی فقط تو این بازه جواب می‌ده؛\nبیرون از بازه ساکت می‌مونه.\n\n"
            . '🔌 وضعیت: <b>' . ($c['hrs'] ? 'روشن' : 'خاموش (همیشه فعال)') . "</b>\n"
            . '🌅 شروع: <b>' . self::hourLabel((int) $c['hf']) . "</b>\n"
            . '🌙 پایان: <b>' . self::hourLabel((int) $c['ht']) . "</b>\n\n"
            . "💡 ساعت‌ها به وقت تهرانه.\nمثلاً از ۲۲:۰۰ تا ۰۸:۰۰ یعنی شبانه.";
        $kb = ikb([
            [btn($c['hrs'] ? '🕐 ساعت فعالیت: روشن' : '🕐 ساعت فعالیت: خاموش', 'bz:hr:t', $c['hrs'] ? 'success' : 'danger')],
            [btn('🌅 شروع: ' . self::hourLabel((int) $c['hf']), 'bz:hr:f', null), btn('🌙 پایان: ' . self::hourLabel((int) $c['ht']), 'bz:hr:e', null)],
            [btn('🔙 برگشت', 'bz:menu', null)],
        ]);
        return [$text, $kb];
    }

    public static function panel(int $uid): array
    {
        $c     = self::cfg($uid);
        $modes = self::modes();
        $text  = "<b>🤝 منشی هوشمند</b>\n"
            . "به پیام‌های پیوی اکانتت\n"
            . "با هوش مصنوعی جواب می‌دم؛ به هر نوع پیامی.\n\n"
            . '🔌 وضعیت: <b>' . ($c['on'] ? 'روشن' : 'خاموش') . "</b>\n"
            . '🔑 کلید گروک: <b>' . ($c['keys'] ? fa((string) count($c['keys'])) . ' کلید' : 'ثبت نشده') . "</b>\n"
            . '🤖 مدل: <b>' . bidi(h(self::model($c['model']))) . "</b>\n"
            . '📝 پرامپت: <b>' . ($c['prompt'] !== '' ? 'سفارشی' : 'بدون پرامپت') . "</b>\n"
            . '⏱ حالت پاسخ‌دهی: <b>' . h($modes[$c['pause']][0] ?? $c['pause']) . "</b>\n"
            . '🧠 سطح فکر کردن: <b>' . h(AI::THINK_LEVELS[$c['think']] ?? 'پیش‌فرض') . "</b>\n"
            . '🕐 ساعت فعالیت: <b>' . ($c['hrs'] ? 'از ' . self::hourLabel((int) $c['hf']) . ' تا ' . self::hourLabel((int) $c['ht']) : 'همیشه') . "</b>\n"
            . '🗑 پیام‌های پاک‌شده: <b>' . ($c['del'] ? 'روشن' : 'خاموش') . "</b>\n"
            . '✏️ پیام‌های ویرایش‌شده: <b>' . ($c['edit'] ? 'روشن' : 'خاموش') . '</b>';
        $kb = ikb([
            [btn('🔑 کلید گروک', 'bz:key'), btn('🤖 انتخاب مدل', 'bz:md')],
            [btn($c['on'] ? '🔌 منشی: روشن' : '🔌 منشی: خاموش', 'bz:on', $c['on'] ? 'success' : 'danger')],
            [btn('📝 پرامپت', 'bz:prompt', null), btn('⏱ حالت پاسخ‌دهی', 'bz:pm', null)],
            [btn('🧠 سطح فکر کردن', 'bz:th', null), btn('🕐 ساعت فعالیت', 'bz:hr', null)],
            [btn('➕ اتصال به اکانت', 'bz:how', 'primary')],
            [
                btn($c['del'] ? '🗑 روشن' : '🗑 خاموش', 'bz:del', $c['del'] ? 'success' : 'danger'),
                btn($c['edit'] ? '✏️ روشن' : '✏️ خاموش', 'bz:edit', $c['edit'] ? 'success' : 'danger'),
            ],
            ...(Store::get('biz_back', (string) $uid) === 1 ? [[btn('🔙 برگشت', 'home', null)]] : []),
        ]);
        return [$text, $kb];
    }

    public static function onCallback(array $cq, string $data): void
    {
        $tg     = App::tg();
        $uid    = (int) ($cq['from']['id'] ?? 0);
        $chatId = $cq['message']['chat']['id'] ?? null;
        $msgId  = $cq['message']['message_id'] ?? null;
        if ($chatId === null || $msgId === null || !Switches::on('business')) {
            $tg->answer($cq);
            return;
        }
        $back = [btn('🔙 برگشت', 'bz:menu', null)];

        if ($data === 'bz:menu:h') {
            Store::set('biz_back', (string) $uid, 1);
        } elseif ($data === 'bz:on') {
            self::save($uid, ['on' => !self::cfg($uid)['on']]);
        } elseif ($data === 'bz:del') {
            self::save($uid, ['del' => !self::cfg($uid)['del']]);
        } elseif ($data === 'bz:edit') {
            self::save($uid, ['edit' => !self::cfg($uid)['edit']]);
        } elseif ($data === 'bz:clr') {
            self::save($uid, ['prompt' => '']);
        } elseif ($data === 'bz:key') {
            Store::set('biz_wait', (string) $uid, ['step' => 'key']);
            Ui::show($chatId, $msgId, "<b>🔑 کلید گروک</b>\nکلید API گروکت رو همین‌جا بفرست؛\nبا <code>gsk_</code> شروع می‌شه.\nرایگانه، از console.groq.com/keys بگیر.\n\n🔁 می‌تونی چند کلید بفرستی (هر کدوم تو یه خط)\nیا هر بار یکی اضافه کنی؛ منشی بینشون می‌چرخه\nو اگه یکی خطا داد، سراغ بعدی می‌ره.\n\n💡 بعد از ذخیره، پیامی که کلید توشه پاک می‌شه.", ikb([[btn('🗑 حذف همه کلیدها', 'bz:kc', 'danger')], $back]));
            $tg->answer($cq);
            return;
        } elseif ($data === 'bz:kc') {
            self::save($uid, ['keys' => [], 'key' => '']);
        } elseif ($data === 'bz:md') {
            $rows = [];
            foreach (FreeAI::models('groq') as $i => $e) {
                $rows[] = [btn(FreeAI::title('groq', $e['id']), 'bz:md:' . $i, null)];
            }
            $rows[] = $back;
            Ui::show($chatId, $msgId, "<b>🤖 انتخاب مدل</b>\nمدل مورد نظرت رو انتخاب کن 😎\nمنشی با همون به پیام‌هات جواب می‌ده.", ikb($rows));
            $tg->answer($cq);
            return;
        } elseif (preg_match('/^bz:md:(\d+)$/', $data, $m) && isset(FreeAI::models('groq')[(int) $m[1]])) {
            self::save($uid, ['model' => FreeAI::models('groq')[(int) $m[1]]['id']]);
        } elseif ($data === 'bz:prompt') {
            Store::set('biz_wait', (string) $uid, ['step' => 'prompt']);
            Ui::show($chatId, $msgId, "<b>📝 پرامپت منشی</b>\nشخصیت و دستورالعمل منشی رو بفرست.\n\nمثال:\n<code>تو منشی شخصی من هستی؛\nوقتی نیستم مودب و کوتاه جواب بده\nو بگو وقتی خودم اومدم راهنماییت می‌کنم.</code>\n\n💡 اگه پرامپت نذاری، بدون پرامپت جواب می‌ده.", ikb([[btn('🗑 حذف پرامپت', 'bz:clr', 'danger')], $back]));
            $tg->answer($cq);
            return;
        } elseif ($data === 'bz:pm') {
            $rows = [];
            foreach (array_chunk(array_keys(self::modes()), 2) as $pair) {
                $rows[] = array_map(static fn($k) => btn(self::modes()[$k][0], 'bz:pm:' . $k, null), $pair);
            }
            $rows[] = $back;
            Ui::show($chatId, $msgId, "<b>⏱ حالت پاسخ‌دهی</b>\nبعد از هر جواب، یا وقتی خودت دستی\nتو یه چت پیام می‌دی، چقدر ساکت بمونم؟\n\n⚡ فوری: همیشه جواب می‌دم؛\nفقط بعد از پیام دستی خودت ۱ دقیقه صبر می‌کنم.\n♾ دائمی: بعد از اولین جواب یا پیام دستی‌ت،\nدیگه خودکار جواب نمی‌دم.", ikb($rows));
            $tg->answer($cq);
            return;
        } elseif (preg_match('/^bz:pm:(\w+)$/', $data, $m) && isset(self::modes()[$m[1]])) {
            self::save($uid, ['pause' => $m[1]]);
        } elseif ($data === 'bz:th') {
            $cur  = self::cfg($uid)['think'];
            $rows = [];
            foreach (array_keys(AI::THINK_LEVELS) as $k) {
                $rows[] = [btn(($k === $cur ? '✅ ' : '') . AI::THINK_LEVELS[$k], 'bz:th:' . $k, $k === $cur ? 'success' : null)];
            }
            $rows[] = $back;
            Ui::show($chatId, $msgId, "<b>🧠 سطح فکر کردن</b>\nمنشی قبل از جواب چقدر فکر کنه؟\n\nفقط روی مدل‌هایی اثر داره که فکر کردن دارن.\nکمتر = سریع‌تر، بیشتر = دقیق‌تر.\n\nاین تنظیم مخصوص منشی توئه و به هوش مصنوعی اصلی ربات ربطی نداره.", ikb($rows));
            $tg->answer($cq);
            return;
        } elseif (preg_match('/^bz:th:(\w+)$/', $data, $m) && isset(AI::THINK_LEVELS[$m[1]])) {
            self::save($uid, ['think' => $m[1]]);
        } elseif ($data === 'bz:hr' || preg_match('/^bz:hr:(t|f|e)(?::(\d+))?$/', $data, $m)) {
            $act = $m[1] ?? '';
            $num = isset($m[2]) ? (int) $m[2] : null;
            if ($act === 't') {
                self::save($uid, ['hrs' => !self::cfg($uid)['hrs']]);
            } elseif (($act === 'f' || $act === 'e') && $num !== null && $num >= 0 && $num <= 23) {
                self::save($uid, [$act === 'f' ? 'hf' : 'ht' => $num]);
            } elseif ($act === 'f' || $act === 'e') {
                $c    = self::cfg($uid);
                $cur  = (int) ($act === 'f' ? $c['hf'] : $c['ht']);
                $rows = [];
                foreach (array_chunk(range(0, 23), 4) as $chunk) {
                    $rows[] = array_map(static fn($h) => btn(($h === $cur ? '✅ ' : '') . self::hourLabel($h), "bz:hr:{$act}:{$h}", $h === $cur ? 'success' : null), $chunk);
                }
                $rows[] = [btn('🔙 برگشت', 'bz:hr', null)];
                Ui::show($chatId, $msgId, $act === 'f' ? "<b>🌅 ساعت شروع</b>\nمنشی از چه ساعتی فعال بشه؟" : "<b>🌙 ساعت پایان</b>\nمنشی تا چه ساعتی فعال باشه؟", ikb($rows));
                $tg->answer($cq);
                return;
            }
            self::clear($uid);
            [$text, $kb] = self::hoursMenu($uid);
            Ui::show($chatId, $msgId, $text, $kb);
            $tg->answer($cq);
            return;
        } elseif ($data === 'bz:how') {
            $bot = App::botUsername();
            Ui::show($chatId, $msgId, "<b>➕ اتصال به اکانت</b>\n\n۱. تلگرام ← پروفایل\n۲. ویرایش پروفایل\n۳. خودکار سازی گفت و گو\n۴. یوزرنیم منو بزن: <code>@{$bot}</code>\n۵. چت‌هایی که می‌خوای جواب بدم رو انتخاب کن\n\n💡 دسترسی «پاسخ به پیام‌ها» رو هم بده،\nوگرنه نمی‌تونم جواب بدم.", ikb([$back]));
            $tg->answer($cq);
            return;
        }

        self::clear($uid);
        [$text, $kb] = self::panel($uid);
        Ui::show($chatId, $msgId, $text, $kb);
        $tg->answer($cq);
    }

    public static function input(array $msg): bool
    {
        $uid = (int) $msg['from']['id'];
        $w   = Store::get('biz_wait', (string) $uid);
        if (!is_array($w)) {
            return false;
        }
        $tg   = App::tg();
        $text = mbTrim((string) ($msg['text'] ?? ''));
        if ($text === '') {
            $tg->reply($msg, 'فقط متن بفرست.');
            return true;
        }
        $found = [];
        if ($w['step'] === 'key') {
            $found = array_values(array_filter(preg_split('/\s+/u', $text) ?: [], static fn($t) => str_starts_with($t, 'gsk_')));
            if (!$found) {
                $tg->reply($msg, "😕 این شبیه کلید گروک نیست؛\nباید با <code>gsk_</code> شروع بشه.\nدوباره بفرست.");
                return true;
            }
        }
        self::clear($uid);
        if ($w['step'] === 'key') {
            self::save($uid, ['keys' => array_values(array_unique(array_merge(self::cfg($uid)['keys'], $found))), 'key' => '']);
            $tg->call('deleteMessage', ['chat_id' => $msg['chat']['id'], 'message_id' => $msg['message_id']], 8);
            $done = 'کلید ذخیره شد ✅';
        } else {
            self::save($uid, ['prompt' => mb_substr($text, 0, 2000)]);
            $done = 'پرامپت ذخیره شد ✅';
        }
        [$panel, $kb] = self::panel($uid);
        $tg->send($msg['chat']['id'], "{$done}\n\n" . $panel, ['reply_markup' => $kb]);
        return true;
    }
}
