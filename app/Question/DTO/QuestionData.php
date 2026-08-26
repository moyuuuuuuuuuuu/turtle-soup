<?php

declare(strict_types=1);

namespace App\Question\DTO;

final readonly class QuestionData
{
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
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            difficulty: (int) ($data['difficulty'] ?? 1),
            minPlayers: (int) ($data['min_players'] ?? 1),
            maxPlayers: (int) ($data['max_players'] ?? 1),
            translations: array_values((array) ($data['translations'] ?? [])),
            points: array_values((array) ($data['points'] ?? [])),
            hints: array_values((array) ($data['hints'] ?? [])),
            tagIds: array_map('intval', (array) ($data['tag_ids'] ?? [])),
            version: (int) ($data['version'] ?? 0),
            sourceType: (string) ($data['source_type'] ?? 'manual'),
        );
    }
}
