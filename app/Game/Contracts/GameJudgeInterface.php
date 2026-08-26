<?php

declare(strict_types=1);

namespace App\Game\Contracts;

interface GameJudgeInterface
{
    public function judgeQuestion(array $context, string $question): array;
    public function judgeGuess(array $context, string $guess): array;
}
