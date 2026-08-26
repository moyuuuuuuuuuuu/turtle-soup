<?php

declare(strict_types=1);

namespace App\Auth\Models;

use App\Common\Models\PersistenceModel;

final class EmailCode extends PersistenceModel
{
    protected $table = 'turtle_email_codes';

    protected $hidden = ['code_hash', 'request_ip_hash'];
}
