<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateMultiplayerRoomsAndDonations extends AbstractMigration
{
    public function up(): void
    {
        $this->table('turtle_rooms', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id', 'biginteger', ['identity' => true, 'signed' => false])
            ->addColumn('public_id', 'string', ['limit' => 26])
            ->addColumn('invite_code', 'char', ['limit' => 8])
            ->addColumn('owner_user_id', 'biginteger', ['signed' => false])
            ->addColumn('game_id', 'biginteger', ['signed' => false, 'null' => true])
            ->addColumn('question_id', 'biginteger', ['signed' => false])
            ->addColumn('name', 'string', ['limit' => 80])
            ->addColumn('status', 'string', ['limit' => 20, 'default' => 'waiting'])
            ->addColumn('visibility', 'string', ['limit' => 20, 'default' => 'private'])
            ->addColumn('max_players', 'integer', ['limit' => 1, 'default' => 4])
            ->addColumn('content_locale', 'string', ['limit' => 16, 'default' => 'zh-CN'])
            ->addColumn('risk_confirmed', 'boolean', ['default' => false])
            ->addColumn('started_at', 'datetime', ['null' => true])
            ->addColumn('finished_at', 'datetime', ['null' => true])
            ->addColumn('create_time', 'datetime')
            ->addColumn('update_time', 'datetime')
            ->addIndex(['public_id'], ['unique' => true])
            ->addIndex(['invite_code'], ['unique' => true])
            ->addIndex(['owner_user_id', 'status'])
            ->addIndex(['status', 'visibility', 'create_time'])
            ->addIndex(['question_id'])
            ->addIndex(['game_id'], ['unique' => true])
            ->addForeignKey('owner_user_id', 'turtle_users', 'id', ['delete' => 'RESTRICT'])
            ->addForeignKey('question_id', 'turtle_questions', 'id', ['delete' => 'RESTRICT'])
            ->create();

        $this->table('turtle_room_members', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id', 'biginteger', ['identity' => true, 'signed' => false])
            ->addColumn('room_id', 'biginteger', ['signed' => false])
            ->addColumn('user_id', 'biginteger', ['signed' => false])
            ->addColumn('role', 'string', ['limit' => 20, 'default' => 'member'])
            ->addColumn('status', 'string', ['limit' => 20, 'default' => 'active'])
            ->addColumn('is_ready', 'boolean', ['default' => false])
            ->addColumn('joined_at', 'datetime')
            ->addColumn('left_at', 'datetime', ['null' => true])
            ->addColumn('last_active_at', 'datetime')
            ->addColumn('create_time', 'datetime')
            ->addColumn('update_time', 'datetime')
            ->addIndex(['room_id', 'user_id'], ['unique' => true])
            ->addIndex(['user_id', 'status'])
            ->addIndex(['room_id', 'status'])
            ->addForeignKey('room_id', 'turtle_rooms', 'id', ['delete' => 'CASCADE'])
            ->addForeignKey('user_id', 'turtle_users', 'id', ['delete' => 'CASCADE'])
            ->create();

        $this->table('turtle_room_messages', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id', 'biginteger', ['identity' => true, 'signed' => false])
            ->addColumn('room_id', 'biginteger', ['signed' => false])
            ->addColumn('user_id', 'biginteger', ['signed' => false])
            ->addColumn('sequence', 'biginteger', ['signed' => false])
            ->addColumn('request_id', 'string', ['limit' => 64])
            ->addColumn('content', 'string', ['limit' => 2000])
            ->addColumn('create_time', 'datetime')
            ->addColumn('update_time', 'datetime')
            ->addIndex(['room_id', 'sequence'], ['unique' => true])
            ->addIndex(['room_id', 'request_id'], ['unique' => true])
            ->addForeignKey('room_id', 'turtle_rooms', 'id', ['delete' => 'CASCADE'])
            ->addForeignKey('user_id', 'turtle_users', 'id', ['delete' => 'CASCADE'])
            ->create();

        $this->table('turtle_games')
            ->addColumn('room_id', 'biginteger', ['signed' => false, 'null' => true, 'after' => 'user_id'])
            ->addIndex(['room_id'])
            ->addForeignKey('room_id', 'turtle_rooms', 'id', ['delete' => 'SET_NULL'])
            ->update();

        $this->table('turtle_rooms')
            ->addForeignKey('game_id', 'turtle_games', 'id', ['delete' => 'SET_NULL'])
            ->update();

        $this->table('turtle_game_messages')
            ->addColumn('user_id', 'biginteger', ['signed' => false, 'null' => true, 'after' => 'game_id'])
            ->addIndex(['user_id'])
            ->addForeignKey('user_id', 'turtle_users', 'id', ['delete' => 'SET_NULL'])
            ->update();

        $this->table('turtle_donation_channels', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id', 'biginteger', ['identity' => true, 'signed' => false])
            ->addColumn('method', 'string', ['limit' => 20])
            ->addColumn('name', 'string', ['limit' => 50])
            ->addColumn('qr_code_url', 'string', ['limit' => 500])
            ->addColumn('qr_code_object_key', 'string', ['limit' => 500])
            ->addColumn('status', 'boolean', ['default' => true])
            ->addColumn('sort', 'integer', ['default' => 0])
            ->addColumn('create_time', 'datetime')
            ->addColumn('update_time', 'datetime')
            ->addIndex(['method'], ['unique' => true])
            ->addIndex(['status', 'sort'])
            ->create();

        $this->table('turtle_donations', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id', 'biginteger', ['identity' => true, 'signed' => false])
            ->addColumn('public_id', 'string', ['limit' => 26])
            ->addColumn('donor_name', 'string', ['limit' => 80])
            ->addColumn('amount', 'decimal', ['precision' => 10, 'scale' => 2])
            ->addColumn('method', 'string', ['limit' => 20, 'null' => true])
            ->addColumn('message', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('donated_at', 'datetime')
            ->addColumn('status', 'boolean', ['default' => true])
            ->addColumn('sort', 'integer', ['default' => 0])
            ->addColumn('created_by', 'biginteger', ['signed' => false, 'null' => true])
            ->addColumn('updated_by', 'biginteger', ['signed' => false, 'null' => true])
            ->addColumn('create_time', 'datetime')
            ->addColumn('update_time', 'datetime')
            ->addIndex(['public_id'], ['unique' => true])
            ->addIndex(['status', 'donated_at'])
            ->create();

        $this->insertMenus();
    }

    public function down(): void
    {
        $this->getQueryBuilder('delete')->delete('sa_system_role_menu')->whereInList('menu_id', range(900200, 900217))->execute();
        $this->getQueryBuilder('delete')->delete('sa_system_menu')->whereInList('id', range(900200, 900217))->execute();
        $this->table('turtle_game_messages')->dropForeignKey('user_id')->removeIndex(['user_id'])->removeColumn('user_id')->update();
        $this->table('turtle_rooms')->dropForeignKey('game_id')->update();
        $this->table('turtle_games')->dropForeignKey('room_id')->removeIndex(['room_id'])->removeColumn('room_id')->update();
        $this->table('turtle_donations')->drop()->save();
        $this->table('turtle_donation_channels')->drop()->save();
        $this->table('turtle_room_messages')->drop()->save();
        $this->table('turtle_room_members')->drop()->save();
        $this->table('turtle_rooms')->drop()->save();
    }

    private function insertMenus(): void
    {
        $now = date('Y-m-d H:i:s');
        $base = ['code' => '', 'icon' => '', 'status' => 1, 'is_iframe' => 2, 'is_keep_alive' => 1, 'is_hidden' => 2, 'is_fixed_tab' => 2, 'is_full_page' => 2, 'create_time' => $now, 'update_time' => $now];
        $rows = [
            ['id' => 900200, 'parent_id' => 0, 'name' => '游戏运营', 'code' => 'GameOperations', 'slug' => 'room:index', 'type' => 1, 'path' => '/operations', 'component' => '', 'method' => 'GET', 'icon' => 'ri:gamepad-line', 'sort' => 7],
            ['id' => 900201, 'parent_id' => 900200, 'name' => '多人房间', 'code' => 'RoomIndex', 'slug' => 'room:index', 'type' => 2, 'path' => 'rooms', 'component' => 'room/index', 'method' => 'GET', 'sort' => 1],
            ['id' => 900202, 'parent_id' => 900201, 'name' => '房间详情', 'slug' => 'room:read', 'type' => 3, 'path' => '/core/room/read', 'component' => '', 'method' => 'GET', 'sort' => 1],
            ['id' => 900203, 'parent_id' => 900201, 'name' => '关闭房间', 'slug' => 'room:close', 'type' => 3, 'path' => '/core/room/close', 'component' => '', 'method' => 'POST', 'sort' => 2],
            ['id' => 900210, 'parent_id' => 900200, 'name' => '捐赠管理', 'code' => 'DonationIndex', 'slug' => 'donation:index', 'type' => 2, 'path' => 'donations', 'component' => 'donation/index', 'method' => 'GET', 'sort' => 2],
            ['id' => 900211, 'parent_id' => 900210, 'name' => '捐赠记录列表', 'slug' => 'donation:index', 'type' => 3, 'path' => '/core/donation/index', 'component' => '', 'method' => 'GET', 'sort' => 1],
            ['id' => 900212, 'parent_id' => 900210, 'name' => '新增捐赠记录', 'slug' => 'donation:create', 'type' => 3, 'path' => '/core/donation/save', 'component' => '', 'method' => 'POST', 'sort' => 2],
            ['id' => 900213, 'parent_id' => 900210, 'name' => '编辑捐赠记录', 'slug' => 'donation:update', 'type' => 3, 'path' => '/core/donation/update', 'component' => '', 'method' => 'PUT', 'sort' => 3],
            ['id' => 900214, 'parent_id' => 900210, 'name' => '删除捐赠记录', 'slug' => 'donation:delete', 'type' => 3, 'path' => '/core/donation/destroy', 'component' => '', 'method' => 'DELETE', 'sort' => 4],
            ['id' => 900215, 'parent_id' => 900210, 'name' => '收款码配置', 'slug' => 'donation:channel', 'type' => 3, 'path' => '/core/donation/channels', 'component' => '', 'method' => 'GET', 'sort' => 5],
            ['id' => 900216, 'parent_id' => 900210, 'name' => '上传收款码', 'slug' => 'donation:channel:update', 'type' => 3, 'path' => '/core/donation/channelUpdate', 'component' => '', 'method' => 'POST', 'sort' => 6],
            ['id' => 900217, 'parent_id' => 900210, 'name' => '捐赠统计', 'slug' => 'donation:stats', 'type' => 3, 'path' => '/core/donation/stats', 'component' => '', 'method' => 'GET', 'sort' => 7],
        ];
        $this->table('sa_system_menu')->insert(array_map(static fn (array $row): array => array_merge($base, $row), $rows))->saveData();
    }
}
