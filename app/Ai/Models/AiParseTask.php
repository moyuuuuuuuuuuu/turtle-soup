<?php

declare(strict_types=1);

namespace App\Ai\Models;

use App\Common\Models\PersistenceModel;

final class AiParseTask extends PersistenceModel
{
    protected $table = 'turtle_ai_parse_tasks';

    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'request_payload' => 'array',
            'result_payload' => 'array',
        ]);
    }
}
