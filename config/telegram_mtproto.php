<?php

return [
    /*
    |--------------------------------------------------------------------------
    | MTProto Account Setup Configuration
    |--------------------------------------------------------------------------
    */

    'setup' => [
        // Enable/disable account setup pipeline
        'enabled' => env('TELEGRAM_MTPROTO_SETUP_ENABLED', false),

        // 2FA settings
        '2fa' => [
            'enable' => env('TELEGRAM_MTPROTO_SETUP_2FA_ENABLE', false),
            // Base Gmail address for email aliasing (e.g., myemail@gmail.com)
            'base_email' => env('TELEGRAM_MTPROTO_SETUP_2FA_BASE_EMAIL', null),
            // Optional password hint
            'hint' => env('TELEGRAM_MTPROTO_SETUP_2FA_HINT', null),
            // Path to Gmail API credentials JSON file
            'gmail_credentials_path' => env('TELEGRAM_MTPROTO_SETUP_2FA_GMAIL_CREDENTIALS_PATH', storage_path('app/gmail-credentials.json')),
            // Gmail polling timeout (seconds)
            'gmail_poll_timeout_seconds' => env('TELEGRAM_MTPROTO_SETUP_2FA_GMAIL_POLL_TIMEOUT', 300),
            // Gmail polling interval (seconds between checks)
            'gmail_poll_interval_seconds' => env('TELEGRAM_MTPROTO_SETUP_2FA_GMAIL_POLL_INTERVAL', 10),
        ],

        // Media file paths
        'media' => [
            'default_jpg_path' => env('TELEGRAM_MTPROTO_SETUP_MEDIA_JPG_PATH', null),
            'default_gif_path' => env('TELEGRAM_MTPROTO_SETUP_MEDIA_GIF_PATH', null),
            'story_image_path' => env('TELEGRAM_MTPROTO_SETUP_MEDIA_STORY_IMAGE_PATH', null),
            'story_video_path' => env('TELEGRAM_MTPROTO_SETUP_MEDIA_STORY_VIDEO_PATH', null),
            // Recommended max concurrency for media uploads (informational)
            // 'max_concurrency' => env('TELEGRAM_MTPROTO_SETUP_MEDIA_MAX_CONCURRENCY', 5),
        ],

        // Retry backoff (in seconds)
        'retry' => [
            'backoff_seconds' => [
                (int) env('TELEGRAM_MTPROTO_SETUP_RETRY_BACKOFF_1', 60),
                (int) env('TELEGRAM_MTPROTO_SETUP_RETRY_BACKOFF_2', 300),
                (int) env('TELEGRAM_MTPROTO_SETUP_RETRY_BACKOFF_3', 900),
                (int) env('TELEGRAM_MTPROTO_SETUP_RETRY_BACKOFF_4', 3600),
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | MTProto Pool Settings
    |--------------------------------------------------------------------------
    */

    'job_timeout_seconds' => env('TELEGRAM_MTPROTO_JOB_TIMEOUT_SECONDS', 60),
    'lock_ttl_buffer_seconds' => env('TELEGRAM_MTPROTO_LOCK_TTL_BUFFER_SECONDS', 60),
    'account_lock_ttl_seconds' => env('TELEGRAM_MTPROTO_ACCOUNT_LOCK_TTL_SECONDS', 0),
    'max_fail_count_before_disable' => env('TELEGRAM_MTPROTO_MAX_FAIL_COUNT_BEFORE_DISABLE', 10),
    'proxy_health_ttl_seconds' => env('TELEGRAM_MTPROTO_PROXY_HEALTH_TTL_SECONDS', 600),

    // Account selection
    'selection_batch_size' => env('TELEGRAM_MTPROTO_SELECTION_BATCH_SIZE', 20),
    'selection_top_k' => env('TELEGRAM_MTPROTO_SELECTION_TOP_K', 7),
    'selection_penalty_weight' => env('TELEGRAM_MTPROTO_SELECTION_PENALTY_WEIGHT', 1000),
    'lock_penalty_ttl_seconds' => env('TELEGRAM_MTPROTO_LOCK_PENALTY_TTL_SECONDS', 20),
    'proxy_throttle_sec' => env('TELEGRAM_MTPROTO_PROXY_THROTTLE_SEC', 2),
    'proxy_throttle_max_wait_inspect_sec' => env('TELEGRAM_MTPROTO_PROXY_THROTTLE_MAX_WAIT_INSPECT_SEC', 8),
    'max_accounts_to_try_per_call' => env('TELEGRAM_MTPROTO_MAX_ACCOUNTS_TO_TRY_PER_CALL', 8),
    'call_deadline_ms' => env('TELEGRAM_MTPROTO_CALL_DEADLINE_MS', 30000),
    'call_deadline_inspect_ms' => env('TELEGRAM_MTPROTO_CALL_DEADLINE_INSPECT_MS', 0),
    'proxy_mode' => env('TELEGRAM_MTPROTO_PROXY_MODE', 'rotating'), // rotating | static

    // Debug: detailed logs for account selection and pool execution (why no account, why same account)
    'debug_selection' => env('TG_MTPROTO_DEBUG_SELECTION', true),

    /*
    |--------------------------------------------------------------------------
    | Profile Seed Import from Google Sheets
    |--------------------------------------------------------------------------
    */

    'sheet' => [
        'enabled' => env('TELEGRAM_SHEET_ENABLED', false),
        'csv_url' => env('TELEGRAM_SHEET_CSV_URL', null),
        'private' => [
            'use_api' => env('TELEGRAM_SHEET_USE_API', false),
            'spreadsheet_id' => env('TELEGRAM_SHEET_SPREADSHEET_ID', null),
            'range' => env('TELEGRAM_SHEET_RANGE', 'Sheet1!A:E'),
        ],
        'match_by_username' => env('TELEGRAM_SHEET_MATCH_BY_USERNAME', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Media Download Settings
    |--------------------------------------------------------------------------
    */

    'media' => [
        'storage_dir' => env('TELEGRAM_MEDIA_STORAGE_DIR', 'telegram_media'),
        'max_bytes' => env('TELEGRAM_MEDIA_MAX_BYTES', 30 * 1024 * 1024), // 30MB
        'allowed_extensions' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'mp4', 'mov'],
        'allowed_mimes' => [
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
            'video/mp4',
            'video/quicktime',
        ],
        'download_timeout_seconds' => env('TELEGRAM_MEDIA_DOWNLOAD_TIMEOUT', 300), // 5 minutes
    ],
];
