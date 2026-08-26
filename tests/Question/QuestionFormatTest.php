<?php

declare(strict_types=1);

namespace Tests\Question;

use App\Question\Formats\QuestionFormat;
use App\Question\Models\Question;
use App\Question\Models\QuestionTranslation;
use Illuminate\Database\Eloquent\Collection;
use PHPUnit\Framework\TestCase;

final class QuestionFormatTest extends TestCase
{
    public function testProtectedBottomIsRemovedFromPreview(): void
    {
        $question = new Question(['id' => 1, 'status' => 'draft']);
        $question->setRelation('translations', new Collection([
            new QuestionTranslation([
                'language' => 'zh-CN',
                'title' => '标题',
                'surface' => '汤面',
                'bottom' => '受保护的汤底',
            ]),
        ]));

        $formatted = QuestionFormat::detail($question, false);

        self::assertArrayNotHasKey('bottom', $formatted['translations'][0]);
    }

    public function testAuthorizedDetailIncludesBottom(): void
    {
        $question = new Question(['id' => 1, 'status' => 'draft']);
        $question->setRelation('translations', new Collection([
            new QuestionTranslation([
                'language' => 'zh-CN',
                'bottom' => '受保护的汤底',
            ]),
        ]));

        $formatted = QuestionFormat::detail($question, true);

        self::assertSame('受保护的汤底', $formatted['translations'][0]['bottom']);
    }
}
