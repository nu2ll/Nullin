<?php

final class AnswerButtons
{
    private const NS        = 'aibtn';
    private const VOICE_MAX = 2500;

    public static function plain(string $answer): string
    {
        return trim((string) preg_replace('/\n\n🔗 منابع:.*$/su', '', $answer));
    }

    public static function keyboard(bool $searched, bool $voice = true, bool $noSearch = false): array
    {
        $row = [];
        if (!$searched && !$noSearch && WebSearch::available()) {
            $row[] = btn('🔎', 'ab:s', 'primary');
        }
        if ($voice) {
            $row[] = btn('🔊', 'ab:v', 'success');
        }

        return ['inline_keyboard' => $row ? [$row] : []];
    }

    public static function retryKeyboard(): array
    {
        return ['inline_keyboard' => [[btn('🔄', 'ab:r', 'danger')]]];
    }

    public static function searchOnly(): ?array
    {
        return WebSearch::available() ? ['inline_keyboard' => [[btn('🔎', 'ab:s', 'primary')]]] : null;
    }

    public static function attachInline(string $imid, array $rec): void
    {
        if ($imid === '' || trim((string) ($rec['a'] ?? '')) === '' || trim((string) ($rec['q'] ?? '')) === '') {
            return;
        }
        $rec['a']  = mb_substr((string) $rec['a'], 0, 12000);
        $rec['q']  = mb_substr((string) $rec['q'], 0, 4000);
        $rec['b']  = App::botIndex();
        $rec['in'] = 1;
        Store::set(self::NS, 'i:' . $imid, $rec);
    }

    public static function attach(int $chatId, int $mid, array $rec, bool $withMarkup = true): void
    {
        if ($mid <= 0 || trim((string) ($rec['a'] ?? '')) === '') {
            return;
        }
        $rec['a'] = mb_substr((string) $rec['a'], 0, 12000);
        $rec['q'] = mb_substr((string) ($rec['q'] ?? ''), 0, 4000);
        $rec['b'] = App::botIndex();
        Store::set(self::NS, $chatId . ':' . $mid, $rec);
        if ($withMarkup) {
            App::tg()->call('editMessageReplyMarkup', [
                'chat_id'      => $chatId,
                'message_id'   => $mid,
                'reply_markup' => !empty($rec['f']) ? self::retryKeyboard() : self::keyboard(!empty($rec['sr']), empty($rec['vo']), !empty($rec['ns'])),
            ], 8);
        }
    }

    public static function sweep(int $budget = 100): int
    {
        $end  = microtime(true) + $budget;
        $done = 0;
        $keys = Db::run('SELECT k FROM kv WHERE ns = ? AND updated_at < ? LIMIT 3000', [self::NS, Store::dayStart()])->fetchAll(PDO::FETCH_COLUMN);
        foreach ($keys as $k) {
            if (microtime(true) > $end) {
                break;
            }
            $rec = Store::get(self::NS, (string) $k);
            $tgx = App::tgFor((int) (is_array($rec) ? ($rec['b'] ?? 0) : 0));
            if (str_starts_with((string) $k, 'i:')) {
                $r = $tgx->call('editMessageReplyMarkup', [
                    'inline_message_id' => substr((string) $k, 2),
                    'reply_markup'      => ['inline_keyboard' => []],
                ], 5);
            } else {
                [$chat, $mid] = array_pad(explode(':', (string) $k, 2), 2, '0');
                $r = $tgx->call('editMessageReplyMarkup', [
                    'chat_id'      => (int) $chat,
                    'message_id'   => (int) $mid,
                    'reply_markup' => ['inline_keyboard' => []],
                ], 5);
            }
            if ((int) ($r['error_code'] ?? 0) === 429) {
                break;
            }
            Store::delete(self::NS, (string) $k);
            $done++;
            usleep(60000);
        }
        return $done;
    }

