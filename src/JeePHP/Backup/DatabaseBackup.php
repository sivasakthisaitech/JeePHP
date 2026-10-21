<?php
declare(strict_types=1);

namespace JeePHP\Backup;

use JeePHP\Database\Database;
use PDO;
use RuntimeException;

/** Creates and restores portable MySQL/MariaDB SQL backups. */
final class DatabaseBackup
{
    public function __construct(private readonly string $directory) {}

    public function create(bool $encrypt = false, string $key = ''): string
    {
        if (!function_exists('gzopen')) {
            throw new RuntimeException('Enable the PHP zlib extension before creating database backups.');
        }
        if ($encrypt) {
            BackupCrypto::validateKey($key);
        }
        $pdo = Database::connection();
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql') {
            throw new RuntimeException('Database backup currently supports MySQL and MariaDB.');
        }
        $this->ensureDirectory();
        $stamp = gmdate('Ymd-His') . '-' . bin2hex(random_bytes(4));
        $plainPath = $this->directory . '/jeephp-database-' . $stamp . '.sql.gz';
        $gzip = @gzopen($plainPath, 'wb9');
        if ($gzip === false) {
            throw new RuntimeException('Could not create the local backup file.');
        }

        $write = static function (string $sql) use ($gzip): void {
            $written = gzwrite($gzip, $sql . "\n");
            if ($written === false || $written !== strlen($sql) + 1) {
                throw new RuntimeException('Could not write the database dump.');
            }
        };

        $transactionStarted = false;
        try {
            $write('-- JeePHP MySQL backup; schema, table data, and views.');
            $write('SET NAMES utf8mb4;');
            $write('SET FOREIGN_KEY_CHECKS=0;');
            $pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
            $pdo->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT');
            $transactionStarted = true;

            $tables = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetchAll(PDO::FETCH_NUM);
            foreach ($tables as $row) {
                $table = (string) ($row[0] ?? '');
                if ($table === '') {
                    continue;
                }
                $quotedTable = self::quoteIdentifier($table);
                $schema = $pdo->query('SHOW CREATE TABLE ' . $quotedTable)->fetch(PDO::FETCH_NUM);
                if (!is_array($schema) || !isset($schema[1])) {
                    throw new RuntimeException('Could not read schema for table: ' . $table);
                }
                $write('DROP TABLE IF EXISTS ' . $quotedTable . ';');
                $write((string) $schema[1] . ';');

                $columnRows = $pdo->query('SHOW FULL COLUMNS FROM ' . $quotedTable)->fetchAll(PDO::FETCH_ASSOC);
                $columns = [];
                $types = [];
                foreach ($columnRows as $column) {
                    $field = (string) ($column['Field'] ?? '');
                    $extra = strtolower((string) ($column['Extra'] ?? ''));
                    if ($field === '' || str_contains($extra, 'generated')) {
                        continue;
                    }
                    $columns[] = $field;
                    $types[$field] = strtolower((string) ($column['Type'] ?? ''));
                }
                if ($columns === []) {
                    continue;
                }

                $selectColumns = implode(', ', array_map(self::quoteIdentifier(...), $columns));
                $statement = $pdo->query('SELECT ' . $selectColumns . ' FROM ' . $quotedTable);
                $batch = [];
                while (($record = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
                    $values = [];
                    foreach ($columns as $column) {
                        $values[] = self::sqlValue($record[$column] ?? null, $types[$column] ?? '');
                    }
                    $batch[] = '(' . implode(', ', $values) . ')';
                    if (count($batch) >= 100) {
                        $write('INSERT INTO ' . $quotedTable . ' (' . $selectColumns . ') VALUES ' . implode(', ', $batch) . ';');
                        $batch = [];
                    }
                }
                if ($batch !== []) {
                    $write('INSERT INTO ' . $quotedTable . ' (' . $selectColumns . ') VALUES ' . implode(', ', $batch) . ';');
                }
                $statement->closeCursor();
            }

            $views = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'VIEW'")->fetchAll(PDO::FETCH_NUM);
            foreach ($views as $row) {
                $view = (string) ($row[0] ?? '');
                if ($view === '') {
                    continue;
                }
                $quotedView = self::quoteIdentifier($view);
                $definition = $pdo->query('SHOW CREATE VIEW ' . $quotedView)->fetch(PDO::FETCH_ASSOC);
                $create = is_array($definition) ? (string) ($definition['Create View'] ?? '') : '';
                if ($create !== '') {
                    $write('DROP VIEW IF EXISTS ' . $quotedView . ';');
                    $write($create . ';');
                }
            }

            $write('SET FOREIGN_KEY_CHECKS=1;');
            $pdo->commit();
            $transactionStarted = false;
            $closed = gzclose($gzip);
            $gzip = null;
            if (!$closed) {
                throw new RuntimeException('Could not finish the compressed SQL backup. Check free disk space and storage/backups permissions.');
            }
            @chmod($plainPath, 0600);

            if (!$encrypt) {
                return $plainPath;
            }
            $encryptedPath = $plainPath . '.enc';
            try {
                BackupCrypto::encryptFile($plainPath, $encryptedPath, $key);
            } catch (\Throwable $error) {
                @unlink($plainPath);
                throw $error;
            }
            if (!@unlink($plainPath)) {
                @unlink($encryptedPath);
                throw new RuntimeException('Could not remove the temporary unencrypted dump. Check storage/backups permissions and remove the plain .sql.gz file securely.');
            }
            return $encryptedPath;
        } catch (\Throwable $error) {
            if ($transactionStarted && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if (is_resource($gzip)) {
                gzclose($gzip);
            }
            @unlink($plainPath);
            throw $error;
        }
    }

    public function restore(string $backupPath, string $key = ''): int
    {
        $this->ensureDirectory();
        if (!is_file($backupPath) || !is_readable($backupPath)) {
            throw new RuntimeException('Backup file does not exist or cannot be read: ' . $backupPath);
        }
        $temporary = null;
        $gzipPath = $backupPath;
        if (str_ends_with($backupPath, '.enc')) {
            $temporary = $this->directory . '/.restore-' . bin2hex(random_bytes(6)) . '.sql.gz';
            BackupCrypto::decryptFile($backupPath, $temporary, $key);
            $gzipPath = $temporary;
        }
        if (!str_ends_with($gzipPath, '.sql.gz')) {
            if ($temporary !== null) {
                @unlink($temporary);
            }
            throw new RuntimeException('Restore expects a .sql.gz or .sql.gz.enc JeePHP backup.');
        }

        $gzip = @gzopen($gzipPath, 'rb');
        if ($gzip === false) {
            if ($temporary !== null) {
                @unlink($temporary);
            }
            throw new RuntimeException('Could not open the compressed SQL backup.');
        }
        $pdo = Database::connection();
        $statements = 0;
        try {
            $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
            foreach (self::splitSqlStream($gzip) as $sql) {
                $sql = trim($sql);
                if ($sql === '' || str_starts_with($sql, '--')) {
                    continue;
                }
                $pdo->exec($sql);
                $statements++;
            }
            $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
            return $statements;
        } catch (\Throwable $error) {
            try {
                $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
            } catch (\Throwable) {
                // Preserve the original restore error.
            }
            throw new RuntimeException('Restore stopped after ' . $statements . ' statements: ' . $error->getMessage(), 0, $error);
        } finally {
            gzclose($gzip);
            if ($temporary !== null) {
                @unlink($temporary);
            }
        }
    }

    public function localBackups(): array
    {
        $this->ensureDirectory();
        $files = glob($this->directory . '/jeephp-database-*.sql.gz*') ?: [];
        usort($files, static fn (string $a, string $b): int => filemtime($b) <=> filemtime($a));
        return $files;
    }

    public function prune(int $keep): int
    {
        if ($keep < 1) {
            throw new RuntimeException('Keep at least one backup. Use --keep=NUMBER.');
        }
        $files = $this->localBackups();
        $removed = 0;
        foreach (array_slice($files, $keep) as $file) {
            if (is_file($file) && unlink($file)) {
                $removed++;
            }
        }
        return $removed;
    }

    private function ensureDirectory(): void
    {
        if (!is_dir($this->directory) && !mkdir($this->directory, 0700, true) && !is_dir($this->directory)) {
            throw new RuntimeException('Could not create backup folder: ' . $this->directory);
        }
        @chmod($this->directory, 0700);
    }

    private static function quoteIdentifier(string $identifier): string
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }

