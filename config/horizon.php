<?php

use Illuminate\Support\Str;

return [

    'domain' => env('HORIZON_DOMAIN'),

    'path' => env('HORIZON_PATH', 'horizon'),

    'use' => 'default',

    'prefix' => env(
        'HORIZON_PREFIX',
        Str::slug(env('APP_NAME', 'laravel'), '_').'_horizon:'
    ),

    'middleware' => ['web', 'auth'],

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
        | TG HIGH PRIORITY QUEUES
        |--------------------------------------------------------------------------
        */

        'tg-high' => [
            'connection' => 'redis',
            'queue' => ['tg-p0', 'tg-p1'],
            'balance' => 'auto',
            'autoScalingStrategy' => 'time',
            'maxProcesses' => 4,
            'balanceMaxShift' => 1,
            'balanceCooldown' => 3,
            'tries' => 1,
            'timeout' => 120,
            'memory' => 256,
        ],

        /*
        |--------------------------------------------------------------------------
        | TG LOW PRIORITY QUEUES
        |--------------------------------------------------------------------------
        */

        'tg-low' => [
            'connection' => 'redis',
            'queue' => ['tg-p2', 'tg-p3'],
            'balance' => 'auto',
            'autoScalingStrategy' => 'time',
            'maxProcesses' => 3,
            'balanceMaxShift' => 1,
            'balanceCooldown' => 3,
            'tries' => 1,
            'timeout' => 120,
            'memory' => 256,
        ],

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
            'maxProcesses' => 3,
            'balanceMaxShift' => 1,
            'balanceCooldown' => 3,
            'tries' => 2,
            'timeout' => 900, // 🔥 long jobs
            'memory' => 512,
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
            'maxProcesses' => 3,
            'balanceMaxShift' => 1,
            'balanceCooldown' => 3,
            'tries' => 3,
            'timeout' => 300,
            'memory' => 256,
        ],
    ],

    'environments' => [

        'production' => [
            'tg-high' => [
                'connection' => 'redis',
                'queue' => ['tg-p0', 'tg-p1'],
                'balance' => 'auto',
                'autoScalingStrategy' => 'time',
                'maxProcesses' => 4,
                'balanceMaxShift' => 1,
                'balanceCooldown' => 3,
                'tries' => 1,
                'timeout' => 120,
                'memory' => 256,
            ],

            'tg-low' => [
                'connection' => 'redis',
                'queue' => ['tg-p2', 'tg-p3'],
                'balance' => 'auto',
                'autoScalingStrategy' => 'time',
                'maxProcesses' => 3,
                'balanceMaxShift' => 1,
                'balanceCooldown' => 3,
                'tries' => 1,
                'timeout' => 120,
                'memory' => 256,
            ],

            'tg-inspect' => [
                'connection' => 'redis',
                'queue' => ['tg-inspect'],
                'balance' => 'auto',
                'autoScalingStrategy' => 'time',
                'maxProcesses' => 2,
                'balanceMaxShift' => 1,
                'balanceCooldown' => 3,
                'tries' => 2,
                'timeout' => 900,
                'memory' => 512,
            ],

            'main' => [
                'connection' => 'redis',
                'queue' => ['socpanel-poll', 'providers', 'default'],
                'balance' => 'auto',
                'autoScalingStrategy' => 'time',
                'maxProcesses' => 3,
                'balanceMaxShift' => 1,
                'balanceCooldown' => 3,
                'tries' => 3,
                'timeout' => 300,
                'memory' => 256,
            ],
        ],

        'local' => [
            'tg-high' => [
                'connection' => 'redis',
                'queue' => ['tg-p0', 'tg-p1'],
                'balance' => 'auto',
                'autoScalingStrategy' => 'time',
                'maxProcesses' => 4,
                'balanceMaxShift' => 1,
                'balanceCooldown' => 3,
                'tries' => 1,
                'timeout' => 120,
                'memory' => 256,
            ],

            'tg-low' => [
                'connection' => 'redis',
                'queue' => ['tg-p2', 'tg-p3'],
                'balance' => 'auto',
                'autoScalingStrategy' => 'time',
                'maxProcesses' => 3,
                'balanceMaxShift' => 1,
                'balanceCooldown' => 3,
                'tries' => 1,
                'timeout' => 120,
                'memory' => 256,
            ],

            'tg-inspect' => [
                'connection' => 'redis',
                'queue' => ['tg-inspect'],
                'balance' => 'auto',
                'autoScalingStrategy' => 'time',
                'maxProcesses' => 2,
                'balanceMaxShift' => 1,
                'balanceCooldown' => 3,
                'tries' => 2,
                'timeout' => 900,
                'memory' => 512,
            ],

            'main' => [
                'connection' => 'redis',
                'queue' => ['socpanel-poll', 'providers', 'default'],
                'balance' => 'auto',
                'autoScalingStrategy' => 'time',
                'maxProcesses' => 3,
                'balanceMaxShift' => 1,
                'balanceCooldown' => 3,
                'tries' => 3,
                'timeout' => 300,
                'memory' => 256,
            ],
        ],
    ],
];
