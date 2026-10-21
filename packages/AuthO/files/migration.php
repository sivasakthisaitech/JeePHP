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
            ->stringColumn('email_verified_at', 32)
            ->timestamps()
            ->create();
        $database->exec('CREATE UNIQUE INDEX `auth_users_email_unique` ON `auth_users` (`email`)');
        $database->exec("CREATE TABLE auth_password_resets (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(190) NOT NULL,
            token_hash CHAR(64) NOT NULL UNIQUE,
            expires_at DATETIME NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX auth_password_resets_email (email)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $database->exec("CREATE TABLE auth_email_verifications (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL,
            token_hash CHAR(64) NOT NULL UNIQUE,
            expires_at DATETIME NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX auth_email_verifications_user (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    public function down(\PDO $database): void
    {
        $database->exec('DROP TABLE IF EXISTS auth_email_verifications');
        $database->exec('DROP TABLE IF EXISTS auth_password_resets');
        drop_table('auth_users');
    }
};
