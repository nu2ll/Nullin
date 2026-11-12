<?php

final class Assistant
{
    // key => label
    private const TAGS = [
        'title'         => 'Title',
        'artist'        => 'Artist',
        'album'         => 'Album',
        'album_artist'  => 'Album artist',
        'date'          => 'Track date',
        'year'          => 'Year',
        'originaldate'  => 'Original release date',
        'genre'         => 'Genre',
        'track'         => 'Track number',
        'disc'          => 'Disc number',
        'composer'      => 'Composer',
        'lyricist'      => 'Lyricist',
        'comment'       => 'Comment',
        'copyright'     => 'Copyright',
        'publisher'     => 'Publisher',
        'language'      => 'Language',
        'lyrics'        => 'Lyrics',
        'creation_time' => 'File creation time',
    ];

    private static function memKey(int $chatId, int $userId): string
    {
        return "{$chatId}_{$userId}";
    }

    public static function history(int $chatId, int $userId): array
    {
        $m = Store::get('ai_memory', self::memKey($chatId, $userId));
        $msgs = is_array($m) && ($m['day'] ?? '') === date('Y-m-d') ? ($m['messages'] ?? []) : [];
        return array_values($msgs);
    }

    private static function remember(int $chatId, int $userId, string $user, string $assistant): void
    {
        $limit = max(2, (int) App::cfg('ai_memory_messages', 20));
        $day   = date('Y-m-d');
        Store::update('ai_memory', self::memKey($chatId, $userId), static function ($m) use ($user, $assistant, $limit, $day) {
            $msgs   = is_array($m) && ($m['day'] ?? '') === $day ? ($m['messages'] ?? []) : [];
            $msgs[] = ['role' => 'user', 'content' => mb_substr($user, 0, 3000)];
            $msgs[] = ['role' => 'assistant', 'content' => mb_substr($assistant, 0, 3000)];
            return ['at' => time(), 'day' => $day, 'messages' => array_slice($msgs, -$limit)];
        });
    }

    private static function role(int $chatId, int $userId): ?string
    {
        $r = App::tg()->call('getChatMember', ['chat_id' => $chatId, 'user_id' => $userId], 8);
        $m = $r['result'] ?? null;
        if (empty($r['ok']) || !is_array($m)) {
            return null;
        }
        $role = match ($m['status'] ?? '') {
            'creator'       => 'Group owner',
            'administrator' => 'Admin',
            'restricted', 'member' => 'Member',
            default         => '',
        };
        if ($role === '') {
            return null;
        }
        $tag = trim((string) ($m['custom_title'] ?? $m['tag'] ?? ''));
        return $role . ($tag !== '' ? " (tag: {$tag})" : '');
    }

    private static function chatInfo(int $chatId): array
    {
        return Store::remember('cache', 'gchat_' . $chatId, 3600, static function () use ($chatId) {
            $r = App::tg()->call('getChat', ['chat_id' => $chatId], 8);
            return !empty($r['ok']) ? ['title' => $r['result']['title'] ?? '', 'bio' => $r['result']['description'] ?? ''] : null;
        }) ?? ['title' => '', 'bio' => ''];
    }

    private static function bestPhoto(array $msg): ?string
    {
        $photos = $msg['photo'] ?? [];
        if (!$photos) {
            return self::frameId($msg);
        }
        $best = end($photos);
        return $best['file_id'] ?? null;
    }

    public static function frameId(array $msg): ?string
    {
        $s = $msg['sticker'] ?? null;
        if (is_array($s) && empty($s['is_animated']) && empty($s['is_video'])) {
            return $s['file_id'] ?? null;
        }
        $m = $s ?? $msg['animation'] ?? $msg['video'] ?? $msg['video_note'] ?? null;
        $t = is_array($m) ? ($m['thumbnail'] ?? $m['thumb'] ?? null) : null;
        return is_array($t) ? ($t['file_id'] ?? null) : null;
    }

    public static function isFrame(array $m): bool
    {
        if (isset($m['photo'])) {
            return false;
        }
        $s = $m['sticker'] ?? null;
        if (is_array($s)) {
            return (!empty($s['is_animated']) || !empty($s['is_video'])) && self::frameId($m) !== null;
        }
        return (isset($m['animation']) || isset($m['video']) || isset($m['video_note'])) && self::frameId($m) !== null;
    }

    public static function withAlbum(string $note, array $m): string
    {
        if (empty($m['media_group_id']) || !str_starts_with($note, '[The user') || !str_ends_with($note, ']')) {
            return $note;
        }
        return substr($note, 0, -1) . '; this is part of a multi-item album and only this one item is attached]';
    }

