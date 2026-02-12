<?php

use Illuminate\Support\Str;

return [

    /*
    |--------------------------------------------------------------------------
    | Horizon Name
    |--------------------------------------------------------------------------
    */
    'name' => env('HORIZON_NAME', 'telegram-horizon'),

    /*
    |--------------------------------------------------------------------------
    | Horizon Domain
    |--------------------------------------------------------------------------
    */
    'domain' => env('HORIZON_DOMAIN'),

    /*
    |--------------------------------------------------------------------------
    | Horizon Path
    |--------------------------------------------------------------------------
    */
    'path' => env('HORIZON_PATH', 'horizon'),

    /*
    |--------------------------------------------------------------------------
    | Horizon Redis Connection
    |--------------------------------------------------------------------------
    */
    'use' => 'default',

    /*
    |--------------------------------------------------------------------------
    | Horizon Redis Prefix
    |--------------------------------------------------------------------------
    */
    'prefix' => env(
        'HORIZON_PREFIX',
        Str::slug(env('APP_NAME', 'laravel'), '_') . '_horizon:'
    ),

    /*
    |--------------------------------------------------------------------------
    | Horizon Route Middleware
    |--------------------------------------------------------------------------
    */
    'middleware' => ['web'],

    /*
    |--------------------------------------------------------------------------
    | Queue Wait Time Thresholds
    |--------------------------------------------------------------------------
    */
    'waits' => [
        'redis:tg-inspect' => 180,

        'redis:tg-p0' => 180,
        'redis:tg-p1' => 180,
        'redis:tg-p2' => 180,
        'redis:tg-p3' => 180,

        'redis:default' => 180,
    ],

    /*
    |--------------------------------------------------------------------------
    | Job Trimming Times
    |--------------------------------------------------------------------------
    */
    'trim' => [
        'recent' => 60,
        'pending' => 60,
        'completed' => 60,
        'recent_failed' => 10080,
        'failed' => 10080,
        'monitored' => 10080,
    ],

    /*
    |--------------------------------------------------------------------------
    | Silenced Jobs
    |--------------------------------------------------------------------------
    */
    'silenced' => [],
    'silenced_tags' => [],

    /*
    |--------------------------------------------------------------------------
    | Metrics
    |--------------------------------------------------------------------------
    */
    'metrics' => [
        'trim_snapshots' => [
            'job' => 24,
            'queue' => 24,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Fast Termination
    |--------------------------------------------------------------------------
    */
    'fast_termination' => false,

    /*
    |--------------------------------------------------------------------------
    | Memory Limit (MB)
    |--------------------------------------------------------------------------
    */
    'memory_limit' => 256,

    /*
    |--------------------------------------------------------------------------
    | Queue Worker Configuration (SUPERVISORS)
    |--------------------------------------------------------------------------
    */
    'defaults' => [

        /*
        |----------------------------------------------------------
        | High priority (subscribe / unsubscribe)
        | tg-p0, tg-p1
        |----------------------------------------------------------
        */
        'tg-high' => [
            'connection' => 'redis',
            'queue' => ['tg-p0', 'tg-p1'],
            'balance' => 'simple',
            'autoScalingStrategy' => 'time',
            'maxProcesses' => 4,
            'maxTime' => 0,
            'maxJobs' => 0,
            'memory' => 128,
            'tries' => 1,
            'timeout' => 60,
            'nice' => 0,
        ],

        /*
        |----------------------------------------------------------
        | Low priority (views, reactions, bot_start, dripfeed)
        | tg-p2, tg-p3
        |----------------------------------------------------------
        */
        'tg-low' => [
            'connection' => 'redis',
            'queue' => ['tg-p2', 'tg-p3'],
            'balance' => 'simple',
            'autoScalingStrategy' => 'time',
            'maxProcesses' => 2,
            'maxTime' => 0,
            'maxJobs' => 0,
            'memory' => 128,
            'tries' => 1,
            'timeout' => 180,
            'nice' => 5,
        ],

        /*
        |----------------------------------------------------------
        | Inspect / misc jobs
        | tg-inspect + default
        |----------------------------------------------------------
        */
        'tg-inspect' => [
            'connection' => 'redis',
            'queue' => ['tg-inspect'], // ✅ remove default
            'balance' => 'simple',
            'autoScalingStrategy' => 'time',
            'maxProcesses' => 6,
            'maxTime' => 0,
            'maxJobs' => 0,
            'memory' => 256,          // ✅ MTProto is heavy
            'tries' => 1,
            'timeout' => 210,
            'nice' => 10,
        ],

        'socpanel-poll' => [
            'connection' => 'redis',
            'queue' => ['socpanel-poll'],
            'balance' => 'simple',
            'maxProcesses' => 1,
            'maxTime' => 0,
            'maxJobs' => 0,
            'memory' => 128,
            'tries' => 1,
            'timeout' => 240,
            'nice' => 5,
        ],


        'default' => [
            'connection' => 'redis',
            'queue' => ['default'],
            'balance' => 'simple',
            'maxProcesses' => 2,
            'maxTime' => 0,
            'maxJobs' => 0,
            'memory' => 128,
            'tries' => 1,
            'timeout' => 120,
            'nice' => 10,
        ],


        'providers' => [
            'connection' => 'redis',
            'queue' => ['providers'],
            'balance' => 'simple',
            'maxProcesses' => 1,
            'maxTime' => 0,
            'maxJobs' => 0,
            'memory' => 64,
            'tries' => 3,
            'timeout' => 120,
            'nice' => 10,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Environment Overrides
    |--------------------------------------------------------------------------
    */
    'environments' => [

        'production' => [

            'tg-high' => [
                'maxProcesses' => 16,
                'balanceMaxShift' => 2,
                'balanceCooldown' => 3,
            ],

            'tg-low' => [
                'maxProcesses' => 6,
                'balanceMaxShift' => 1,
                'balanceCooldown' => 3,
            ],

            'tg-inspect' => [
                'maxProcesses' => 3,
                'balanceMaxShift' => 1,
                'balanceCooldown' => 3,
            ],

            'providers' => [
                'maxProcesses' => 1,
            ],
        ],

        'local' => [

            'tg-high' => [
                'maxProcesses' => 4,
            ],

            'tg-low' => [
                'maxProcesses' => 2,
            ],

            'tg-inspect' => [
                'maxProcesses' => 2,
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | File Watcher Configuration
    |--------------------------------------------------------------------------
    */
    'watch' => [
        'app',
        'bootstrap',
        'config/**/*.php',
        'database/**/*.php',
        'routes',
        'composer.lock',
        'composer.json',
        '.env',
    ],
];
