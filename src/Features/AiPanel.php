<?php

final class AiPanel
{
    private const COLS     = ['gemini' => 1];
    private const PER_PAGE = ['gemini' => 12];

    public static function providers(): array
    {
        return (AI::keys() ? ['gemini' => 'Gemini'] : []) + FreeAI::active();
    }

    public static function label(string $route): string
    {
        [$prov, $model] = explode(':', $route, 2) + [1 => ''];
        $name = $prov === 'gemini' ? 'Gemini' : (FreeAI::PROVIDERS[$prov]['name'] ?? $prov);
        if ($model === '') {
            return $name . ' · ' . L('خودکار', 'Auto');
        }
        return $name . ' · ' . ($prov === 'gemini' ? AI::shortModel($model) : FreeAI::title($prov, $model));
    }

    private static function catalog(string $prov): array
    {
        $out = [];
        if ($prov === 'gemini') {
            foreach (AI::allModels() as $m) {
                $out[] = ['route' => 'gemini:' . $m, 'name' => AI::shortModel($m)];
            }
            return $out;
        }
        if (!isset(self::providers()[$prov])) {
            return [];
        }
        foreach (FreeAI::ordered($prov) as $id) {
            $out[] = ['route' => $prov . ':' . $id, 'name' => FreeAI::title($prov, $id)];
        }
        return $out;
    }

    public static function text(): string
    {
        $cur = AI::route();
        return L("<b>🤖 پنل هوش مصنوعی</b>\n\nمدل فعال روی همه‌ی بخش‌ها:\n<b>", "<b>🤖 AI panel</b>\n\nActive model for all features:\n<b>")
            . bidi(h(self::label($cur))) . "</b>"
            . L("\n\n🔎 سرچ وب: <b>", "\n\n🔎 Web search: <b>") . (AI::searchOn() ? L('روشن', 'On') : L('خاموش', 'Off')) . '</b>';
    }

    public static function keyboard(): array
    {
        $cur  = explode(':', AI::route(), 2)[0];
        $rows = [];
        foreach (self::providers() as $id => $name) {
            $on     = $cur === $id;
            $rows[] = [btn(($on ? '✅ ' : '') . $name . ' ▸', "ai:p:{$id}:0", $on ? 'success' : 'primary')];
        }
        if (self::thinkProviders()) {
            $rows[] = [btn(L('🧠 سطح فکر کردن ▸', '🧠 Thinking level ▸'), 'ai:th', 'primary')];
        }
        $rows[] = [btn(AI::searchOn() ? L('🔎 سرچ: روشن ✅', '🔎 Search: on ✅') : L('🔎 سرچ: خاموش ⛔', '🔎 Search: off ⛔'), 'ai:search', AI::searchOn() ? 'success' : null)];
        return ikb($rows);
    }

    private static function thinkProviders(): array
    {
        return array_intersect_key(self::providers(), ['gemini' => 1, 'groq' => 1]);
    }

    private static function thinkCatalog(string $prov): array
    {
        return array_values(array_filter(self::catalog($prov), static fn($m) => !str_starts_with(substr($m['route'], strlen($prov) + 1), 'gemma')));
    }

    private static function thinkModel(string $prov, string $hash): ?array
    {
        foreach (self::thinkCatalog($prov) as $m) {
            if (substr(md5($m['route']), 0, 8) === $hash) {
                return $m + ['id' => substr($m['route'], strlen($prov) + 1)];
            }
        }
        return null;
    }

    public static function thinkText(?string $prov = null, bool $list = false, ?array $model = null): string
    {
        $l = [L('<b>🧠 سطح فکر کردن مدل</b>', '<b>🧠 Model thinking level</b>')];
        if ($prov === null) {
            foreach (self::thinkProviders() as $id => $name) {
                $l[] = $name . ': <b>' . AI::thinkLabel(AI::thinkLevel($id)) . '</b>';
            }
            $l[] = L('فقط روی جواب‌های چت اعمال می‌شه.', 'Applies to chat replies only.');
        } elseif ($model !== null) {
            $raw = AI::modelThinkLevel($prov, $model['id']);
            $l[] = bidi(h($model['name'])) . ': <b>' . ($raw === 'auto' ? L('همون تنظیم کلی', 'Same as the global setting') : AI::thinkLabel($raw)) . '</b>';
            $l[] = L('این سطح فقط وقتی اعمال می‌شه که همین مدل رو ثابت انتخاب کرده باشی.', 'This level only applies when you pin this exact model.');
        } elseif ($list) {
            $l[] = L(
                "📌 سطح هر مدل جدا\nوقتی یه مدل رو ثابت انتخاب کنی (نه خودکار)،\nسطح خودش اعمال می‌شه؛ برای بقیه‌ی حالت‌ها تنظیم کلی.",
                "📌 Per-model level\nWhen you pin a model (not Auto),\nits own level is used; in every other mode the global setting applies."
            );
        } else {
            $l[] = (self::providers()[$prov] ?? $prov) . ': <b>' . AI::thinkLabel(AI::thinkLevel($prov)) . '</b>';
            $l[] = L(
                "⚡ روی حالت خودکار،\nاین سطح روی همه‌ی مدل‌ها اعمال می‌شه.\nهرچی بالاتر، دقیق‌تر ولی کندتر.\nپیش‌فرض: همون تنظیم قبلیِ ربات.",
                "⚡ In Auto mode,\nthis level applies to every model.\nHigher is more accurate but slower.\nDefault: the bot’s previous setting."
            );
        }
        return implode("\n\n", $l);
    }

