<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Horizon Domain
    |--------------------------------------------------------------------------
    |
    | This is the subdomain where Horizon will be accessible from. If this
    | setting is null, Horizon will reside under the same domain as the
    | main application. Otherwise, this value will serve as the subdomain.
    |
    */

    'domain' => env('HORIZON_DOMAIN'),

    /*
    |--------------------------------------------------------------------------
    | Horizon Path
    |--------------------------------------------------------------------------
    |
    | This is the URI path where Horizon will be accessible from. Feel free
    | to change this path to anything you like. Note that the URI will not
    | affect the paths of its internal API that aren't exposed to users.
    |
    */

    'path' => env('HORIZON_PATH', 'horizon'),

    /*
    |--------------------------------------------------------------------------
    | Horizon Storage Driver
    |--------------------------------------------------------------------------
    |
    | This configuration option determines the storage driver that will
    | be used to store Horizon metrics and snapshot data. By default,
    | Horizon stores this data in Redis for fast access.
    |
    */

    'use' => 'default',

    /*
    |--------------------------------------------------------------------------
    | Horizon Prefix
    |--------------------------------------------------------------------------
    |
    | This prefix will be used when storing all Horizon data in Redis.
    | You may modify this prefix if you are running multiple Horizon
    | instances on the same Redis database.
    |
    */

    'prefix' => env('HORIZON_PREFIX', 'horizon:'),

    /*
    |--------------------------------------------------------------------------
    | Horizon Route Middleware
    |--------------------------------------------------------------------------
    |
    | These middleware will get attached to every Horizon route, giving you
    | the chance to add your own middleware to this list or change any of
    | the existing middleware. Or, you can change the host or scheme.
    |
    */

    'middleware' => ['web', 'auth', 'admin'],

    /*
    |--------------------------------------------------------------------------
    | Queue Wait Time Thresholds
    |--------------------------------------------------------------------------
    |
    | This option configures the threshold (in seconds) for how long a job
    | can wait in queue before Horizon generates a warning notification.
    |
    */

    'waits' => [
        'redis:high' => 30,
        'redis:default' => 60,
        'redis:embeddings' => 120,
        'redis:exports' => 300,
        'redis:notifications' => 60,
    ],

    /*
    |--------------------------------------------------------------------------
    | Job Trimming Times
    |--------------------------------------------------------------------------
    |
    | Here you can configure for how long (in minutes) Horizon will keep
    | completed, failed, and pending jobs in storage.
    |
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
    | Fast Termination
    |--------------------------------------------------------------------------
    |
    | When this option is enabled, Horizon will terminate immediately when
    | receiving a SIGTERM signal instead of waiting for workers to finish.
    |
    */

    'fast_termination' => false,

    /*
    |--------------------------------------------------------------------------
    | Memory Limit
    |--------------------------------------------------------------------------
    |
    | Note that Horizon memory limit is specified in megabytes.
    |
    */

    'memory_limit' => 128,

    /*
    |--------------------------------------------------------------------------
    | Queue Worker Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may define the queue worker settings used by your application.
    | You can configure supervisors for different environments (local, prod).
    |
    */

    'defaults' => [
        'supervisor-high' => [
            'connection' => 'redis',
            'queue' => ['high', 'default'],
            'balance' => 'auto',
            'auto_scaling_strategy' => 'time',
            'min_processes' => 2,
            'max_processes' => 10,
            'balance_max_shift' => 1,
            'balance_cooldown' => 3,
            'memory' => 128,
            'tries' => 3,
            'timeout' => 60,
        ],
        'supervisor-embeddings' => [
            'connection' => 'redis',
            'queue' => ['embeddings', 'exports', 'notifications'],
            'balance' => 'auto',
            'auto_scaling_strategy' => 'time',
            'min_processes' => 1,
            'max_processes' => 5,
            'balance_max_shift' => 1,
            'balance_cooldown' => 5,
            'memory' => 256,
            'tries' => 3,
            'timeout' => 300,
        ],
    ],

    'environments' => [
        'production' => [
            'supervisor-high' => [
                'max_processes' => 20,
                'min_processes' => 3,
            ],
            'supervisor-embeddings' => [
                'max_processes' => 10,
                'min_processes' => 2,
            ],
        ],

        'local' => [
            'supervisor-high' => [
                'max_processes' => 5,
            ],
            'supervisor-embeddings' => [
                'max_processes' => 3,
            ],
        ],
    ],
];
