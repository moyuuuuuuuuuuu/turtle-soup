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
 * @property string $role
 * @property string $status
 * @property bool $is_ready
 * @property User $user
 */

final class RoomMember extends PersistenceModel
{
    protected $table = 'turtle_room_members';

    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'is_ready' => 'boolean',
            'joined_at' => 'datetime:Y-m-d H:i:s',
            'left_at' => 'datetime:Y-m-d H:i:s',
            'last_active_at' => 'datetime:Y-m-d H:i:s',
        ]);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
