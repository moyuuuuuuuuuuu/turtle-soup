<?php
return [
    'default' => [
        'host' => sprintf('redis://%s:%d', env('REDIS_HOST', '127.0.0.1'), (int) env('REDIS_PORT', 6379)),
        'options' => [
            'auth' => env('REDIS_PASSWORD') ?: null,
            'db' => (int) env('REDIS_QUEUE_DB', 1),
            'prefix' => env('REDIS_QUEUE_PREFIX', 'turtle_soup_queue:'),
            'max_attempts'  => 5,
            'retry_seconds' => 5,
        ],
        // Connection pool, supports only Swoole or Swow drivers.
        'pool' => [
            'max_connections' => 5,
            'min_connections' => 0,
            'wait_timeout' => 3,
            'idle_timeout' => 60,
            'heartbeat_interval' => 50,
        ]
    ],
];