    public static function fileInfo(array $m): ?string
    {
        $a = $m['audio'] ?? (empty($m['animation']) ? ($m['document'] ?? null) : null);
        if (!is_array($a)) {
            return null;
        }
        $name  = (string) ($a['file_name'] ?? '');
        $ext   = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if ($ext === '') {
            $ext = strtolower(ltrim((string) strrchr((string) ($a['mime_type'] ?? ''), '/'), '/'));
        }
        $music = isset($m['audio']) || str_starts_with((string) ($a['mime_type'] ?? ''), 'audio/');
        $size  = (int) ($a['file_size'] ?? 0);
        $f     = ['Type: ' . ($music ? 'Song' : 'File') . ($ext !== '' ? " ({$ext})" : '')];
        if ($name !== '') {
            $f[] = 'Name: ' . $name;
        }
        if ($music) {
            $f = array_merge($f, self::musicInfo($a));
        }
        if ($size > 0) {
            $f[] = 'Size: ' . ($size >= 1048576 ? round($size / 1048576, 1) . ' MB' : max(1, (int) round($size / 1024)) . ' KB');
        }
        return implode(' | ', $f);
    }

    public static function dataInfo(array $m): ?array
    {
        if (is_array($m['poll'] ?? null)) {
            $p = $m['poll'];
            $o = [];
            foreach ((array) ($p['options'] ?? []) as $i => $opt) {
                $o[] = ($i + 1) . ') ' . ($opt['text'] ?? '') . (isset($opt['voter_count']) ? ' (' . $opt['voter_count'] . ' votes' . ')' : '');
            }
            $quiz = ($p['type'] ?? '') === 'quiz';
            $f    = ['Question: ' . ($p['question'] ?? ''), 'Options: ' . implode(', ', $o)];
            if (isset($p['total_voter_count'])) {
                $f[] = 'Total votes: ' . $p['total_voter_count'];
            }
            $f[] = !empty($p['is_anonymous']) ? 'Anonymous' : 'Public';
            if (!empty($p['allows_multiple_answers'])) {
                $f[] = 'Multiple answers';
            }
            if (!empty($p['is_closed'])) {
                $f[] = 'Closed';
            }
            if ($quiz && isset($p['correct_option_id'])) {
                $f[] = 'Correct answer: option ' . ((int) $p['correct_option_id'] + 1);
            }
            if (!empty($p['explanation'])) {
                $f[] = 'Explanation: ' . $p['explanation'];
            }
            return [$quiz ? 'quiz' : 'poll', implode(' | ', $f)];
        }
        if (is_array($m['contact'] ?? null)) {
            $c    = $m['contact'];
            $name = trim((string) ($c['first_name'] ?? '') . ' ' . (string) ($c['last_name'] ?? ''));
            $f    = [];
            if ($name !== '') {
                $f[] = 'Name: ' . $name;
            }
            if (!empty($c['phone_number'])) {
                $f[] = 'Phone: ' . $c['phone_number'];
            }
            if (!empty($c['user_id'])) {
                $f[] = 'Telegram numeric id: ' . $c['user_id'] . ' (is on Telegram)';
            }
            if (!empty($c['vcard'])) {
                foreach (['ORG' => 'Organization', 'TITLE' => 'Title', 'EMAIL' => 'Email', 'URL' => 'Link', 'BDAY' => 'Birthday', 'NOTE' => 'Note'] as $k => $label) {
                    if (preg_match('/^' . $k . '[^:\r\n]*:(.+)$/mi', (string) $c['vcard'], $mm) && trim($mm[1]) !== '') {
                        $f[] = $label . ': ' . mb_substr(trim($mm[1]), 0, 200);
                    }
                }
            }
            return $f ? ['contact', implode(' | ', $f)] : null;
        }
        if (is_array($m['dice'] ?? null)) {
            $emoji = (string) ($m['dice']['emoji'] ?? '🎲');
            $val   = (int) ($m['dice']['value'] ?? 0);
            [$name, $rule] = self::diceKind($emoji);
            $reels = '';
            if (str_replace("\u{FE0F}", '', $emoji) === '🎰' && $val >= 1 && $val <= 64) {
                $sym   = ['BAR', 'grapes', 'lemon', '7'];
                $reels = ' | Reels from left to right: ' . $sym[($val - 1) % 4] . ' - ' . $sym[intdiv($val - 1, 4) % 4] . ' - ' . $sym[intdiv($val - 1, 16)];
            }
            return [$name, 'Emoji: ' . $emoji . ' | Result: ' . ($m['dice']['value'] ?? '?') . $reels . ($rule !== '' ? ' | ' . $rule : '')];
        }
        $v = is_array($m['venue'] ?? null) ? $m['venue'] : null;
        $l = $v['location'] ?? $m['location'] ?? null;
        if (!is_array($l) || !isset($l['latitude'], $l['longitude'])) {
            return null;
        }
        $f = [];
        if (!empty($v['title'])) {
            $f[] = 'Name: ' . $v['title'];
        }
        if (!empty($v['address'])) {
            $f[] = 'Address: ' . $v['address'];
        }
        $f[] = 'Latitude: ' . $l['latitude'];
        $f[] = 'Longitude: ' . $l['longitude'];
        if (!empty($l['live_period'])) {
            $f[] = ('Live location for ' . intdiv((int) $l['live_period'], 60) . ' minutes');
        }
        if (($place = self::placeName((float) $l['latitude'], (float) $l['longitude'])) !== '') {
            $f[] = 'Approximate address on the map: ' . $place;
        }
        return [$v ? 'venue' : 'location', implode(' | ', $f)];
    }

