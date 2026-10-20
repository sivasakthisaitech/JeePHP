<?php
declare(strict_types=1);

namespace JeePHP\Database;

use InvalidArgumentException;
use RuntimeException;

final class ModelQuery
{
    private TableQuery $query;

    /** @param class-string<Model> $modelClass @param list<string> $relations */
    public function __construct(private readonly string $modelClass, private readonly array $relations)
    {
        if (!is_subclass_of($modelClass, Model::class)) {
            throw new InvalidArgumentException('ModelQuery requires a JeePHP model class.');
        }
        if ($relations === []) {
            throw new InvalidArgumentException('Pass at least one relationship name to with().');
        }
        foreach ($relations as $relation) {
            if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $relation)) {
                throw new InvalidArgumentException('Use a direct relationship method name in with(); nested paths are not supported yet.');
            }
        }
        $this->query = $modelClass::query();
    }

    public function where(string $column, mixed $value, string $operator = '='): self
    {
        $this->query->where($column, $value, $operator);
        return $this;
    }

    public function whereIn(string $column, array $values): self
    {
        $this->query->whereIn($column, $values);
        return $this;
    }

    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $this->query->orderBy($column, $direction);
        return $this;
    }

    public function limit(int $count): self
    {
        $this->query->limit($count);
        return $this;
    }

    /** @return list<Model> */
    public function get(): array
    {
        $class = $this->modelClass;
        $models = [];
        foreach ($this->query->get() as $row) {
            $models[] = new $class($row);
        }
        if ($models === []) {
            return [];
        }

        foreach ($this->relations as $relationName) {
            $definition = $this->definition($models[0], $relationName);
            $this->loadRelation($models, $relationName, $definition);
        }

        return $models;
    }

    public function first(): ?Model
    {
        $models = $this->limit(1)->get();
        return $models[0] ?? null;
    }

    public function count(): int
    {
        return $this->query->count();
    }

    private function definition(Model $model, string $relationName): RelationDefinition
    {
        if (!is_callable([$model, $relationName])) {
            throw new RuntimeException('Relationship method not found: ' . $this->modelClass . '::' . $relationName . '().');
        }
        $definition = $model->{$relationName}();
        if (!$definition instanceof RelationDefinition) {
            throw new RuntimeException($this->modelClass . '::' . $relationName . '() must return a JeePHP relationship.');
        }
        return $definition;
    }

    /** @param list<Model> $models */
    private function loadRelation(array $models, string $name, RelationDefinition $relation): void
    {
        $relatedClass = $relation->relatedModel;
        $keys = [];
        foreach ($models as $model) {
            $value = $relation->type === RelationDefinition::BELONGS_TO
                ? $model->attribute($relation->foreignKey)
                : $model->attribute($relation->localKey);
            if ($value !== null) {
                $keys[(string) $value] = $value;
            }
        }

        $related = [];
        foreach (array_chunk(array_values($keys), 500) as $keyChunk) {
            $column = $relation->type === RelationDefinition::BELONGS_TO
                ? $relation->localKey
                : $relation->foreignKey;
            foreach ($relatedClass::query()->whereIn($column, $keyChunk)->get() as $row) {
                $related[] = new $relatedClass($row);
            }
        }

        if ($relation->type === RelationDefinition::BELONGS_TO) {
            $byKey = [];
            foreach ($related as $item) {
                $value = $item->attribute($relation->localKey);
                if ($value !== null) {
                    $byKey[(string) $value] = $item;
                }
            }
            foreach ($models as $model) {
                $value = $model->attribute($relation->foreignKey);
                $model->setRelation($name, $value === null ? null : ($byKey[(string) $value] ?? null));
            }
            return;
        }

        $byKey = [];
        foreach ($related as $item) {
            $value = $item->attribute($relation->foreignKey);
            if ($value !== null) {
                $byKey[(string) $value][] = $item;
            }
        }
        foreach ($models as $model) {
            $value = $model->attribute($relation->localKey);
            $model->setRelation($name, $value === null ? [] : ($byKey[(string) $value] ?? []));
        }
    }
}
