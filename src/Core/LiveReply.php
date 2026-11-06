<?php

final class LiveReply
{
    public const DRAWING = '🎨 دارم می‌کشمش، چند لحظه صبر کن...';

    public const THINKING = '🧠 نالین داره فکر می‌کنه';

    private const WAIT_TEXT = 'نالین داره فکر می‌کنه';

    private array $ids    = [];
    private array $sent   = [];
    private string $mode  = 'wait';
    private int $lastLen  = 0;
    private int $lastTyping = 0;
    private bool $draft   = false;
    private bool $rich    = false;
    private int $draftId  = 0;
    private float $t0     = 0.0;
    private int $shown    = 0;
    private bool $armed   = false;
    private bool $adopted = false;
    private bool $richMsg = false;
    private bool $inTick  = false;
    private bool $typingOnly = false;

    public function __construct(private array $msg, private ?string $imid = null, private string $head = '', private bool $reaction = true, private bool $status = false, private bool $hold = false)
    {
    }

    public static function typingOnly(array $msg): self
    {
        $l             = new self($msg, null, '', false, true);
        $l->typingOnly = true;
        return $l;
    }

    public function watch(bool $now = false): void
    {
        if ($this->imid === null || $this->armed) {
            return;
        }
        if ($now) {
            App::tg()->editInline($this->imid, $this->frame(0));
        }
        $this->arm();
    }

    public function adopt(int $mid, bool $rich = false): void
    {
        $this->adopted = true;
        $this->richMsg = $rich;
        $this->ids[0] = $mid;
        $this->editMsg($mid, $this->frame(0));
        $this->arm();
    }

    private function editMsg(int $mid, string $html): ?array
    {
        $chatId = $this->msg['chat']['id'];
        if ($this->richMsg && $mid === ($this->ids[0] ?? 0)) {
            return AnswerButtons::editHtmlRich((int) $chatId, $mid, $html);
        }
        return App::tg()->editMessage($chatId, $mid, $html);
    }

    public function stop(): void
    {
        $this->disarm();
    }

    private function arm(): void
    {
        $this->armed = true;
        $this->t0    = microtime(true);
        $this->shown = 0;
        Http::$tick  = $this->tick(...);
    }

    private function disarm(): void
    {
        if (!$this->armed) {
            return;
        }
        $this->armed = false;
        Http::$tick  = null;
    }

    public function tick(): void
    {
        if (!$this->armed || $this->inTick || $this->mode === 'live') {
            return;
        }
        if ($this->typingOnly) {
            $this->inTick = true;
            try {
                $this->typing();
            } catch (Throwable) {
            } finally {
                $this->inTick = false;
            }
            return;
        }
        $n    = (int) floor(microtime(true) - $this->t0);
        if ($n < $this->shown + 1) {
            return;
        }
        $this->inTick = true;
        $this->shown  = $n;
        try {
            $html = $this->frame($n);
            $r    = null;
            if ($this->draft) {
                $this->waitDraft($n, $html);
            } elseif ($this->imid !== null) {
                $r = App::tg()->editInline($this->imid, $html);
            } elseif (isset($this->ids[0])) {
                $r = $this->editMsg($this->ids[0], $html);
            }
            if ((int) ($r['error_code'] ?? 0) === 429) {
                $this->disarm();
            }
        } catch (Throwable) {
        } finally {
            $this->inTick = false;
        }
    }

    public function start(): void
    {
        if (!$this->status || $this->imid !== null || $this->ids !== [] || $this->draft) {
            return;
        }
        if ($this->typingOnly) {
            $this->typing();
            $this->arm();
            return;
        }
        $tg = App::tg();

        if (($this->msg['chat']['type'] ?? '') === 'private') {
            $this->draftId = mt_rand(1, 2147483646);
            if ($this->sendThinking(0)) {
                $this->rich  = true;
                $this->draft = true;
                $this->arm();
                return;
            }
            if ($this->sendDraft($this->frame(0))) {
                $this->draft = true;
                $this->arm();
                return;
            }
        }

        $this->typing();
        $r = $tg->reply($this->msg, $this->frame(0));
        if (empty($r['ok']) || !isset($r['result']['message_id'])) {
            return;
        }
        $this->ids[0] = (int) $r['result']['message_id'];
        $this->arm();
    }

