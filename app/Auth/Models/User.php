<?php

declare(strict_types=1);

namespace App\Auth\Models;

use App\Common\Models\PersistenceModel;

/**
 * @property int $id
 * @property string $public_id
 * @property string $username
 * @property string $email
 * @property string $password_hash
 * @property string $status
 * @property null|string $username_changed_at
 * @property null|string $avatar_url
 * @property null|string $bio
 */
final class User extends PersistenceModel
{
    protected $table = 'turtle_users';

    protected $hidden = ['password_hash'];
}
