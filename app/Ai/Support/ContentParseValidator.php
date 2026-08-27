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
        if (!is_int($result['difficulty']) || $result['difficulty'] < 1 || $result['difficulty'] > 5
            || !is_array($result['translations']) || $result['translations'] === []
            || !is_array($result['points']) || $result['points'] === []
            || !is_array($result['hints']) || count($result['hints']) !== 3
            || !is_array($result['suggested_tags']) || !is_array($result['quality_warnings'])) {
            throw new RuntimeException('ai.invalid_response');
        }
        $result['min_players'] ??= 1;
        $result['max_players'] ??= 8;
        if (!is_int($result['min_players']) || !is_int($result['max_players'])
            || $result['min_players'] < 1 || $result['min_players'] > $result['max_players']
            || $result['max_players'] > 8) {
            throw new RuntimeException('ai.invalid_response');
        }
        $languages = [];
        foreach ($result['translations'] as $translation) {
            $language = trim((string) ($translation['language'] ?? ''));
            if ($language === '' || isset($languages[$language])
                || trim((string) ($translation['title'] ?? '')) === ''
                || trim((string) ($translation['surface'] ?? '')) === ''
                || trim((string) ($translation['bottom'] ?? '')) === '') {
                throw new RuntimeException('ai.invalid_response');
            }
            $languages[$language] = true;
        }
        foreach ($result['points'] as $index => $point) {
            if (!is_array($point) || (int) ($point['weight'] ?? 0) < 1 || (int) ($point['weight'] ?? 0) > 100
                || !array_key_exists('is_required', $point) || !is_bool($point['is_required'])
                || (int) ($point['sort'] ?? 0) !== $index + 1
                || !self::hasAllLanguages((array) ($point['translations'] ?? []), $languages)) {
                throw new RuntimeException('ai.invalid_response');
            }
        }
        if (!array_filter($result['points'], static fn (array $point): bool => $point['is_required'])) {
            throw new RuntimeException('ai.invalid_response');
        }
        $hintLevels = [];
        foreach ($result['hints'] as $hint) {
            $level = (int) ($hint['level'] ?? 0);
            if (!is_array($hint) || !in_array($level, [1, 2, 3], true) || isset($hintLevels[$level])
                || !self::hasAllLanguages((array) ($hint['translations'] ?? []), $languages)) {
                throw new RuntimeException('ai.invalid_response');
            }
            $hintLevels[$level] = true;
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
        $result['suggested_tags'] = self::tags($result['suggested_tags']);
        $result['quality_warnings'] = array_values(array_filter(array_map(
            static fn (mixed $warning): string => trim((string) $warning),
            $result['quality_warnings'],
        )));

        return $result;
    }

    /** @param list<array<string, mixed>> $translations
     *  @param array<string, bool> $languages
     */
    private static function hasAllLanguages(array $translations, array $languages): bool
    {
        $contents = [];
        foreach ($translations as $translation) {
            $language = trim((string) ($translation['language'] ?? ''));
            if ($language === '' || trim((string) ($translation['content'] ?? '')) === '') {
                return false;
            }
            $contents[$language] = true;
        }

        return array_diff_key($languages, $contents) === [];
    }

    /** @param list<mixed> $tags
     *  @return list<array{name:string, slug:string}>
     */
    private static function tags(array $tags): array
    {
        $normalized = [];
        foreach (array_slice($tags, 0, 8) as $tag) {
            $name = trim((string) (is_array($tag) ? ($tag['name'] ?? '') : $tag));
            $slug = strtolower(trim((string) (is_array($tag) ? ($tag['slug'] ?? '') : '')));
            if ($name === '' || mb_strlen($name) > 64) {
                throw new RuntimeException('ai.invalid_response');
            }
            if (preg_match('/^[a-z0-9_-]{1,64}$/', $slug) !== 1) {
                $ascii = strtolower(trim((string) preg_replace('/[^a-z0-9]+/i', '-', $name), '-'));
                $slug = $ascii !== '' ? substr($ascii, 0, 64) : 'ai-' . substr(hash('sha256', $name), 0, 16);
            }
            $normalized[$slug] = ['name' => $name, 'slug' => $slug];
        }

        return array_values($normalized);
    }
}
