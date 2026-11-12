<?php

final class VoicePick
{
    public const DEFAULT = 'Puck';

    private const NS       = 'voice';
    private const PER_PAGE = 5;

    // name => [emoji, fa, en]
    public const VOICES = [
        'Zephyr'         => ['👩', 'روشن', 'Bright'],
        'Puck'           => ['👨', 'سرزنده', 'Upbeat'],
        'Charon'         => ['👨', 'آموزنده', 'Informative'],
        'Kore'           => ['👩', 'محکم', 'Firm'],
        'Fenrir'         => ['👨', 'پرشور', 'Excitable'],
        'Leda'           => ['👧', 'جوان', 'Youthful'],
        'Orus'           => ['👨', 'محکم', 'Firm'],
        'Aoede'          => ['👩', 'سبک و راحت', 'Breezy'],
        'Callirrhoe'     => ['👩', 'راحت', 'Easy-going'],
        'Autonoe'        => ['👩', 'روشن', 'Bright'],
        'Enceladus'      => ['👨', 'نفس‌دار', 'Breathy'],
        'Iapetus'        => ['👨', 'شفاف', 'Clear'],
        'Umbriel'        => ['👨', 'راحت', 'Easy-going'],
        'Algieba'        => ['👨', 'نرم', 'Smooth'],
        'Despina'        => ['👩', 'نرم', 'Smooth'],
        'Erinome'        => ['👩', 'شفاف', 'Clear'],
        'Algenib'        => ['👨', 'خش‌دار', 'Gravelly'],
        'Rasalgethi'     => ['👨', 'آموزنده', 'Informative'],
        'Laomedeia'      => ['👩', 'سرزنده', 'Upbeat'],
        'Achernar'       => ['👩', 'لطیف', 'Soft'],
        'Alnilam'        => ['👨', 'محکم', 'Firm'],
        'Schedar'        => ['👨', 'یکدست', 'Even'],
        'Gacrux'         => ['👩', 'پخته', 'Mature'],
        'Pulcherrima'    => ['👩', 'رک', 'Forward'],
        'Achird'         => ['👨', 'دوستانه', 'Friendly'],
        'Zubenelgenubi'  => ['👨', 'خودمونی', 'Casual'],
        'Vindemiatrix'   => ['👩', 'ملایم', 'Gentle'],
        'Sadachbia'      => ['👨', 'پرجنب‌وجوش', 'Lively'],
        'Sadaltager'     => ['👨', 'دانا', 'Knowledgeable'],
        'Sulafat'        => ['👩', 'گرم', 'Warm'],
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
        foreach (array_slice($names, $page * self::PER_PAGE, self::PER_PAGE) as $n) {
            [$e, $dFa, $dEn] = self::VOICES[$n];
            $d       = L($dFa, $dEn);
            $rows[]  = [btn("{$e} {$n} · {$d}", "vc:{$uid}:s:{$n}", $n === $cur ? 'success' : null)];
        }

        $nav = [];
        if ($page > 0) {
            $nav[] = btn(L('◀️ قبلی', '◀️ Previous'), "vc:{$uid}:p:" . ($page - 1), null);
        }
        $nav[] = btn(fa($page + 1) . ' / ' . fa($pages), 'noop', null);
        if ($page < $pages - 1) {
            $nav[] = btn(L('بعدی ▶️', 'Next ▶️'), "vc:{$uid}:p:" . ($page + 1), null);
        }
        $rows[] = $nav;

        [$e, $dFa, $dEn] = self::VOICES[$cur];
        $d    = L($dFa, $dEn);
        $text = L(
            "🎙 <b>صدای نالین</b>\n\nصدای فعلی تو: <b>{$e} {$cur}</b> · {$d}\n\nیکی از صداها رو بزن تا همون لحظه فعال بشه.\nاز این به بعد تو هر چتی (پیوی، گروه، مهمان و...) \nوقتی نالین برای تو ویس بفرسته، با همین حرف می‌زنه.",
            "🎙 <b>Nullin’s voice</b>\n\nYour current voice: <b>{$e} {$cur}</b> · {$d}\n\nTap a voice to switch to it right away.\nFrom now on, in every chat (private, group, guest and so on),\nthis is the voice Nullin will use when it sends you a voice message."
        );

        return [$text, ikb($rows)];
    }

    public static function onCallback(array $cq, string $data): void
    {
        $tg  = App::tg();
        $m   = $cq['message'] ?? null;
        $uid = (int) ($cq['from']['id'] ?? 0);

        if (!is_array($m) || !isset($m['chat']['id'], $m['message_id']) || !preg_match('/^vc:(\d+):(p|s):(\w+)$/', $data, $x)) {
            $tg->answer($cq);
            return;
        }
        if ((int) $x[1] !== $uid) {
            $tg->answer($cq);
            return;
        }

        if ($x[2] === 'p') {
            $page = (int) $x[3];
            $tg->answer($cq);
        } else {
            $voice = self::valid($x[3]);
            if ($voice === null) {
                $tg->answer($cq);
                return;
            }
            Store::set(self::NS, (string) $uid, $voice);
            $tg->answer($cq, L("✅ {$voice} انتخاب شد", "✅ {$voice} selected"));
            $page = intdiv((int) array_search($voice, array_keys(self::VOICES), true), self::PER_PAGE);
        }

        [$text, $kb] = self::panel($uid, $page);
        Rich::edit((int) $m['chat']['id'], (int) $m['message_id'], $text, $kb);
    }
}
