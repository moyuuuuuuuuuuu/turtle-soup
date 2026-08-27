<?php

declare(strict_types=1);

namespace App\Auth\Services;

use App\Auth\Entities\PlayerContext;
use App\Auth\Models\RefreshSession;
use App\Auth\Models\User;
use App\Common\Enums\ErrorCode;
use App\Common\Support\PublicId;
use JsonException;
use support\Db;

final class PlayerTokenService
{
    /** @return array{access_token:string,refresh_token:string,expires_in:int,session:array<string,mixed>} */
    public function issue(User $user, string $deviceId, string $deviceName, string $platform): array
    {
        $this->assertConfigured();
        $now = date('Y-m-d H:i:s');
        $deviceHash = hash('sha256', $deviceId);

        return Db::transaction(function () use ($user, $deviceHash, $deviceName, $platform, $now): array {
            $sessions = RefreshSession::query()
                ->where('user_id', $user->id)
                ->whereNull('revoked_at')
                ->where('expires_at', '>', $now)
                ->lockForUpdate()
                ->get();
            $refresh = bin2hex(random_bytes(32));
            $session = RefreshSession::query()
                ->where('user_id', $user->id)
                ->whereNull('revoked_at')
                ->where('expires_at', '>', $now)
                ->where('device_hash', $deviceHash)
                ->lockForUpdate()
                ->first();
            if ($session instanceof RefreshSession) {
                $session->update([
                    'previous_token_hash' => $session->token_hash,
                    'token_hash' => $this->hash($refresh),
                    'device_name' => mb_substr(trim($deviceName) ?: '未知设备', 0, 100),
                    'platform' => mb_substr($platform ?: 'unknown', 0, 30),
                    'last_used_at' => $now,
                    'expires_at' => date('Y-m-d H:i:s', time() + (int) config('player_auth.refresh_ttl', 2592000)),
                ]);

                return $this->response($user, $session->refresh(), $refresh);
            }
            if ($sessions->count() >= max(1, (int) config('player_auth.max_sessions', 3))) {
                ErrorCode::AUTH_DEVICE_LIMIT_REACHED->throw();
            }
            $session = new RefreshSession();
            $session->fill([
                'public_id' => PublicId::make(), 'user_id' => $user->id, 'family_id' => PublicId::make(),
                'token_hash' => $this->hash($refresh), 'device_hash' => $deviceHash,
                'device_name' => mb_substr(trim($deviceName) ?: '未知设备', 0, 100), 'platform' => mb_substr($platform ?: 'unknown', 0, 30),
                'last_used_at' => $now, 'expires_at' => date('Y-m-d H:i:s', time() + (int) config('player_auth.refresh_ttl', 2592000)),
            ]);
            $session->save();

            return $this->response($user, $session, $refresh);
        });
    }

    /** @return array{access_token:string,refresh_token:string,expires_in:int,session:array<string,mixed>} */
    public function refresh(string $token): array
    {
        $hash = $this->hash($token);
        $session = RefreshSession::query()->where('token_hash', $hash)->first();
        if (!$session instanceof RefreshSession) {
            $reused = RefreshSession::query()->where('previous_token_hash', $hash)->first();
            if ($reused instanceof RefreshSession) {
                RefreshSession::query()->where('family_id', $reused->family_id)->whereNull('revoked_at')->update(['revoked_at' => date('Y-m-d H:i:s'), 'revoke_reason' => 'refresh_reuse']);
                ErrorCode::AUTH_REFRESH_TOKEN_REUSED->throw();
            }
            ErrorCode::AUTH_TOKEN_INVALID->throw();
        }
        $user = User::find($session->user_id);
        $this->assertUsable($user, $session);
        $next = bin2hex(random_bytes(32));
        $session->update(['previous_token_hash' => $session->token_hash, 'token_hash' => $this->hash($next), 'last_used_at' => date('Y-m-d H:i:s')]);

        return $this->response($user, $session->refresh(), $next);
    }

    public function authenticate(string $jwt): PlayerContext
    {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3 || !hash_equals($this->sign($parts[0].'.'.$parts[1]), $parts[2])) {
            ErrorCode::AUTH_TOKEN_INVALID->throw();
        }
        try {
            $payload = json_decode($this->decode($parts[1]), true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            ErrorCode::AUTH_TOKEN_INVALID->throw();
        }
        if (($payload['typ'] ?? '') !== 'access' || (int) ($payload['exp'] ?? 0) <= time()) {
            ErrorCode::AUTH_TOKEN_INVALID->throw();
        }
        $user = User::find((int) ($payload['sub'] ?? 0));
        $session = RefreshSession::find((int) ($payload['sid'] ?? 0));
        $this->assertUsable($user, $session);

        return new PlayerContext((int) $user->id, refreshSessionId: (int) $session->id, accessExpiresAt: (int) $payload['exp']);
    }

    public function revoke(int $userId, ?int $sessionId, bool $all = false, string $reason = 'logout'): void
    {
        $query = RefreshSession::query()->where('user_id', $userId)->whereNull('revoked_at');
        if (!$all) {
            $query->where('id', $sessionId ?? 0);
        }
        $query->update(['revoked_at' => date('Y-m-d H:i:s'), 'revoke_reason' => $reason]);
    }

    private function response(User $user, RefreshSession $session, string $refresh): array
    {
        $ttl = (int) config('player_auth.access_ttl', 900);
        $header = $this->encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR));
        $payload = $this->encode(json_encode(['typ' => 'access', 'sub' => (int) $user->id, 'sid' => (int) $session->id, 'jti' => bin2hex(random_bytes(12)), 'iat' => time(), 'exp' => time() + $ttl], JSON_THROW_ON_ERROR));
        $access = $header.'.'.$payload.'.'.$this->sign($header.'.'.$payload);

        return ['access_token' => $access, 'refresh_token' => $refresh, 'expires_in' => $ttl, 'session' => ['id' => $session->public_id, 'device_name' => $session->device_name, 'platform' => $session->platform, 'expires_at' => $session->expires_at]];
    }

    private function assertUsable(mixed $user, mixed $session): void
    {
        if (!$user instanceof User || !$session instanceof RefreshSession || $session->revoked_at || strtotime((string) $session->expires_at) <= time()) {
            ErrorCode::AUTH_TOKEN_INVALID->throw();
        }
        if ($user->status !== 'active') {
            ErrorCode::AUTH_USER_DISABLED->throw();
        }
    }

    private function assertConfigured(): void
    {
        if ((string) config('player_auth.jwt_secret') === '' || (string) config('player_auth.token_hash_secret') === '') {
            ErrorCode::CONFIG_ERROR->throw();
        }
    }

    private function hash(string $token): string
    {
        return hash_hmac('sha256', $token, (string) config('player_auth.token_hash_secret'));
    }
    private function sign(string $value): string
    {
        return $this->encode(hash_hmac('sha256', $value, (string) config('player_auth.jwt_secret'), true));
    }
    private function encode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
    private function decode(string $value): string
    {
        return (string) base64_decode(strtr($value, '-_', '+/'), true);
    }
}
