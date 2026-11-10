<?php

return [
    'bot_token'    => env('BOT_TOKEN', ''),
    'bot_tokens'    => [env('BOT_TOKEN', ''), env('BOT_TOKEN_2', '')],
    'bot_usernames' => [env('BOT_USERNAME', ''), env('BOT_USERNAME_2', '')],
    'premium_packs' => ['RestrictedEmoji', 'NewsEmoji'],
    'bot_version'  => '5.0.0',
    'admins'       => array_values(array_unique(array_filter(array_map('intval', array_merge(envList('ADMIN_IDS'), envList('OWNER_ID')))))),

    'polling' => [
        'timeout'      => (int) env('POLL_TIMEOUT', 10),
        'workers'      => env('POLL_WORKERS', 'auto'),
        'max_workers'  => (int) env('POLL_MAX_WORKERS', 24),
        'drop_pending' => filter_var(env('DROP_PENDING', 'false'), FILTER_VALIDATE_BOOLEAN),
    ],

    'ai' => [
        'keys'        => envList('GEMINI_KEYS'),
        'models'      => envList('GEMINI_MODELS', [
            'gemini-3.8-flash', 'gemini-3.7-flash', 'gemini-3.6-flash', 'gemini-3.5-flash', 'gemini-3-flash-preview', 'gemini-flash-latest',
            'gemma-4-31b-it',
            'gemini-3.5-flash-lite', 'gemini-3.1-flash-lite', 'gemini-flash-lite-latest',
            'gemma-4-26b-a4b-it',
        ]),
        'lite_models' => envList('GEMINI_LITE_MODELS', [
            'gemini-3.5-flash-lite', 'gemini-3.1-flash-lite', 'gemini-flash-lite-latest',
            'gemma-4-26b-a4b-it', 'gemma-4-31b-it', 'gemini-3.8-flash', 'gemini-3.6-flash',
        ]),
        'free_stt'    => [
            'groq' => envList('GROQ_STT_MODELS', ['whisper-large-v3-turbo', 'whisper-large-v3']),
        ],
        'free_order'  => [
            'groq' => envList('GROQ_MODELS'),
        ],
        'free_keys'   => [
            'groq'       => envList('GROQ_KEYS'),
        ],
    ],
    'storage_chat' => env('STORAGE_CHAT_ID', ''),
    'ai_memory_messages' => (int) env('AI_MEMORY_MESSAGES', 20),
    'ai_typing_effect'   => true,
    'ai_stream'          => env('AI_STREAM', '1') !== '0',
    'gen_stop'           => env('GEN_STOP', '1') !== '0',                // دکمه‌ی توقفِ حباب پیش‌نویس (Bot API 10.3)
    'topics'             => env('TOPICS', '1') !== '0',                  // پشتیبانی از تاپیک‌ها تو پی‌وی (Threaded Mode)
    'topics_autoname'    => env('TOPICS_AUTONAME', '1') !== '0',         // اسم‌گذاری خودکار تاپیک بعد از اولین جواب
    'topics_auto_create' => env('TOPICS_AUTO_CREATE', '1') !== '0',      // پیام تو General → ساخت تاپیک جدید توسط ربات

    'cloudflare_account_id' => env('CF_ACCOUNT_ID', ''),
    'cloudflare_api_token'  => env('CF_API_TOKEN', ''),
    'cloudflare_image_model' => env('CF_IMAGE_MODEL', '@cf/black-forest-labs/flux-1-schnell'),
    'cloudflare_image_steps' => (int) env('CF_IMAGE_STEPS', 4),

    'funstat_url'   => env('FUNSTAT_URL', ''),

    'backup' => [
        'token'  => env('GITHUB_TOKEN', ''),
        'repo'   => env('GITHUB_REPO', ''),
        'branch' => env('DATA_BRANCH', 'data'),
    ],

    // REQUIRED_CHATS: comma separated. Each item can be a channel/group:
    //   @username                      (public channel or group)
    //   -100123456789|https://t.me/+invite|Title   (private group/channel: id|link|title)
    'required_chats' => env('REQUIRED_CHATS', '@NullHubs') === '-' ? [] : array_map(
        static function (string $c): array {
            $p  = array_map('trim', explode('|', trim($c)));
            $id = $p[0];
            if (preg_match('/^-?\d+$/', $id)) {
                return ['title' => $p[2] ?? 'گروه', 'chat_id' => (int) $id, 'url' => $p[1] ?? ''];
            }
            $u = ltrim($id, '@');
            return ['title' => $p[2] ?? $u, 'chat_id' => '@' . $u, 'url' => $p[1] ?? ('https://t.me/' . $u)];
        },
        envList('REQUIRED_CHATS', ['@NullHubs'])
    ),

    'creator_name'     => env('CREATOR_NAME', 'Arown'),
    'creator_username' => env('CREATOR_USERNAME', 'nxqyz'),
    'channel_url'      => env('CHANNEL_URL', 'https://t.me/NullHubs'),
];
