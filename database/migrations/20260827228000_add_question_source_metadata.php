<?php

declare(strict_types=1);

use Phinx\Db\Adapter\MysqlAdapter;
use Phinx\Migration\AbstractMigration;

final class AddQuestionSourceMetadata extends AbstractMigration
{
    public function up(): void
    {
        $this->table('turtle_questions')
            ->addColumn('source_url', 'string', ['limit' => 500, 'null' => true, 'after' => 'source_type'])
            ->addColumn('source_author', 'string', ['limit' => 255, 'null' => true, 'after' => 'source_url'])
            ->addColumn('source_license', 'string', ['limit' => 100, 'null' => true, 'after' => 'source_author'])
            ->addColumn('source_hash', 'char', ['limit' => 64, 'null' => true, 'after' => 'source_license'])
            ->addColumn('content_hash', 'char', ['limit' => 64, 'null' => true, 'after' => 'source_hash'])
            ->addIndex(['source_hash'], ['unique' => true, 'name' => 'uniq_question_source_hash'])
            ->addIndex(['content_hash'], ['unique' => true, 'name' => 'uniq_question_content_hash'])
            ->update();

        $this->table('turtle_question_translations')
            ->changeColumn('surface', 'text', ['limit' => MysqlAdapter::TEXT_LONG])
            ->changeColumn('bottom', 'text', ['limit' => MysqlAdapter::TEXT_LONG])
            ->update();
    }

    public function down(): void
    {
        $this->table('turtle_questions')
            ->removeIndexByName('uniq_question_content_hash')
            ->removeIndexByName('uniq_question_source_hash')
            ->removeColumn('content_hash')
            ->removeColumn('source_hash')
            ->removeColumn('source_license')
            ->removeColumn('source_author')
            ->removeColumn('source_url')
            ->update();

        $this->table('turtle_question_translations')
            ->changeColumn('surface', 'text')
            ->changeColumn('bottom', 'text')
            ->update();
    }
}
