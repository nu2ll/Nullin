<?php

final class Rich
{
    private static array $sent = [];

    public static function was(int $chatId, int $mid): bool
    {
        return isset(self::$sent[$chatId . ':' . $mid]);
    }

    public static function mark(int $chatId, int $mid): void
    {
        self::$sent[$chatId . ':' . $mid] = true;
    }

    public static function reply(array $msg, string $text): ?int
    {
        if (!in_array($msg['chat']['type'] ?? '', ['private', 'group', 'supergroup'], true) || trim($text) === '' || mb_strlen($text) > 30000) {
            return null;
        }
        $r = App::tg()->replyRich($msg, self::markdown($text));
        $id = empty($r['ok']) ? 0 : (int) ($r['result']['message_id'] ?? 0);
        if ($id > 0) {
            self::mark((int) $msg['chat']['id'], $id);
        }
        return $id > 0 ? $id : null;
    }

    public static function replyWithButton(array $msg, string $text, string $label = 'Nullin', string $data = 'noop', ?string $style = null): ?int
    {
        $btn = '<tg-button-row><tg-button type="callback_data"' . ($style !== null ? ' style="' . $style . '"' : '') . ' data="' . h($data) . '">' . h($label) . '</tg-button></tg-button-row>';
        $r   = App::tg()->replyRich($msg, self::markdown($text) . "\n\n" . $btn);
        $id  = empty($r['ok']) ? 0 : (int) ($r['result']['message_id'] ?? 0);
        if ($id > 0) {
            self::mark((int) $msg['chat']['id'], $id);
        }
        return $id > 0 ? $id : null;
    }

    public static function supports(?array $kb): bool
    {
        foreach ((array) ($kb['inline_keyboard'] ?? []) as $row) {
            foreach ((array) $row as $b) {
                if (!isset($b['callback_data']) && !isset($b['url'])) {
                    return false;
                }
            }
        }
        return true;
    }

    public static function buttonsHtml(?array $kb, bool $linkDefault = false): string
    {
        $out = '';
        foreach ((array) ($kb['inline_keyboard'] ?? []) as $row) {
            $r = '';
            foreach ((array) $row as $b) {
                $style = isset($b['style']) ? ' style="' . h((string) $b['style']) . '"' : (($linkDefault && isset($b['callback_data'])) ? ' style="link"' : '');
                $label = h((string) ($b['text'] ?? ''));
                if (isset($b['callback_data'])) {
                    $r .= '<tg-button type="callback_data"' . $style . ' data="' . h((string) $b['callback_data']) . '">' . $label . '</tg-button>';
                } elseif (isset($b['url'])) {
                    $r .= '<tg-button type="url"' . $style . ' url="' . h((string) $b['url']) . '">' . $label . '</tg-button>';
                } elseif (isset($b['copy_text']['text'])) {
                    $r .= '<tg-button type="copy_text"' . $style . ' text="' . h((string) $b['copy_text']['text']) . '">' . $label . '</tg-button>';
                }
            }
            if ($r !== '') {
                $out .= '<tg-button-row>' . $r . '</tg-button-row>';
            }
        }
        return $out;
    }

    private static function payload(string $html, ?array $kb, ?string $img, bool $linkDefault = false): array
    {
        $body = '<p>' . str_replace("\n", '<br>', $html) . '</p>' . self::buttonsHtml($kb, $linkDefault);
        if ($img !== null && preg_match('#^https://#', $img)) {
            return ['html' => '<img src="tg://photo?id=thumb"/>' . $body, 'is_rtl' => true, 'media' => [['id' => 'thumb', 'media' => ['type' => 'photo', 'media' => $img]]]];
        }
        return ['html' => $body, 'is_rtl' => true];
    }

    public static function editHtml(int|string $chatId, int $mid, string $html, ?array $kb = null, ?string $img = null): bool
    {
        $r = App::tg()->call('editMessageText', ['chat_id' => $chatId, 'message_id' => $mid, 'rich_message' => self::payload($html, $kb, $img)], 10);
        return !empty($r['ok']) || stripos((string) ($r['description'] ?? ''), 'not modified') !== false;
    }

    public static function editInline(string $inlineId, string $html, ?array $kb = null): ?array
    {
        return App::tg()->call('editMessageText', ['inline_message_id' => $inlineId, 'rich_message' => self::payload($html, $kb, null, true)], 10);
    }

    public static function inlineContent(string $html, ?array $kb = null): array
    {
        return ['rich_message' => self::payload($html, $kb, null, true)];
    }

    public static function edit(int|string $chatId, int $mid, string $html, ?array $kb = null, ?string $img = null): void
    {
        self::editHtml($chatId, $mid, $html, $kb, $img);
    }

    public static function sendHtml(int|string $chatId, string $html, ?int $replyTo = null, ?array $kb = null, ?string $img = null): ?array
    {
        return App::tg()->call('sendRichMessage', [
            'chat_id'          => $chatId,
            'rich_message'     => self::payload($html, $kb, $img),
            'reply_parameters' => $replyTo ? ['message_id' => $replyTo, 'allow_sending_without_reply' => true] : null,
        ], 10);
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
            $o .= $i % 2 === 1 ? $b : (preg_replace('/([\\\\<$])/u', '\\\\$1', $b) ?? $b);
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
            if ($cur === '' || $next === '' || preg_match('/^\s*(?:#{1,6}\s|\||>)/u', $cur) === 1 || preg_match('/^(?:#{1,6}\s|\||>)/u', $next) === 1 || preg_match('/^(?:[-+*]|\d+[.)])\s/u', $next) === 1 || preg_match('/^(?:[-*_]\s*){3,}$/', $next) === 1) {
                continue;
            }
            $lines[$i] = $cur . '\\';
        }
        return implode("\n", $lines);
    }
}