    public static function thinkKeyboard(?string $prov = null, bool $list = false, ?array $model = null): array
    {
        $rows = [];
        if ($prov === null) {
            foreach (self::thinkProviders() as $id => $name) {
                $rows[] = [btn($name . ' · ' . AI::thinkLabel(AI::thinkLevel($id)) . ' ▸', "ai:thp:{$id}", 'primary')];
            }
            $rows[] = [btn(L('🔙 برگشت', '🔙 Back'), 'ai:home', null)];
            return ikb($rows);
        }
        if ($list) {
            foreach (self::thinkCatalog($prov) as $m) {
                $raw    = AI::modelThinkLevel($prov, substr($m['route'], strlen($prov) + 1));
                $rows[] = [btn(mb_strimwidth($m['name'], 0, 28, '…') . ' · ' . ($raw === 'auto' ? L('کلی', 'Global') : AI::thinkLabel($raw)), "ai:thl:{$prov}:" . substr(md5($m['route']), 0, 8), $raw === 'auto' ? 'primary' : 'success')];
            }
            $rows[] = [btn(L('🔙 برگشت', '🔙 Back'), "ai:thp:{$prov}", null)];
            return ikb($rows);
        }
        $cur = $model !== null ? AI::modelThinkLevel($prov, $model['id']) : AI::thinkLevel($prov);
        if ($model === null) {
            $rows[] = [btn(L('📌 هر مدل جدا ▸', '📌 Per model ▸'), "ai:thm:{$prov}", 'primary')];
        }
        foreach (array_keys(AI::THINK_LEVELS) as $k) {
            $on    = $k === $cur;
            $label = ($model !== null && $k === 'auto') ? L('تنظیم کلی', 'Global setting') : AI::thinkLabel($k);
            $data  = $model !== null ? "ai:thv:{$prov}:" . substr(md5($model['route']), 0, 8) . ":{$k}" : "ai:ths:{$prov}:{$k}";
            $rows[] = [btn(($on ? '✅ ' : '') . $label, $data, $on ? 'success' : 'primary')];
        }
        $rows[] = [btn(L('🔙 برگشت', '🔙 Back'), $model !== null ? "ai:thm:{$prov}" : 'ai:th', null)];
        return ikb($rows);
    }

    public static function providerText(string $prov, int $page = 0): string
    {
        $all   = self::catalog($prov);
        $per   = self::PER_PAGE[$prov] ?? 8;
        $pages = max(1, (int) ceil(count($all) / $per));
        $page  = max(0, min($page, $pages - 1));

        $l   = [];
        $l[] = '<b>' . h(self::providers()[$prov] ?? $prov) . '</b>' . ($pages > 1 ? ' · <i>' . L('صفحه‌ی ' . fa($page + 1) . ' از ' . fa($pages), 'Page ' . ($page + 1) . ' of ' . $pages) . '</i>' : '');
        $l[] = L('مدل فعال روی همه‌ی بخش‌ها: <b>', 'Active model for all features: <b>') . bidi(h(self::label(AI::route()))) . '</b>';
        $l[] = L(
            "⚡ <b>خودکار</b>: مدل‌ها به ترتیب امتحان می‌شن\nو اگه سهمیه‌ی یکی تموم شد، خودش می‌ره سراغ بعدی.",
            "⚡ <b>Auto</b>: models are tried in order,\nand when one runs out of quota it moves on to the next."
        );
        $l[] = L(
            "📌 <b>انتخاب یه مدل</b>: فقط همون مدل،\nروی همه‌ی بخش‌ها، ثابت می‌مونه (تا خودت عوضش کنی).",
            "📌 <b>Pick a model</b>: only that model is used,\nfor every feature, until you change it."
        );
        if (!$all) {
            $l[] = L('الان لیست مدل‌ها در دسترس نیست؛ چند دقیقه دیگه دوباره امتحان کن.', 'The model list isn’t available right now; try again in a few minutes.');
        }
        return implode("\n\n", $l);
    }

