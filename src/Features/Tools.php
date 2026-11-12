<?php

final class Tools
{
    private const SETS = [
        'U' => 'ABCDEFGHJKLMNPQRSTUVWXYZ',
        'L' => 'abcdefghijkmnopqrstuvwxyz',
        'D' => '23456789',
        'S' => '!@#$%^&*-_=+?',
    ];

    public static function password(int $length): string
    {
        $length = max(6, min($length, 64));
        $all    = implode('', self::SETS);
        $chars  = [];
        foreach (self::SETS as $set) {
            $chars[] = $set[random_int(0, strlen($set) - 1)];
        }
        while (count($chars) < $length) {
            $chars[] = $all[random_int(0, strlen($all) - 1)];
        }
        for ($i = count($chars) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$chars[$i], $chars[$j]] = [$chars[$j], $chars[$i]];
        }
        return implode('', $chars);
    }

    public static function passwordText(int $length): string
    {
        return L("<b>🔑 پسورد ساز</b>\n\n<code>", "<b>🔑 Password maker</b>\n\n<code>") . h(self::password($length)) . "</code>\n\n"
            . L('📏 طول: ', '📏 Length: ') . fa($length) . L(' · 💪 قدرت: خیلی قوی', ' · 💪 Strength: very strong') . L("\n<i>روی پسورد بزن تا کپی بشه</i>", "\n<i>Tap the password to copy it</i>");
    }

    public static function passwordKeyboard(): array
    {
        $row = [];
        foreach ([8, 12, 16, 24, 32] as $n) {
            $row[] = btn(fa($n), "pass:{$n}");
        }
        return ikb([$row]);
    }

    public static function coinKeyboard(): array
    {
        return ikb([[btn(L('🪙 بنداز!', '🪙 Flip!'), 'coin:flip', 'success')]]);
    }

    public static function coinResult(): string
    {
        $r = random_int(0, 1) ? L('🦁 <b>شیر</b>', '🦁 <b>Heads</b>') : L('➖ <b>خط</b>', '➖ <b>Tails</b>');
        return L("<b>🪙 سکه</b>\n\nسکه چرخید و چرخید و... {$r} اومد!", "<b>🪙 Coin</b>\n\nThe coin spun and spun and... {$r}!");
    }

    public static function hafezUrl(int $n = 0): string
    {
        return 'https://api.codebazan.ir/fal/hafez/' . ($n > 0 ? $n : random_int(1, 149)) . '.gif';
    }

    public static function hafezPhoto(): ?string
    {
        $n  = (string) random_int(1, 149);
        $id = Store::get('hafez', $n);
        if (is_string($id) && $id !== '') {
            return $id;
        }
        $r = Http::request('GET', self::hafezUrl((int) $n), ['timeout' => 20]);
        if ($r['code'] !== 200 || $r['body'] === '') {
            return null;
        }
        $in  = (string) tempnam(sys_get_temp_dir(), 'nl_hf_');
        $out = $in . '.jpg';
        file_put_contents($in, $r['body']);
        @exec('timeout 20 ffmpeg -y -v error -i ' . escapeshellarg($in) . ' -frames:v 1 ' . escapeshellarg($out) . ' 2>/dev/null', $o, $rc);
        $bytes = is_file($out) ? file_get_contents($out) : false;
        @unlink($in);
        @unlink($out);
        if ($bytes === false || $bytes === '') {
            return null;
        }
        $id = ImageGen::fileId($bytes);
        if ($id !== null) {
            Store::set('hafez', $n, $id);
        }
        return $id;
    }

    public static function hafezCaption(): string
    {
        return L('🔮 <b>فال حافظ شما</b>', '🔮 <b>Your Hafez fortune</b>') . L("\n<i>نیت کن، بعد بخون...</i>", "\n<i>Make a wish, then read...</i>");
    }

    public static function hafezKeyboard(): array
    {
        return ikb([[btn(L('🔄 یه فال دیگه', '🔄 Another fortune'), 'fal:refresh')]]);
    }

    public static function showHafez(string $imid): ?array
    {
        return Rich::editInline($imid, self::hafezCaption(), self::hafezKeyboard(), self::hafezPhoto());
    }

    public static function normalizeNid(string $raw): ?string
    {
        $digits = preg_replace('/\D/', '', en($raw)) ?? '';
        if ($digits === '' || strlen($digits) > 10 || strlen($digits) < 8) {
            return null;
        }
        return str_pad($digits, 10, '0', STR_PAD_LEFT);
    }

    public static function isValidNid(string $code): bool
    {
        if (!preg_match('/^\d{10}$/', $code) || preg_match('/^(\d)\1{9}$/', $code)) {
            return false;
        }
        $sum = 0;
        for ($i = 0; $i < 9; $i++) {
            $sum += (int) $code[$i] * (10 - $i);
        }
        $rem   = $sum % 11;
        $check = (int) $code[9];
        return $rem < 2 ? $check === $rem : $check === 11 - $rem;
    }

    public static function nidDisplay(string $code): string
    {
        return "\u{2066}" . substr($code, 0, 3) . '-' . substr($code, 3, 6) . '-' . $code[9] . "\u{2069}";
    }

    public static function nidText(string $code): string
    {
        $ok = self::isValidNid($code);
        return L("<b>🪪 بررسی کد ملی</b>\n\n", "<b>🪪 National ID check</b>\n\n")
            . L('کد: <code>', 'Code: <code>') . self::nidDisplay($code) . "</code>\n"
            . ($ok ? L('✅ ساختار این کد <b>درسته</b>.', '✅ The structure of this code is <b>valid</b>.') : L('❌ ساختار این کد <b>اشتباهه</b>.', '❌ The structure of this code is <b>wrong</b>.')) . "\n\n"
            . L("<i>⚠️ فقط رقم کنترل بررسی شده،\nنه ثبت واقعی در ثبت‌احوال.</i>", "<i>⚠️ Only the check digit was verified,\nnot the actual civil registry.</i>");
    }

    public static function qrUrl(string $text, int $size = 512): string
    {
        return 'https://api.qrserver.com/v1/create-qr-code/?' . http_build_query([
            'size' => "{$size}x{$size}", 'margin' => 12, 'format' => 'jpg', 'data' => $text,
        ]);
    }

    public static function dateText(string $place = ''): string
    {
        return Clock::text($place);
    }
}