    private static function diceKind(string $emoji): array
    {
        return match (str_replace("\u{FE0F}", '', $emoji)) {
            '🎯'    => ['darts', '1 means a miss and 6 means a bullseye'],
            '🏀'    => ['basketball', '4 and 5 mean the ball went in the hoop, lower means it missed'],
            '⚽'    => ['football penalty', '3, 4 and 5 mean a goal, 1 and 2 mean no goal'],
            '🎳'    => ['bowling', '6 means a strike and 1 means a miss'],
            '🎰'    => ['slot machine', 'three matching symbols win and three 7s is the jackpot'],
            default => ['dice', ''],
        };
    }

    private static function placeName(float $lat, float $lon): string
    {
        $j = Http::getJson('https://nominatim.openstreetmap.org/reverse?format=jsonv2&zoom=16&accept-language=' . Lang::get() . '&lat=' . $lat . '&lon=' . $lon, ['User-Agent: NulliniBot'], 8);
        return is_array($j) && !empty($j['display_name']) ? (string) $j['display_name'] : '';
    }

    private static function musicInfo(array $a): array
    {
        $j   = [];
        $fid = (string) ($a['file_id'] ?? '');
        if ($fid !== '' && (int) ($a['file_size'] ?? 0) <= 20_000_000 && ($bytes = App::tg()->download($fid)) !== null && ($tmp = tempnam(sys_get_temp_dir(), 'nl_au_')) !== false) {
            try {
                if (file_put_contents($tmp, $bytes) !== false) {
                    $o = [];
                    @exec('timeout 20 ffprobe -v error -print_format json -show_format -show_streams ' . escapeshellarg($tmp) . ' 2>/dev/null', $o);
                    $j = json_decode(implode('', $o), true) ?: [];
                }
            } finally {
                @unlink($tmp);
            }
        }
        $src    = [(array) ($j['format']['tags'] ?? [])];
        $stream = [];
        foreach ((array) ($j['streams'] ?? []) as $st) {
            if (($st['codec_type'] ?? '') === 'audio') {
                $stream = $stream ?: $st;
                $src[]  = (array) ($st['tags'] ?? []);
            }
        }
        $tags = [];
        foreach ($src as $t) {
            foreach ($t as $k => $v) {
                $k = strtolower((string) $k);
                $v = trim(preg_replace('/\s+/u', ' ', (string) $v) ?? '');
                if ($v !== '' && !isset($tags[$k])) {
                    $tags[$k] = $v;
                }
            }
        }
        if (!isset($tags['title']) && !empty($a['title'])) {
            $tags['title'] = (string) $a['title'];
        }
        if (!isset($tags['artist']) && !empty($a['performer'])) {
            $tags['artist'] = (string) $a['performer'];
        }
        $f = [];
        foreach (array_slice($tags, 0, 40, true) as $k => $v) {
            $f[] = (isset(self::TAGS[$k]) ? (self::TAGS[$k]) : $k) . ': ' . mb_substr($v, 0, 300);
        }
        $dur = (int) round((float) ($j['format']['duration'] ?? $a['duration'] ?? 0));
        if ($dur > 0) {
            $f[] = 'Duration: ' . intdiv($dur, 60) . ':' . sprintf('%02d', $dur % 60);
        }
        if (!empty($stream['codec_name'])) {
            $f[] = 'Codec: ' . $stream['codec_name'];
        }
        $br = (int) ($j['format']['bit_rate'] ?? $stream['bit_rate'] ?? 0);
        if ($br > 0) {
            $f[] = 'Bitrate: ' . (int) round($br / 1000) . ' kbps';
        }
        if (!empty($stream['sample_rate'])) {
            $f[] = 'Sample rate: ' . $stream['sample_rate'] . ' Hz';
        }
        if (!empty($stream['channels'])) {
            $f[] = 'Channels: ' . $stream['channels'];
        }
        return $f;
    }

    public static function hasMedia(array $m): bool
    {
        return isset($m['voice']) || isset($m['audio']) || isset($m['document']) || isset($m['poll']) || isset($m['contact']) || isset($m['dice']) || isset($m['venue']) || isset($m['location']) || isset($m['photo']) || isset($m['sticker']) || isset($m['animation']) || isset($m['video']) || isset($m['video_note']);
    }

