<?php

final class Love
{
    public const NAME_MAX = 32;

    public static function pending(string $tokenA, string $tokenB): array
    {
        $ra    = UserInfo::resolve($tokenA);
        $rb    = UserInfo::resolve($tokenB);
        $a     = mb_substr($ra['name'], 0, self::NAME_MAX);
        $b     = mb_substr($rb['name'], 0, self::NAME_MAX);
        $token = bin2hex(random_bytes(6));
        Store::set('love', $token, ['a' => $a, 'b' => $b, 'ra' => $ra, 'rb' => $rb]);
        return [
            'token'    => $token,
            'a'        => $a,
            'b'        => $b,
            'text'     => L("<b>💘 تطابق عشقی</b>\n\n", "<b>💘 Love match</b>\n\n") . self::tag($ra, $a) . ' ❤️ ' . self::tag($rb, $b) . L("\n\n⏳ دارم عشقشون رو می‌سنجم...", "\n\n⏳ Measuring their love..."),
            'keyboard' => ikb([[btn(L('⏳ صبر کن...', '⏳ Wait...'), 'love:noop', null)]]),
        ];
    }

    public static function resolve(string $token, string $imid): void
    {
        $p = Store::get('love', $token);
        if (!is_array($p)) {
            return;
        }
        Store::delete('love', $token);
        $r = self::build((string) $p['a'], (string) $p['b'], $p['ra'] ?? null, $p['rb'] ?? null);
        Rich::editInline($imid, $r['text'], $r['keyboard']);
    }

    private static function tag(?array $r, string $name): string
    {
        return userTag(!empty($r['id']) ? (int) $r['id'] : null, $name, $r['username'] ?? null);
    }

    public static function build(string $a, string $b, ?array $ra = null, ?array $rb = null): array
    {
        [$p, $line] = self::judge($a, $b);
        $bar  = str_repeat('❤️', (int) round($p / 20)) . str_repeat('🤍', 5 - (int) round($p / 20));
        $text = L("<b>💘 تطابق عشقی</b>\n\n", "<b>💘 Love match</b>\n\n")
            . self::tag($ra, $a) . ' ❤️ ' . self::tag($rb, $b) . "\n\n"
            . "{$bar}  <b>" . fa($p) . L('٪', '%') . "</b>"
            . ($line !== '' ? "\n" . h($line) : '');
        return [
            'text'     => $text,
            'percent'  => $p,
            'keyboard' => ikb([[
                btn('👤 ' . $a, 'love:noop'),
                btn('💗 ' . fa($p) . L('٪', '%'), 'love:noop', 'danger'),
                btn('👤 ' . $b, 'love:noop'),
            ]]),
        ];
    }

    private static function judge(string $a, string $b): array
    {
        $pair = [mb_strtolower(trim($a)), mb_strtolower(trim($b))];
        sort($pair);
        $day = date('Y-m-d');
        $key = 'love:' . md5(implode('|', $pair) . '|' . $day);
        $hit = Store::get('cache', $key);
        if (is_array($hit) && ($hit['day'] ?? '') === $day) {
            return [(int) $hit['p'], (string) $hit['line']];
        }
        $p    = (int) (hexdec(substr(md5(implode('|', $pair) . $day), 0, 6)) % 101);
        $line = '';
        $lang = Lang::aiName();
        $out  = AI::chat([
            ['role' => 'system', 'content' => "You are the funny, casual love-meter of the bot “Nullin”. You are given two names.\n"
                . "Pick a love match percentage from 0 to 100 (purely for fun, any number is allowed) and write one short, funny, casual sentence in {$lang} that matches exactly that percentage;\n"
                . "At most 14 words, just one emoji, no insults or sexual content, no markdown.\n"
                . "Reply with exactly two lines:\nPERCENT: <number>\nTEXT: <sentence>"],
            ['role' => 'user', 'content' => "First person: {$a}\nSecond person: {$b}"],
        ], ['tier' => 'lite', 'temperature' => 1.0, 'deadline' => microtime(true) + 20, 'thinking' => null, 'section' => 'love']);
        if ($out !== null && preg_match('/PERCENT\s*:\s*(\d{1,3})/iu', en($out), $pm)) {
            $p    = max(0, min(100, (int) $pm[1]));
            $line = preg_match('/TEXT\s*:\s*(.+)/iu', $out, $tm) ? mb_substr(mbTrim(str_replace(['*', '`'], '', $tm[1]), " \t\"'«»"), 0, 160) : '';
        }
        Store::set('cache', $key, ['day' => $day, 'p' => $p, 'line' => $line]);
        return [$p, $line];
    }
}
