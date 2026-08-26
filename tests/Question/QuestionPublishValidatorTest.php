<?php

declare(strict_types=1);

namespace Tests\Question;

use App\Question\DTO\QuestionData;
use App\Question\Support\QuestionPublishValidator;
use PHPUnit\Framework\TestCase;

final class QuestionPublishValidatorTest extends TestCase
{
    public function testCompleteChineseCanPublishWhenEnglishIsMissing(): void
    {
        $data = $this->data();

        self::assertTrue(QuestionPublishValidator::canPublishChinese($data));
        self::assertFalse(QuestionPublishValidator::isComplete($data, 'en-US'));
    }

    public function testEveryChinesePointAndThreeHintsAreRequired(): void
    {
        $payload = $this->payload();
        $payload['hints'][2]['translations'][0]['content'] = '';

        self::assertFalse(QuestionPublishValidator::canPublishChinese(QuestionData::fromArray($payload)));
    }

    public function testAtLeastOneRequiredPointIsRequired(): void
    {
        $payload = $this->payload();
        $payload['points'][0]['is_required'] = false;

        self::assertFalse(QuestionPublishValidator::canPublishChinese(QuestionData::fromArray($payload)));
    }

    private function data(): QuestionData
    {
        return QuestionData::fromArray($this->payload());
    }

    /** @return array<string, mixed> */
    private function payload(): array
    {
        return [
            'difficulty' => 3,
            'min_players' => 1,
            'max_players' => 8,
            'translations' => [['language' => 'zh-CN', 'title' => '标题', 'surface' => '汤面', 'bottom' => '汤底']],
            'points' => [[
                'weight' => 10,
                'is_required' => true,
                'sort' => 1,
                'translations' => [['language' => 'zh-CN', 'content' => '关键点']],
            ]],
            'hints' => array_map(fn (int $level) => [
                'level' => $level,
                'translations' => [['language' => 'zh-CN', 'content' => "提示{$level}"]],
            ], [1, 2, 3]),
            'tag_ids' => [],
        ];
    }
}
