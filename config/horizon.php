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
            'maxProcesses' => 2,
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
            'queue' => ['socpanel-poll', 'providers', 'default'],
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

        'production' => [
            'tg-inspect' => [
                'connection' => 'redis',
                'queue' => ['tg-inspect'],
                'balance' => 'auto',
                'autoScalingStrategy' => 'time',
                'minProcesses' => 1,
                'maxProcesses' => 2,         // was 4; 3 covers burst, saves 1 idle poller
                'balanceMaxShift' => 1,
                'balanceCooldown' => 15,     // was 3; 5× fewer balance Redis checks
                'sleep' => 5,               // was implicit 3; ~40% fewer idle polls
                'tries' => 2,
                'timeout' => 900,
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
                'balanceCooldown' => 15,     // was 3
                'sleep' => 5,               // was implicit 3
                'tries' => 2,
                'timeout' => 900,
                'memory' => 512,
            ],

            // Merged yt-inspect + app-inspect: both had maxProcesses=1, timeout=60, memory=128.
            // One supervisor eliminates a full supervisor process + all auto-scale overhead.
            // Workers serve both queues in priority order (yt first, then app).
            'link-inspect' => [
                'connection' => 'redis',
                'queue' => ['yt-inspect', 'app-inspect'],
                'balance' => 'simple',       // fixed workers, no Redis balance polling
                'minProcesses' => 1,
                'maxProcesses' => 2,
                'balanceCooldown' => 30,
                'sleep' => 10,               // event-driven, low-volume; up to 10s wait is fine
                'tries' => 3,
                'timeout' => 60,
                'memory' => 128,
            ],

            'tg-double-check' => [
                'connection' => 'redis',
                'queue' => ['tg-double-check'],
                'balance' => 'simple',       // was auto; fixed 1 worker, zero rebalancing cost
                'minProcesses' => 1,
                'maxProcesses' => 1,
                'sleep' => 10,               // dispatched every 10min; longer sleep is fine
                'tries' => 2,
                'timeout' => 900,
                'memory' => 512,
            ],

            'max-inspect' => [
                'connection' => 'redis',
                'queue' => ['max-inspect'],
                'balance' => 'simple',       // was auto
                'minProcesses' => 1,
                'maxProcesses' => 1,         // was 2; 2nd worker was idle-polling only
                'sleep' => 10,
                'tries' => 1,
                'timeout' => 120,
                'memory' => 128,
            ],

            'main' => [
                'connection' => 'redis',
                'queue' => ['socpanel-poll', 'memberpro-poll', 'providers', 'default'],
                'balance' => 'auto',
                'autoScalingStrategy' => 'time',
                'minProcesses' => 1,
                'maxProcesses' => 2,
                'balanceMaxShift' => 1,
                'balanceCooldown' => 15,     // was 3
                'sleep' => 5,               // was implicit 3
                'tries' => 3,
                'timeout' => 300,
                'memory' => 256,
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
                'queue' => ['socpanel-poll', 'memberpro-poll', 'providers', 'default'],
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
