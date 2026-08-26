<?php

declare(strict_types=1);

namespace App\Ai\DTO;

use plugin\saiadmin\exception\ApiException;

final readonly class ContentParseInput
{
    public function __construct(
        public string $story,
        public string $sourceLanguage,
        public array $targetLanguages,
        public ?string $cefr,
    ) {
    }

    public static function fromArray(array $data): self
    {
        $story = trim((string) ($data['story'] ?? ''));
        if (mb_strlen($story) < 20 || mb_strlen($story) > 20000) {
            throw new ApiException('故事长度应为 20 到 20000 字');
        }

        return new self(
            story: $story,
            sourceLanguage: (string) ($data['source_language'] ?? 'zh-CN'),
            targetLanguages: array_values((array) ($data['target_languages'] ?? ['zh-CN', 'en-US'])),
            cefr: isset($data['cefr']) && $data['cefr'] !== '' ? (string) $data['cefr'] : null,
        );
    }

    public function toArray(): array
    {
        return [
            'story' => $this->story,
            'source_language' => $this->sourceLanguage,
            'target_languages' => $this->targetLanguages,
            'cefr' => $this->cefr,
        ];
    }
}
