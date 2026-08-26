<?php

declare(strict_types=1);

namespace App\Auth\Models;

use App\Common\Models\PersistenceModel;

final class User extends PersistenceModel
{
    protected $table = 'turtle_users';

    protected $hidden = ['password_hash'];
}
