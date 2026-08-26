<?php

declare(strict_types=1);

namespace App\Question\Formats;

use App\Question\Models\Question;

final class QuestionFormat
{
    public static function detail(Question $question, bool $includeBottom): array
    {
        $data = $question->toArray();
        foreach ($data['translations'] ?? [] as &$translation) {
            if (!$includeBottom) {
                unset($translation['bottom']);
            }
        }
        unset($translation);

        return $data;
    }
}
