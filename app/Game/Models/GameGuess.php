<?php

declare(strict_types=1);

namespace App\Game\Models;

use App\Common\Models\PersistenceModel;

final class GameGuess extends PersistenceModel
{
    protected $table = 'turtle_game_guesses';
    protected function casts(): array
    {
        return array_merge(parent::casts(), ['is_solved' => 'boolean','matched_points' => 'array']);
    }
}
