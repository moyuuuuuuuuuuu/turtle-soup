<?php

declare(strict_types=1);

namespace App\Auth\Business;

use App\Auth\Models\AnonymousSession;
use App\Common\Enums\ErrorCode;
use App\Common\Support\PublicId;

final class AnonymousSessionBusiness
{
    /** @return array{token:string,expires_at:string,session_id:string} */
    public function issue(string $deviceId): array
    {
        if (trim($deviceId) === '' || strlen($deviceId) > 200) {
            ErrorCode::PARAM_ERROR->throw();
        }
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', time() + max(1, (int) config('game.anonymous_token_ttl_days', 30)) * 86400);
        $session = AnonymousSession::create([
            'public_id' => PublicId::make(),
            'token_hash' => $this->hash($token),
            'device_hash' => hash('sha256', $deviceId),
            'last_active_at' => date('Y-m-d H:i:s'),
            'expires_at' => $expires,
        ]);

        return ['token' => $token, 'expires_at' => $expires, 'session_id' => (string) $session->public_id];
    }

    public function authenticate(string $token): AnonymousSession
    {
        $session = AnonymousSession::query()->where('token_hash', $this->hash($token))->first();
        if (!$session instanceof AnonymousSession || strtotime((string) $session->expires_at) <= time()) {
            ErrorCode::AUTH_ANONYMOUS_INVALID->throw();
        }
        $session->update(['last_active_at' => date('Y-m-d H:i:s')]);

        return $session;
    }

    /** @return array{token:string,expires_at:string,session_id:string} */
    public function renew(string $token): array
    {
        $session = $this->authenticate($token);
        $newToken = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', time() + max(1, (int) config('game.anonymous_token_ttl_days', 30)) * 86400);
        $session->update(['token_hash' => $this->hash($newToken), 'expires_at' => $expires]);

        return ['token' => $newToken, 'expires_at' => $expires, 'session_id' => (string) $session->public_id];
    }

    private function hash(string $token): string
    {
        return hash_hmac('sha256', $token, (string) config('game.anonymous_token_secret', ''));
    }
}
