<?php
declare(strict_types=1);

namespace JeePHP\Database;

use InvalidArgumentException;
use LogicException;
use PDO;
use PDOStatement;

final class TableQuery
{
    private array $selectedColumns = ['*'];
    private array $conditions = [];
    private array $bindings = [];
    private ?string $sort = null;
    private ?int $rowLimit = null;

    public function __construct(private readonly PDO $pdo, private readonly string $table)
    {
        $this->quoteIdentifier($table);
    }

    public function columns(string ...$columns): self
    {
        $columns = array_values(array_filter($columns, static fn (string $column): bool => trim($column) !== ''));
        if ($columns === []) {
            throw new InvalidArgumentException('Pass at least one column name to columns().');
        }
        $this->selectedColumns = array_map(fn (string $column): string => $this->quoteIdentifier($column), $columns);
        return $this;
    }

    public function where(string $column, mixed $value, string $operator = '='): self
    {
        $allowed = ['=', '!=', '<>', '>', '<', '>=', '<=', 'LIKE'];
        $operator = strtoupper(trim($operator));
        if (!in_array($operator, $allowed, true)) {
            throw new InvalidArgumentException('Unsupported where operator.');
        }

        $placeholder = ':value' . count($this->bindings);
        $this->conditions[] = $this->quoteIdentifier($column) . ' ' . $operator . ' ' . $placeholder;
        $this->bindings[$placeholder] = $value;
        return $this;
    }

    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $direction = strtoupper(trim($direction));
        if (!in_array($direction, ['ASC', 'DESC'], true)) {
            throw new InvalidArgumentException('Sort direction must be ASC or DESC.');
        }
        $this->sort = $this->quoteIdentifier($column) . ' ' . $direction;
        return $this;
    }

    public function limit(int $count): self
    {
        if ($count < 1) {
            throw new InvalidArgumentException('Limit must be at least 1.');
        }
        $this->rowLimit = $count;
        return $this;
    }

    public function get(): array
    {
        $statement = $this->run($this->selectSql(), $this->bindings);
        return $statement->fetchAll();
    }

    public function first(): ?array
    {
        $statement = $this->run($this->selectSql(1), $this->bindings);
        $row = $statement->fetch();
        return $row === false ? null : $row;
    }

    public function count(): int
    {
        $sql = 'SELECT COUNT(*) FROM ' . $this->quoteIdentifier($this->table) . $this->whereSql();
        return (int) $this->run($sql, $this->bindings)->fetchColumn();
    }

    public function insert(array $data): string
    {
        if ($data === []) {
            throw new InvalidArgumentException('Pass at least one value to insert().');
        }

        $columns = [];
        $placeholders = [];
        $bindings = [];
        foreach ($data as $column => $value) {
            $key = ':insert' . count($bindings);
            $columns[] = $this->quoteIdentifier((string) $column);
            $placeholders[] = $key;
            $bindings[$key] = $value;
        }

        $sql = 'INSERT INTO ' . $this->quoteIdentifier($this->table)
            . ' (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')';
        $this->run($sql, $bindings);
        return $this->pdo->lastInsertId();
    }

    public function update(array $data): int
    {
        $this->requireWhere('update');
        if ($data === []) {
            throw new InvalidArgumentException('Pass at least one value to update().');
        }

        $sets = [];
        $bindings = [];
        foreach ($data as $column => $value) {
            $key = ':set' . count($bindings);
            $sets[] = $this->quoteIdentifier((string) $column) . ' = ' . $key;
            $bindings[$key] = $value;
        }
        $bindings += $this->bindings;
        $sql = 'UPDATE ' . $this->quoteIdentifier($this->table) . ' SET ' . implode(', ', $sets) . $this->whereSql();
        return $this->run($sql, $bindings)->rowCount();
    }

    public function delete(): int
    {
        $this->requireWhere('delete');
        $sql = 'DELETE FROM ' . $this->quoteIdentifier($this->table) . $this->whereSql();
        return $this->run($sql, $this->bindings)->rowCount();
    }

    private function selectSql(?int $overrideLimit = null): string
    {
        $sql = 'SELECT ' . implode(', ', $this->selectedColumns)
            . ' FROM ' . $this->quoteIdentifier($this->table)
            . $this->whereSql();
        if ($this->sort !== null) {
            $sql .= ' ORDER BY ' . $this->sort;
        }
        $limit = $overrideLimit ?? $this->rowLimit;
        if ($limit !== null) {
            $sql .= ' LIMIT ' . $limit;
        }
        return $sql;
    }

    private function whereSql(): string
    {
        return $this->conditions === [] ? '' : ' WHERE ' . implode(' AND ', $this->conditions);
    }

    private function quoteIdentifier(string $identifier): string
    {
        $identifier = trim($identifier);
        if ($identifier === '*') {
            return '*';
        }
        $parts = explode('.', $identifier);
        foreach ($parts as $part) {
            if ($part !== '*' && !preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $part)) {
                throw new InvalidArgumentException('Invalid table or column name: ' . $identifier);
            }
        }
        return implode('.', array_map(static fn (string $part): string => $part === '*' ? '*' : '`' . $part . '`', $parts));
    }

    private function requireWhere(string $operation): void
    {
        if ($this->conditions === []) {
            throw new LogicException('Add a where() condition before ' . $operation . '() to protect every row.');
        }
    }

    private function run(string $sql, array $bindings): PDOStatement
    {
        $statement = $this->pdo->prepare($sql);
        foreach ($bindings as $key => $value) {
            $statement->bindValue($key, $value);
        }
        $statement->execute();
        return $statement;
    }
}
