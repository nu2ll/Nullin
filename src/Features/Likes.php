<?php

final class Likes
{
    public const TEXT_MAX = 256;

    public static function token(string $text): string
    {
        return substr(md5($text), 0, 16);
    }

    public static function body(string $text): string
    {
        return h($text);
    }

    public static function keyboard(string $token, int $count = 0): array
    {
        return ikb([[btn($count > 0 ? '❤️ ' . fa((string) $count) : '❤️', 'lk:' . $token, null)]]);
    }

    public static function article(string $text): array
    {
        $text  = mb_substr(trim($text), 0, self::TEXT_MAX);
        $token = self::token($text);
        Store::set('like_txt', $token, ['t' => $text]);
        return Ui::article(
            'lk_' . $token,
            'like',
            '❤️ ' . mb_substr($text, 0, 60),
            L('پیام با دکمه‌ی لایک می‌فرسته', 'Sends a message with a like button'),
            self::body($text),
            self::keyboard($token, 0)
        );
    }

    public static function onChosen(string $token, string $imid): void
    {
        $src = Store::get('like_txt', $token);
        if (!is_array($src) || !isset($src['t'])) {
            return;
        }
        Store::update('like', $imid, static fn($s) => (is_array($s) && isset($s['t'])) ? null : ['t' => (string) $src['t'], 'u' => []]);
    }

    public static function onCallback(array $cq, string $data, string $imid): void
    {
        $tg    = App::tg();
        $uid   = (int) ($cq['from']['id'] ?? 0);
        $token = substr($data, 3);
        if ($uid <= 0 || !preg_match('/^[a-f0-9]{16}$/', $token)) {
            $tg->answer($cq);
            return;
        }

        $liked   = false;
        $missing = false;
        $state   = Store::update('like', $imid, static function ($s) use ($uid, $token, &$liked, &$missing) {
            if (!is_array($s) || !isset($s['t'])) {
                $src = Store::get('like_txt', $token);
                if (!is_array($src) || !isset($src['t'])) {
                    $missing = true;
                    return null;
                }
                $s = ['t' => (string) $src['t'], 'u' => []];
            }
            $users = array_map('intval', (array) ($s['u'] ?? []));
            if (in_array($uid, $users, true)) {
                $users = array_values(array_diff($users, [$uid]));
            } else {
                $users[] = $uid;
                $liked   = true;
            }
            $s['u'] = $users;
            return $s;
        });

        if ($missing || !is_array($state)) {
            $tg->alert($cq, L('⌛️ این لایک منقضی شده.', '⌛️ This like has expired.'));
            return;
        }

        Rich::editInline($imid, self::body((string) $state['t']), self::keyboard($token, count((array) $state['u'])));
        $tg->answer($cq, $liked ? L('❤️ لایک شد', '❤️ Liked') : L('💔 لایکت برداشته شد', '💔 Like removed'));
    }
}
