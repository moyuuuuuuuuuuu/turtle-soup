<?php

declare(strict_types=1);

namespace App\Auth\Services;

use App\Auth\Business\AnonymousSessionBusiness;
use App\Auth\Entities\PlayerContext;
use App\Auth\Models\RefreshSession;
use App\Auth\Models\User;
use App\Common\Enums\ErrorCode;

final class PlayerPrincipalService
{
    public function authenticate(string $token): PlayerContext
    {
        if (substr_count($token, '.') === 2) {
            return (new PlayerTokenService())->authenticate($token);
        }
        $session = (new AnonymousSessionBusiness())->authenticate($token);
        return new PlayerContext(anonymousSessionId: (int) $session->id);
    }

    public function validate(PlayerContext $context): void
    {
        if (!$context->isUser()) {
            return;
        }
        if (($context->accessExpiresAt ?? 0) <= time()) {
            ErrorCode::AUTH_TOKEN_INVALID->throw();
        }
        $user = User::find($context->userId);
        $session = RefreshSession::find($context->refreshSessionId);
        if (!$user instanceof User || $user->getAttribute('status') !== 'active') {
            ErrorCode::AUTH_USER_DISABLED->throw();
        }
        if (!$session instanceof RefreshSession || $session->getAttribute('revoked_at') || strtotime((string) $session->getAttribute('expires_at')) <= time()) {
            ErrorCode::AUTH_TOKEN_INVALID->throw();
        }
    }
}