    public static function mediaPart(array $m): ?array
    {
        $tg = App::tg();
        if (isset($m['voice'])) {
            $a     = $m['voice'];
            $bytes = ($a['file_size'] ?? 0) <= 20_000_000 ? $tg->download($a['file_id']) : null;
            return $bytes ? ['type' => 'media', 'mime' => $a['mime_type'] ?? 'audio/ogg', 'data' => base64_encode($bytes)] : null;
        }
        if ($fid = self::bestPhoto($m)) {
            $bytes = $tg->download($fid);
            $info  = $bytes ? @getimagesizefromstring($bytes) : false;
            if ($info === false) {
                return null;
            }
            return ['type' => 'media', 'mime' => $info['mime'] ?? 'image/jpeg', 'data' => base64_encode($bytes)];
        }
        return null;
    }

    public static function kind(array $m): string
    {
        return match (true) {
            isset($m['voice'])                       => 'voice message',
            isset($m['audio'])                       => 'song',
            isset($m['photo'])                       => 'photo',
            isset($m['sticker'])                     => 'sticker',
            isset($m['animation'])                   => 'GIF',
            isset($m['video']) || isset($m['video_note']) => 'video',
            isset($m['document'])                    => 'file',
            isset($m['poll'])                        => 'poll',
            isset($m['contact'])                     => 'contact',
            isset($m['dice'])                        => self::diceKind((string) ($m['dice']['emoji'] ?? '🎲'))[0],
            isset($m['venue'])                       => 'venue',
            isset($m['location'])                    => 'location',
            default                                  => 'message',
        };
    }

    private static function typing(int $chatId, string $answer): void
    {
        if (!App::cfg('ai_typing_effect', true)) {
            return;
        }
        $seconds = min(3.5, max(0.8, mb_strlen($answer) / 40));
        App::tg()->action($chatId, 'typing');
        usleep((int) ($seconds * 1_000_000));
    }

    private static function voiceReply(array $msg, string $answer, ?LiveReply $live = null): void
    {
        $tg = App::tg();
        $tg->action($msg['chat']['id'], 'record_voice');
        $file = AI::speak($answer, null, 'ogg', VoicePick::get((int) ($msg['from']['id'] ?? 0)));
        $live?->stop();
        if ($file === null) {
            return;
        }
        $vr = $tg->call('sendVoice', [
            'chat_id'          => $msg['chat']['id'],
            'voice'            => new CURLFile($file, 'audio/ogg', 'voice.ogg'),
            'reply_parameters' => ['message_id' => $msg['message_id'], 'allow_sending_without_reply' => true],
        ], 120);
        AI::markMsg($msg['chat']['id'], $vr['result']['message_id'] ?? 0);
        @unlink($file);
    }

    private static function send(array $msg, string $answer): int
    {
        $rid = Rich::reply($msg, $answer);
        if ($rid === null) {
            return 0;
        }
        AI::markMsg($msg['chat']['id'], $rid);
        return $rid;
    }