    private static function sqlValue(mixed $value, string $type): string
    {
        if ($value === null) {
            return 'NULL';
        }
        if (preg_match('/^(tinyint|smallint|mediumint|int|integer|bigint|decimal|numeric|float|double|real|year)/', $type) === 1
            && is_numeric($value)) {
            return (string) $value;
        }

        $hex = '0x' . bin2hex((string) $value);
        if (preg_match('/^(binary|varbinary|tinyblob|blob|mediumblob|longblob|bit)/', $type) === 1) {
            return $hex;
        }
        if (preg_match('/^(geometry|point|linestring|polygon|multipoint|multilinestring|multipolygon|geometrycollection)/', $type) === 1) {
            return 'ST_GeomFromWKB(' . $hex . ')';
        }
        return 'CONVERT(' . $hex . ' USING utf8mb4)';
    }

    /** @param resource $gzip @return \Generator<int,string> */
    private static function splitSqlStream($gzip): \Generator
    {
        $statement = '';
        $quote = null;
        $escaped = false;
        $lineStart = true;
        $comment = false;
        while (!gzeof($gzip)) {
            $chunk = gzread($gzip, 65536);
            if ($chunk === false) {
                throw new RuntimeException('Compressed database backup is damaged or incomplete.');
            }
            if ($chunk === '') {
                if (!gzeof($gzip)) {
                    throw new RuntimeException('Compressed database backup ended unexpectedly.');
                }
                break;
            }
            $length = strlen($chunk);
            for ($index = 0; $index < $length; $index++) {
                $char = $chunk[$index];
                if ($comment) {
                    if ($char === "\n") {
                        $comment = false;
                        $lineStart = true;
                    }
                    continue;
                }
                if ($quote === null && $lineStart && ($char === ' ' || $char === "\t")) {
                    if (trim($statement) === '') {
                        continue;
                    }
                }
                if ($quote === null && $lineStart && $char === '-' && isset($chunk[$index + 1]) && $chunk[$index + 1] === '-') {
                    $comment = true;
                    $index++;
                    continue;
                }
                $lineStart = $char === "\n";

                if ($quote !== null) {
                    $statement .= $char;
                    if ($escaped) {
                        $escaped = false;
                    } elseif ($char === '\\' && $quote !== '`') {
                        $escaped = true;
                    } elseif ($char === $quote) {
                        if (isset($chunk[$index + 1]) && $chunk[$index + 1] === $quote) {
                            $statement .= $chunk[++$index];
                        } else {
                            $quote = null;
                        }
                    }
                    continue;
                }

                if ($char === "'" || $char === '"' || $char === '`') {
                    $quote = $char;
                    $statement .= $char;
                    continue;
                }
                if ($char === ';') {
                    yield $statement;
                    $statement = '';
                    continue;
                }
                $statement .= $char;
            }
        }
        if (trim($statement) !== '') {
            yield $statement;
        }
    }
}
