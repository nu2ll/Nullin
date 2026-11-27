<?php

final class Quote
{
    private static function endpoint(): string
    {
        $u = rtrim(trim((string) App::cfg('quote_api', '')), '/');
        if ($u === '') {
            return '';
        }
        return preg_replace('~/generate(?:\.\w+)?$~', '', $u) . '/generate';
    }

    public static function notice(string $kind): string
    {
        return match ($kind) {
            'none'  => L("💬 روی پیامی که می‌خوای استیکرش کنم ریپلای کن؛\nبعد <code>/q</code> رو بزن.", "💬 Reply to the message you want as a sticker;\nthen send <code>/q</code>."),
            'empty' => L("😕 این پیام چیزی نداره؛\nنمی‌تونم ازش استیکر بسازم.", "😕 This message has nothing in it;\nI can’t make a sticker from it."),
            'send'  => L("😕 استیکر ساخته شد ولی ارسالش به مشکل خورد؛\nدوباره امتحان کن.", "😕 The sticker was made but sending it failed;\ntry again."),
            default => L("😕 الان نتونستم استیکر بسازم؛\nیه‌کم دیگه امتحان کن.", "😕 I couldn’t make the sticker right now;\ntry again in a bit."),
        };
    }

    private static function who(array $u): array
    {
        $title = trim((string) ($u['title'] ?? ''));
        $name  = $title !== '' ? $title : trim(trim((string) ($u['first_name'] ?? '')) . ' ' . trim((string) ($u['last_name'] ?? '')));
        return [
            'id'       => (int) ($u['id'] ?? 0),
            'name'     => $name !== '' ? $name : (string) ($u['username'] ?? '…'),
            'username' => isset($u['username']) ? (string) $u['username'] : null,
        ];
    }

    private static function author(array $m): array
    {
        $o = $m['forward_origin'] ?? null;
        if (is_array($o)) {
            $t = (string) ($o['type'] ?? '');
            if ($t === 'user' && is_array($o['sender_user'] ?? null)) {
                return self::who($o['sender_user']);
            }
            if ($t === 'hidden_user') {
                $n = (string) ($o['sender_user_name'] ?? '');
                return ['id' => crc32($n), 'name' => $n !== '' ? $n : '…', 'username' => null];
            }
            $c = $o['chat'] ?? $o['sender_chat'] ?? null;
            if (is_array($c)) {
                return self::who($c);
            }
        }
        return self::who($m['sender_chat'] ?? $m['from'] ?? []);
    }

    /** آواتار و ایموجی‌استاتوس (اگه تلگرام بده) با یه getChat؛ اگه نشد از getUserProfilePhotos */
    private static function profile(int $id): array
    {
        if ($id === 0) {
            return [];
        }
        $tg  = App::tg();
        $r   = $tg->call('getChat', ['chat_id' => $id], 8);
        $fid = (string) ($r['result']['photo']['big_file_id'] ?? '');
        $es  = (string) ($r['result']['emoji_status_custom_emoji_id'] ?? '');
        if ($fid === '' && $id > 0) {
            $r     = $tg->call('getUserProfilePhotos', ['user_id' => $id, 'limit' => 1], 8);
            $sizes = $r['result']['photos'][0] ?? [];
            $fid   = is_array($sizes) && $sizes ? (string) (end($sizes)['file_id'] ?? '') : '';
        }
        $out = [];
        if ($fid !== '') {
            $url = self::isTest() ? $tg->fileUrl($fid) : null;
            $out['photo'] = $url !== null ? ['url' => $url] : ['big_file_id' => $fid];
        }
        if ($es !== '') {
            $out['emoji_status'] = $es;
        }
        return $out;
    }

    private static function size(array $f): array
    {
        return ['file_id' => (string) ($f['file_id'] ?? ''), 'width' => (int) ($f['width'] ?? 0), 'height' => (int) ($f['height'] ?? 0)];
    }

    /** ربات بتا روی سرور تست تلگرامه؛ سرویس quote آدرس /test/ رو نمی‌شناسه، پس به‌جای file_id لینک مستقیم فایل می‌دیم */
    private static function isTest(): bool
    {
        return App::botIndex() === 2;
    }

