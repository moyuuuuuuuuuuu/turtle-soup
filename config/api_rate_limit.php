<?php

return [
    'enabled' => filter_var(env('API_RATE_LIMIT_ENABLED', true), FILTER_VALIDATE_BOOL),
    'prefix' => 'hgt:api-rate:',
    'rules' => [
        ['methods' => ['POST'], 'paths' => ['/api/v1/anonymous/session', '/api/v1/anonymous/session/renew'], 'limit' => 60, 'window' => 60],
        ['methods' => ['POST'], 'paths' => ['/api/v1/auth/register', '/api/v1/auth/login/password', '/api/v1/auth/login/email-code', '/api/v1/auth/password/reset'], 'limit' => 10, 'window' => 60],
        ['methods' => ['POST'], 'paths' => ['/api/v1/games/ask', '/api/v1/games/hint', '/api/v1/games/guess'], 'limit' => 30, 'window' => 60],
        ['methods' => ['POST'], 'paths' => ['/api/v1/me/avatar'], 'limit' => 10, 'window' => 3600],
        ['methods' => ['POST'], 'paths' => ['/api/v1/rooms', '/api/v1/rooms/join'], 'limit' => 20, 'window' => 60],
    ],
];
