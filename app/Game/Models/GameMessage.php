<?php

declare(strict_types=1);

namespace App\Game\Models;

use App\Common\Models\PersistenceModel;

final class GameMessage extends PersistenceModel
{
    protected $table = 'turtle_game_messages';
    protected function casts(): array
    {
        return array_merge(parent::casts(), ['metadata' => 'array']);
    }
}
