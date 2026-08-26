<?php

declare(strict_types=1);

return [
    'enabled' => filter_var(env('MAIL_ENABLED', false), FILTER_VALIDATE_BOOL),
    'host' => (string) env('SMTP_HOST', ''), 'port' => (int) env('SMTP_PORT', 465),
    'username' => (string) env('SMTP_USERNAME', ''), 'password' => (string) env('SMTP_PASSWORD', ''),
    'encryption' => (string) env('SMTP_ENCRYPTION', 'ssl'),
    'from_address' => (string) env('SMTP_FROM_ADDRESS', env('MAIL_FROM_ADDRESS', '')),
    'from_name' => (string) env('SMTP_FROM_NAME', env('MAIL_FROM_NAME', '海龟汤')),
];
