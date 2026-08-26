<?php

declare(strict_types=1);

namespace App\Auth\Models;

use App\Common\Models\PersistenceModel;

final class RefreshSession extends PersistenceModel
{
    protected $table = 'turtle_refresh_sessions';

    protected $hidden = ['token_hash', 'previous_token_hash', 'device_hash'];
}
