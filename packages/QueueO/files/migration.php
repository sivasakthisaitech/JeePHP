<?php
declare(strict_types=1);

use JeePHP\Database\Migration;

return new class implements Migration {
    public function up(\PDO $database): void
    {
        $database->exec("CREATE TABLE jeep_jobs (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            job_class VARCHAR(190) NOT NULL,
            payload JSON NOT NULL,
            attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            max_attempts SMALLINT UNSIGNED NOT NULL DEFAULT 3,
            available_at DATETIME NOT NULL,
            reserved_at DATETIME NULL,
            failed_at DATETIME NULL,
            last_error TEXT NULL,
            status VARCHAR(16) NOT NULL DEFAULT 'queued',
            created_at DATETIME NOT NULL,
            INDEX jeep_jobs_ready (status, available_at, id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    public function down(\PDO $database): void
    {
        $database->exec('DROP TABLE IF EXISTS jeep_jobs');
    }
};
