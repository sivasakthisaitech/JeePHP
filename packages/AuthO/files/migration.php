<?php
declare(strict_types=1);

use JeePHP\Database\Migration;

return new class implements Migration {
    public function up(\PDO $database): void
    {
        create_table('auth_users')
            ->stringColumn('name', 150, false)
            ->stringColumn('email', 190, false)
            ->stringColumn('password_hash', 255, false)
            ->integer('failed_attempts')
            ->stringColumn('locked_until', 32)
            ->timestamps()
            ->create();
        $database->exec('CREATE UNIQUE INDEX `auth_users_email_unique` ON `auth_users` (`email`)');
    }

    public function down(\PDO $database): void
    {
        drop_table('auth_users');
    }
};
