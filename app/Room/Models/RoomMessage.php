<?php

declare(strict_types=1);

namespace App\Room\Models;

use App\Auth\Models\User;
use App\Common\Models\PersistenceModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $room_id
 * @property int $user_id
 * @property int $sequence
 * @property string $content
 * @property string|null $create_time
 * @property User $user
 */

final class RoomMessage extends PersistenceModel
{
    protected $table = 'turtle_room_messages';

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
