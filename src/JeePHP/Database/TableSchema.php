<?php
declare(strict_types=1);

namespace JeePHP\Database;

use InvalidArgumentException;
use PDO;

final class TableSchema
{
    /** @var array<string, string> */
    private array $columns = [];
    private bool $hasId = true;
    private ?string $primaryKey = null;

    public function __construct(private readonly PDO $database, private readonly string $table)
    {
        self::assertIdentifier($table);
    }

    /** Add one or more nullable VARCHAR(255) columns. */
    public function column(string ...$names): self
    {
        foreach ($names as $name) {
            $this->add($name, 'VARCHAR(255) NULL');
        }
        return $this;
    }

    public function stringColumn(string $name, int $length = 255, bool $nullable = true): self
    {
        if ($length < 1 || $length > 16382) {
            throw new InvalidArgumentException('String column length must be between 1 and 16382.');
        }
        $this->add($name, 'VARCHAR(' . $length . ')' . ($nullable ? ' NULL' : ' NOT NULL'));
        return $this;
    }

    public function integer(string $name): self
    {
        $this->add($name, 'INT NULL');
        return $this;
    }

    public function text(string $name): self
    {
        $this->add($name, 'TEXT NULL');
        return $this;
    }

    public function boolean(string $name, int $default = 0): self
    {
        if (!in_array($default, [0, 1], true)) {
            throw new InvalidArgumentException('Boolean column defaults can be 0 or 1.');
        }
        $this->add($name, 'TINYINT(1) NOT NULL DEFAULT ' . $default);
        return $this;
    }

    public function decimal(string $name, int $precision = 10, int $scale = 2): self
    {
        if ($precision < 1 || $precision > 65 || $scale < 0 || $scale > 30 || $scale > $precision) {
            throw new InvalidArgumentException('Decimal precision must be 1–65 and scale must be 0–30, not greater than precision.');
        }
        $this->add($name, "DECIMAL({$precision},{$scale}) NULL");
        return $this;
    }

    public function timestamps(): self
    {
        $this->add('created_at', 'TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP');
        $this->add('updated_at', 'TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');
        return $this;
    }

    /** Tables get an auto-incrementing id by default; use this for a custom primary key. */
    public function withoutId(): self
    {
        $this->hasId = false;
        return $this;
    }

    public function primary(string $name): self
    {
        self::assertIdentifier($name);
        if ($this->hasId) {
            throw new InvalidArgumentException('Call withoutId() before defining a custom primary key.');
        }
        if (!array_key_exists($name, $this->columns)) {
            throw new InvalidArgumentException('Add the primary key column before calling primary().');
        }
        $this->primaryKey = $name;
        return $this;
    }

    public function create(): void
    {
        $definitions = [];
        if ($this->hasId) {
            $definitions[] = '`id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY';
        }
        foreach ($this->columns as $name => $definition) {
            $definitions[] = '`' . $name . '` ' . $definition;
        }
        if (!$this->hasId && $this->primaryKey !== null) {
            $definitions[] = 'PRIMARY KEY (`' . $this->primaryKey . '`)';
        }

        $sql = 'CREATE TABLE `' . $this->table . '` (' . implode(', ', $definitions) . ')';
        $this->database->exec($sql);
    }

    public static function drop(PDO $database, string $table): void
    {
        self::assertIdentifier($table);
        $database->exec('DROP TABLE IF EXISTS `' . $table . '`');
    }

    private function add(string $name, string $definition): void
    {
        self::assertIdentifier($name);
        if ($name === 'id' && $this->hasId) {
            throw new InvalidArgumentException('The id column is created automatically. Use withoutId() if you need your own id column.');
        }
        if (array_key_exists($name, $this->columns)) {
            throw new InvalidArgumentException('Duplicate migration column: ' . $name);
        }
        $this->columns[$name] = $definition;
    }

    private static function assertIdentifier(string $identifier): void
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $identifier)) {
            throw new InvalidArgumentException('Table and column names may contain only letters, numbers, and underscores, and cannot start with a number.');
        }
    }
}
