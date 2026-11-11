<?php

final class ChosenHandler
{
    public const OWNED = ['calc', 'unit', 'coin', 'fal', 'pass'];

    private const GAMES = ['rps' => 'rps', 'ttt' => 'ttt', 'c4' => 'c4', 'math' => 'math', 'chess' => 'chess'];

    private const GAME_CLASS = ['rps' => Rps::class, 'ttt' => TicTacToe::class, 'c4' => ConnectFour::class, 'math' => MathDuel::class, 'chess' => Chess::class];

    public static function handle(array $c): void
    {
        $id   = (string) ($c['result_id'] ?? '');
        $imid = $c['inline_message_id'] ?? null;
        $from = $c['from'] ?? [];
        if (!$imid || empty($from['id'])) {
            return;
        }

        Users::hit('inline:' . (strstr($id, '_', true) ?: $id));

        if (in_array($id, self::OWNED, true)) {
            Store::set('owner', $imid, (int) $from['id']);
        }
        if ($id === 'fal') {
            Tools::showHafez($imid);
        }
        if ($id === 'unit') {
            $s = UnitConvert::initial();
            Rich::editInline($imid, UnitConvert::render($s), UnitConvert::keyboard($s));
        }
        if ($id === 'calc') {
            Rich::editInline($imid, Calculator::render('0'), Calculator::keyboard());
        }
        if ($id === 'coin') {
            Rich::editInline($imid, "<b>🪙 سکه</b>\n\nآماده‌ی پرتاب! 👇", Tools::coinKeyboard());
        }
        if ($id === 'pass') {
            Rich::editInline($imid, Tools::passwordText(16), Tools::passwordKeyboard());
        }
        if (isset(self::GAMES[$id])) {
            GameKit::save($imid, GameKit::fresh(self::GAMES[$id], GameKit::player($from)));
            $cls = self::GAME_CLASS[$id];
            Rich::editInline($imid, $cls::intro(), $cls::modeKeyboard());
        }
        if (preg_match('/^(ai|tr)q_(\w+)$/', $id, $m)) {
            InlineHandler::resolveDeferred($m[2], $imid);
        }
        if (str_starts_with($id, 'rs_')) {
            Roast::resolve(substr($id, 3), $imid);
        }
        if (str_starts_with($id, 'st_')) {
            LinkCheck::resolveInline(substr($id, 3), $imid);
        }
        if (str_starts_with($id, 'lv_')) {
            Love::resolve(substr($id, 3), $imid);
        }
        if (str_starts_with($id, 'gwi_')) {
            Giveaway::onChosen(substr($id, 4), $imid, $from);
        }
        if (str_starts_with($id, 'lk_')) {
            Likes::onChosen(substr($id, 3), $imid);
        }
        if (str_starts_with($id, 'wk_')) {
            Wiki::onChosen($id, $imid, (int) $from['id']);
        }
        if (str_starts_with($id, 'wh_')) {
            Whisper::bind(substr($id, 3), $imid);
        }
        if (str_starts_with($id, 'pc_')) {
            PriceCard::onChosen(substr($id, 3), $imid);
        }
        if (str_starts_with($id, 'wt_')) {
            $city = Store::get('weather_pending', substr($id, 3));
            if (is_string($city)) {
                Store::set('weather', $imid, $city);
            }
        }
    }
}
