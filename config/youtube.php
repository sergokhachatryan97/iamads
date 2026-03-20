<?php

return [
    /*
    |--------------------------------------------------------------------------
    | YouTube Data API v3
    |--------------------------------------------------------------------------
    */

    'api_key' => env('YOUTUBE_API_KEY', ''),

    /*
    |--------------------------------------------------------------------------
    | YouTube Target Types & Allowed Actions (business policy)
    |--------------------------------------------------------------------------
    |
    | Single source of truth for which actions are allowed per detected target.
    | Used by InspectYouTubeLinkJob and any policy/validation layer.
    |
    | Target types: video (regular/shorts), live (live/upcoming/finished stream), channel.
    |
    */

    'allowed_actions' => [
        'video' => ['view', 'react', 'comment', 'share', 'watch', 'comment-react'],
        'live' => ['view', 'react', 'comment'],
        'channel' => ['subscribe'],
    ],

    /*
    |--------------------------------------------------------------------------
    | YouTube API Cache TTL (seconds)
    |--------------------------------------------------------------------------
    | Cache video/channel metadata to reduce quota. 0 = no cache.
    */

    'api_cache_ttl_seconds' => (int) env('YOUTUBE_API_CACHE_TTL', 3600),

    /*
    |--------------------------------------------------------------------------
    | YouTube Execution Policy
    |--------------------------------------------------------------------------
    |
    | Default per_call and interval_seconds per action. Used by InspectYouTubeLinkJob
    | when building execution_meta. Override per template via execution_policy_map.
    |
    */

    'default_per_call' => 1,

    'default_interval_seconds' => 30,

    /*
    |--------------------------------------------------------------------------
    | Per-Action Defaults
    |--------------------------------------------------------------------------
    */

    'action_defaults' => [
        'subscribe' => [
            'per_call' => 1,
            'interval_seconds' => 45,
        ],
        'view' => [
            'per_call' => 1,
            'interval_seconds' => 25,
        ],
        'react' => [
            'per_call' => 1,
            'interval_seconds' => 30,
        ],
        'comment' => [
            'per_call' => 1,
            'interval_seconds' => 35,
        ],
        'share' => [
            'per_call' => 1,
            'interval_seconds' => 40,
        ],
        'comment-react' => [
            'per_call' => 1,
            'interval_seconds' => 35,
        ],
        'watch' => [
            'per_call' => 1,
            'interval_seconds' => 45,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Watch time (video) – default duration in seconds when not set on service
    |--------------------------------------------------------------------------
    */
    'default_watch_time_seconds' => (int) env('YOUTUBE_DEFAULT_WATCH_TIME_SECONDS', 30),

    /*
    |--------------------------------------------------------------------------
    | ETA / Interval Tiers (optional, for large quantities)
    |--------------------------------------------------------------------------
    */

    'qty_target_eta' => [
        'light' => [
            '<=1000' => 120,
            '<=10000' => 1200,
            '<=50000' => 28800,
            '<=100000' => 28800,
            'else' => 86400,
        ],
        'heavy' => [
            '<=1000' => 120,
            '<=10000' => 3600,
            '<=50000' => 43200,
            '<=100000' => 345600,
            'else' => 518400,
        ],
    ],
];
