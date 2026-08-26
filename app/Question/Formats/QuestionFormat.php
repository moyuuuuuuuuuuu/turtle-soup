<?php

declare(strict_types=1);

namespace App\Question\Formats;

use App\Question\Models\Question;

final class QuestionFormat
{
    public static function detail(Question $question, bool $includeBottom): array
    {
        $data = $question->toArray();
        if (!$includeBottom && isset($data['translations']) && is_array($data['translations'])) {
            foreach ($data['translations'] as $index => $translation) {
                unset($translation['bottom']);
                $data['translations'][$index] = $translation;
            }
        }

        return $data;
    }
}
