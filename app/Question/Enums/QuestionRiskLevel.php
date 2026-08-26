<?php

declare(strict_types=1);

namespace App\Question\Enums;

enum QuestionRiskLevel: string
{
    case SAFE = 'safe';
    case CAUTION = 'caution';
    case RESTRICTED = 'restricted';

    public function requiresConfirmation(): bool
    {
        return $this !== self::SAFE;
    }
}
