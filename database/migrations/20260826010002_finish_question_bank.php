<?php

declare(strict_types=1);

use Phinx\Db\Adapter\MysqlAdapter;
use Phinx\Migration\AbstractMigration;

final class FinishQuestionBank extends AbstractMigration
{
    private const MENU_IDS = [900008, 900009, 900010, 900011];

    public function up(): void
    {
        $this->table('turtle_questions')
            ->addColumn('risk_level', 'string', ['limit' => 20, 'null' => false, 'default' => 'safe', 'after' => 'source_type'])
            ->addColumn('risk_types', 'text', ['null' => true, 'after' => 'risk_level'])
            ->addColumn('risk_note', 'text', ['null' => true, 'after' => 'risk_types'])
            ->addColumn('risk_reviewed_by', 'biginteger', ['null' => true, 'signed' => false, 'after' => 'risk_note'])
            ->addColumn('risk_reviewed_at', 'datetime', ['null' => true, 'after' => 'risk_reviewed_by'])
            ->addIndex(['risk_level'])
            ->update();

        $this->table('turtle_question_versions', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id', 'biginteger', ['identity' => true, 'signed' => false])
            ->addColumn('question_id', 'biginteger', ['signed' => false, 'null' => false])
            ->addColumn('version', 'integer', ['null' => false])
            ->addColumn('snapshot', 'text', ['limit' => MysqlAdapter::TEXT_LONG, 'null' => false])
            ->addColumn('published_by', 'biginteger', ['null' => true, 'signed' => false])
            ->addColumn('published_at', 'datetime', ['null' => false])
            ->addColumn('create_time', 'datetime', ['null' => false])
            ->addColumn('update_time', 'datetime', ['null' => false])
            ->addIndex(['question_id', 'version'], ['unique' => true])
            ->addForeignKey('question_id', 'turtle_questions', 'id', ['delete' => 'CASCADE'])
            ->create();

        $now = date('Y-m-d H:i:s');
        $defaults = [
            'status' => 1,
            'is_iframe' => 2,
            'is_keep_alive' => 1,
            'is_hidden' => 2,
            'is_fixed_tab' => 2,
            'is_full_page' => 2,
            'create_time' => $now,
            'update_time' => $now,
        ];
        $rows = [
            ['id' => 900008, 'parent_id' => 900001, 'name' => '查看题目汤底', 'code' => '', 'slug' => 'question:answer:read', 'type' => 3, 'path' => '/core/question/read', 'component' => '', 'method' => 'GET', 'icon' => '', 'sort' => 7],
            ['id' => 900009, 'parent_id' => 900001, 'name' => '复制题目', 'code' => '', 'slug' => 'question:copy', 'type' => 3, 'path' => '/core/question/copy', 'component' => '', 'method' => 'POST', 'icon' => '', 'sort' => 8],
            ['id' => 900010, 'parent_id' => 900001, 'name' => '题目版本历史', 'code' => '', 'slug' => 'question:history', 'type' => 3, 'path' => '/core/question/history', 'component' => '', 'method' => 'GET', 'icon' => '', 'sort' => 9],
            ['id' => 900011, 'parent_id' => 900000, 'name' => '标签管理', 'code' => 'QuestionTags', 'slug' => 'question:tag:index', 'type' => 2, 'path' => 'tags', 'component' => 'question/tags', 'method' => 'GET', 'icon' => 'ri:price-tag-3-line', 'sort' => 2],
        ];
        $this->table('sa_system_menu')->insert(array_map(fn (array $row) => array_merge($defaults, $row), $rows))->saveData();
        $this->getQueryBuilder('update')->update('sa_system_menu')
            ->set(['parent_id' => 900011, 'name' => '编辑标签', 'sort' => 1, 'update_time' => $now])
            ->where(['id' => 900005])
            ->execute();
    }

    public function down(): void
    {
        $this->getQueryBuilder('delete')->delete('sa_system_role_menu')->whereInList('menu_id', self::MENU_IDS)->execute();
        $this->getQueryBuilder('delete')->delete('sa_system_menu')->whereInList('id', self::MENU_IDS)->execute();
        $this->getQueryBuilder('update')->update('sa_system_menu')
            ->set(['parent_id' => 900001, 'name' => '标签管理', 'sort' => 4, 'update_time' => date('Y-m-d H:i:s')])
            ->where(['id' => 900005])
            ->execute();
        $this->table('turtle_question_versions')->drop()->save();
        $this->table('turtle_questions')
            ->removeIndex(['risk_level'])
            ->removeColumn('risk_reviewed_at')
            ->removeColumn('risk_reviewed_by')
            ->removeColumn('risk_note')
            ->removeColumn('risk_types')
            ->removeColumn('risk_level')
            ->update();
    }
}
