<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class SeedQuestionSoupTypeTags extends AbstractMigration
{
    /** @var array<string, string> */
    private const TAGS = [
        'white-soup' => '白汤',
        'red-soup' => '红汤',
        'black-soup' => '黑汤',
    ];

    public function up(): void
    {
        $existing = $this->fetchAll(sprintf(
            'SELECT slug FROM turtle_tags WHERE slug IN (%s)',
            $this->quotedSlugs(),
        ));
        $existingSlugs = array_column($existing, 'slug');
        $now = date('Y-m-d H:i:s');
        $rows = [];
        foreach (self::TAGS as $slug => $name) {
            if (!in_array($slug, $existingSlugs, true)) {
                $rows[] = [
                    'name' => $name,
                    'slug' => $slug,
                    'create_time' => $now,
                    'update_time' => $now,
                ];
            }
        }
        if ($rows !== []) {
            $this->table('turtle_tags')->insert($rows)->saveData();
        }
    }

    public function down(): void
    {
        $this->execute(sprintf(
            'DELETE tag FROM turtle_tags tag LEFT JOIN turtle_question_tags relation ON relation.tag_id = tag.id WHERE relation.tag_id IS NULL AND tag.slug IN (%s)',
            $this->quotedSlugs(),
        ));
    }

    private function quotedSlugs(): string
    {
        return implode(', ', array_map(
            static fn (string $slug): string => "'" . addslashes($slug) . "'",
            array_keys(self::TAGS),
        ));
    }
}
