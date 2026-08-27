<?php

declare(strict_types=1);

namespace App\Game\Models;

use App\Common\Models\PersistenceModel;
use App\Question\Models\Question;
use App\Room\Models\Room;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $public_id
 * @property int|null $room_id
 * @property Room|null $room
 * @property Question|null $question
 */

final class Game extends PersistenceModel
{
    protected $table = 'turtle_games';
    protected function casts(): array
    {
        return array_merge(parent::casts(), ['question_snapshot' => 'array','risk_confirmed' => 'boolean','started_at' => 'datetime:Y-m-d H:i:s','finished_at' => 'datetime:Y-m-d H:i:s']);
    }
    public function messages()
    {
        return $this->hasMany(GameMessage::class)->orderBy('sequence');
    }
    public function hints()
    {
        return $this->hasMany(GameHint::class);
    }
    public function points()
    {
        return $this->hasMany(GameDiscoveredPoint::class);
    }
    public function guess()
    {
        return $this->hasOne(GameGuess::class);
    }

    /** @return BelongsTo<Room, $this> */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    /** @return HasMany<GamePlayer, $this> */
    public function players(): HasMany
    {
        return $this->hasMany(GamePlayer::class);
    }

    /** @return BelongsTo<Question, $this> */
    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }
}
