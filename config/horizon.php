<?php

use Illuminate\Support\Str;

return [

    'domain' => env('HORIZON_DOMAIN'),

    'path' => env('HORIZON_PATH', 'staff/horizon'),

    'use' => 'default',

    'prefix' => env(
        'HORIZON_PREFIX',
        Str::slug(env('APP_NAME', 'laravel'), '_').'_horizon:'
    ),

    'middleware' => ['web', 'auth:staff'],

    'waits' => [
        'redis:default' => 60,
    ],

    'trim' => [
        'recent'        => 60,
        'pending'       => 60,
        'completed'     => 60,
        'recent_failed' => 10080,
        'failed'        => 10080,
        'monitored'     => 10080,
    ],

    'fast_termination' => false,

    'memory_limit' => 512,

    'defaults' => [

        /*
        |--------------------------------------------------------------------------
        | TG INSPECT (HEAVY JOBS)
        |--------------------------------------------------------------------------
        */

        'tg-inspect' => [
            'connection' => 'redis',
            'queue' => ['tg-inspect'],
            'balance' => 'auto',
            'autoScalingStrategy' => 'time',
            'minProcesses' => 1,
            'maxProcesses' => 3,
            'balanceMaxShift' => 1,
            'balanceCooldown' => 15,
            'sleep' => 5,
            'tries' => 2,
            'timeout' => 900, // long jobs
            'memory' => 512,
        ],
        'tg-panel-inspect' => [
            'connection' => 'redis',
            'queue' => ['tg-panel-inspect'],
            'balance' => 'auto',
            'autoScalingStrategy' => 'time',
            'minProcesses' => 1,
            'maxProcesses' => 2,
            'balanceMaxShift' => 1,
            'balanceCooldown' => 15,
            'sleep' => 5,
            'tries' => 2,
            'timeout' => 900, // long jobs
            'memory' => 512,
        ],

        // Merged yt-inspect + app-inspect: identical profiles, low-volume event-driven queues.
        // simple balance = fixed worker count, no auto-scale Redis overhead.
        'link-inspect' => [
            'connection' => 'redis',
            'queue' => ['yt-inspect', 'app-inspect'],
            'balance' => 'simple',
            'minProcesses' => 1,
            'maxProcesses' => 2,
            'balanceCooldown' => 30,
            'sleep' => 10,
            'tries' => 3,
            'timeout' => 60,
            'memory' => 128,
        ],

        'tg-double-check' => [
            'connection' => 'redis',
            'queue' => ['tg-double-check'],
            'balance' => 'simple',
            'minProcesses' => 1,
            'maxProcesses' => 1,
            'sleep' => 10,
            'tries' => 2,
            'timeout' => 900,
            'memory' => 512,
        ],

        'max-inspect' => [
            'connection' => 'redis',
            'queue' => ['max-inspect'],
            'balance' => 'simple',
            'minProcesses' => 1,
            'maxProcesses' => 1,
            'sleep' => 10,
            'tries' => 1,
            'timeout' => 120,
            'memory' => 128,
        ],

        /*
        |--------------------------------------------------------------------------
        | MAIN SYSTEM QUEUES
        |--------------------------------------------------------------------------
        */

        'main' => [
            'connection' => 'redis',
            'queue' => ['external-provider', 'socpanel-poll', 'providers', 'default'],
            'balance' => 'auto',
            'autoScalingStrategy' => 'time',
            'minProcesses' => 1,
            'maxProcesses' => 2,
            'balanceMaxShift' => 1,
            'balanceCooldown' => 15,
            'sleep' => 5,
            'tries' => 3,
            'timeout' => 300,
            'memory' => 256,
        ],
    ],

    'environments' => [

        /*
        |------------------------------------------------------------------
        | PRODUCTION WORKER BUDGET — total cap ≈ 8 concurrent PHP workers.
        |------------------------------------------------------------------
        | Each worker is a long-lived PHP process (~512 MB for MTProto-heavy
        | queues, ~128–256 MB otherwise) that can saturate a core if a job
        | is CPU-bound (MTProto crypto, provider HTTP). On a 16-core box
        | keeping total workers well below core count leaves headroom for
        | PHP-FPM + MadelineProto IPC children. Raise a single queue only
        | after confirming load/core stays < 1.5 for 24h.
        |
        |   tg-inspect        : 2 (MTProto, heaviest)
        |   tg-panel-inspect  : 3 (panel inspections, auto-scales for bulk uploads)
        |   link-inspect      : 1 (yt + app, merged, event-driven)
        |   tg-double-check   : 1 (slow, every 10min)
        |   max-inspect       : 1 (low-volume)
        |   main              : 2 (socpanel-poll, providers, default)
        |   ─────────────────────
        |   total             : 8
        */
        'production' => [
            'tg-inspect' => [
                'connection' => 'redis',
                'queue' => ['tg-inspect'],
                'balance' => 'simple',       // fixed; auto-scale added Redis churn + CPU
                'minProcesses' => 1,
                'maxProcesses' => 2,         // was 3; MTProto is CPU-bound, 2 is enough
                'balanceMaxShift' => 1,
                'balanceCooldown' => 15,
                'sleep' => 5,
                'tries' => 2,
                'timeout' => 900,
                'memory' => 512,
                'maxJobs' => 500,            // recycle worker to free leaked memory
            ],

            'tg-panel-inspect' => [
                'connection' => 'redis',
                'queue' => ['tg-panel-inspect'],
                'balance' => 'auto',
                'autoScalingStrategy' => 'time',
                'minProcesses' => 1,
                'maxProcesses' => 2,
                'balanceMaxShift' => 1,
                'balanceCooldown' => 15,
                'sleep' => 5,
                'tries' => 2,
                'timeout' => 900,
                'memory' => 512,
                'maxJobs' => 500,
            ],

            // Merged yt-inspect + app-inspect: identical profiles, low-volume.
            'link-inspect' => [
                'connection' => 'redis',
                'queue' => ['yt-inspect', 'app-inspect'],
                'balance' => 'simple',
                'minProcesses' => 1,
                'maxProcesses' => 1,         // was 2; event-driven queues are slow-moving
                'balanceCooldown' => 30,
                'sleep' => 10,
                'tries' => 3,
                'timeout' => 60,
                'memory' => 128,
                'maxJobs' => 1000,
            ],

            'tg-double-check' => [
                'connection' => 'redis',
                'queue' => ['tg-double-check'],
                'balance' => 'simple',
                'minProcesses' => 1,
                'maxProcesses' => 1,
                'sleep' => 10,
                'tries' => 2,
                'timeout' => 900,
                'memory' => 512,
                'maxJobs' => 500,
            ],

            'max-inspect' => [
                'connection' => 'redis',
                'queue' => ['max-inspect'],
                'balance' => 'simple',
                'minProcesses' => 1,
                'maxProcesses' => 1,
                'sleep' => 10,
                'tries' => 1,
                'timeout' => 120,
                'memory' => 128,
                'maxJobs' => 1000,
            ],

            'main' => [
                'connection' => 'redis',
                'queue' => ['external-provider', 'socpanel-poll', 'providers', 'default'],
                'balance' => 'simple',       // was auto
                'minProcesses' => 1,
                'maxProcesses' => 2,
                'balanceMaxShift' => 1,
                'balanceCooldown' => 15,
                'sleep' => 5,
                'tries' => 3,
                'timeout' => 300,
                'memory' => 256,
                'maxJobs' => 1000,
            ],
        ],

        'local' => [
            'tg-inspect' => [
                'connection' => 'redis',
                'queue' => ['tg-inspect'],
                'balance' => 'auto',
                'autoScalingStrategy' => 'time',
                'maxProcesses' => 4,
                'balanceMaxShift' => 1,
                'balanceCooldown' => 3,
                'tries' => 2,
                'timeout' => 900,
                'memory' => 512,
            ],
            'yt-inspect' => [
                'connection' => 'redis',
                'queue' => ['yt-inspect'],
                'balance' => 'auto',
                'autoScalingStrategy' => 'time',
                'maxProcesses' => 2,
                'balanceMaxShift' => 1,
                'balanceCooldown' => 3,
                'tries' => 3,
                'timeout' => 60,
                'memory' => 128,
            ],
            'app-inspect' => [
                'connection' => 'redis',
                'queue' => ['app-inspect'],
                'balance' => 'auto',
                'autoScalingStrategy' => 'time',
                'maxProcesses' => 2,
                'balanceMaxShift' => 1,
                'balanceCooldown' => 3,
                'tries' => 3,
                'timeout' => 60,
                'memory' => 128,
            ],
            'tg-double-check' => [
                'connection' => 'redis',
                'queue' => ['tg-double-check'],
                'balance' => 'auto',
                'autoScalingStrategy' => 'time',
                'maxProcesses' => 4,
                'balanceMaxShift' => 1,
                'balanceCooldown' => 3,
                'tries' => 2,
                'timeout' => 900,
                'memory' => 512,
            ],
            'max-inspect' => [
                'connection' => 'redis',
                'queue' => ['max-inspect'],
                'balance' => 'auto',
                'autoScalingStrategy' => 'time',
                'maxProcesses' => 2,
                'balanceMaxShift' => 1,
                'balanceCooldown' => 3,
                'tries' => 1,
                'timeout' => 120,
                'memory' => 128,
            ],
            'main' => [
                'connection' => 'redis',
                'queue' => ['external-provider', 'socpanel-poll', 'memberpro-poll', 'providers', 'default'],
                'balance' => 'auto',
                'autoScalingStrategy' => 'time',
                'maxProcesses' => 4,
                'balanceMaxShift' => 1,
                'balanceCooldown' => 3,
                'tries' => 3,
                'timeout' => 300,
                'memory' => 256,
            ],
        ],
    ],
];