    public static function handle(array $msg, string $question): void
    {
        if (!Switches::on('ai')) {
            return;
        }
        $tg       = App::tg();
        $chatId   = (int) $msg['chat']['id'];
        $chatType = (string) $msg['chat']['type'];
        $from     = $msg['from'] ?? [];
        $userId   = (int) ($from['id'] ?? 0);
        $service  = AI::isServiceSender($from, $msg['sender_chat'] ?? null);
        $autoPost = !empty($msg['is_automatic_forward']);

        if (!$service && AI::busy($userId)) {
            $tg->reply($msg, (Lang::is('en') ? AI::BUSY_TEXT_EN : AI::BUSY_TEXT));
            return;
        }

        $tg->action($chatId, 'typing');
        Users::hit($chatType === 'private' ? 'ai:private' : 'ai:group');

        $ctx = ['user_name' => $service ? '' : userName($from), 'user_id' => $userId, 'service' => $service, 'chat_type' => $chatType];
        if (in_array($chatType, ['group', 'supergroup'], true)) {
            $info              = self::chatInfo($chatId);
            $ctx['chat_title'] = $info['title'];
            $ctx['chat_bio']   = mb_substr((string) $info['bio'], 0, 300);
            if (!$service && $userId) {
                $ctx['user_role'] = self::role($chatId, $userId);
            }
            $rf = $msg['reply_to_message']['from'] ?? null;
            $rid = (int) ($rf['id'] ?? 0);
            if ($rid && empty($rf['is_bot']) && empty($msg['reply_to_message']['sender_chat']) && empty($msg['reply_to_message']['is_automatic_forward'])) {
                $ctx['reply_role'] = $rid === $userId && isset($ctx['user_role']) ? $ctx['user_role'] : self::role($chatId, $rid);
            }
        }

        $parts = [];
        $notes = [];
        $log   = $question;

        $reply = $msg['reply_to_message'] ?? null;
        $n0    = count($notes);
        if ($reply) {
            $rt = trim((string) ($reply['text'] ?? $reply['caption'] ?? ''));
            if ($media = self::mediaPart($reply)) {
                $parts[] = $media;
                $notes[] = ('[The user replied to a ' . self::kind($reply) . ' that is attached' . (self::isFrame($reply) ? '; only its first-frame image is attached' : '') . ($rt !== '' ? "; its text: {$rt}" : '') . ']');
            } elseif ($info = self::fileInfo($reply)) {
                $notes[] = ("[The user replied to a file; {$info}" . ($rt !== '' ? "; its text: {$rt}" : '') . ']');
            } elseif ($data = self::dataInfo($reply)) {
                $notes[] = "[The user replied to a {$data[0]}; {$data[1]}]";
            } elseif ($rt !== '') {
                $notes[] = "[The message the user replied to]: {$rt}";
            } elseif (!empty($reply['from'])) {
                $notes[] = '[The user replied to a message that has no text]';
            }
        }
        if ($reply && count($notes) > $n0) {
            $notes[$n0] = self::withAlbum($notes[$n0], $reply);
        }
        $n1         = count($notes);
        $replyMedia = count($parts);

        if (self::hasMedia($msg)) {
            $media = self::mediaPart($msg);
            $info  = self::fileInfo($msg);
            $data  = self::dataInfo($msg);
            if (!$media && $info === null && $data === null) {
                $tg->reply($msg, L("😕 نتونستم فایلت رو دانلود کنم؛\nدوباره بفرست.", "😕 I couldn’t download your file;\nplease send it again."));
                return;
            }
            if ($media) {
                $parts[] = $media;
            }
            $isVoice = isset($msg['voice']);
            $notes[] = $data !== null
                ? "[The user sent a {$data[0]}; {$data[1]}]"
                : ($info !== null
                ? "[The user sent a file; {$info}]"
                : ($isVoice
                    ? '[The user sent a voice message; listen to it and reply to what they said, as if they had typed it; never repeat it back to them]'
                    : (isset($msg['photo'])
                        ? '[The user sent a photo; look at it and answer]'
                        : ('[The user sent a ' . self::kind($msg) . '; ' . (self::isFrame($msg) ? 'only its first-frame image is attached' : 'its image is attached') . ', look at it and answer]'))));
            $log = '[' . self::kind($msg) . '] ' . (isset($msg['contact']) && $data !== null ? $data[1] . ' ' : '') . $question;
            if (count($notes) > $n1) {
                $notes[$n1] = self::withAlbum($notes[$n1], $msg);
            }
        }

        $text = trim(implode("\n", $notes) . ($question !== '' ? "\n\n{$question}" : ''));
        if ($text === '') {
            $text = 'Hello';
        }
        $content = $parts ? array_merge($parts, [['type' => 'text', 'text' => $text]]) : $text;
        if ($notes && $reply) {
            $log = $notes[0] . "\n" . $log;
        }

        $history = $service ? [] : self::history($chatId, $userId);
        $voiceIn = isset($msg['voice']) && !$service;
        $live    = App::cfg('ai_stream', true) ? new LiveReply($msg, null, '', true, true, $voiceIn) : null;
        $live?->start();
        $opt     = ['max_len' => PHP_INT_MAX] + ($live ? ['on_text' => $live->feed(...)] : []);
        $res     = AI::reply($content, $ctx, $history, $opt);

        $wasStopped = $live !== null && $live->stopped();
        $live?->releaseStop();
        if ($wasStopped) {
            $res['action'] = null;
            $res['image']  = null;
            unset($res['image_partial']);
            if (($res['answer'] ?? null) === null) {
                $live?->stop();
                return;
            }
        }
        if (!$voiceIn) {
            $live?->stop();
        }

        if ($res['reaction'] !== null) {
            $tg->react($chatId, (int) $msg['message_id'], $res['reaction']);
        }

        if (!empty($res['action'])) {
            $live?->discard();
            [$act, $arg] = $res['action'];
            if (Intent::run($msg, $act, $arg)) {
                if (!$service) {
                    self::remember($chatId, $userId, $log, ('[I did ' . $act . ($arg !== '' ? ': ' . $arg : '') . ' for them]'));
                }
                return;
            }
            $res = AI::reply($content, ['no_action' => true] + $ctx, $history, $opt);
        }
        $searched = AI::$searched;

        if (!empty($res['image']) || !empty($res['image_partial'])) {
            if (!Switches::on('image')) {
                $live?->discard();
                return;
            }
            Users::hit('image');
            $caption = null;
            if (!empty($res['image_partial'])) {
                $plan = AI::imagePrompt($question !== '' ? $question : (string) ($msg['text'] ?? ''));
                $res['image'] = $plan['prompt'] ?? (AI::googleTranslate($question, 'English') ?? $question);
                $caption      = $plan['caption'] ?? null;
            }
            if (ImageGen::draw($msg, $res['image'], $caption, $live) && !$service) {
                self::remember($chatId, $userId, $log, '[[image | ' . mb_substr($res['image'], 0, 200) . ']]');
            }
            return;
        }
        $answer = $res['answer'] ?? (Lang::is('en') ? AI::FAIL_TEXT_EN : AI::FAIL_TEXT);
        $shown  = $wasStopped ? $answer . L("\n\n⏹ متوقف شد", "\n\n⏹ Stopped") : $answer;

        if ($res['answer'] !== null && $voiceIn) {
            self::voiceReply($msg, $res['answer'], $live);
        }

        $lastId = 0;
        $chunks = 1;
        if (!$live || !$live->finish($shown)) {
            self::typing($chatId, $shown);
            $chunks = count(AI::chunks($shown));
            $lastId = self::send($msg, $shown);
        } else {
            $ids    = $live->ids();
            $chunks = max(1, count($ids));
            foreach ($ids as $mid) {
                AI::markMsg($chatId, $mid);
            }
            $lastId = (int) end($ids);
        }

        if ($lastId > 0 && ($res['answer'] !== null || $replyMedia === 0)) {
            AnswerButtons::attach($chatId, $lastId, [
                'u'  => $service ? 0 : $userId,
                'q'  => $text,
                'a'  => $answer,
                'mc' => count($parts),
                'n'  => $chunks,
                'o'  => (int) $msg['message_id'],
                'sr' => $searched ? 1 : 0,
                'vo' => $voiceIn ? 1 : 0,
                'f'  => $res['answer'] === null ? 1 : 0,
                'ns' => $autoPost ? 1 : 0,
            ]);
        }

        if ($res['answer'] !== null && !$service) {
            self::remember($chatId, $userId, $log, preg_replace('/\n\n🔗 (?:منابع|Sources):.*$/su', '', $res['answer']) ?? $res['answer']);
        }

        if ($res['answer'] !== null && !$service && $chatType === 'private' && !App::isAdmin($userId)) {
            Store::set('rl', 'ai_' . $userId, time());
        }
    }

