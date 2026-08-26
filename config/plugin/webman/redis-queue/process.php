<?php

if (!filter_var(env('REDIS_QUEUE_ENABLED', false), FILTER_VALIDATE_BOOL)) {
    return [];
}

return [
    'consumer'  => [
        'handler'     => Webman\RedisQueue\Process\Consumer::class,
        'count'       => (int) env('REDIS_QUEUE_CONSUMER_COUNT', 1),
        'constructor' => [
            // 消费者类目录
            'consumer_dir' => app_path() . '/queue/redis'
        ]
    ]
];
