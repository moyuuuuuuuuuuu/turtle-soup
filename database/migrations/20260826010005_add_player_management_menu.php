<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddPlayerManagementMenu extends AbstractMigration
{
    public function up(): void
    {
        $now = date('Y-m-d H:i:s');
        $base = ['code' => '', 'icon' => '', 'status' => 1, 'is_iframe' => 2, 'is_keep_alive' => 1, 'is_hidden' => 2, 'is_fixed_tab' => 2, 'is_full_page' => 2, 'create_time' => $now, 'update_time' => $now];
        $rows = [
            ['id' => 900100, 'parent_id' => 0, 'name' => '玩家管理', 'code' => 'PlayerManagement', 'slug' => 'player:index', 'type' => 1, 'path' => '/player', 'component' => '', 'method' => 'GET', 'icon' => 'ri:user-heart-line', 'sort' => 8],
            ['id' => 900101, 'parent_id' => 900100, 'name' => '玩家列表', 'code' => 'PlayerIndex', 'slug' => 'player:index', 'type' => 2, 'path' => 'index', 'component' => 'player/index', 'method' => 'GET', 'icon' => 'ri:user-line', 'sort' => 1],
            ['id' => 900102, 'parent_id' => 900101, 'name' => '玩家详情', 'slug' => 'player:read', 'type' => 3, 'path' => '/core/player/read', 'component' => '', 'method' => 'GET', 'sort' => 1],
            ['id' => 900103, 'parent_id' => 900101, 'name' => '启用禁用玩家', 'slug' => 'player:status', 'type' => 3, 'path' => '/core/player/status', 'component' => '', 'method' => 'POST', 'sort' => 2],
            ['id' => 900104, 'parent_id' => 900101, 'name' => '撤销玩家会话', 'slug' => 'player:session:revoke', 'type' => 3, 'path' => '/core/player/revoke', 'component' => '', 'method' => 'POST', 'sort' => 3],
            ['id' => 900105, 'parent_id' => 900101, 'name' => '玩家日志', 'slug' => 'player:log', 'type' => 3, 'path' => '/core/player/loginLogs', 'component' => '', 'method' => 'GET', 'sort' => 4],
        ];
        $this->table('sa_system_menu')->insert(array_map(fn ($row) => array_merge($base, $row), $rows))->saveData();
    }
    public function down(): void
    {
        $this->getQueryBuilder('delete')->delete('sa_system_role_menu')->whereInList('menu_id', [900100,900101,900102,900103,900104,900105])->execute();
        $this->getQueryBuilder('delete')->delete('sa_system_menu')->whereInList('id', [900100,900101,900102,900103,900104,900105])->execute();
    }
}
