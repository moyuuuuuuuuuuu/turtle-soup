<?php

declare(strict_types=1);

namespace App\Game\Models;

use App\Auth\Models\User;
use App\Common\Models\PersistenceModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class GameMessage extends PersistenceModel
{
    protected $table = 'turtle_game_messages';
    protected function casts(): array
    {
        return array_merge(parent::casts(), ['metadata' => 'array']);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
