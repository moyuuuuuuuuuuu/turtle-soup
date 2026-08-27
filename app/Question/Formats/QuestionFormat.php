<?php

declare(strict_types=1);

namespace App\Question\Formats;

use App\Question\Models\Question;

final class QuestionFormat
{
    public static function detail(Question $question, bool $includeBottom): array
    {
        $data = $question->toArray();
        $data['tag_ids'] = $question->tags
            ->map(static fn ($tag): int => (int) $tag->getKey())
            ->values()
            ->all();
        $riskTypes = $question->getAttribute('risk_types');
        if (is_string($riskTypes)) {
            $decoded = json_decode($riskTypes, true);
            $riskTypes = is_array($decoded) ? $decoded : [];
        }
        $data['risk_types'] = array_values(array_map('strval', is_array($riskTypes) ? $riskTypes : []));
        $riskNote = $question->getAttribute('risk_note');
        $data['risk_note'] = $riskNote === null ? null : (string) $riskNote;
        if (!$includeBottom && isset($data['translations']) && is_array($data['translations'])) {
            foreach ($data['translations'] as $index => $translation) {
                unset($translation['bottom']);
                $data['translations'][$index] = $translation;
            }
        }

        return $data;
    }
}
