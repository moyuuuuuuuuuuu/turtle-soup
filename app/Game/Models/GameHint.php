<?php

declare(strict_types=1);

namespace App\Game\Models;

use App\Common\Models\PersistenceModel;

final class GameHint extends PersistenceModel
{
    protected $table = 'turtle_game_hints';
}
