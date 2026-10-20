<?php
declare(strict_types=1);

namespace JeePHP\Database;

use InvalidArgumentException;
use LogicException;
use PDO;
use PDOStatement;
use JeePHP\Http\Request;

final class TableQuery
{
    private array $selectedColumns = ['*'];
    private array $selectedColumnNames = ['*'];
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
        $this->selectedColumnNames = $columns;
        $this->selectedColumns = array_map(fn (string $column): string => $this->quoteIdentifier($column), $columns);
        return $this;
    }

    /**
     * Alias for columns(), useful when listing columns for a table response.
     */
    public function whereColumn(string ...$columns): self
    {
        return $this->columns(...$columns);
    }

    public function where_column(string ...$columns): self
    {
        return $this->columns(...$columns);
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

    public function whereIn(string $column, array $values): self
    {
        if ($values === []) {
            $this->conditions[] = '1 = 0';
            return $this;
        }

        $placeholders = [];
        foreach (array_values($values) as $value) {
            $placeholder = ':in' . count($this->bindings);
            $placeholders[] = $placeholder;
            $this->bindings[$placeholder] = $value;
        }
        $this->conditions[] = $this->quoteIdentifier($column) . ' IN (' . implode(', ', $placeholders) . ')';
        return $this;
    }

    public function where_in(string $column, array $values): self
    {
        return $this->whereIn($column, $values);
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

    /**
     * Return rows for a normal page render.
     */
    public function data(): array
    {
        return $this->get();
    }

    /** Fetch this table and render a view with the result data attached. */
    public function load_table(string $view): void
    {
        // Avoid unexpectedly loading an entire large table into a page.
        $this->rowLimit ??= 500;
        $rows = $this->get();
        $viewData = [$this->table => $rows];

        // A single result can be used directly as {column_name} in the view.
        if (count($rows) === 1) {
            $viewData = array_replace($rows[0], $viewData);
        }

        \load_view($view, $viewData);
    }

    /**
     * Build the standard server-side DataTables response.
     *
     * Column names always come from columns()/whereColumn(), never from the
     * browser request, so sorting and search cannot inject SQL identifiers.
     */
    public function ajax(Request $request): array
    {
        if (in_array('*', $this->selectedColumnNames, true)) {
            throw new LogicException('Choose columns() before using ajax() so search and sort columns are safe.');
        }

        $input = $request->all();
        $draw = $this->safeInteger($input['draw'] ?? 0, 0);
        $start = $this->safeInteger($input['start'] ?? 0, 0);
        $requestedLength = filter_var($input['length'] ?? 10, FILTER_VALIDATE_INT);
        // Keep a broken or malicious client from requesting an unbounded result set.
        $length = $requestedLength === -1
            ? 500
            : ($requestedLength === false || $requestedLength < 1 ? 10 : min($requestedLength, 500));

        $total = $this->countRows($this->conditions, $this->bindings);

        $conditions = $this->conditions;
        $bindings = $this->bindings;
        $search = $input['search'] ?? [];
        $rawSearchValue = is_array($search) ? ($search['value'] ?? '') : '';
        $searchValue = is_scalar($rawSearchValue) ? trim((string) $rawSearchValue) : '';
        if ($searchValue !== '') {
            $searchParts = [];
            $clientColumns = is_array($input['columns'] ?? null) ? $input['columns'] : [];
            foreach ($this->selectedColumnNames as $index => $column) {
                $clientColumn = $clientColumns[$index] ?? [];
                $searchable = is_array($clientColumn) ? ($clientColumn['searchable'] ?? true) : true;
                if (in_array($searchable, [false, 0, '0', 'false'], true)) {
                    continue;
                }
                $placeholder = ':dt_search_' . $index;
                $searchParts[] = $this->quoteIdentifier($column) . ' LIKE ' . $placeholder;
                $bindings[$placeholder] = '%' . $searchValue . '%';
            }
            if ($searchParts !== []) {
                $conditions[] = '(' . implode(' OR ', $searchParts) . ')';
            }
        }

        $filtered = $this->countRows($conditions, $bindings);
        $sortParts = $this->dataTableSort($input['order'] ?? []);
        if ($sortParts === [] && $this->sort !== null) {
            $sortParts[] = $this->sort;
        }

        $sql = 'SELECT ' . implode(', ', $this->selectedColumns)
            . ' FROM ' . $this->quoteIdentifier($this->table)
            . $this->conditionsSql($conditions);
        if ($sortParts !== []) {
            $sql .= ' ORDER BY ' . implode(', ', $sortParts);
        }
        $sql .= ' LIMIT ' . $length . ' OFFSET ' . $start;

        return [
            'draw' => $draw,
            'recordsTotal' => $total,
            'recordsFiltered' => $filtered,
            'data' => $this->run($sql, $bindings)->fetchAll(),
        ];
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
        return $this->conditionsSql($this->conditions);
    }

    private function conditionsSql(array $conditions): string
    {
        return $conditions === [] ? '' : ' WHERE ' . implode(' AND ', $conditions);
    }

    private function countRows(array $conditions, array $bindings): int
    {
        $sql = 'SELECT COUNT(*) FROM ' . $this->quoteIdentifier($this->table) . $this->conditionsSql($conditions);
        return (int) $this->run($sql, $bindings)->fetchColumn();
    }

    private function dataTableSort(mixed $orders): array
    {
        if (!is_array($orders)) {
            return [];
        }

        $sorts = [];
        foreach ($orders as $order) {
            if (!is_array($order)) {
                continue;
            }
            $index = filter_var($order['column'] ?? null, FILTER_VALIDATE_INT);
            if ($index === false || !array_key_exists($index, $this->selectedColumnNames)) {
                continue;
            }
            $direction = strtoupper((string) ($order['dir'] ?? 'ASC'));
            if (!in_array($direction, ['ASC', 'DESC'], true)) {
                $direction = 'ASC';
            }
            $sorts[] = $this->quoteIdentifier($this->selectedColumnNames[$index]) . ' ' . $direction;
        }
        return $sorts;
    }

    private function safeInteger(mixed $value, int $default): int
    {
        $integer = filter_var($value, FILTER_VALIDATE_INT);
        return $integer === false ? $default : max(0, $integer);
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
        QueryMonitor::record($sql);
        $statement = $this->pdo->prepare($sql);
        foreach ($bindings as $key => $value) {
            $statement->bindValue($key, $value);
        }
        $statement->execute();
        return $statement;
    }
}
