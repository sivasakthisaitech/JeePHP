<?php
declare(strict_types=1);

namespace JeePHP\Database;

use InvalidArgumentException;
use RuntimeException;

abstract class Model
{
    protected static string $table = '';
    protected static string $primaryKey = 'id';
    protected array $fillable = [];
    protected array $hidden = ['password'];
    private array $loadedRelations = [];

    public function __construct(protected array $attributes = []) {}

    public static function query(): TableQuery
    {
        return table_load(static::tableName());
    }

    /** Fetch this model and its declared relationships in batched queries. */
    public static function with(string ...$relations): ModelQuery
    {
        return new ModelQuery(static::class, $relations);
    }

    public static function all(): array
    {
        return array_map(fn (array $row) => new static($row), static::query()->get());
    }

    public static function find(int|string $id): ?static
    {
        $row = static::query()->where(static::$primaryKey, $id)->first();
        return $row === null ? null : new static($row);
    }

    public static function create(array $data): static
    {
        return static::insert_data($data);
    }

    /** Create and save a model using only its fillable attributes. */
    public static function insert_data(array $data): static
    {
        $model = new static();
        $model->fill($data);
        if ($model->attributes === []) {
            throw new InvalidArgumentException('No fillable model fields were provided for insert_data().');
        }
        $model->save();
        return $model;
    }

    public function fill(array $data): static
    {
        foreach ($data as $key => $value) {
            if ($key !== static::$primaryKey && in_array($key, $this->fillable, true)) {
                if (!is_scalar($value) && $value !== null) {
                    throw new InvalidArgumentException('Model values must be scalar or null: ' . $key);
                }
                $this->attributes[$key] = $value;
            }
        }
        return $this;
    }

    public function save(): bool
    {
        $table = static::tableName();
        $key = static::$primaryKey;
        if (isset($this->attributes[$key])) {
            $id = $this->attributes[$key];
            $data = array_intersect_key($this->attributes, array_flip($this->fillable));
            if ($data === []) {
                return true;
            }
            table_connect($table)->where($key, $id)->update($data);
            return true;
        }

        $id = table_connect($table)->insert($this->attributes);
        $this->attributes[$key] = $id;
        return true;
    }

    public function update(array $data): bool
    {
        return $this->fill($data)->save();
    }

    public function delete(): int
    {
        $key = static::$primaryKey;
        if (!isset($this->attributes[$key])) {
            throw new RuntimeException('Cannot delete a model before it has been saved.');
        }
        return table_connect(static::tableName())->where($key, $this->attributes[$key])->delete();
    }

    public function toArray(): array
    {
        $data = array_diff_key($this->attributes, array_flip($this->hidden));
        foreach ($this->loadedRelations as $name => $value) {
            if ($value instanceof self) {
                $data[$name] = $value->toArray();
            } elseif (is_array($value)) {
                $data[$name] = array_map(
                    static fn (mixed $item): mixed => $item instanceof self ? $item->toArray() : $item,
                    $value
                );
            } else {
                $data[$name] = $value;
            }
        }
        return $data;
    }

    public function __get(string $key): mixed
    {
        if (array_key_exists($key, $this->loadedRelations)) {
            return $this->loadedRelations[$key];
        }
        if (is_callable([$this, $key])) {
            throw new RuntimeException('Relationship ' . static::class . '::' . $key . '() was not eager-loaded. Use ' . static::class . '::with(\'' . $key . '\')->get() to avoid N+1 queries.');
        }
        return $this->attributes[$key] ?? null;
    }

    public function __isset(string $key): bool
    {
        return array_key_exists($key, $this->loadedRelations)
            ? $this->loadedRelations[$key] !== null
            : isset($this->attributes[$key]);
    }

    /** @internal Used by ModelQuery after a batched relationship query. */
    public function setRelation(string $name, mixed $value): void
    {
        $this->loadedRelations[$name] = $value;
    }

    /** @internal Read a relationship key without invoking magic relation access. */
    public function attribute(string $name): mixed
    {
        return $this->attributes[$name] ?? null;
    }

    protected function belongsTo(string $model, string $foreignKey, string $ownerKey = 'id'): RelationDefinition
    {
        return RelationDefinition::belongsTo($model, $foreignKey, $ownerKey);
    }

    protected function hasMany(string $model, string $foreignKey, string $localKey = 'id'): RelationDefinition
    {
        return RelationDefinition::hasMany($model, $foreignKey, $localKey);
    }

    public function __set(string $key, mixed $value): void
    {
        if (!in_array($key, $this->fillable, true) && $key !== static::$primaryKey) {
            throw new InvalidArgumentException('The ' . $key . ' attribute is not fillable.');
        }
        $this->attributes[$key] = $value;
    }

    private static function tableName(): string
    {
        if (static::$table === '') {
            throw new RuntimeException(static::class . ' must define its table name.');
        }
        return static::$table;
    }
}
