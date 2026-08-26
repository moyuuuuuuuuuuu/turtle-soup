<?php

declare(strict_types=1);

namespace App\Auth\Entities;

final readonly class PlayerContext
{
    public function __construct(
        public ?int $userId = null,
        public ?int $anonymousSessionId = null,
        public ?int $refreshSessionId = null,
        public ?int $accessExpiresAt = null,
    ) {
    }

    public function isUser(): bool
    {
        return $this->userId !== null;
    }
}
