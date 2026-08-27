<?php

declare(strict_types=1);

namespace Tests\Ai;

use App\Ai\Services\MockContentParser;
use App\Ai\Support\ContentParseValidator;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ContentParseValidatorTest extends TestCase
{
    public function testMockResultSatisfiesContract(): void
    {
        $result = (new MockContentParser())->parse(['story' => str_repeat('测试故事', 10)]);

        self::assertSame($result, ContentParseValidator::validate($result));
    }

    public function testRejectsMissingProtectedBottom(): void
    {
        $result = (new MockContentParser())->parse(['story' => str_repeat('测试故事', 10)]);
        unset($result['translations'][0]['bottom']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('ai.invalid_response');
        ContentParseValidator::validate($result);
    }

    public function testAcceptsReviewedRiskSuggestion(): void
    {
        $result = (new MockContentParser())->parse(['story' => str_repeat('测试故事', 10)]);
        $result['risk_level'] = 'caution';
        $result['risk_types'] = ['death', 'violence', 'death'];
        $result['risk_note'] = '包含死亡与暴力情节。';

        $validated = ContentParseValidator::validate($result);

        self::assertSame(['death', 'violence'], $validated['risk_types']);
    }

    public function testRejectsRiskWithoutReviewNote(): void
    {
        $result = (new MockContentParser())->parse(['story' => str_repeat('测试故事', 10)]);
        $result['risk_level'] = 'restricted';
        $result['risk_types'] = ['child_safety'];

        $this->expectException(RuntimeException::class);
        ContentParseValidator::validate($result);
    }

    public function testNormalizesStructuredTags(): void
    {
        $result = (new MockContentParser())->parse(['story' => str_repeat('测试故事', 10)]);
        $result['suggested_tags'] = [
            ['name' => '悬疑', 'slug' => 'mystery'],
            ['name' => '悬疑重复', 'slug' => 'mystery'],
        ];

        $validated = ContentParseValidator::validate($result);

        self::assertSame([['name' => '悬疑重复', 'slug' => 'mystery']], $validated['suggested_tags']);
    }

    public function testRejectsInvalidPlayerRange(): void
    {
        $result = (new MockContentParser())->parse(['story' => str_repeat('测试故事', 10)]);
        $result['min_players'] = 5;
        $result['max_players'] = 3;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('ai.invalid_response');
        ContentParseValidator::validate($result);
    }

    public function testRejectsIncompletePointTranslation(): void
    {
        $result = (new MockContentParser())->parse(['story' => str_repeat('测试故事', 10)]);
        $result['translations'][] = [
            'language' => 'en-US',
            'title' => 'Title',
            'surface' => 'Surface',
            'bottom' => 'Bottom',
        ];

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('ai.invalid_response');
        ContentParseValidator::validate($result);
    }
}