    public static function retry(array $cq, array $rec): void
    {
        $tg       = App::tg();
        $m        = $cq['message'];
        $chatId   = (int) $m['chat']['id'];
        $mid      = (int) $m['message_id'];
        $uid      = (int) ($cq['from']['id'] ?? 0);
        $chatType = (string) ($m['chat']['type'] ?? 'private');
        $service  = (int) ($rec['u'] ?? 0) === 0;
        $origId   = (int) ($rec['o'] ?? 0) ?: $mid;
        $orig     = is_array($m['reply_to_message'] ?? null) ? $m['reply_to_message'] : [];
        $origMsg  = ['chat' => ['id' => $chatId, 'type' => $chatType], 'message_id' => $origId, 'from' => $orig['from'] ?? ['id' => $uid]];

        $media = [];
        if ((int) ($rec['mc'] ?? 0) > 0) {
            if ($orig && self::hasMedia($orig) && ($p = self::mediaPart($orig))) {
                $media[] = $p;
            }
            if (count($media) < (int) $rec['mc']) {
                $tg->call('editMessageReplyMarkup', ['chat_id' => $chatId, 'message_id' => $mid, 'reply_markup' => ['inline_keyboard' => []]], 8);
                return;
            }
        }

        $voiceIn = !empty($rec['vo']) && !$service;
        $text    = (string) $rec['q'];
        $content = $media ? array_merge($media, [['type' => 'text', 'text' => $text]]) : $text;

        $ctx = ['user_name' => $service ? '' : userName($cq['from'] ?? []), 'user_id' => (int) ($cq['from']['id'] ?? 0), 'service' => $service, 'chat_type' => $chatType];
        if (in_array($chatType, ['group', 'supergroup'], true)) {
            $info              = self::chatInfo($chatId);
            $ctx['chat_title'] = $info['title'];
            $ctx['chat_bio']   = mb_substr((string) $info['bio'], 0, 300);
            if (!$service && $uid) {
                $ctx['user_role'] = self::role($chatId, $uid);
            }
        }

        $history = $service ? [] : self::history($chatId, $uid);
        $live    = new LiveReply($origMsg, null, '', true, true, $voiceIn);
        $live->adopt($mid, !empty($rec['rc']));
        $opt     = ['max_len' => PHP_INT_MAX] + (App::cfg('ai_stream', true) ? ['on_text' => $live->feed(...)] : []);

        $settled = false;
        try {
            $res = AI::reply($content, $ctx, $history, $opt);
            if (!empty($res['action'])) {
                $res = AI::reply($content, ['no_action' => true] + $ctx, $history, $opt);
            }
            if ($res['reaction'] !== null) {
                $tg->react($chatId, $origId, $res['reaction']);
            }
            $searched = AI::$searched;

            if (!empty($res['image']) || !empty($res['image_partial'])) {
                $settled = true;
                if (!Switches::on('image')) {
                    $live->discard();
                    return;
                }
                Users::hit('image');
                $caption = null;
                if (!empty($res['image_partial'])) {
                    $plan         = AI::imagePrompt($text);
                    $res['image'] = $plan['prompt'] ?? (AI::googleTranslate($text, 'English') ?? $text);
                    $caption      = $plan['caption'] ?? null;
                }
                if (ImageGen::draw($origMsg, $res['image'], $caption, $live) && !$service) {
                    self::remember($chatId, $uid, $text, '[[image | ' . mb_substr($res['image'], 0, 200) . ']]');
                }
                return;
            }

            $failed = $res['answer'] === null;
            $answer = $res['answer'] ?? (Lang::is('en') ? AI::FAIL_TEXT_EN : AI::FAIL_TEXT);

            if (!$failed && $voiceIn) {
                self::voiceReply($origMsg, $res['answer'], $live);
            }

            $lastId = 0;
            $chunks = 1;
            if (!$live->finish($answer)) {
                $chunks = count(AI::chunks($answer));
                $lastId = self::send($origMsg, $answer);
            } else {
                $ids    = $live->ids();
                $chunks = max(1, count($ids));
                foreach ($ids as $id) {
                    AI::markMsg($chatId, $id);
                }
                $lastId = (int) end($ids);
            }
            $settled = true;

            if ($lastId > 0) {
                AnswerButtons::attach($chatId, $lastId, [
                    'u'  => (int) ($rec['u'] ?? 0),
                    'q'  => $text,
                    'a'  => $answer,
                    'mc' => (int) ($rec['mc'] ?? 0),
                    'n'  => $chunks,
                    'o'  => $origId,
                    'sr' => $searched ? 1 : 0,
                    'vo' => $voiceIn ? 1 : 0,
                    'f'  => $failed ? 1 : 0,
                    'ns' => !empty($rec['ns']) ? 1 : 0,
                ]);
            }
            if (!$failed && !$service) {
                self::remember($chatId, $uid, $text, preg_replace('/\n\n🔗 (?:منابع|Sources):.*$/su', '', $answer) ?? $answer);
            }
        } finally {
            $live->stop();
            if (!$settled) {
                AnswerButtons::failEdit($chatId, $mid, (Lang::is('en') ? AI::FAIL_TEXT_EN : AI::FAIL_TEXT));
            }
        }
    }

