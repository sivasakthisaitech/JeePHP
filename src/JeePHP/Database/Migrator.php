<?php
declare(strict_types=1);

namespace JeePHP\Database;

use RuntimeException;

final class Migrator
{
    public function __construct(private readonly string $migrationPath)
    {
        if (!is_dir($migrationPath)) {
            mkdir($migrationPath, 0775, true);
        }
    }

    public function run(): array
    {
        $database = Database::connection();
        $database->exec('CREATE TABLE IF NOT EXISTS jeep_migrations (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, migration VARCHAR(255) NOT NULL UNIQUE, batch INT NOT NULL, ran_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)');
        $ran = $database->query('SELECT migration FROM jeep_migrations')->fetchAll(\PDO::FETCH_COLUMN);
        $files = glob($this->migrationPath . '/*.php') ?: [];
        sort($files);
        $batch = (int) $database->query('SELECT COALESCE(MAX(batch), 0) + 1 FROM jeep_migrations')->fetchColumn();
        $applied = [];

        foreach ($files as $file) {
            $name = basename($file, '.php');
            if (in_array($name, $ran, true)) {
                continue;
            }
            $migration = require $file;
            if (!$migration instanceof Migration) {
                throw new RuntimeException($name . ' must return an object implementing JeePHP\\Database\\Migration.');
            }
            // MySQL commits many schema changes implicitly, so do not wrap DDL in a transaction.
            $migration->up($database);
            $statement = $database->prepare('INSERT INTO jeep_migrations (migration, batch) VALUES (:migration, :batch)');
            $statement->execute(['migration' => $name, 'batch' => $batch]);
            $applied[] = $name;
        }

        return $applied;
    }

    public function rollback(): array
    {
        $database = Database::connection();
        $database->exec('CREATE TABLE IF NOT EXISTS jeep_migrations (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, migration VARCHAR(255) NOT NULL UNIQUE, batch INT NOT NULL, ran_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)');
        $batch = $database->query('SELECT MAX(batch) FROM jeep_migrations')->fetchColumn();
        if ($batch === false || $batch === null) {
            return [];
        }

        $statement = $database->prepare('SELECT migration FROM jeep_migrations WHERE batch = :batch ORDER BY id DESC');
        $statement->execute(['batch' => $batch]);
        $names = $statement->fetchAll(\PDO::FETCH_COLUMN);
        $rolledBack = [];

        foreach ($names as $name) {
            $file = $this->migrationPath . '/' . basename((string) $name) . '.php';
            if (!is_file($file)) {
                throw new RuntimeException('Migration file not found: ' . $name);
            }
            $migration = require $file;
            if (!$migration instanceof Migration) {
                throw new RuntimeException($name . ' must return a JeePHP migration object.');
            }
            $migration->down($database);
            $delete = $database->prepare('DELETE FROM jeep_migrations WHERE migration = :migration');
            $delete->execute(['migration' => $name]);
            $rolledBack[] = $name;
        }

        return $rolledBack;
    }
}
