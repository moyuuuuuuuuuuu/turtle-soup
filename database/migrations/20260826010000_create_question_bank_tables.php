<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateQuestionBankTables extends AbstractMigration
{
    public function change(): void
    {
        $this->table('turtle_questions', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id', 'biginteger', ['identity' => true, 'signed' => false])
            ->addColumn('public_id', 'string', ['limit' => 26])
            ->addColumn('difficulty', 'integer', ['limit' => 1, 'default' => 1])
            ->addColumn('status', 'string', ['limit' => 20, 'default' => 'draft'])
            ->addColumn('source_type', 'string', ['limit' => 20, 'default' => 'manual'])
            ->addColumn('min_players', 'integer', ['limit' => 2, 'default' => 1])
            ->addColumn('max_players', 'integer', ['limit' => 2, 'default' => 1])
            ->addColumn('version', 'integer', ['default' => 1])
            ->addColumn('created_by', 'biginteger', ['null' => true, 'signed' => false])
            ->addColumn('updated_by', 'biginteger', ['null' => true, 'signed' => false])
            ->addColumn('published_at', 'datetime', ['null' => true])
            ->addColumn('create_time', 'datetime')
            ->addColumn('update_time', 'datetime')
            ->addColumn('delete_time', 'datetime', ['null' => true])
            ->addIndex(['public_id'], ['unique' => true])
            ->addIndex(['status', 'difficulty'])
            ->create();

        $this->table('turtle_question_translations', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id', 'biginteger', ['identity' => true, 'signed' => false])
            ->addColumn('question_id', 'biginteger', ['signed' => false])
            ->addColumn('language', 'string', ['limit' => 16])
            ->addColumn('title', 'string', ['limit' => 160])
            ->addColumn('surface', 'text')
            ->addColumn('bottom', 'text')
            ->addColumn('create_time', 'datetime')
            ->addColumn('update_time', 'datetime')
            ->addIndex(['question_id', 'language'], ['unique' => true])
            ->addForeignKey('question_id', 'turtle_questions', 'id', ['delete' => 'CASCADE'])
            ->create();

        $this->table('turtle_question_points', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id', 'biginteger', ['identity' => true, 'signed' => false])
            ->addColumn('question_id', 'biginteger', ['signed' => false])
            ->addColumn('weight', 'integer', ['default' => 1])
            ->addColumn('is_required', 'boolean', ['default' => false])
            ->addColumn('sort', 'integer', ['default' => 0])
            ->addColumn('create_time', 'datetime')
            ->addColumn('update_time', 'datetime')
            ->addIndex(['question_id', 'sort'])
            ->addForeignKey('question_id', 'turtle_questions', 'id', ['delete' => 'CASCADE'])
            ->create();

        $this->table('turtle_question_point_translations', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id', 'biginteger', ['identity' => true, 'signed' => false])
            ->addColumn('point_id', 'biginteger', ['signed' => false])
            ->addColumn('language', 'string', ['limit' => 16])
            ->addColumn('content', 'text')
            ->addColumn('create_time', 'datetime')
            ->addColumn('update_time', 'datetime')
            ->addIndex(['point_id', 'language'], ['unique' => true])
            ->addForeignKey('point_id', 'turtle_question_points', 'id', ['delete' => 'CASCADE'])
            ->create();

        $this->table('turtle_question_hints', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id', 'biginteger', ['identity' => true, 'signed' => false])
            ->addColumn('question_id', 'biginteger', ['signed' => false])
            ->addColumn('level', 'integer', ['limit' => 1])
            ->addColumn('target_point_id', 'biginteger', ['null' => true, 'signed' => false])
            ->addColumn('create_time', 'datetime')
            ->addColumn('update_time', 'datetime')
            ->addIndex(['question_id', 'level'], ['unique' => true])
            ->addForeignKey('question_id', 'turtle_questions', 'id', ['delete' => 'CASCADE'])
            ->addForeignKey('target_point_id', 'turtle_question_points', 'id', ['delete' => 'SET_NULL'])
            ->create();

        $this->table('turtle_question_hint_translations', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id', 'biginteger', ['identity' => true, 'signed' => false])
            ->addColumn('hint_id', 'biginteger', ['signed' => false])
            ->addColumn('language', 'string', ['limit' => 16])
            ->addColumn('content', 'text')
            ->addColumn('create_time', 'datetime')
            ->addColumn('update_time', 'datetime')
            ->addIndex(['hint_id', 'language'], ['unique' => true])
            ->addForeignKey('hint_id', 'turtle_question_hints', 'id', ['delete' => 'CASCADE'])
            ->create();

        $this->table('turtle_tags', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id', 'biginteger', ['identity' => true, 'signed' => false])
            ->addColumn('name', 'string', ['limit' => 64])
            ->addColumn('slug', 'string', ['limit' => 64])
            ->addColumn('create_time', 'datetime')
            ->addColumn('update_time', 'datetime')
            ->addColumn('delete_time', 'datetime', ['null' => true])
            ->addIndex(['slug'], ['unique' => true])
            ->create();

        $this->table('turtle_question_tags', ['id' => false, 'primary_key' => ['question_id', 'tag_id']])
            ->addColumn('question_id', 'biginteger', ['signed' => false])
            ->addColumn('tag_id', 'biginteger', ['signed' => false])
            ->addForeignKey('question_id', 'turtle_questions', 'id', ['delete' => 'CASCADE'])
            ->addForeignKey('tag_id', 'turtle_tags', 'id', ['delete' => 'CASCADE'])
            ->create();

        $this->table('turtle_ai_parse_tasks', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id', 'biginteger', ['identity' => true, 'signed' => false])
            ->addColumn('public_id', 'string', ['limit' => 26])
            ->addColumn('question_id', 'biginteger', ['null' => true, 'signed' => false])
            ->addColumn('status', 'string', ['limit' => 20, 'default' => 'pending'])
            ->addColumn('progress', 'integer', ['limit' => 3, 'default' => 0])
            ->addColumn('workflow_version', 'string', ['limit' => 64, 'default' => 'turtle_content_parser_v1'])
            ->addColumn('request_payload', 'text')
            ->addColumn('result_payload', 'text', ['null' => true])
            ->addColumn('error_code', 'string', ['limit' => 100, 'null' => true])
            ->addColumn('error_message', 'string', ['limit' => 500, 'null' => true])
            ->addColumn('created_by', 'biginteger', ['null' => true, 'signed' => false])
            ->addColumn('create_time', 'datetime')
            ->addColumn('update_time', 'datetime')
            ->addIndex(['public_id'], ['unique' => true])
            ->addIndex(['status', 'create_time'])
            ->addForeignKey('question_id', 'turtle_questions', 'id', ['delete' => 'SET_NULL'])
            ->create();
    }
}
