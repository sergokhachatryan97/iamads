<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI', env('APP_URL') . '/auth/google/callback'),
    ],

    'apple' => [
        'client_id' => env('APPLE_CLIENT_ID'),
        'client_secret' => env('APPLE_CLIENT_SECRET', ''), // Generated dynamically from private_key
        'team_id' => env('APPLE_TEAM_ID'),
        'key_id' => env('APPLE_KEY_ID'),
        'private_key' => env('APPLE_PRIVATE_KEY'),
        'redirect' => env('APPLE_REDIRECT_URI', env('APP_URL') . '/auth/apple/callback'),
    ],

    'yandex' => [
        'client_id' => env('YANDEX_CLIENT_ID'),
        'client_secret' => env('YANDEX_CLIENT_SECRET'),
        'redirect' => env('YANDEX_REDIRECT_URI', env('APP_URL') . '/auth/yandex/callback'),
    ],

    'telegram' => [
        'bot_token' => env('TELEGRAM_BOT_TOKEN'),
        'bot_username' => env('TELEGRAM_BOT_USERNAME'),
        'api_id' => env('TELEGRAM_API_ID'),
        'api_hash' => env('TELEGRAM_API_HASH'),
        'inspect_rate_limit_per_second' => env('TELEGRAM_INSPECT_RATE_LIMIT_PER_SECOND', 10),
    ],

    'provider' => [
        'base_url' => env('PROVIDER_BASE_URL'),
        'api_key' => env('PROVIDER_API_KEY'),
        'webhook_secret' => env('PROVIDER_WEBHOOK_SECRET'),
        'token' => env('PROVIDER_TOKEN'), // Shared secret for provider pull API authentication
        'status_rate_limit_per_second' => env('PROVIDER_STATUS_RATE_LIMIT_PER_SECOND', 5),
        'webhook_stale_minutes' => env('PROVIDER_WEBHOOK_STALE_MINUTES', 15),
        'poll_min_minutes' => env('PROVIDER_POLL_MIN_MINUTES', 5),
        'sync_lock_ttl_minutes' => env('PROVIDER_SYNC_LOCK_TTL_MINUTES', 5),

        // Rate limiting for push mode only (legacy)
        // In pull mode, provider controls its own rate limiting
        'rate_limit_per_second' => (int) env('PROVIDER_RPS', 20),

        // optional per queue (push mode only)
        'rate_limit_per_second_by_queue' => [
            'tg-p0' => (int) env('PROVIDER_RPS_P0', 30),
            'tg-p1' => (int) env('PROVIDER_RPS_P1', 20),
            'tg-p2' => (int) env('PROVIDER_RPS_P2', 10),
            'tg-p3' => (int) env('PROVIDER_RPS_P3', 5),
        ],

        // optional per action (push mode only)
        'rate_limit_per_second_by_action' => [
            'subscribe' => (int) env('PROVIDER_RPS_SUBSCRIBE', 10),
            'unsubscribe' => (int) env('PROVIDER_RPS_UNSUBSCRIBE', 10),
            'view' => (int) env('PROVIDER_RPS_VIEW', 30),
            'react' => (int) env('PROVIDER_RPS_REACT', 25),
            'comment' => (int) env('PROVIDER_RPS_COMMENT', 15),
            'bot_start' => (int) env('PROVIDER_RPS_BOT_START', 25),
            'story_react' => (int) env('PROVIDER_RPS_STORY', 20),
        ],
    ],
];
