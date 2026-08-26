<?php

declare(strict_types=1);

namespace App\Ai\Support;

use RuntimeException;

final class ContentParseValidator
{
    public static function validate(array $result): array
    {
        foreach (['difficulty', 'translations', 'points', 'hints', 'quality_warnings'] as $field) {
            if (!array_key_exists($field, $result)) {
                throw new RuntimeException('ai.invalid_response');
            }
        }
        if (!is_array($result['translations']) || !is_array($result['points']) || count($result['hints']) !== 3) {
            throw new RuntimeException('ai.invalid_response');
        }
        foreach ($result['translations'] as $translation) {
            if (trim((string) ($translation['title'] ?? '')) === '' || trim((string) ($translation['surface'] ?? '')) === '' || trim((string) ($translation['bottom'] ?? '')) === '') {
                throw new RuntimeException('ai.invalid_response');
            }
        }

        return $result;
    }
}
