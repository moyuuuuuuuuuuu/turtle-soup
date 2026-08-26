<?php

declare(strict_types=1);

namespace App\Donation\Models;

use App\Common\Models\PersistenceModel;

/**
 * @property int $id
 * @property string $method
 * @property string $name
 * @property string $qr_code_url
 * @property string $qr_code_object_key
 * @property bool $status
 */

final class DonationChannel extends PersistenceModel
{
    protected $table = 'turtle_donation_channels';

    protected function casts(): array
    {
        return array_merge(parent::casts(), ['status' => 'boolean']);
    }
}
