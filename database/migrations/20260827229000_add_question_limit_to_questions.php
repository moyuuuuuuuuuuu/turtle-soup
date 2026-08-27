<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddQuestionLimitToQuestions extends AbstractMigration
{
    public function up(): void
    {
        $this->table('turtle_questions')
            ->addColumn('question_limit', 'integer', [
                'null' => true,
                'signed' => false,
                'after' => 'difficulty',
                'comment' => 'Per-question ask limit; null uses difficulty default',
            ])
            ->update();

        $this->execute(
            'UPDATE turtle_questions
             SET question_limit = CASE difficulty
                 WHEN 1 THEN 12
                 WHEN 2 THEN 20
                 WHEN 3 THEN 28
                 WHEN 4 THEN 36
                 WHEN 5 THEN 44
                 ELSE 12
             END
             WHERE question_limit IS NULL',
        );
    }

    public function down(): void
    {
        $this->table('turtle_questions')
            ->removeColumn('question_limit')
            ->update();
    }
}
