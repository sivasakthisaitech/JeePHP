<?php
declare(strict_types=1);

namespace JeePHP\Database;

use InvalidArgumentException;

final class RelationDefinition
{
    public const BELONGS_TO = 'belongsTo';
    public const HAS_MANY = 'hasMany';

    private function __construct(
        public readonly string $type,
        public readonly string $relatedModel,
        public readonly string $foreignKey,
        public readonly string $localKey
    ) {
        if (!is_subclass_of($relatedModel, Model::class)) {
            throw new InvalidArgumentException('A relationship must point to a JeePHP model: ' . $relatedModel);
        }
    }

    public static function belongsTo(string $relatedModel, string $foreignKey, string $ownerKey = 'id'): self
    {
        return new self(self::BELONGS_TO, $relatedModel, $foreignKey, $ownerKey);
    }

    public static function hasMany(string $relatedModel, string $foreignKey, string $localKey = 'id'): self
    {
        return new self(self::HAS_MANY, $relatedModel, $foreignKey, $localKey);
    }
}
