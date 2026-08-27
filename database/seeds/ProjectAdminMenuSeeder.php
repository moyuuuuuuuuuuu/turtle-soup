<?php

declare(strict_types=1);

use Phinx\Seed\AbstractSeed;

final class ProjectAdminMenuSeeder extends AbstractSeed
{
    private const SUPER_ADMIN_ROLE_ID = 1;

    private const MENU_IDS = [
        900000, 900001, 900002, 900003, 900004, 900005, 900006, 900007,
        900008, 900009, 900010, 900011,
        900100, 900101, 900102, 900103, 900104, 900105,
        900200, 900201, 900202, 900203,
        900210, 900211, 900212, 900213, 900214, 900215, 900216, 900217,
    ];

    public function run(): void
    {
        $menuIds = implode(',', self::MENU_IDS);

        $this->execute(sprintf(
            'INSERT INTO sa_system_role_menu (role_id, menu_id)
             SELECT %d, menu.id
             FROM sa_system_menu menu
             WHERE menu.id IN (%s)
               AND EXISTS (SELECT 1 FROM sa_system_role role WHERE role.id = %d)
               AND NOT EXISTS (
                   SELECT 1 FROM sa_system_role_menu relation
                   WHERE relation.role_id = %d AND relation.menu_id = menu.id
               )',
            self::SUPER_ADMIN_ROLE_ID,
            $menuIds,
            self::SUPER_ADMIN_ROLE_ID,
            self::SUPER_ADMIN_ROLE_ID,
        ));
    }
}
