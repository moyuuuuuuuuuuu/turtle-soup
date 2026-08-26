<?php

declare(strict_types=1);

namespace App\Donation\Models;

use App\Common\Models\PersistenceModel;

/**
 * @property int $id
 * @property string $public_id
 * @property string $donor_name
 * @property string $amount
 * @property string $method
 * @property string|null $message
 * @property \Carbon\CarbonInterface $donated_at
 * @property bool $status
 */

final class Donation extends PersistenceModel
{
    protected $table = 'turtle_donations';

    protected function casts(): array
    {
        return array_merge(parent::casts(), [
            'amount' => 'decimal:2',
            'status' => 'boolean',
            'donated_at' => 'datetime:Y-m-d H:i:s',
        ]);
    }
}
