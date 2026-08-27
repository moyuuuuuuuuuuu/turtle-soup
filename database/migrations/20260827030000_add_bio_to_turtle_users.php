<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddBioToTurtleUsers extends AbstractMigration
{
    public function change(): void
    {
        $this->table('turtle_users')
            ->addColumn('bio', 'string', ['limit' => 200, 'null' => true, 'after' => 'avatar_object_key'])
            ->update();
    }
}
