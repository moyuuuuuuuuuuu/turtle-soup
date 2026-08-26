<?php

declare(strict_types=1);

namespace App\Auth\Formats;

use App\Auth\Models\User;

final class PlayerFormat
{
    public static function user(User $user): array
    {
        return ['id' => $user->public_id, 'username' => $user->username, 'email' => $user->email, 'status' => $user->status, 'email_verified_at' => $user->email_verified_at, 'username_changed_at' => $user->username_changed_at, 'create_time' => $user->create_time];
    }
}
