<?php

final class Switches
{
    public const TOOLS = [
        'ai'       => ['🧠 هوش مصنوعی', '🧠 AI'],
        'image'    => ['🎨 ساخت عکس', '🎨 Image creation'],
        'translate' => ['🌐 ترجمه', '🌐 Translate'],
        'search'   => ['🔍 سرچ گوگل', '🔍 Google search'],
        'wiki'     => ['📖 ویکی‌پدیا', '📖 Wikipedia'],
        'weather'  => ['🌦 آب‌وهوا', '🌦 Weather'],
        'rates'    => ['💱 نرخ ارز و طلا', '💱 Exchange rates & gold'],
        'download' => ['⬇️ دانلودر', '⬇️ Downloader'],
        'linkcheck' => ['🛡 ردیاب اشتراک', '🛡 Invite link tracker'],
        'business' => ['🤝 منشی هوشمند', '🤝 Smart secretary'],
        'whisper'  => ['🤫 نجوا', '🤫 Whisper'],
        'giveaway' => ['🎁 قرعه‌کشی', '🎁 Giveaway'],
        'like'     => ['❤️ لایک‌ساز', '❤️ Like maker'],
        'font'     => ['✨ فونت‌ساز', '✨ Font maker'],
        'qr'       => ['🔳 QR کد', '🔳 QR code'],
        'short'    => ['🔗 کوتاه‌کننده لینک', '🔗 Link shortener'],
        'calc'     => ['🧮 ماشین‌حساب', '🧮 Calculator'],
        'love'     => ['💘 تطابق عشقی', '💘 Love match'],
        'roast'    => ['🔥 روست', '🔥 Roast'],
        'nid'      => ['🪪 کد ملی', '🪪 National ID'],
        'unit'     => ['⚖️ تبدیل واحد', '⚖️ Unit converter'],
        'userinfo' => ['👤 اطلاعات کاربر', '👤 User info'],
        'funstat'  => ['🔍 استعلام کاربر', '🔍 User lookup'],
        'fal'      => ['🔮 فال حافظ', '🔮 Hafez fortune'],
        'password' => ['🔑 پسورد ساز', '🔑 Password maker'],
        'date'     => ['📅 تاریخ و ساعت', '📅 Date & time'],
        'coin'     => ['🪙 شیر یا خط', '🪙 Coin flip'],
        'chess'    => ['♟️ شطرنج', '♟️ Chess'],
        'ttt'      => ['⭕ دوز', '⭕ Tic-tac-toe'],
        'c4'       => ['🔴 دوز سکه‌ای', '🔴 Connect Four'],
        'rps'      => ['✊ سنگ کاغذ قیچی', '✊ Rock paper scissors'],
        'chop'     => ['🖐 چاپ‌استیک', '🖐 Chopsticks'],
        'math'     => ['🔢 دوئل ریاضی', '🔢 Math duel'],
    ];

    public const COMMAND_SWITCH = [
        'img' => 'image', 'image' => 'image',
        'tr'  => 'translate', 'translate' => 'translate',
        'ft'  => 'font', 'font' => 'font',
        'gw'  => 'giveaway', 'giveaway' => 'giveaway',
        'wk'  => 'wiki', 'wiki' => 'wiki',
        'st' => 'linkcheck',
        'monshi' => 'business',
    ];

    private const GAME_KEYS = ['chess', 'ttt', 'c4', 'rps', 'chop', 'math'];

    private static function load(): array
    {
        $raw = (array) Store::get('cfg', 'tools_off', []);
        if (in_array('games', $raw, true)) {
            $raw = array_merge($raw, self::GAME_KEYS);
        }
        return array_values(array_unique(array_filter($raw, static fn($k) => isset(self::TOOLS[$k]))));
    }

    public static function label(string $key): string
    {
        $v = self::TOOLS[$key] ?? [$key, $key];
        return L($v[0], $v[1]);
    }

    public static function on(string $key): bool
    {
        return !isset(self::TOOLS[$key]) || !in_array($key, self::load(), true);
    }

    public static function toggle(string $key): void
    {
        if (!isset(self::TOOLS[$key])) {
            return;
        }
        $off = self::load();
        $off = in_array($key, $off, true) ? array_values(array_diff($off, [$key])) : array_merge($off, [$key]);
        Store::set('cfg', 'tools_off', $off);
    }

    public static function setAll(bool $on): void
    {
        $off = $on ? [] : array_keys(self::TOOLS);
        Store::set('cfg', 'tools_off', $off);
    }

    public static function syncCommands(): void
    {
        try {
            foreach (App::tokens() as $bi => $_) {
                $tg = App::tgFor($bi);
                Ui::pushCommands($tg, 'private', ['type' => 'all_private_chats']);
                Ui::pushCommands($tg, 'group', ['type' => 'all_group_chats']);
                foreach ((array) App::cfg('admins', []) as $adminId) {
                    if ((int) $adminId > 0) {
                        Ui::pushCommands($tg, 'admin', ['type' => 'chat', 'chat_id' => (int) $adminId]);
                    }
                }
            }
        } catch (\Throwable $e) {
            logError('tools', 'syncCommands: ' . $e->getMessage());
        }
    }

    public static function guard(string $key): bool
    {
        return self::on($key);
    }

    public static function text(): string
    {
        $off = self::load();
        return L("<b>🧰 مدیریت ابزارها</b>\n\nروی هر دکمه بزنی روشن/خاموش می‌شه.\nسبز = روشن · قرمز = خاموش", "<b>🧰 Tool management</b>\n\nTap a button to switch it on/off.\nGreen = on · Red = off")
            . ($off ? L("\n\nخاموش: ", "\n\nOff: ") . fa((string) count($off)) . L(' از ', ' of ') . fa((string) count(self::TOOLS)) : '');
    }

    public static function keyboard(): array
    {
        $rows = [];
        $row  = [];
        foreach (array_keys(self::TOOLS) as $k) {
            $row[] = btn(self::label($k), 'tl:t:' . $k, self::on($k) ? 'success' : 'danger');
            if (count($row) === 2) {
                $rows[] = $row;
                $row    = [];
            }
        }
        if ($row) {
            $rows[] = $row;
        }
        $rows[] = [btn(L('روشن‌کردن همه', 'Turn all on'), 'tl:all:1', null), btn(L('خاموش‌کردن همه', 'Turn all off'), 'tl:all:0', null)];
        return ikb($rows);
    }

    public static function onCallback(array $cq, string $data): void
    {
        $tg = App::tg();
        if (!App::isAdmin((int) ($cq['from']['id'] ?? 0))) {
            $tg->alert($cq, L('🙅 این پنل مخصوص ادمینه.', '🙅 This panel is for admins only.'));
            return;
        }
        $chatId = $cq['message']['chat']['id'] ?? null;
        $msgId  = $cq['message']['message_id'] ?? null;
        $p      = explode(':', $data);
        if ($p[1] === 't' && isset($p[2])) {
            self::toggle($p[2]);
        } elseif (($p[1] ?? '') === 'all') {
            self::setAll(($p[2] ?? '1') === '1');
        }
        if ($chatId !== null && $msgId !== null) {
            Rich::edit($chatId, $msgId, self::text(), self::keyboard());
        }
        $tg->answer($cq);
        self::syncCommands();
    }
}
