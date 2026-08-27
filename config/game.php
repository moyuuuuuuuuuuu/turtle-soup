<?php

return [
    'anonymous_token_ttl_days' => (int) env('ANONYMOUS_TOKEN_TTL_DAYS', 30),
    'anonymous_token_secret' => (string) env('ANONYMOUS_TOKEN_SECRET', ''),
    'question_limits' => [1 => 12, 2 => 20, 3 => 28, 4 => 36, 5 => 44],
    'question_judge_workflow_id' => (string) env('COZE_QUESTION_JUDGE_WORKFLOW_ID', ''),
    'guess_judge_workflow_id' => (string) env('COZE_GUESS_JUDGE_WORKFLOW_ID', ''),
];