    public static function retryTranslate(array $cq, array $rec): void
    {
        $tg     = App::tg();
        $m      = $cq['message'];
        $chatId = (int) $m['chat']['id'];
        $mid    = (int) $m['message_id'];
        $live   = new LiveReply(['chat' => ['id' => $chatId, 'type' => (string) ($m['chat']['type'] ?? 'private')], 'message_id' => (int) ($rec['o'] ?? 0) ?: $mid], null, '', true, true);
        $live->adopt($mid, !empty($rec['rc']));
        $out = null;
        try {
            $out = AI::translateFast((string) $rec['q'], (string) ($rec['t'] ?? 'auto'));
        } finally {
            $live->stop();
            if ($out !== null && $out !== '') {
                $html = (string) ($rec['h'] ?? '') . h($out);
                if (!empty($rec['rc'])) {
                    AnswerButtons::editHtmlRich($chatId, $mid, $html);
                } else {
                    Rich::editMsg($chatId, $mid, $html);
                }
                Store::delete('aibtn', $chatId . ':' . $mid);
            } else {
                AnswerButtons::failEdit($chatId, $mid, (Lang::is('en') ? AI::FAIL_TEXT_EN : AI::FAIL_TEXT));
            }
        }
    }

    public static function searchAgain(array $cq, array $rec): void
    {
        $tg       = App::tg();
        $m        = $cq['message'];
        $chatId   = (int) $m['chat']['id'];
        $mid      = (int) $m['message_id'];
        $uid      = (int) ($cq['from']['id'] ?? 0);
        $chatType = (string) ($m['chat']['type'] ?? 'private');
        $service  = (int) ($rec['u'] ?? 0) === 0;
        $old      = (string) $rec['a'];
        $origId   = (int) ($rec['o'] ?? 0) ?: $mid;

        $media = [];
        if ((int) ($rec['mc'] ?? 0) > 0) {
            $orig = $m['reply_to_message'] ?? null;
            if (is_array($orig) && self::hasMedia($orig) && ($p = self::mediaPart($orig))) {
                $media[] = $p;
            }
            if (count($media) < (int) $rec['mc']) {
                $tg->answer($cq);
                return;
            }
        }

        $voiceLeft = empty($rec['vo']);
        $rich      = !empty($rec['rc']);
        if ($rich) {
            AnswerButtons::editRich($chatId, $mid, $old, AnswerButtons::rows(true, $voiceLeft));
        }
        $tg->action($chatId, 'typing');

        $done = false;
        try {
            $plainOld = (string) preg_replace('/\n\n🔗 (?:منابع|Sources):.*$/su', '', $old);
            $note     = "\n\n[System instruction: this time, before answering, you must search the web with the web_search tool and base your answer on the up-to-date results.]";
            $text    = (string) $rec['q'] . $note;
            $content = $media ? array_merge($media, [['type' => 'text', 'text' => $text]]) : $text;

            $ctx = ['user_name' => $service ? '' : userName($cq['from'] ?? []), 'user_id' => (int) ($cq['from']['id'] ?? 0), 'service' => $service, 'chat_type' => $chatType];
            if (in_array($chatType, ['group', 'supergroup'], true)) {
                $info              = self::chatInfo($chatId);
                $ctx['chat_title'] = $info['title'];
                $ctx['chat_bio']   = mb_substr((string) $info['bio'], 0, 300);
                if (!$service && $uid) {
                    $ctx['user_role'] = self::role($chatId, $uid);
                }
            }

            $history = $service ? [] : self::historyWithout($chatId, $uid, $plainOld);
            $opt     = ['max_len' => PHP_INT_MAX, 'reaction' => false, 'force_search' => true];
            $res     = AI::reply($content, $ctx, $history, $opt);
            if (!empty($res['action'])) {
                $res = AI::reply($content, ['no_action' => true] + $ctx, $history, $opt);
            }
            $searched = AI::$searched;
            $answer = !empty($res['image']) || !empty($res['image_partial']) ? null : ($res['answer'] ?? null);
            if ($answer === null) {
                return;
            }

            $chunks = AI::chunks($answer);
            $newMid = $mid;
            $edited = false;
            $isRich = false;
            if ($rich) {
                AnswerButtons::editRich($chatId, $mid, $answer, AnswerButtons::rows($searched, $voiceLeft));
                $edited = true;
                $isRich = true;
            }
            if (!$edited) {
                $newMid = self::send(['chat' => ['id' => $chatId, 'type' => $chatType], 'message_id' => $origId], $answer);
            }
            $done = true;

            AnswerButtons::attach($chatId, $newMid, [
                'u'  => (int) ($rec['u'] ?? 0),
                'q'  => (string) $rec['q'],
                'a'  => $answer,
                'mc' => (int) ($rec['mc'] ?? 0),
                'n'  => count($chunks),
                'o'  => $origId,
                'sr' => $searched ? 1 : 0,
                'vo' => $voiceLeft ? 0 : 1,
                'rc' => $isRich ? 1 : 0,
            ], !$edited);

            if (!$service) {
                self::replaceLastAnswer($chatId, $uid, $plainOld, preg_replace('/\n\n🔗 (?:منابع|Sources):.*$/su', '', $answer) ?? $answer);
            }
        } finally {
            if (!$done) {
                if ($rich) {
                    AnswerButtons::editRich($chatId, $mid, $old, AnswerButtons::rows(!empty($rec['sr']), $voiceLeft));
                }
            }
            $tg->answer($cq, $done ? null : (Lang::is('en') ? AI::FAIL_TEXT_EN : AI::FAIL_TEXT));
        }
    }

