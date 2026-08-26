<?php

return [
    'anonymous_token_ttl_days' => (int) env('ANONYMOUS_TOKEN_TTL_DAYS', 30),
    'anonymous_token_secret' => (string) env('ANONYMOUS_TOKEN_SECRET', ''),
    'question_limits' => [1 => 20, 2 => 16, 3 => 12, 4 => 10, 5 => 8],
    'question_judge_workflow_id' => (string) env('COZE_QUESTION_JUDGE_WORKFLOW_ID', ''),
    'guess_judge_workflow_id' => (string) env('COZE_GUESS_JUDGE_WORKFLOW_ID', ''),
];
