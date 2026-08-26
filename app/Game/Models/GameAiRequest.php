<?php

declare(strict_types=1);

namespace App\Game\Models;

use App\Common\Models\PersistenceModel;

final class GameAiRequest extends PersistenceModel
{
    protected $table = 'turtle_game_ai_requests';

    protected function casts(): array
    {
        return array_merge(parent::casts(), ['safe_result' => 'array']);
    }
}
