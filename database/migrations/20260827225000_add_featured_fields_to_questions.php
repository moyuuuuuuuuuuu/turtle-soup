<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddFeaturedFieldsToQuestions extends AbstractMigration
{
    public function change(): void
    {
        $this->table('turtle_questions')
            ->addColumn('is_featured', 'boolean', ['null' => false, 'default' => false, 'after' => 'risk_reviewed_at'])
            ->addColumn('featured_sort', 'integer', ['null' => false, 'default' => 0, 'signed' => false, 'after' => 'is_featured'])
            ->addColumn('featured_starts_at', 'datetime', ['null' => true, 'after' => 'featured_sort'])
            ->addColumn('featured_ends_at', 'datetime', ['null' => true, 'after' => 'featured_starts_at'])
            ->addIndex(['status', 'is_featured', 'featured_sort'], ['name' => 'idx_question_featured'])
            ->update();
    }
}
