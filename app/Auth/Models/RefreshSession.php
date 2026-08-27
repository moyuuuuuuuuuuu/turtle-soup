<?php

declare(strict_types=1);

namespace App\Auth\Models;

use App\Common\Models\PersistenceModel;

/**
 * @property int $id
 * @property int $user_id
 * @property string $public_id
 * @property string $family_id
 * @property string $token_hash
 * @property null|string $previous_token_hash
 * @property string $device_hash
 * @property string $device_name
 * @property string $platform
 * @property null|string $revoked_at
 * @property string $expires_at
 */
final class RefreshSession extends PersistenceModel
{
    protected $table = 'turtle_refresh_sessions';

    protected $hidden = ['token_hash', 'previous_token_hash', 'device_hash'];
}
