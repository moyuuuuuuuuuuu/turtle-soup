<?php

declare(strict_types=1);

namespace App\Ai\Support;

use RuntimeException;

final class ContentParseValidator
{
    private const RISK_LEVELS = ['safe', 'caution', 'restricted'];
    private const RISK_TYPES = ['death', 'violence', 'gore', 'self_harm', 'sexual', 'child_safety', 'discrimination', 'illegal', 'substance', 'other'];

    public static function validate(array $result): array
    {
        foreach (['difficulty', 'translations', 'points', 'hints', 'suggested_tags', 'quality_warnings', 'risk_level', 'risk_types', 'risk_note'] as $field) {
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
        if (!in_array($result['risk_level'], self::RISK_LEVELS, true)
            || !is_array($result['risk_types'])
            || array_diff($result['risk_types'], self::RISK_TYPES) !== []) {
            throw new RuntimeException('ai.invalid_response');
        }
        $result['risk_types'] = array_values(array_unique($result['risk_types']));
        $result['risk_note'] = trim((string) $result['risk_note']);
        if ($result['risk_level'] !== 'safe' && $result['risk_note'] === '') {
            throw new RuntimeException('ai.invalid_response');
        }

        return $result;
    }
}
