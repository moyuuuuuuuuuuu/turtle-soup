<?php

declare(strict_types=1);

namespace App\Auth\Models;

use App\Common\Models\PersistenceModel;

final class AnonymousMergeLog extends PersistenceModel
{
    protected $table = 'turtle_anonymous_merge_logs';
}
