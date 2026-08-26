<?php

return [
    'content_parser' => [
        'driver' => env('COZE_DRIVER', 'mock'),
        'base_url' => env('COZE_API_BASE_URL', 'https://api.coze.cn'),
        'token' => env('COZE_API_TOKEN', ''),
        'workflow_id' => env('COZE_WORKFLOW_ID', ''),
        'workflow_version' => 'turtle_content_parser_v1',
        'timeout' => (int) env('COZE_TIMEOUT_SECONDS', 120),
        'retries' => (int) env('COZE_RETRY_TIMES', 1),
        'retry_delay_ms' => (int) env('COZE_RETRY_DELAY_MS', 250),
    ],
    'game_judge' => [
        'driver' => env('COZE_GAME_DRIVER', env('COZE_DRIVER', 'mock')),
        'base_url' => env('COZE_API_BASE_URL', 'https://api.coze.cn'),
        'token' => env('COZE_API_TOKEN', ''),
        'question_workflow_id' => env('COZE_QUESTION_JUDGE_WORKFLOW_ID', ''),
        'guess_workflow_id' => env('COZE_GUESS_JUDGE_WORKFLOW_ID', ''),
        'workflow_id' => '',
        'workflow_version' => 'unknown',
        'timeout' => (int) env('COZE_TIMEOUT_SECONDS', 120),
        'retries' => (int) env('COZE_RETRY_TIMES', 1),
        'retry_delay_ms' => (int) env('COZE_RETRY_DELAY_MS', 250),
    ],
];