    private function sendThinking(int $n): bool
    {
        $r = App::tg()->call('sendRichMessageDraft', [
            'chat_id'      => $this->msg['chat']['id'],
            'draft_id'     => $this->draftId,
            'rich_message' => ['html' => '<tg-thinking>🧠 نالین داره فکر می‌کنه… ' . $n . 's</tg-thinking>'],
        ], 8);
        return !empty($r['ok']);
    }

    private function sendRichText(string $text): bool
    {
        $r = App::tg()->call('sendRichMessageDraft', [
            'chat_id'      => $this->msg['chat']['id'],
            'draft_id'     => $this->draftId,
            'rich_message' => ['markdown' => Rich::markdown($text) . ' ▍'],
        ], 8);
        return !empty($r['ok']);
    }

    private function waitDraft(int $n, string $html): void
    {
        if ($this->rich && $this->sendThinking($n)) {
            return;
        }
        $this->rich = false;
        $this->sendDraft($html);
    }

    private function sendDraft(string $html): bool
    {
        $p = [
            'chat_id'    => $this->msg['chat']['id'],
            'draft_id'   => $this->draftId,
            'text'       => $html,
            'parse_mode' => 'HTML',
        ];
        $r = App::tg()->call('sendMessageDraft', $p, 8);
        return !empty($r['ok']);
    }

    private function frame(int $n): string
    {
        $label = $this->head !== '' ? $this->head . self::WAIT_TEXT : self::THINKING;
        return $label . "\n\u{061C}" . $n . 's';
    }

    private function typing(): void
    {
        if ($this->status && !$this->draft && time() - $this->lastTyping >= 4) {
            $this->lastTyping = time();
            App::tg()->action($this->msg['chat']['id'], 'typing');
        }
    }

    public function feed(string $raw): void
    {
        if ($this->typingOnly || $this->mode === 'silent') {
            return;
        }
        $this->typing();
        $body = $this->body($raw, false);
        if ($body === false) {
            $this->mode = 'silent';
            return;
        }
        if ($body === null) {
            return;
        }
        if ($this->hold && $body !== self::DRAWING) {
            return;
        }
        $this->mode = 'live';
        $this->disarm();
        $this->show($body);
    }

    public function finish(string $answer): bool
    {
        $this->disarm();
        if ($this->typingOnly) {
            return false;
        }
        if ($this->draft) {
            $this->waitDraft(0, $this->frame(0));
            $ok = $this->sync($answer, true);
            if ($ok) {
                return true;
            }
            $this->discard();
            return false;
        }
        if ($this->ids === []) {
            return false;
        }
        if ($this->sync($answer, true)) {
            return true;
        }
        $this->discard();
        return false;
    }

    public function finishHtml(string $html): bool
    {
        $this->disarm();
        if (!isset($this->ids[0])) {
            return false;
        }
        $r = $this->editMsg($this->ids[0], $html);
        if (!empty($r['ok']) || stripos((string) ($r['description'] ?? ''), 'not modified') !== false) {
            return true;
        }
        $this->discard();
        return false;
    }

    public function draftId(): int
    {
        return $this->draft ? $this->draftId : 0;
    }

    public function ids(): array
    {
        return array_values($this->ids);
    }

    public function active(): bool
    {
        return $this->ids !== [];
    }

    public function discard(): void
    {
        if (!$this->draft) {
            $this->disarm();
        }
        foreach ($this->ids as $id) {
            App::tg()->call('deleteMessage', ['chat_id' => $this->msg['chat']['id'], 'message_id' => $id], 8);
        }
        $this->ids     = [];
        $this->sent    = [];
        $this->mode    = 'wait';
        $this->lastLen = 0;
    }

    private function body(string $raw, bool $final)
    {
        $t = preg_replace('#<think>.*?</think>#si', '', $raw) ?? $raw;
        $t = ltrim(preg_replace('#<think>.*$#si', '', $t) ?? $t);
        if ($t === '') {
            return null;
        }

        $nl = strpos($t, "\n");
        if ($this->reaction) {
            if ($nl === false) {
                if (!$final && (preg_match('/^[\s*`«»\[]*reaction(?![A-Za-z])/iu', $t) || AI::isEmojiLine($t))) {
                    return null;
                }
            } else {
                $t = AI::splitReaction($t, true)[1];
                if (!$final && AI::isEmojiLine($t)) {
                    return null;
                }
            }
        }

        $t = ltrim($t);
        if ($t === '') {
            return null;
        }

        if (preg_match('/^\s*\[\[\s*image(?![A-Za-z])/iu', $t)) {
            return self::DRAWING;
        }
        if (preg_match('/^\s*\[\[\s*(' . implode('|', Intent::NAMES) . ')(?![A-Za-z])/iu', $t)) {
            return false;
        }
        if (!$final && preg_match('/^\s*\[{1,2}\s*[A-Za-z]*$/u', $t)) {
            return null;
        }

        if (preg_match('/^[\s*`«»]*([A-Za-z]+)(.*)$/su', $t, $w)) {
            if (!$final && trim($w[2]) === '') {
                return null;
            }
        }
        return $t;
    }

