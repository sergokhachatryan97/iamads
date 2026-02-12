<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Global Telegram Settings
    |--------------------------------------------------------------------------
    */


    'use_pull_provider' => env('TELEGRAM_USE_PULL_PROVIDER', true),

    'global_action_cooldown_seconds' => env('TELEGRAM_GLOBAL_ACTION_COOLDOWN_SECONDS', 1800), // 30 minutes (fallback)

    /*
    |--------------------------------------------------------------------------
    | Per-Action Cooldown TTLs
    |--------------------------------------------------------------------------
    |
    | Cooldown TTL per action type. If not specified, falls back to
    | global_action_cooldown_seconds.
    |
    */

    'action_cooldowns' => [
        'subscribe' => 1800,      // 30 minutes
        'unsubscribe' => 1800,    // 30 minutes

        // ✅ light actions — 20 minutes as requested
        'view' => 1200,
        'react' => 1200,
        'comment' => 1200,
        'bot_start' => 1200,
        'story_react' => 1200,
        'follow' => 1200,
        'join' => 1200,
    ],

    /*
    |--------------------------------------------------------------------------
    | Telegram Action Policies
    |--------------------------------------------------------------------------
    |
    | Defines per-action rules for Telegram operations including cooldowns,
    | daily caps, deduplication, and lifecycle management.
    |
    */

    'action_policies' => [
        'subscribe' => [
            'daily_cap' => env('TELEGRAM_SUBSCRIBE_DAILY_CAP', 4),
            'dedupe_per_link' => true,
            'reallow_after_unsubscribe' => true,
            'unsubscribe_after_days' => env('TELEGRAM_SUBSCRIBE_UNSUBSCRIBE_AFTER_DAYS', 14),
        ],

        'unsubscribe' => [
            'daily_cap' => env('TELEGRAM_UNSUBSCRIBE_DAILY_CAP', 4),
            'dedupe_per_link' => false,
            'reallow_after_unsubscribe' => false,
        ],

        'follow' => [
            'daily_cap' => 10,
            'dedupe_per_link' => true,
            'reallow_after_unsubscribe' => true,
        ],

        'join' => [
            'daily_cap' => 10,
            'dedupe_per_link' => true,
            'reallow_after_unsubscribe' => true,
        ],

        'view' => [
            'daily_cap' => null,
            'dedupe_per_link' => false,
            'reallow_after_unsubscribe' => false,
        ],

        'react' => [
            'daily_cap' => null,
            'dedupe_per_link' => true,
            'reallow_after_unsubscribe' => false,
        ],

        'comment' => [
            'daily_cap' => null,
            'dedupe_per_link' => true,
            'reallow_after_unsubscribe' => false,
        ],

        'story_react' => [
            'daily_cap' => null,
            'dedupe_per_link' => true,
            'reallow_after_unsubscribe' => false,
        ],

        'bot_start' => [
            'daily_cap' => null,
            'dedupe_per_link' => true,
            'reallow_after_unsubscribe' => false,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Execution Policy Map
    |--------------------------------------------------------------------------
    |
    | Maps (policy_key, link_type) to execution parameters:
    | - action: Which action to perform (subscribe, follow, join, react, view)
    | - interval_seconds: base delay (legacy; you now compute interval dynamically)
    | - per_call: Number of units processed per API call (default: 1)
    |
    */

    'execution_policy_map' => [
        // ------------------------
        // Legacy default behavior
        // ------------------------
        'default' => [
            'group' => [
                'action' => 'subscribe',
                'interval_seconds' => 5,
                'per_call' => 1,
            ],
            'channel' => [
                'action' => 'subscribe',
                'interval_seconds' => 5,
                'per_call' => 1,
            ],
            'user' => [
                'action' => 'follow',
                'interval_seconds' => 5,
                'per_call' => 1,
            ],
            'invite' => [
                'action' => 'join',
                'interval_seconds' => 5,
                'per_call' => 1,
            ],
        ],

        // ✅ NEW: public subscribe policies (fast)
        'sub_public' => [
            'channel' => [
                'action' => 'subscribe',
                'interval_seconds' => 1, // informational only; interval is recalculated
                'per_call' => 1,
            ],
            'group' => [
                'action' => 'subscribe',
                'interval_seconds' => 1,
                'per_call' => 1,
            ],
            'supergroup' => [
                'action' => 'subscribe',
                'interval_seconds' => 1,
                'per_call' => 1,
            ],
        ],

        // ✅ NEW: private/invite policies (safer)
        'sub_private' => [
            'group' => [
                'action' => 'subscribe',
                'interval_seconds' => 5,
                'per_call' => 1,
            ],
            'supergroup' => [
                'action' => 'subscribe',
                'interval_seconds' => 5,
                'per_call' => 1,
            ],
            // invite links usually map to join
            'invite' => [
                'action' => 'join',
                'interval_seconds' => 5,
                'per_call' => 1,
            ],
        ],

        // ------------------------
        // Other service policies
        // ------------------------
        'reaction' => [
            'public_post' => [
                'action' => 'react',
                'interval_seconds' => 2,
                'per_call' => 1,
            ],
            // if you have group post reactions with different link_type,
            // add it here too
            // 'group_post' => [...]
        ],

        'views' => [
            'public_post' => [
                'action' => 'view',
                'interval_seconds' => 1,
                'per_call' => 1,
            ],
        ],

        'bot' => [
            'bot' => [
                'action' => 'bot_start',
                'interval_seconds' => 1,
                'per_call' => 25,
            ],
        ],

        'comment' => [
            'public_post' => [
                'action' => 'comment',
                'interval_seconds' => 2,
                'per_call' => 1,
            ],
        ],

        'story' => [
            'public_username' => [
                'action' => 'story_react',
                'interval_seconds' => 3,
                'per_call' => 1,
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Service-Type Specific Unsubscribe Delays
    |--------------------------------------------------------------------------
    */

    'unsubscribe_delays' => [
        // 'subscription' => 30,
        // 'telegram_join' => 7,
    ],

    /*
    |--------------------------------------------------------------------------
    | Redis Stream Configuration
    |--------------------------------------------------------------------------
    */

    'stream' => [
        'name' => 'tg:step-results',
        'consumer_group' => 'flush-workers',
        'batch_size' => 1000,
    ],

    /*
    |--------------------------------------------------------------------------
    | Provider Task Fallback
    |--------------------------------------------------------------------------
    */

    'provider_task_fallback_seconds' => env('TELEGRAM_PROVIDER_TASK_FALLBACK_SECONDS', 600),

    /*
    |--------------------------------------------------------------------------
    | Account Password Generation
    |--------------------------------------------------------------------------
    */

    'account_password' => [
        'length' => env('TELEGRAM_ACCOUNT_PASSWORD_LENGTH', 20),
    ],

    /*
    |--------------------------------------------------------------------------
    | Quantity Target ETA Tiers
    |--------------------------------------------------------------------------
    */

    'qty_target_eta' => [
        'heavy' => [
            '<=1000' => 60,        // 1 minute
            '<=10000' => 3600,      // 1 hour
            '<=50000' => 43200,     // 12 hours
            '<=100000' => 345600,   // 4 days
            'else' => 518400,       // 6 days
        ],
        'light' => [
            '<=1000' => 60,         // 1 minute
            '<=10000' => 1200,      // 20 minutes
            '<=100000' => 28800,    // 8 hours
            'else' => 86400,        // 1 day
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Member Count Multipliers
    |--------------------------------------------------------------------------
    */

    'member_count_multipliers' => [
        'unknown' => 3.0,
        'tiny' => 2.0,
        'small' => 1.4,
        'medium' => 1.0,
        'large' => 1.2,
    ],

    /*
    |--------------------------------------------------------------------------
    | Account Selection
    |--------------------------------------------------------------------------
    */

    'account_selection' => [
        'max_attempts' => 20,
        'reschedule_delay_min' => 10,
        'reschedule_delay_max' => 30,
        'max_scan_limit' => 2000,
        'batch_size' => 200,
        // Burst mode optimizations
        'burst_max_scan_limit' => 300,
        'burst_batch_size' => 500,
    ],

    /*
    |--------------------------------------------------------------------------
    | Burst Mode Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for burst/windowed dispatch mode execution.
    | This mode enables high-throughput execution without provider batch support.
    |
    */

    'burst' => [
        // Maximum number of step jobs to dispatch per dispatcher tick
        // This ensures fairness across multiple orders
        'max_dispatch_per_tick' => env('TELEGRAM_BURST_MAX_DISPATCH_PER_TICK', 200),
    ],

    'strict_paid_messages_check' => env('TELEGRAM_STRICT_PAID_MESSAGES_CHECK', true),
    'paid_messages_cache_hours' => env('TELEGRAM_PAID_MESSAGES_CACHE_HOURS', 6),

    /*
    |--------------------------------------------------------------------------
    | Quota Settings
    |--------------------------------------------------------------------------
    */

    'quota' => [
        // TTL for posts snapshot cache (in hours)
        'posts_snapshot_ttl_hours' => env('TELEGRAM_QUOTA_POSTS_SNAPSHOT_TTL_HOURS', 24),
    ],

    /*
    |--------------------------------------------------------------------------
    | Local Provider Worker
    |--------------------------------------------------------------------------
    |
    | Configuration for in-app task execution via MadelineProto (local worker).
    |
    */

    'local_worker' => [
        'lease_ttl_seconds' => env('TELEGRAM_LOCAL_WORKER_LEASE_TTL', 60),
        'per_account_lock_ttl_seconds' => env('TELEGRAM_LOCAL_WORKER_PER_ACCOUNT_LOCK_TTL', 120),
        'max_attempts' => env('TELEGRAM_LOCAL_WORKER_MAX_ATTEMPTS', 5),
        'retry_backoff_seconds' => [60, 120, 300, 600, 600], // min(60 * attempt, 600) style
    ],

];
