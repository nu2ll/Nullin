<?php

final class VoicePick
{
    public const DEFAULT = 'Puck';

    private const NS       = 'voice';
    private const PER_PAGE = 10;

    // ۳۰ صدای رسمی Gemini TTS: اسم => [ایموجی، ویژگی]
    // ویژگی‌ها: ترجمه‌ی توصیف رسمی گوگل (ai.google.dev/gemini-api/docs/speech-generation)
    // جنسیت: طبق جدول رسمی Google Cloud (cloud.google.com/text-to-speech/docs/gemini-tts)؛ 👩 زن، 👨 مرد
    // لدا تنها صدایی‌ه که گوگل «Youthful» (جوان) نوشته، برای همین 👧
    public const VOICES = [
        'Zephyr'         => ['👩', 'روشن'],            // Bright
        'Puck'           => ['👨', 'سرزنده'],          // Upbeat
        'Charon'         => ['👨', 'آموزنده'],         // Informative
        'Kore'           => ['👩', 'محکم'],            // Firm
        'Fenrir'         => ['👨', 'پرشور'],           // Excitable
        'Leda'           => ['👧', 'جوان'],            // Youthful
        'Orus'           => ['👨', 'محکم'],            // Firm
        'Aoede'          => ['👩', 'سبک و راحت'],      // Breezy
        'Callirrhoe'     => ['👩', 'راحت'],            // Easy-going
        'Autonoe'        => ['👩', 'روشن'],            // Bright
        'Enceladus'      => ['👨', 'نفس‌دار'],         // Breathy
        'Iapetus'        => ['👨', 'شفاف'],            // Clear
        'Umbriel'        => ['👨', 'راحت'],            // Easy-going
        'Algieba'        => ['👨', 'نرم'],             // Smooth
        'Despina'        => ['👩', 'نرم'],             // Smooth
        'Erinome'        => ['👩', 'شفاف'],            // Clear
        'Algenib'        => ['👨', 'خش‌دار'],          // Gravelly
        'Rasalgethi'     => ['👨', 'آموزنده'],         // Informative
        'Laomedeia'      => ['👩', 'سرزنده'],          // Upbeat
        'Achernar'       => ['👩', 'لطیف'],            // Soft
        'Alnilam'        => ['👨', 'محکم'],            // Firm
        'Schedar'        => ['👨', 'یکدست'],           // Even
        'Gacrux'         => ['👩', 'پخته'],            // Mature
        'Pulcherrima'    => ['👩', 'رک'],              // Forward
        'Achird'         => ['👨', 'دوستانه'],         // Friendly
        'Zubenelgenubi'  => ['👨', 'خودمونی'],         // Casual
        'Vindemiatrix'   => ['👩', 'ملایم'],           // Gentle
        'Sadachbia'      => ['👨', 'پرجنب‌وجوش'],      // Lively
        'Sadaltager'     => ['👨', 'دانا'],            // Knowledgeable
        'Sulafat'        => ['👩', 'گرم'],             // Warm
    ];

    public static function valid(?string $name): ?string
    {
        return ($name !== null && isset(self::VOICES[$name])) ? $name : null;
    }

    public static function get(int $uid): string
    {
        if ($uid <= 0) {
            return self::DEFAULT;
        }
        try {
            return self::valid((string) Store::get(self::NS, (string) $uid)) ?? self::DEFAULT;
        } catch (\Throwable $e) {
            return self::DEFAULT;
        }
    }

    public static function panel(int $uid, int $page = 0): array
    {
        $names = array_keys(self::VOICES);
        $pages = (int) ceil(count($names) / self::PER_PAGE);
        $page  = max(0, min($page, $pages - 1));
        $cur   = self::get($uid);

        $rows = [];
        foreach (array_chunk(array_slice($names, $page * self::PER_PAGE, self::PER_PAGE), 2) as $pair) {
            $row = [];
            foreach ($pair as $n) {
                [$e, $d] = self::VOICES[$n];
                $row[]   = btn("{$e} {$n} · {$d}", "vc:{$uid}:s:{$n}", $n === $cur ? 'success' : null);
            }
            $rows[] = $row;
        }

        $nav = [];
        if ($page > 0) {
            $nav[] = btn('◀️ قبلی', "vc:{$uid}:p:" . ($page - 1), null);
        }
        $nav[] = btn(fa($page + 1) . ' / ' . fa($pages), 'noop', null);
        if ($page < $pages - 1) {
            $nav[] = btn('بعدی ▶️', "vc:{$uid}:p:" . ($page + 1), null);
        }
        $rows[] = $nav;
        $rows[] = [btn('🔄 برگشت به صدای پیش‌فرض', "vc:{$uid}:d", null)];

        [$e, $d] = self::VOICES[$cur];
        $text = "🎙 <b>صدای نالینی</b>\n\n"
            . "صدای فعلی تو: <b>{$e} {$cur}</b> · {$d}\n\n"
            . 'یکی از صداها رو بزن تا همون لحظه فعال بشه. '
            . 'از این به بعد تو هر چتی (پیوی، گروه، مهمان و...) وقتی نالینی برای تو ویس بفرسته، با همین صدا حرف می‌زنه.';

        return [$text, ikb($rows)];
    }

    public static function onCallback(array $cq, string $data): void
    {
        $tg  = App::tg();
        $m   = $cq['message'] ?? null;
        $uid = (int) ($cq['from']['id'] ?? 0);

        if (!is_array($m) || !isset($m['chat']['id'], $m['message_id']) || !preg_match('/^vc:(\d+):(p|s|d)(?::(\w+))?$/', $data, $x)) {
            $tg->answer($cq);
            return;
        }
        if ((int) $x[1] !== $uid) {
            $tg->alert($cq, '🔒 این پنل مال کسیه که /voice زده؛ خودت /voice بزن.');
            return;
        }

        $chatId = (int) $m['chat']['id'];
        $msgId  = (int) $m['message_id'];
        $arg    = $x[3] ?? '';
        $page   = 0;

        if ($x[2] === 'p') {
            $page = (int) $arg;
            $tg->answer($cq);
        } elseif ($x[2] === 'd') {
            Store::delete(self::NS, (string) $uid);
            $tg->answer($cq, '🔄 صدا به حالت پیش‌فرض برگشت');
        } else {
            $picked = self::valid($arg);
            if ($picked === null) {
                $tg->answer($cq);
                return;
            }
            Store::set(self::NS, (string) $uid, $picked);
            $tg->answer($cq, "✅ {$picked} انتخاب شد");
            $page = intdiv((int) array_search($picked, array_keys(self::VOICES), true), self::PER_PAGE);
        }

        [$text, $kb] = self::panel($uid, $page);
        $tg->editMessage($chatId, $msgId, $text, $kb);
    }
}
