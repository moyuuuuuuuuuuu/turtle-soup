<?php

declare(strict_types=1);

return [
    'access_key' => (string) env('BOS_ACCESS_KEY', ''),
    'secret_key' => (string) env('BOS_SECRET_KEY', ''),
    'endpoint' => rtrim((string) env('BOS_ENDPOINT', 'https://bj.bcebos.com'), '/'),
    'bucket' => (string) env('BOS_BUCKET', ''),
    'public_base_url' => rtrim((string) env('BOS_PUBLIC_BASE_URL', ''), '/'),
];