    public static function onCallback(array $cq): void
    {
        $tg   = App::tg();
        $imid = (string) ($cq['inline_message_id'] ?? '');
        if ($imid !== '') {
            self::onInline($cq, $imid);
            return;
        }
        $m = $cq['message'] ?? null;
        if (!is_array($m) || !isset($m['chat']['id'], $m['message_id']) || !Switches::on('ai')) {
            $tg->answer($cq);
            return;
        }

        $chatId = (int) $m['chat']['id'];
        $mid    = (int) $m['message_id'];
        $uid    = (int) ($cq['from']['id'] ?? 0);
        $act    = substr((string) ($cq['data'] ?? ''), 3);
        $key    = $chatId . ':' . $mid;
        $rec    = Store::getToday(self::NS, $key);

        $late = $act === 'v' || $act === 's';
        if (!$late) {
            $tg->answer($cq);
        }
        if (!is_array($rec) || trim((string) ($rec['a'] ?? '')) === '') {
            if ($late) {
                $tg->answer($cq);
            }
            $tg->call('editMessageReplyMarkup', ['chat_id' => $chatId, 'message_id' => $mid, 'reply_markup' => ['inline_keyboard' => []]], 8);
            return;
        }

        switch ($act) {
            case 'r':
                $owner = (int) ($rec['u'] ?? 0);
                if (($owner !== 0 && $owner !== $uid) || empty($rec['f']) || !self::lock($key, 'r')) {
                    return;
                }
                try {
                    match ((string) ($rec['k'] ?? '')) {
                        'tr'    => Assistant::retryTranslate($cq, $rec),
                        'img'   => ImageGen::retry($cq, $rec),
                        default => Assistant::retry($cq, $rec),
                    };
                } finally {
                    self::unlock($key, 'r');
                }
                return;

            case 'v':
                $owner = (int) ($rec['u'] ?? 0);
                if (($owner !== 0 && $owner !== $uid) || !empty($rec['vo']) || !self::lock($key, 'v')) {
                    $tg->answer($cq);
                    return;
                }
                $rec['vo'] = 1;
                Store::set(self::NS, $key, $rec);
                self::setKeyboard($chatId, $mid, self::keyboard(!empty($rec['sr']), false, !empty($rec['ns'])));
                $voiced = self::voice($chatId, $mid, (string) $rec['a'], $uid);
                if (!$voiced) {
                    $rec['vo'] = 0;
                    Store::set(self::NS, $key, $rec);
                    self::setKeyboard($chatId, $mid, self::keyboard(!empty($rec['sr']), true, !empty($rec['ns'])));
                }
                self::unlock($key, 'v');
                $tg->answer($cq, $voiced ? null : '😕 نتونستم ویس بسازم، یه‌کم دیگه دوباره امتحان کن.');
                return;

            case 's':
                $owner = (int) ($rec['u'] ?? 0);
                if (($owner !== 0 && $owner !== $uid) || !empty($rec['ns']) || !WebSearch::available() || !empty($rec['sr']) || !self::lock($key, 's')) {
                    $tg->answer($cq);
                    return;
                }
                try {
                    Assistant::searchAgain($cq, $rec);
                } finally {
                    self::unlock($key, 's');
                }
                return;
        }
    }