    public static function providerKeyboard(string $prov, int $page = 0): array
    {
        $all   = self::catalog($prov);
        $per   = self::PER_PAGE[$prov] ?? 8;
        $cols  = self::COLS[$prov] ?? 1;
        $pages = max(1, (int) ceil(count($all) / $per));
        $page  = max(0, min($page, $pages - 1));
        $cur   = AI::route();

        $auto = $cur === $prov;
        $rows = [[btn(($auto ? '✅ ' : '') . L('⚡ خودکار', '⚡ Auto'), "ai:set:{$prov}:a:{$page}", $auto ? 'success' : 'primary')]];

        $row = [];
        foreach (array_slice($all, $page * $per, $per) as $m) {
            $on    = $m['route'] === $cur;
            $row[] = btn(($on ? '✅ ' : '') . mb_strimwidth($m['name'], 0, $cols > 1 ? 18 : 40, '…'), "ai:set:{$prov}:" . substr(md5($m['route']), 0, 8) . ":{$page}", $on ? 'success' : 'primary');
            if (count($row) === $cols) {
                $rows[] = $row;
                $row    = [];
            }
        }
        if ($row) {
            $rows[] = $row;
        }

        if ($pages > 1) {
            $nav = [];
            if ($page > 0) {
                $nav[] = Ui::navButton(false, "ai:p:{$prov}:" . ($page - 1));
            }
            if ($page < $pages - 1) {
                $nav[] = Ui::navButton(true, "ai:p:{$prov}:" . ($page + 1));
            }
            $rows[] = $nav;
        }
        $rows[] = [btn(L('🔙 برگشت', '🔙 Back'), 'ai:home', null)];
        return ikb($rows);
    }

    public static function onCallback(array $cq, string $data): void
    {
        $tg = App::tg();
        if (!App::isAdmin((int) ($cq['from']['id'] ?? 0))) {
            $tg->alert($cq, L('🙅 این پنل مخصوص ادمینه.', '🙅 This panel is admin-only.'));
            return;
        }
        $chatId = $cq['message']['chat']['id'] ?? null;
        $msgId  = $cq['message']['message_id'] ?? null;
        if ($chatId === null || $msgId === null) {
            $tg->answer($cq);
            return;
        }

        $p     = explode(':', $data);
        $act   = $p[1] ?? '';
        $prov  = $p[2] ?? '';
        $known = isset(self::providers()[$prov]);
        $page  = 0;
        $saved = false;

        if (in_array($act, ['th', 'thp', 'ths', 'thm', 'thl', 'thv'], true) && ($act === 'th' || isset(self::thinkProviders()[$prov]))) {
            $model = in_array($act, ['thl', 'thv'], true) ? self::thinkModel($prov, $p[3] ?? '') : null;
            if ($act === 'ths' && isset(AI::THINK_LEVELS[$p[3] ?? ''])) {
                AI::setThinkLevel($prov, $p[3]);
                $saved = true;
            }
            if ($act === 'thv' && $model !== null && isset(AI::THINK_LEVELS[$p[4] ?? ''])) {
                AI::setModelThinkLevel($prov, $model['id'], $p[4]);
                $saved = true;
            }
            $tp = $act === 'th' ? null : $prov;
            Rich::edit($chatId, $msgId, self::thinkText($tp, $act === 'thm', $model), self::thinkKeyboard($tp, $act === 'thm', $model));
            $tg->answer($cq, $saved ? L('✅ ذخیره شد', '✅ Saved') : null);
            return;
        }

        if ($act === 'search') {
            AI::setSearch(!AI::searchOn());
            $saved = true;
        }

        if ($known && $act === 'p') {
            $page = (int) ($p[3] ?? 0);
        } elseif ($known && $act === 'set') {
            $pick  = $p[3] ?? '';
            $page  = (int) ($p[4] ?? 0);
            $route = $pick === 'a' ? $prov : null;
            if ($route === null) {
                foreach (self::catalog($prov) as $m) {
                    if (substr(md5($m['route']), 0, 8) === $pick) {
                        $route = $m['route'];
                        break;
                    }
                }
            }
            if ($route !== null && AI::validRoute($route)) {
                AI::setRoute($route);
                $saved = true;
            }
        }

        if ($known && ($act === 'p' || $act === 'set')) {
            Rich::edit($chatId, $msgId, self::providerText($prov, $page), self::providerKeyboard($prov, $page));
        } else {
            Rich::edit($chatId, $msgId, self::text(), self::keyboard());
        }
        $tg->answer($cq, $saved ? L('✅ ذخیره شد', '✅ Saved') : null);
    }
}
