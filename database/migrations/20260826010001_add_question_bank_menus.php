<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddQuestionBankMenus extends AbstractMigration
{
    private const IDS = [900000, 900001, 900002, 900003, 900004, 900005, 900006, 900007];

    public function up(): void
    {
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
            ['id' => 900000, 'parent_id' => 0, 'name' => '题库管理', 'code' => 'QuestionBank', 'slug' => 'question', 'type' => 1, 'path' => '/question', 'component' => '', 'method' => '', 'icon' => 'ri:question-answer-line', 'sort' => 20],
            ['id' => 900001, 'parent_id' => 900000, 'name' => '全部题目', 'code' => 'QuestionList', 'slug' => 'question:index', 'type' => 2, 'path' => 'list', 'component' => 'question/index', 'method' => 'GET', 'icon' => 'ri:file-list-3-line', 'sort' => 1],
            ['id' => 900002, 'parent_id' => 900001, 'name' => '查看题目', 'code' => '', 'slug' => 'question:read', 'type' => 3, 'path' => '/core/question/read', 'component' => '', 'method' => 'GET', 'icon' => '', 'sort' => 1],
            ['id' => 900003, 'parent_id' => 900001, 'name' => '编辑题目', 'code' => '', 'slug' => 'question:edit', 'type' => 3, 'path' => '/core/question/save', 'component' => '', 'method' => 'POST', 'icon' => '', 'sort' => 2],
            ['id' => 900004, 'parent_id' => 900001, 'name' => '发布题目', 'code' => '', 'slug' => 'question:publish', 'type' => 3, 'path' => '/core/question/publish', 'component' => '', 'method' => 'POST', 'icon' => '', 'sort' => 3],
            ['id' => 900005, 'parent_id' => 900001, 'name' => '标签管理', 'code' => '', 'slug' => 'question:tag:edit', 'type' => 3, 'path' => '/core/questionTag/save', 'component' => '', 'method' => 'POST', 'icon' => '', 'sort' => 4],
            ['id' => 900006, 'parent_id' => 900001, 'name' => 'AI 解析', 'code' => '', 'slug' => 'question:ai:create', 'type' => 3, 'path' => '/core/questionAi/create', 'component' => '', 'method' => 'POST', 'icon' => '', 'sort' => 5],
            ['id' => 900007, 'parent_id' => 900001, 'name' => '采纳 AI 草稿', 'code' => '', 'slug' => 'question:ai:adopt', 'type' => 3, 'path' => '/core/questionAi/adopt', 'component' => '', 'method' => 'POST', 'icon' => '', 'sort' => 6],
        ];
        $this->table('sa_system_menu')->insert(array_map(fn (array $row) => array_merge($defaults, $row), $rows))->saveData();
    }

    public function down(): void
    {
        $this->getQueryBuilder()->delete('sa_system_role_menu')->whereInList('menu_id', self::IDS)->execute();
        $this->getQueryBuilder()->delete('sa_system_menu')->whereInList('id', self::IDS)->execute();
    }
}
