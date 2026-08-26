<?php

declare(strict_types=1);

namespace App\Question\Support;

use App\Question\DTO\QuestionData;

final class QuestionPublishValidator
{
    public static function isComplete(QuestionData $data, string $language): bool
    {
        $translation = array_values(array_filter(
            $data->translations,
            fn (array $item) => ($item['language'] ?? '') === $language,
        ))[0] ?? [];
        $levels = array_unique(array_map(fn (array $item) => (int) ($item['level'] ?? 0), $data->hints));

        return trim((string) ($translation['title'] ?? '')) !== ''
            && trim((string) ($translation['surface'] ?? '')) !== ''
            && trim((string) ($translation['bottom'] ?? '')) !== ''
            && $data->points !== []
            && self::allHaveContent($data->points, $language)
            && array_diff([1, 2, 3], $levels) === []
            && self::allHaveContent($data->hints, $language);
    }

    public static function canPublishChinese(QuestionData $data): bool
    {
        return self::isComplete($data, 'zh-CN')
            && array_filter($data->points, fn (array $item) => (bool) ($item['is_required'] ?? false)) !== [];
    }

    /** @param list<array<string, mixed>> $items */
    private static function allHaveContent(array $items, string $language): bool
    {
        foreach ($items as $item) {
            $matched = false;
            foreach ((array) ($item['translations'] ?? []) as $translation) {
                if (($translation['language'] ?? '') === $language && trim((string) ($translation['content'] ?? '')) !== '') {
                    $matched = true;
                    break;
                }
            }
            if (!$matched) {
                return false;
            }
        }

        return true;
    }
}
