<?php

declare(strict_types=1);

namespace App\Auth\Models;

use App\Common\Models\PersistenceModel;

final class AnonymousSession extends PersistenceModel
{
    protected $table = 'turtle_anonymous_sessions';

    protected $hidden = ['token_hash', 'device_hash'];
}
