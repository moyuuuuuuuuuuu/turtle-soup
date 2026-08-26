<?php

declare(strict_types=1);

namespace App\Question\DTO;

final readonly class QuestionData
{
    /**
     * @param list<array<string, mixed>> $translations
     * @param list<array<string, mixed>> $points
     * @param list<array<string, mixed>> $hints
     * @param list<int> $tagIds
     * @param list<string> $riskTypes
     */
    public function __construct(
        public int $difficulty,
        public int $minPlayers,
        public int $maxPlayers,
        public array $translations,
        public array $points,
        public array $hints,
        public array $tagIds,
        public int $version = 0,
        public string $sourceType = 'manual',
        public string $riskLevel = 'safe',
        public array $riskTypes = [],
        public ?string $riskNote = null,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            difficulty: (int) ($data['difficulty'] ?? 1),
            minPlayers: (int) ($data['min_players'] ?? 1),
            maxPlayers: (int) ($data['max_players'] ?? 1),
            translations: self::translations((array) ($data['translations'] ?? [])),
            points: self::points((array) ($data['points'] ?? [])),
            hints: self::hints((array) ($data['hints'] ?? [])),
            tagIds: array_map('intval', (array) ($data['tag_ids'] ?? [])),
            version: (int) ($data['version'] ?? 0),
            sourceType: (string) ($data['source_type'] ?? 'manual'),
            riskLevel: (string) ($data['risk_level'] ?? 'safe'),
            riskTypes: array_values(array_unique(array_map('strval', (array) ($data['risk_types'] ?? [])))),
            riskNote: trim((string) ($data['risk_note'] ?? '')) ?: null,
        );
    }

    /**
     * @param array<array-key, mixed> $items
     * @return list<array<string, mixed>>
     */
    private static function translations(array $items): array
    {
        return array_map(fn (array $item) => [
            'language' => (string) ($item['language'] ?? ''),
            'title' => (string) ($item['title'] ?? ''),
            'surface' => (string) ($item['surface'] ?? ''),
            'bottom' => (string) ($item['bottom'] ?? ''),
        ], array_values($items));
    }

    /**
     * @param array<array-key, mixed> $items
     * @return list<array<string, mixed>>
     */
    private static function points(array $items): array
    {
        return array_map(fn (array $item) => [
            'weight' => (int) ($item['weight'] ?? 1),
            'is_required' => (bool) ($item['is_required'] ?? false),
            'sort' => (int) ($item['sort'] ?? 0),
            'translations' => self::contents((array) ($item['translations'] ?? [])),
        ], array_values($items));
    }

    /**
     * @param array<array-key, mixed> $items
     * @return list<array<string, mixed>>
     */
    private static function hints(array $items): array
    {
        return array_map(fn (array $item) => [
            'level' => (int) ($item['level'] ?? 0),
            'target_point_id' => isset($item['target_point_id']) ? (int) $item['target_point_id'] : null,
            'translations' => self::contents((array) ($item['translations'] ?? [])),
        ], array_values($items));
    }

    /**
     * @param array<array-key, mixed> $items
     * @return list<array<string, string>>
     */
    private static function contents(array $items): array
    {
        return array_map(fn (array $item) => [
            'language' => (string) ($item['language'] ?? ''),
            'content' => (string) ($item['content'] ?? ''),
        ], array_values($items));
    }
}
