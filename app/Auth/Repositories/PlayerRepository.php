<?php

declare(strict_types=1);

namespace App\Auth\Repositories;

use App\Auth\Models\AnonymousMergeLog;
use App\Auth\Models\AnonymousSession;
use App\Auth\Models\RefreshSession;
use App\Auth\Models\User;
use App\Game\Models\Game;

final class PlayerRepository
{
    public function byAccount(string $normalized): ?User
    {
        $user = User::query()->where('username_normalized', $normalized)->orWhere('email_normalized', $normalized)->first();
        return $user instanceof User ? $user : null;
    }

    public function byEmail(string $email): ?User
    {
        $user = User::query()->where('email_normalized', $email)->first();
        return $user instanceof User ? $user : null;
    }

    public function mergeAnonymous(User $user, AnonymousSession $session): int
    {
        $existing = AnonymousMergeLog::query()->where('user_id', $user->id)->where('anonymous_session_id', $session->id)->first();
        if ($existing instanceof AnonymousMergeLog) {
            return (int) $existing->merged_games;
        }
        $count = Game::query()->where('anonymous_session_id', $session->id)->whereNull('user_id')->update(['user_id' => $user->id]);
        $session->update(['user_id' => $user->id, 'bound_at' => date('Y-m-d H:i:s'), 'revoked_at' => date('Y-m-d H:i:s')]);
        AnonymousMergeLog::create(['user_id' => $user->id, 'anonymous_session_id' => $session->id, 'merged_games' => $count, 'result' => 'succeeded']);
        return $count;
    }

    public function sessions(int $userId): array
    {
        return RefreshSession::query()->where('user_id', $userId)->whereNull('revoked_at')->where('expires_at', '>', date('Y-m-d H:i:s'))->orderByDesc('last_used_at')->get()->map(fn ($item) => ['id' => $item->public_id, 'device_name' => $item->device_name, 'platform' => $item->platform, 'last_used_at' => $item->last_used_at, 'expires_at' => $item->expires_at])->all();
    }
}
