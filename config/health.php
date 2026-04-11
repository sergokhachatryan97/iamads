<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Alert delivery (Telegram)
    |--------------------------------------------------------------------------
    |
    | Uses the existing TELEGRAM_BOT_TOKEN. Set HEALTH_ALERT_CHAT_ID to the
    | chat id that should receive server health alerts (your personal chat
    | id or an admin group id — prefix groups with `-100` as Telegram does).
    |
    */
    'telegram' => [
        'bot_token' => env('TELEGRAM_BOT_TOKEN'),
        'chat_id' => env('HEALTH_ALERT_CHAT_ID'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Thresholds
    |--------------------------------------------------------------------------
    */
    'thresholds' => [
        // 1-minute load average divided by number of cores. 0.9 = 90% busy.
        'cpu_load_per_core' => env('HEALTH_CPU_LOAD_PER_CORE', 0.9),

        // Used memory percentage (based on /proc/meminfo MemAvailable).
        'memory_percent' => env('HEALTH_MEMORY_PERCENT', 85),

        // Used disk percentage on the configured path.
        'disk_percent' => env('HEALTH_DISK_PERCENT', 85),

        // Maximum queue backlog before alerting.
        'queue_size_max' => env('HEALTH_QUEUE_SIZE_MAX', 5000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Alert cooldown
    |--------------------------------------------------------------------------
    |
    | How long to suppress duplicate alerts for the same metric after one
    | fires. 900 seconds = 15 minutes.
    |
    */
    'alert_cooldown_seconds' => env('HEALTH_ALERT_COOLDOWN_SECONDS', 900),

    /*
    |--------------------------------------------------------------------------
    | Disk path to monitor
    |--------------------------------------------------------------------------
    |
    | Defaults to the Laravel base path. Override with HEALTH_DISK_PATH if you
    | want to monitor a specific mount (e.g. /var or /mnt/data).
    |
    */
    'disk_path' => env('HEALTH_DISK_PATH'),
];
