<?php

final class LiveReply
{
    public const DRAWING = '🎨 دارم می‌کشمش، چند لحظه صبر کن...';

    public const THINKING = '🤔 نالینی داره فکر می‌کنه...';

    private array $ids    = [];
    private array $sent   = [];
    private string $mode  = 'wait';
    private int $lastLen  = 0;
    private int $lastTyping = 0;
    private bool $draft   = false;
    private int $draftId  = 0;
    private bool $canStop = true;
    private int $genSaved = 0;

    public function __construct(private array $msg, private ?string $imid = null, private string $head = '', private bool $reaction = true, private bool $status = false)
    {
    }

    public function start(): void
    {
        if (!$this->status || $this->imid !== null || $this->ids !== [] || $this->draft) {
            return;
        }
        $tg = App::tg();
        $cid = $this->msg['chat']['id'];

        if (($this->msg['chat']['type'] ?? '') === 'private') {
            $this->draftId = mt_rand(1, 2147483646);
            if ($this->sendDraft(self::frame(1))) {
                $this->draft = true;
                $this->saveGen('', true);
                return;
            }
        }

        $this->typing();
        $r = $tg->reply($this->msg, self::frame(1));
        if (empty($r['ok']) || !isset($r['result']['message_id'])) {
            return;
        }
        $this->ids[0] = (int) $r['result']['message_id'];
    }

    private function sendDraft(string $html): bool
    {
        $p = [
            'chat_id'    => $this->msg['chat']['id'],
            'draft_id'   => $this->draftId,
            'text'       => $html,
            'parse_mode' => 'HTML',
        ];
        if ($this->canStop) {
            $p['can_stop'] = true;
        }
        $r = App::tg()->call('sendMessageDraft', $p, 8);
        if (empty($r['ok']) && $this->canStop && !$this->draft) {
            $this->canStop = false;
            unset($p['can_stop']);
            $r = App::tg()->call('sendMessageDraft', $p, 8);
        }
        return !empty($r['ok']);
    }

    private static function genFile(int|string $chatId): string
    {
        return sys_get_temp_dir() . '/nullini_gen_' . $chatId . '.json';
    }

    private function saveGen(string $text, bool $force = false): void
    {
        if (!$force && time() - $this->genSaved < 1) {
            return;
        }
        $this->genSaved = time();
        @file_put_contents(self::genFile($this->msg['chat']['id']), json_encode(['pid' => getmypid(), 'draft' => $this->draftId, 'text' => $text], JSON_UNESCAPED_UNICODE));
    }

    private function clearGen(): void
    {
        if ($this->draft) {
            @unlink(self::genFile($this->msg['chat']['id']));
        }
    }

    public static function onStop(array $s): void
    {
        $cid = $s['chat']['id'] ?? $s['chat_id'] ?? $s['from']['id'] ?? null;
        if (!$cid) {
            return;
        }
        $f = self::genFile($cid);
        $d = is_file($f) && time() - (int) @filemtime($f) < 120 ? json_decode((string) @file_get_contents($f), true) : null;
        if (!is_array($d)) {
            return;
        }
        if (isset($s['draft_id']) && (int) $s['draft_id'] !== (int) ($d['draft'] ?? 0)) {
            return;
        }
        @unlink($f);
        $pid = (int) ($d['pid'] ?? 0);
        if ($pid > 1 && $pid !== getmypid() && function_exists('posix_kill')) {
            @posix_kill($pid, 9);
        }
        $text = trim((string) ($d['text'] ?? ''));
        App::tg()->send($cid, $text !== '' ? AI::toHtml(mb_substr($text, 0, 3900)) : '⛔ متوقف شد.');
        if (($s['chat']['type'] ?? 'private') === 'private' && !App::isAdmin((int) $cid)) {
            Assistant::holdDots((int) $cid, (int) $cid);
        }
    }

    private static function frame(int $n): string
    {
        return self::THINKING;
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
        if ($this->mode === 'silent') {
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
        $this->mode = 'live';
        $this->show($body);
    }

    public function finish(string $answer): bool
    {
        if ($this->draft) {
            $ok = $this->sync($answer, true);
            $this->clearGen();
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
        if (!isset($this->ids[0])) {
            return false;
        }
        $r = App::tg()->editMessage($this->msg['chat']['id'], $this->ids[0], $html);
        if (!empty($r['ok']) || stripos((string) ($r['description'] ?? ''), 'not modified') !== false) {
            return true;
        }
        $this->discard();
        return false;
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
        $this->clearGen();
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
            $this->saveGen($text);
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
            $tg->editMessage($this->msg['chat']['id'], $this->ids[0], $html);
        }
    }

    private function sync(string $text, bool $final): bool
    {
        $tg    = App::tg();
        $parts = AI::chunks($text);
        $last  = count($parts) - 1;

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
                $r = $tg->editMessage($this->msg['chat']['id'], $this->ids[$i], $html);
                if (empty($r['ok']) && stripos((string) ($r['description'] ?? ''), 'not modified') === false) {
                    return false;
                }
            }
            $this->sent[$i] = $html;
        }
        return true;
    }
}
