<?php

declare(strict_types=1);

namespace App\Game\Models;

use App\Auth\Models\User;
use App\Common\Models\PersistenceModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class GamePlayer extends PersistenceModel
{
    protected $table = 'turtle_game_players';

    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'joined_at' => 'datetime:Y-m-d H:i:s',
            'completed_at' => 'datetime:Y-m-d H:i:s',
        ]);
    }

    /** @return BelongsTo<Game, $this> */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
