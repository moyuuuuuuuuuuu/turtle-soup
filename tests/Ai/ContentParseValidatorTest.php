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
}
