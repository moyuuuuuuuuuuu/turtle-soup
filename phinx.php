<?php

declare(strict_types=1);

require_once __DIR__.'/vendor/autoload.php';

Dotenv\Dotenv::createImmutable(__DIR__)->safeLoad();

$value = static function (string $name, mixed $default = null): mixed {
    $environmentValue = getenv($name);

    return $environmentValue !== false
        ? $environmentValue
        : ($_ENV[$name] ?? $_SERVER[$name] ?? $default);
};

return [
    'paths' => [
        'migrations' => '%%PHINX_CONFIG_DIR%%/database/migrations',
        'seeds' => '%%PHINX_CONFIG_DIR%%/database/seeds',
    ],
    'environments' => [
        'default_migration_table' => 'phinxlog_project',
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
