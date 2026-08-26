<?php

declare(strict_types=1);

namespace App\Auth\Models;

use App\Common\Models\PersistenceModel;

final class UserLoginLog extends PersistenceModel
{
    protected $table = 'turtle_user_login_logs';
}
