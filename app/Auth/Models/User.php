<?php

declare(strict_types=1);

namespace App\Auth\Models;

use App\Common\Models\PersistenceModel;

/**
 * @property int $id
 * @property string $public_id
 * @property string $username
 * @property string $email
 * @property null|string $avatar_url
 */
final class User extends PersistenceModel
{
    protected $table = 'turtle_users';

    protected $hidden = ['password_hash'];
}
