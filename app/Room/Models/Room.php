<?php

declare(strict_types=1);

namespace App\Room\Models;

use App\Common\Models\PersistenceModel;
use App\Game\Models\Game;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $public_id
 * @property string $invite_code
 * @property string $name
 * @property string $status
 * @property string $visibility
 * @property int $max_players
 * @property int $owner_user_id
 * @property int|null $game_id
 * @property string $content_locale
 * @property bool $risk_confirmed
 * @property string|null $create_time
 * @property Collection<int, RoomMember> $members
 * @property Collection<int, RoomMessage> $messages
 * @property Game|null $game
 */

final class Room extends PersistenceModel
{
    protected $table = 'turtle_rooms';

    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'risk_confirmed' => 'boolean',
            'started_at' => 'datetime:Y-m-d H:i:s',
            'finished_at' => 'datetime:Y-m-d H:i:s',
        ]);
    }

    /** @return HasMany<RoomMember, $this> */
    public function members(): HasMany
    {
        return $this->hasMany(RoomMember::class);
    }

    /** @return HasMany<RoomMessage, $this> */
    public function messages(): HasMany
    {
        $relation = $this->hasMany(RoomMessage::class);
        $relation->getQuery()->orderBy('sequence');

        return $relation;
    }

    /** @return BelongsTo<Game, $this> */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }
}
