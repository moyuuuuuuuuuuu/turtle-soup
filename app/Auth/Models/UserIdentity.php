<?php

declare(strict_types=1);

namespace App\Auth\Models;

use App\Common\Models\PersistenceModel;

final class UserIdentity extends PersistenceModel
{
    protected $table = 'turtle_user_identities';

    protected function casts(): array
    {
        return array_merge(parent::casts(), ['metadata' => 'array']);
    }
}