    private static function onInline(array $cq, string $imid): void
    {
        $tg    = App::tg();
        $key   = 'i:' . $imid;
        $rec   = Store::getToday(self::NS, $key);
        $rm    = static fn() => $tg->call('editMessageReplyMarkup', ['inline_message_id' => $imid, 'reply_markup' => ['inline_keyboard' => []]], 8);
        $data  = (string) ($cq['data'] ?? '');
        $retry = $data === 'ab:r';

        if (!Switches::on('ai') || ($data !== 'ab:s' && !$retry)) {
            $tg->answer($cq);
            return;
        }
        if (!is_array($rec) || trim((string) ($rec['a'] ?? '')) === '' || trim((string) ($rec['q'] ?? '')) === '') {
            $tg->answer($cq);
            $rm();
            return;
        }
        $owner = (int) ($rec['u'] ?? 0);
        $uid   = (int) ($cq['from']['id'] ?? 0);
        $act   = $retry ? 'r' : 's';
        if (($owner !== 0 && $owner !== $uid) || ($retry ? empty($rec['f']) : !WebSearch::available()) || !self::lock($key, $act)) {
            $tg->answer($cq);
            return;
        }

        $kind = (string) ($rec['k'] ?? '');
        if ($retry && $kind === 'img') {
            $tg->answer($cq);
            Store::delete(self::NS, $key);
            try {
                GuestHandler::image($imid, null, (string) $rec['q'], $owner);
            } finally {
                self::unlock($key, $act);
            }
            return;
        }
        if ($retry && $kind === 'tr') {
            $tg->answer($cq);
            $tl = new LiveReply(['chat' => ['id' => 0, 'type' => 'inline'], 'message_id' => 0], $imid, '', false);
            $tl->watch(true);
            try {
                $out = AI::translateFast((string) $rec['q'], (string) ($rec['t'] ?? 'auto'));
            } finally {
                $tl->stop();
            }
            if ($out !== null && $out !== '') {
                Store::delete(self::NS, $key);
                $tg->editInline($imid, (string) ($rec['h'] ?? '') . h($out));
            } else {
                $tg->editInline($imid, (string) ($rec['x'] ?? '') . AI::toHtml(AI::FAIL_TEXT), self::retryKeyboard());
            }
            self::unlock($key, $act);
            return;
        }

        $head = (string) ($rec['h'] ?? '');
        $live = null;
        if ($retry) {
            $tg->answer($cq);
            $live = new LiveReply(['chat' => ['id' => 0, 'type' => 'inline'], 'message_id' => 0], $imid, $head, false);
            $live->watch(true);
        } else {
            $rm();
        }
        $done = false;
        try {
            $ctx = ['user_name' => userName($cq['from'] ?? []), 'chat_type' => 'guest'];
            if ($retry) {
                $q   = (string) $rec['q'];
                $opt = ['max_len' => PHP_INT_MAX, 'reaction' => false] + ($live && App::cfg('ai_stream', true) ? ['on_text' => $live->feed(...)] : []);
            } else {
                $q   = (string) $rec['q'] . "\n\n[دستور سیستم: این بار حتماً قبل از جواب دادن با ابزار web_search توی وب سرچ کن و جوابت رو بر اساس نتایج به‌روز بنویس.]";
                $opt = ['max_len' => PHP_INT_MAX, 'reaction' => false, 'force_search' => true];
            }
            $res = AI::reply($q, $ctx, [], $opt);
            if (!empty($res['action'])) {
                $res = AI::reply($q, ['no_action' => true] + $ctx, [], $opt);
            }
            $live?->stop();
            $searched = AI::$searched;
            $answer   = !empty($res['image']) || !empty($res['image_partial']) ? null : ($res['answer'] ?? null);
            if ($answer === null) {
                return;
            }
            $kb   = $searched ? null : self::searchOnly();
            $text = $head . AI::toHtml(AI::fit($answer, $head));
            $r    = $tg->editInline($imid, $text, $kb);
            if (empty($r['ok']) && stripos((string) ($r['description'] ?? ''), 'not modified') === false) {
                $r = $tg->editInline($imid, mb_substr(strip_tags($text), 0, 4096), $kb, false);
            }
            if (empty($r['ok']) && stripos((string) ($r['description'] ?? ''), 'not modified') === false) {
                return;
            }
            $done = true;
            if ($searched) {
                Store::delete(self::NS, $key);
            } else {
                $rec['a'] = $answer;
                unset($rec['f']);
                Store::set(self::NS, $key, $rec);
            }
        } finally {
            $live?->stop();
            if ($retry) {
                if (!$done) {
                    $tg->editInline($imid, $head . AI::toHtml(AI::FAIL_TEXT), self::retryKeyboard());
                }
            } else {
                $tg->answer($cq, $done ? null : AI::FAIL_TEXT);
                if (!$done) {
                    $tg->call('editMessageReplyMarkup', ['inline_message_id' => $imid, 'reply_markup' => self::searchOnly() ?? ['inline_keyboard' => []]], 8);
                }
            }
            self::unlock($key, $act);
        }
    }

    private static function setKeyboard(int $chatId, int $mid, array $kb): void
    {
        App::tg()->call('editMessageReplyMarkup', ['chat_id' => $chatId, 'message_id' => $mid, 'reply_markup' => $kb], 8);
    }

    private static function voice(int $chatId, int $mid, string $answer, int $uid = 0): bool
    {
        $tg = App::tg();
        $tg->action($chatId, 'record_voice');
        $file = AI::speak(mb_substr(self::plain($answer), 0, self::VOICE_MAX), null, 'ogg', VoicePick::get($uid));
        if ($file === null) {
            return false;
        }
        $r = $tg->call('sendVoice', [
            'chat_id'          => $chatId,
            'voice'            => new CURLFile($file, 'audio/ogg', 'voice.ogg'),
            'reply_markup'     => ikb([[btn('Nullini', 'noop', null)]]),
            'reply_parameters' => ['message_id' => $mid, 'allow_sending_without_reply' => true],
        ], 120);
        AI::markMsg($chatId, $r['result']['message_id'] ?? 0);
        @unlink($file);
        return !empty($r['ok']);
    }

    private static function lock(string $key, string $act): bool
    {
        return Store::update('rl', "abl_{$act}_" . md5($key), static fn($at) => (is_int($at) && time() - $at < 120) ? null : time()) !== null;
    }

    private static function unlock(string $key, string $act): void
    {
        Store::delete('rl', "abl_{$act}_" . md5($key));
    }
}