    private function show(string $body): void
    {
        $chunked = $this->imid === null && $this->head === '';
        $text    = AI::clean($body, $chunked ? PHP_INT_MAX : 4094 - AI::visible($this->head));
        if ($text === '') {
            return;
        }
        $len = mb_strlen($text);
        if ($len === $this->lastLen) {
            return;
        }
        $this->lastLen = $len;

        if ($this->draft) {
            if ($len > 3500) {
                $text = mb_substr($text, -3500);
            }
            if (substr_count($text, '```') % 2 === 1) {
                $text .= "\n```";
            }
            if ($this->rich && $this->sendRichText($text)) {
                return;
            }
            $this->rich = false;
            $this->sendDraft(AI::toHtml($text) . ' ▍');
            return;
        }

        if ($chunked) {
            $this->sync($text, false);
            return;
        }

        if (substr_count($text, '```') % 2 === 1) {
            $text .= "\n```";
        }
        $html = $this->head . AI::toHtml($text) . ' ▍';
        $tg   = App::tg();

        if ($this->imid !== null) {
            $tg->editInline($this->imid, $html);
        } elseif (!isset($this->ids[0])) {
            $r = $tg->reply($this->msg, $html);
            if (!empty($r['ok']) && isset($r['result']['message_id'])) {
                $this->ids[0] = (int) $r['result']['message_id'];
            }
        } else {
            $this->editMsg($this->ids[0], $html);
        }
    }

    private function sync(string $text, bool $final): bool
    {
        $tg    = App::tg();
        $parts = AI::chunks($text);
        $last  = count($parts) - 1;

        if ($final && $this->richMsg && isset($this->ids[0]) && mb_strlen($text) <= 12000) {
            $mid = (int) $this->ids[0];
            $r   = $tg->call('editMessageText', [
                'chat_id'      => $this->msg['chat']['id'],
                'message_id'   => $mid,
                'rich_message' => ['markdown' => Rich::markdown(substr_count($text, '```') % 2 === 1 ? $text . "\n```" : $text)],
            ], 10);
            if (!empty($r['ok']) || stripos((string) ($r['description'] ?? ''), 'not modified') !== false) {
                Rich::mark((int) $this->msg['chat']['id'], $mid);
                $this->ids  = [0 => $mid];
                $this->sent = [];
                return true;
            }
        }

        if ($final && !$this->adopted && ($rid = Rich::reply($this->msg, substr_count($text, '```') % 2 === 1 ? $text . "\n```" : $text)) !== null) {
            foreach ($this->ids as $old) {
                $tg->call('deleteMessage', ['chat_id' => $this->msg['chat']['id'], 'message_id' => $old], 8);
            }
            $this->ids  = [0 => $rid];
            $this->sent = [];
            return true;
        }

        foreach ($parts as $i => $part) {
            if (substr_count($part, '```') % 2 === 1) {
                $part .= "\n```";
            }
            $html = AI::toHtml($part) . ($i === $last && !$final && mb_strlen($part) <= 4094 ? ' ▍' : '');
            if (($this->sent[$i] ?? null) === $html) {
                continue;
            }
            if (!isset($this->ids[$i])) {
                $r = $tg->reply($this->msg, $html);
                if (empty($r['ok']) || !isset($r['result']['message_id'])) {
                    return false;
                }
                $this->ids[$i] = (int) $r['result']['message_id'];
            } else {
                $r = $i === 0 ? $this->editMsg($this->ids[0], $html) : $tg->editMessage($this->msg['chat']['id'], $this->ids[$i], $html);
                if (empty($r['ok']) && stripos((string) ($r['description'] ?? ''), 'not modified') === false) {
                    return false;
                }
            }
            $this->sent[$i] = $html;
        }
        return true;
    }
}
