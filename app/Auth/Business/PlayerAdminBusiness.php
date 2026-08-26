<?php

declare(strict_types=1);

namespace App\Auth\Business;

use App\Auth\Formats\PlayerFormat;
use App\Auth\Models\AnonymousMergeLog;
use App\Auth\Models\RefreshSession;
use App\Auth\Models\User;
use App\Auth\Models\UserLoginLog;
use App\Common\Enums\ErrorCode;

final class PlayerAdminBusiness
{
    public function page(array $filters, int $page, int $size): array
    {
        $query = User::query();
        if ($keyword = trim((string) ($filters['keyword'] ?? ''))) {
            $query->where(fn ($q) => $q->where('username', 'like', "%{$keyword}%")->orWhere('email', 'like', "%{$keyword}%"));
        }
        if ($status = (string) ($filters['status'] ?? '')) {
            $query->where('status', $status);
        }
        $total = $query->count();
        $items = $query->orderByDesc('id')->forPage($page, $size)->get()->map(fn (User $user) => array_merge(PlayerFormat::user($user), ['database_id' => (int) $user->id, 'active_sessions' => RefreshSession::query()->where('user_id', $user->id)->whereNull('revoked_at')->where('expires_at', '>', date('Y-m-d H:i:s'))->count()]))->all();
        return ['items' => $items, 'total' => $total, 'page' => $page, 'pageSize' => $size];
    }
    public function read(int $id): array
    {
        $user = User::find($id);
        if (!$user instanceof User) {
            ErrorCode::AUTH_USER_NOT_FOUND->throw();
        } return ['user' => PlayerFormat::user($user), 'sessions' => (new \App\Auth\Repositories\PlayerRepository())->sessions($id)];
    }
    public function status(int $id, string $status): void
    {
        if (!in_array($status, ['active', 'disabled'], true)) {
            ErrorCode::PARAM_ERROR->throw();
        } $user = User::find($id);
        if (!$user instanceof User) {
            ErrorCode::AUTH_USER_NOT_FOUND->throw();
        } $user->update(['status' => $status]);
        if ($status === 'disabled') {
            $this->revoke($id);
        }
    }
    public function revoke(int $id): void
    {
        RefreshSession::query()->where('user_id', $id)->whereNull('revoked_at')->update(['revoked_at' => date('Y-m-d H:i:s'), 'revoke_reason' => 'admin_revoked']);
    }
    public function loginLogs(int $id): array
    {
        return UserLoginLog::query()->where('user_id', $id)->orderByDesc('id')->limit(100)->get()->toArray();
    }
    public function mergeLogs(int $id): array
    {
        return AnonymousMergeLog::query()->where('user_id', $id)->orderByDesc('id')->limit(100)->get()->toArray();
    }
}
