<?php

declare(strict_types=1);

namespace App\Common\Models;

use support\Model;

abstract class PersistenceModel extends Model
{
    public const CREATED_AT = 'create_time';
    public const UPDATED_AT = 'update_time';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'create_time' => 'datetime:Y-m-d H:i:s',
            'update_time' => 'datetime:Y-m-d H:i:s',
        ];
    }
}