    private static function pack(array $sizes): array
    {
        if (!self::isTest() || !$sizes) {
            return $sizes;
        }
        $f   = end($sizes);
        $url = App::tg()->fileUrl((string) ($f['file_id'] ?? ''));
        return $url === null ? $sizes : [['url' => $url, 'width' => $f['width'] ?? 0, 'height' => $f['height'] ?? 0] + $f];
    }

    private static function media(array $m): array
    {
        if (!empty($m['photo']) && is_array($m['photo'])) {
            return ['media' => self::pack(array_map([self::class, 'size'], array_values($m['photo'])))];
        }
        if (!empty($m['sticker']) && is_array($m['sticker'])) {
            $s = $m['sticker'];
            if ((!empty($s['is_animated']) || !empty($s['is_video'])) && !empty($s['thumbnail']['file_id'])) {
                $s = $s['thumbnail'] + ['is_animated' => false];
            }
            return [
                'media'     => [self::pack([self::size($s)])[0] + ['is_animated' => !empty($s['is_animated'])]],
                'mediaType' => 'sticker',
            ];
        }
        if (!empty($m['voice']) && is_array($m['voice'])) {
            return ['voice' => ['duration' => (int) ($m['voice']['duration'] ?? 0)]];
        }
        foreach (['video', 'animation', 'video_note', 'document', 'audio'] as $k) {
            $t = $m[$k]['thumbnail'] ?? null;
            if (is_array($t) && !empty($t['file_id'])) {
                return ['media' => self::pack([self::size($t)])];
            }
        }
        return [];
    }

    /** thumbnail کادر ریپلای (عکس/استیکر/ویدیو…)؛ ویس و بقیه چیزی ندارن */
    private static function thumb(array $m): string
    {
        if (!empty($m['photo']) && is_array($m['photo'])) {
            $ph = array_values($m['photo']);
            return (string) (($ph[1] ?? $ph[0])['file_id'] ?? '');
        }
        if (!empty($m['sticker']) && is_array($m['sticker'])) {
            $st = $m['sticker'];
            $an = !empty($st['is_animated']) || !empty($st['is_video']);
            return (string) ($an ? ($st['thumbnail']['file_id'] ?? '') : ($st['file_id'] ?? ''));
        }
        foreach (['video', 'animation', 'video_note', 'document', 'audio'] as $k) {
            $id = (string) ($m[$k]['thumbnail']['file_id'] ?? '');
            if ($id !== '') {
                return $id;
            }
        }
        return '';
    }

    private static function build(array $m): ?array
    {
        $text     = (string) ($m['text'] ?? $m['caption'] ?? '');
        $entities = $m['entities'] ?? $m['caption_entities'] ?? [];
        $media    = self::media($m);
        if (trim($text) === '' && !$media) {
            return null;
        }
        $from  = self::author($m);
        $from += self::profile($from['id']);
        $out = [
            'from'     => array_filter($from, static fn($v) => $v !== null),
            'text'     => $text,
            'entities' => is_array($entities) ? $entities : [],
            'avatar'   => true,
        ] + $media;

        $rep = $m['reply_to_message'] ?? null;
        if (is_array($rep)) {
            $rt = (string) ($rep['text'] ?? $rep['caption'] ?? '');
            $re = $rep['entities'] ?? $rep['caption_entities'] ?? [];
            $th = self::thumb($rep);
            if (trim($rt) !== '' || $th !== '') {
                $ra = self::author($rep);
                $out['replyMessage'] = [
                    'name'     => $ra['name'],
                    'text'     => $rt,
                    'entities' => is_array($re) ? $re : [],
                    'chatId'   => $ra['id'],
                ] + ($th !== '' ? ['media' => ['fileId' => $th]] : []);
            }
        }
        return $out;
    }

    private static function render(array $one): ?string
    {
        $url = self::endpoint();
        $tok = trim(App::tg()->token());
        $r   = Http::postJson($url, [
            'type'            => 'quote',
            'format'          => 'webp',
            'backgroundColor' => '#1b1429',
            'width'           => 512,
            'height'          => 768,
            'scale'           => 2,
            'emojiBrand'      => 'apple',
            'messages'        => [$one],
        ] + ($tok !== '' ? ['botToken' => $tok] : []), [], 45);
        $d   = (array) ($r['data'] ?? []);
        $b64 = (string) ($d['image'] ?? $d['result']['image'] ?? '');
        $raw = $b64 !== '' ? base64_decode($b64, true) : false;
        if ((int) $r['code'] !== 200 || !is_string($raw) || $raw === '') {
            logWarn('quote', 'HTTP ' . (int) $r['code'] . ' ' . mb_substr((string) ($d['error'] ?? $r['raw'] ?? ''), 0, 120));
            return null;
        }
        return $raw;
    }

