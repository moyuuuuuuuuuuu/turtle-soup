<?php

declare(strict_types=1);

$value = static fn (string $name, mixed $default = null): mixed => getenv($name) !== false ? getenv($name) : $default;

return [
    'paths' => [
        'migrations' => '%%PHINX_CONFIG_DIR%%/database/migrations',
        'seeds' => '%%PHINX_CONFIG_DIR%%/database/seeds',
    ],
    'environments' => [
        'default_migration_table' => 'phinxlog',
        'default_environment' => 'development',
        'development' => [
            'adapter' => $value('DB_TYPE', 'mysql'),
            'host' => $value('DB_HOST', '127.0.0.1'),
            'name' => $value('DB_NAME', ''),
            'user' => $value('DB_USER', ''),
            'pass' => $value('DB_PASSWORD', ''),
            'port' => (int) $value('DB_PORT', 3306),
            'charset' => $value('DB_CHARSET', 'utf8mb4'),
        ],
    ],
    'version_order' => 'creation',
];