    private static function historyWithout(int $chatId, int $userId, string $lastAnswer): array
    {
        $h = self::history($chatId, $userId);
        $n = count($h);
        if ($n >= 2 && ($h[$n - 1]['role'] ?? '') === 'assistant' && ($h[$n - 1]['content'] ?? '') === mb_substr($lastAnswer, 0, 3000)) {
            return array_slice($h, 0, $n - 2);
        }
        return $h;
    }

    private static function replaceLastAnswer(int $chatId, int $userId, string $oldAnswer, string $newAnswer): void
    {
        Store::update('ai_memory', self::memKey($chatId, $userId), static function ($m) use ($oldAnswer, $newAnswer) {
            if (!is_array($m) || empty($m['messages'])) {
                return null;
            }
            $i = count($m['messages']) - 1;
            if (($m['messages'][$i]['role'] ?? '') !== 'assistant' || ($m['messages'][$i]['content'] ?? '') !== mb_substr($oldAnswer, 0, 3000)) {
                return null;
            }
            $m['messages'][$i]['content'] = mb_substr($newAnswer, 0, 3000);
            $m['at'] = time();
            return $m;
        });
    }

    public static function translate(array $msg, string $text, string $target = 'auto'): void
    {
        $tg     = App::tg();
        $source = trim($text);
        if ($source === '' && isset($msg['reply_to_message'])) {
            $source = trim((string) ($msg['reply_to_message']['text'] ?? $msg['reply_to_message']['caption'] ?? ''));
        }
        if ($source === '') {
            $tg->reply($msg, Ui::guide('translate'));
            return;
        }
        $tg->action($msg['chat']['id'], 'typing');
        $out = AI::translateFast($source, $target !== '' ? $target : 'auto');
        if ($out) {
            $tg->reply($msg, L("🌐 <b>ترجمه:</b>\n\n", "🌐 <b>Translation:</b>\n\n") . h($out));
            return;
        }
        AnswerButtons::sendFail($msg, (Lang::is('en') ? AI::FAIL_TEXT_EN : AI::FAIL_TEXT), [
            'k' => 'tr', 'u' => (int) ($msg['from']['id'] ?? 0), 'q' => $source, 't' => $target !== '' ? $target : 'auto',
            'h' => L("🌐 <b>ترجمه:</b>\n\n", "🌐 <b>Translation:</b>\n\n"), 'o' => (int) $msg['message_id'],
        ]);
    }
}
