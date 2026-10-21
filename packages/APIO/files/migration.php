<?php
declare(strict_types=1);

use JeePHP\Database\Migration;

return new class implements Migration {
    public function up(\PDO $database): void
    {
        $database->exec("CREATE TABLE api_tokens (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL,
            name VARCHAR(100) NOT NULL,
            token_hash CHAR(64) NOT NULL UNIQUE,
            abilities JSON NOT NULL,
            last_used_at DATETIME NULL,
            expires_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            INDEX api_tokens_user (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    public function down(\PDO $database): void
    {
        $database->exec('DROP TABLE IF EXISTS api_tokens');
    }
};
