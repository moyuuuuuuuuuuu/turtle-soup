<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class SeedClassicTurtleSoupTags extends AbstractMigration
{
    /** @var array<string, string> */
    private const TAGS = [
        'honkaku' => '本格',
        'henkaku' => '变格',
        'neo-honkaku' => '新本格',
        'wordplay' => '文字诡计',
        'identity-misdirection' => '身份误导',
        'unreliable-narrator' => '叙述诡计',
        'time-trick' => '时间诡计',
        'closed-room' => '密室',
        'multiple-reversal' => '多重反转',
        'one-line' => '一句话汤',
    ];

    public function up(): void
    {
        $this->execute(
            "UPDATE turtle_tags SET name = '清汤', slug = 'clear-soup', update_time = CURRENT_TIMESTAMP
             WHERE slug = 'white-soup'
               AND NOT EXISTS (SELECT 1 FROM (SELECT slug FROM turtle_tags) tags WHERE tags.slug = 'clear-soup')",
        );

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
        $this->execute(
            "UPDATE turtle_tags SET name = '白汤', slug = 'white-soup', update_time = CURRENT_TIMESTAMP
             WHERE slug = 'clear-soup'
               AND NOT EXISTS (SELECT 1 FROM (SELECT slug FROM turtle_tags) tags WHERE tags.slug = 'white-soup')",
        );
    }

    private function quotedSlugs(): string
    {
        return implode(', ', array_map(
            static fn (string $slug): string => "'" . addslashes($slug) . "'",
            array_keys(self::TAGS),
        ));
    }
}
