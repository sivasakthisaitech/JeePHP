<?php
declare(strict_types=1);

use JeePHP\Database\Migration;

return new class implements Migration {
    public function up(\PDO $database): void
    {
        create_table('roles')->stringColumn('name', 64, false)->timestamps()->create();
        create_table('permissions')->stringColumn('name', 96, false)->timestamps()->create();
        create_table('user_roles')->integer('user_id')->integer('role_id')->timestamps()->create();
        create_table('role_permissions')->integer('role_id')->integer('permission_id')->timestamps()->create();
        $database->exec('CREATE UNIQUE INDEX `roles_name_unique` ON `roles` (`name`)');
        $database->exec('CREATE UNIQUE INDEX `permissions_name_unique` ON `permissions` (`name`)');
        $database->exec('CREATE UNIQUE INDEX `user_roles_pair_unique` ON `user_roles` (`user_id`, `role_id`)');
        $database->exec('CREATE UNIQUE INDEX `role_permissions_pair_unique` ON `role_permissions` (`role_id`, `permission_id`)');
    }

    public function down(\PDO $database): void
    {
        drop_table('role_permissions');
        drop_table('user_roles');
        drop_table('permissions');
        drop_table('roles');
    }
};
