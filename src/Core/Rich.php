<?php

final class Rich
{
    public static function on(): bool
    {
        return (bool) App::cfg('ai_rich', false);
    }

    public static function reply(array $msg, string $text): ?int
    {
        if (!self::on() || ($msg['chat']['type'] ?? '') !== 'private' || trim($text) === '' || mb_strlen($text) > 30000) {
            return null;
        }
        $r = App::tg()->replyRich($msg, self::markdown($text));
        $id = empty($r['ok']) ? 0 : (int) ($r['result']['message_id'] ?? 0);
        return $id > 0 ? $id : null;
    }

    public static function markdown(string $text): string
    {
        $parts = preg_split('/(```.*?```)/su', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($parts === false) {
            return $text;
        }
        $out = '';
        foreach ($parts as $i => $part) {
            $out .= $i % 2 === 1 ? $part : self::prose($part);
        }
        return $out;
    }

    private static function prose(string $s): string
    {
        $bits = preg_split('/(\[[^\]\n]{1,100}\]\(https?:\/\/[^)\s]+\)|`[^`\n]{1,200}`)/u', $s, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($bits === false) {
            return $s;
        }
        $o = '';
        foreach ($bits as $i => $b) {
            $o .= $i % 2 === 1 ? $b : (preg_replace('/([\\\\`*_~#|<>\[\]()$^&])/u', '\\\\$1', $b) ?? $b);
        }
        return self::breaks($o);
    }

    private static function breaks(string $s): string
    {
        $lines = explode("\n", $s);
        $n     = count($lines);
        for ($i = 0; $i < $n - 1; $i++) {
            $cur  = rtrim($lines[$i]);
            $next = ltrim($lines[$i + 1]);
            if ($cur === '' || $next === '' || preg_match('/^(?:[-+*]|\d+[.)])\s/u', $next) === 1 || preg_match('/^(?:[-*_]\s*){3,}$/', $next) === 1) {
                continue;
            }
            $lines[$i] = $cur . '\\';
        }
        return implode("\n", $lines);
    }
}
