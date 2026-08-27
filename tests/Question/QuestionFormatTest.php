<?php

declare(strict_types=1);

namespace Tests\Question;

use App\Question\Formats\QuestionFormat;
use App\Question\Models\Question;
use App\Question\Models\Tag;
use Illuminate\Database\Eloquent\Collection;
use PHPUnit\Framework\TestCase;

final class QuestionFormatTest extends TestCase
{
    public function testDetailIncludesTagIdsForTheManagementEditor(): void
    {
        $first = new Tag();
        $first->setAttribute('id', 3);
        $second = new Tag();
        $second->setAttribute('id', 8);
        $question = new Question();
        $question->setAttribute('risk_types', ['death', 'violence']);
        $question->setAttribute('risk_note', '包含死亡与暴力情节。');
        $question->setRelation('tags', new Collection([$first, $second]));
        $question->setRelation('translations', new Collection());

        $data = QuestionFormat::detail($question, true);

        self::assertSame([3, 8], $data['tag_ids']);
        self::assertSame(['death', 'violence'], $data['risk_types']);
        self::assertSame('包含死亡与暴力情节。', $data['risk_note']);
    }
}
