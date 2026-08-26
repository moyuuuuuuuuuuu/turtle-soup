<?php

declare(strict_types=1);

return [
    'paths' => [
        'migrations' => '%%PHINX_CONFIG_DIR%%/database/migrations',
        'seeds' => '%%PHINX_CONFIG_DIR%%/database/seeds',
    ],
    'environments' => [
        'default_migration_table' => 'phinxlog',
        'default_environment' => env('APP_ENV', 'development'),
        'development' => [
            'adapter' => env('DB_TYPE', 'mysql'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'name' => env('DB_NAME', ''),
            'user' => env('DB_USER', ''),
            'pass' => env('DB_PASSWORD', ''),
            'port' => (int) env('DB_PORT', 3306),
            'charset' => env('DB_CHARSET', 'utf8mb4'),
        ],
    ],
    'version_order' => 'creation',
];
