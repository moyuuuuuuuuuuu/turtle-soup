<?php

declare(strict_types=1);

namespace Tests\Game;

use App\Game\Support\QuestionLimitResolver;
use PHPUnit\Framework\TestCase;

final class QuestionLimitResolverTest extends TestCase
{
    public function testExplicitQuestionLimitOverridesDifficultyDefault(): void
    {
        self::assertSame(7, QuestionLimitResolver::resolve(3, 7));
    }

    public function testMissingQuestionLimitUsesDifficultyDefault(): void
    {
        self::assertSame(12, QuestionLimitResolver::resolve(1, null));
        self::assertSame(28, QuestionLimitResolver::resolve(3, null));
        self::assertSame(44, QuestionLimitResolver::resolve(5, null));
    }
}
