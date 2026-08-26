<?php

declare(strict_types=1);

namespace Tests\Game;

use App\Game\Services\MockGameJudge;
use PHPUnit\Framework\TestCase;

final class MockGameJudgeTest extends TestCase
{
    /** @var array<string, list<array<string, bool|string>>> */
    private array $context = [
        'points' => [
            ['key' => 'point_1', 'content' => '男人曾经遭遇海难', 'required' => true],
            ['key' => 'point_2', 'content' => '食物并非海龟肉', 'required' => true],
        ],
    ];

    public function testQuestionReturnsStableFourWayJudgement(): void
    {
        $result = (new MockGameJudge())->judgeQuestion($this->context, '男人是否遭遇过海难？');

        self::assertContains($result['answer'], ['yes', 'no', 'irrelevant', 'partial']);
        self::assertContains('point_1', $result['matched_point_keys']);
    }

    public function testGuessReturnsBooleanAndMatchedPointKeys(): void
    {
        $result = (new MockGameJudge())->judgeGuess($this->context, '男人海难时吃的并非海龟肉');

        self::assertIsBool($result['is_solved']);
        self::assertNotEmpty($result['matched_point_keys']);
    }
}