    private static function sticker(array $msg, string $bytes): bool
    {
        $tmp = tempnam(sys_get_temp_dir(), 'nl_q_');
        if ($tmp === false || file_put_contents($tmp, $bytes) === false) {
            return false;
        }
        try {
            $r = App::tg()->call('sendSticker', [
                'chat_id'          => $msg['chat']['id'],
                'sticker'          => new CURLFile($tmp, 'image/webp', 'quote.webp'),
                'reply_parameters' => ['message_id' => (int) $msg['message_id'], 'allow_sending_without_reply' => true],
            ], 60);
        } finally {
            @unlink($tmp);
        }
        if (empty($r['ok'])) {
            logWarn('quote', 'sendSticker failed: ' . (string) ($r['description'] ?? ''));
        }
        return !empty($r['ok']);
    }

    private static function stickerId(string $bytes): ?string
    {
        $chat = (string) App::cfg('storage_chat', '');
        $chat = $chat !== '' ? $chat : (string) (App::cfg('admins', [])[0] ?? '');
        $tmp  = tempnam(sys_get_temp_dir(), 'nl_q_');
        if ($chat === '' || $tmp === false || file_put_contents($tmp, $bytes) === false) {
            return null;
        }
        try {
            $tg = App::tg();
            $r  = $tg->call('sendSticker', ['chat_id' => $chat, 'sticker' => new CURLFile($tmp, 'image/webp', 'quote.webp'), 'disable_notification' => true], 60);
        } finally {
            @unlink($tmp);
        }
        if (empty($r['ok'])) {
            logWarn('quote', 'storage sendSticker failed: ' . (string) ($r['description'] ?? ''));
            return null;
        }
        $id = $r['result']['sticker']['file_id'] ?? null;
        if (isset($r['result']['message_id'])) {
            $tg->call('deleteMessage', ['chat_id' => $chat, 'message_id' => $r['result']['message_id']]);
        }
        return is_string($id) ? $id : null;
    }

    /** [webp bytes یا null, نوع پیام خطا] */
    private static function make(?array $src): array
    {
        if (!is_array($src)) {
            return [null, 'none'];
        }
        if (self::endpoint() === '') {
            logError('quote', 'QUOTE_API_URL is not set');
            return [null, 'fail'];
        }
        $one = self::build($src);
        if ($one === null) {
            return [null, 'empty'];
        }
        $img = self::render($one);
        return $img === null ? [null, 'fail'] : [$img, ''];
    }

    public static function send(array $msg): bool
    {
        $tg = App::tg();
        if (is_array($msg['reply_to_message'] ?? null) && self::endpoint() !== '') {
            $tg->action($msg['chat']['id'], 'choose_sticker');
        }
        [$img, $err] = self::make($msg['reply_to_message'] ?? null);
        if ($img === null) {
            $tg->reply($msg, self::notice($err));
            return true;
        }
        if (!self::sticker($msg, $img)) {
            $tg->reply($msg, self::notice('send'));
        }
        return true;
    }

    /** حالت مهمان: استیکر با answerGuestQuery برمی‌گرده */
    public static function guest(array $msg, string $qid): void
    {
        [$img, $err] = self::make($msg['reply_to_message'] ?? null);
        if ($img === null) {
            GuestHandler::say($qid, self::notice($err));
            return;
        }
        $id = self::stickerId($img);
        if ($id !== null) {
            $r = App::tg()->call('answerGuestQuery', ['guest_query_id' => $qid, 'result' => [
                'type'            => 'sticker',
                'id'              => substr(md5($qid . microtime()), 0, 16),
                'sticker_file_id' => $id,
            ]], 15);
            if (!empty($r['ok'])) {
                return;
            }
            logWarn('quote', 'guest answer failed: ' . (string) ($r['description'] ?? ''));
        }
        GuestHandler::say($qid, self::notice('send'));
    }
}
