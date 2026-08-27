<?php

declare(strict_types=1);

return [
    'idle_timeout_seconds' => (int) env('ROOM_IDLE_TIMEOUT_SECONDS', 1800),
    'cleanup_interval_seconds' => (int) env('ROOM_CLEANUP_INTERVAL_SECONDS', 60),
];
