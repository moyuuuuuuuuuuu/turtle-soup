<?php

declare(strict_types=1);

namespace App\Game\Support;

final class QuestionLimitResolver
{
    /** @var array<int, int> */
    private const DEFAULT_LIMITS = [1 => 12, 2 => 20, 3 => 28, 4 => 36, 5 => 44];

    public static function resolve(int $difficulty, ?int $questionLimit): int
    {
        if ($questionLimit !== null) {
            return $questionLimit;
        }
        $configured = (array) config('game.question_limits');
        $limits = $configured === [] ? self::DEFAULT_LIMITS : $configured;

        return (int) ($limits[$difficulty] ?? 12);
    }
}
