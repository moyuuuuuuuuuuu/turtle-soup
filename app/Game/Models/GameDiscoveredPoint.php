<?php

declare(strict_types=1);

namespace App\Game\Models;

use App\Common\Models\PersistenceModel;

final class GameDiscoveredPoint extends PersistenceModel
{
    protected $table = 'turtle_game_discovered_points';
}
