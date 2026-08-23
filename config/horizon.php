<?php

use Illuminate\Support\Str;

return [
    'domain' => env('HORIZON_DOMAIN'),
    'path' => env('HORIZON_PATH', 'horizon'),
    'use' => 'default',
    'prefix' => env('HORIZON_PREFIX', Str::slug(env('APP_NAME', 'laravel'), '_').'_horizon:'),
    'middleware' => ['web'],
    'waits' => [
        'redis:default' => 60,
    ],
    'trim' => [
        'recent' => 60,
        'pending' => 60,
        'completed' => 60,
        'recent_failed' => 10080,
        'failed' => 10080,
        'monitored' => 10080,
    ],
    'silenced' => [],
    'metrics' => [
        'trim_snapshots' => [
            'job' => 24,
            'queue' => 24,
        ],
    ],
    'fast_termination' => false,
    'memory_limit' => 64,

    'defaults' => [
        'supervisor-1' => [
            'connection' => 'redis',
            'queue' => ['extract-text', 'extract-media', 'inference', 'explanation', 'default'],
            'balance' => 'auto',
            'autoScalingStrategy' => 'time',
            'maxProcesses' => 1,
            'maxTime' => 0,
            'maxJobs' => 0,
            'memory' => 256,
            'tries' => 1,
            'timeout' => 60,
            'nice' => 0,
        ],
    ],

    'environments' => [
        'production' => [
            'supervisor-1' => [
                'connection' => 'redis',
                'queue' => ['extract-text', 'extract-media'],
                'balance' => 'auto',
                'processes' => 2,
                'tries' => 3,
                'timeout' => 75,
            ],
            'supervisor-2' => [
                'connection' => 'redis',
                'queue' => ['inference'],
                'balance' => 'auto',
                'processes' => 2,
                'tries' => 3,
                'timeout' => 45,
            ],
            'supervisor-3' => [
                'connection' => 'redis',
                'queue' => ['explanation'],
                'balance' => 'auto',
                'processes' => 1,
                'tries' => 3,
                'timeout' => 60,
            ],
            'supervisor-4' => [
                'connection' => 'redis',
                'queue' => ['default'],
                'balance' => 'simple',
                'processes' => 1,
                'tries' => 3,
                'timeout' => 30,
            ],
        ],
        'local' => [
            'supervisor-1' => [
                'connection' => 'redis',
                'queue' => ['extract-text', 'extract-media', 'inference', 'explanation', 'default'],
                'balance' => 'auto',
                'maxProcesses' => 3,
                'tries' => 1,
                'timeout' => 90,
            ],
        ],
    ],
];
