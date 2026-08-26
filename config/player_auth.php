<?php

declare(strict_types=1);

return [
    'jwt_secret' => (string) env('PLAYER_JWT_SECRET', ''),
    'token_hash_secret' => (string) env('PLAYER_TOKEN_HASH_SECRET', ''),
    'email_code_secret' => (string) env('PLAYER_EMAIL_CODE_SECRET', ''),
    'access_ttl' => (int) env('PLAYER_ACCESS_TOKEN_TTL', 900),
    'refresh_ttl' => (int) env('PLAYER_REFRESH_TOKEN_TTL', 2592000),
    'max_sessions' => (int) env('PLAYER_MAX_SESSIONS', 3),
];
