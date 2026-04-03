<?php

declare(strict_types=1);

namespace Xpress\Orm\Entity;

use ReflectionClass;
use ReflectionProperty;
use Xpress\Orm\Attributes\Entity\XEntity;
use Xpress\Orm\Attributes\Entity\XColumn;
use Xpress\Orm\Attributes\Entity\XId;
use Xpress\Orm\Attributes\Entity\XRelation;
use Xpress\Orm\Attributes\Entity\XIndex;

final class XEntityMap
{
    private static array $cache = [];

    private string $className;
    private string $table;
    private ?string $schema;
    private ?string $comment;
    private ?string $primaryKey;
    private bool $primaryKeyAutoIncrement = false;
    private array $columns = [];
    private array $relations = [];
    private array $indexes = [];
    private array $propertyToColumn = [];
    private array $columnToProperty = [];

    public function __construct(string $className)
    {
        $this->className = $className;
        $this->parseEntity();
    }

    private function parseEntity(): void
    {
        $reflection = new ReflectionClass($this->className);

        $entityAttr = $reflection->getAttributes(XEntity::class);
        if (empty($entityAttr)) {
            throw new \InvalidArgumentException(
                "Class {$this->className} must have #[XEntity] attribute"
            );
        }

        $entity = $entityAttr[0]->newInstance();
        $this->table = $entity->table;
        $this->schema = $entity->schema;
        $this->comment = $entity->comment;

        $indexes = $reflection->getAttributes(XIndex::class);
        foreach ($indexes as $indexAttr) {
            $this->indexes[] = $indexAttr->newInstance();
        }

        foreach ($reflection->getProperties() as $property) {
            $this->parseProperty($property);
        }
    }

    private function parseProperty(ReflectionProperty $property): void
    {
        $propertyName = $property->getName();
        $columnName = $propertyName;
        $isPrimaryKey = false;
        $isAutoIncrement = false;

        $columnAttr = $property->getAttributes(XColumn::class);
        $idAttr = $property->getAttributes(XId::class);
        $relationAttr = $property->getAttributes(XRelation::class);

        if (!empty($columnAttr)) {
            $column = $columnAttr[0]->newInstance();
            $columnName = $column->name ?? $propertyName;
            $isAutoIncrement = $column->increment;
        }

        if (!empty($idAttr)) {
            $isPrimaryKey = true;
            $this->primaryKey = $propertyName;

            if (!empty($columnAttr)) {
                $column = $columnAttr[0]->newInstance();
                $isAutoIncrement = $column->increment;
            }
        }

        if ($isPrimaryKey) {
            $this->primaryKeyAutoIncrement = $isAutoIncrement;
        }

        $this->propertyToColumn[$propertyName] = $columnName;
        $this->columnToProperty[$columnName] = $propertyName;

        if (!empty($relationAttr)) {
            $relation = $relationAttr[0]->newInstance();
            $this->relations[$propertyName] = [
                'property' => $propertyName,
                'column' => $columnName,
                'type' => $relation->type,
                'targetEntity' => $relation->targetEntity,
                'mappedBy' => $relation->mappedBy,
                'inversedBy' => $relation->inversedBy,
                'foreignKey' => $relation->foreignKey,
                'joinColumn' => $relation->joinColumn,
                'joinTable' => $relation->joinTableName,
                'cascade' => $relation->cascade,
                'eager' => $relation->eager,
                'orphanRemoval' => $relation->orphanRemoval,
            ];
        } else {
            $column = !empty($columnAttr) ? $columnAttr[0]->newInstance() : new XColumn();

            $this->columns[$propertyName] = [
                'property' => $propertyName,
                'column' => $columnName,
                'type' => $column->type,
                'length' => $column->length,
                'nullable' => $column->nullable,
                'default' => $column->default,
                'unique' => $column->unique,
                'comment' => $column->comment,
                'enum' => $column->enum,
                'precision' => $column->precision,
                'scale' => $column->scale,
                'isPrimaryKey' => $isPrimaryKey,
                'isAutoIncrement' => $isAutoIncrement,
            ];
        }
    }

    public function getClassName(): string
    {
        return $this->className;
    }

    public function getTable(): string
    {
        return $this->table;
    }

    public function getFullTableName(): string
    {
        if ($this->schema) {
            return "{$this->schema}.{$this->table}";
        }
        return $this->table;
    }

    public function getSchema(): ?string
    {
        return $this->schema;
    }

    public function getPrimaryKey(): ?string
    {
        return $this->primaryKey;
    }

    public function getPrimaryKeyColumn(): ?string
    {
        return $this->primaryKey ? ($this->columns[$this->primaryKey]['column'] ?? $this->primaryKey) : null;
    }

    public function isPrimaryKeyAutoIncrement(): bool
    {
        return $this->primaryKeyAutoIncrement;
    }

    public function getColumns(): array
    {
        return $this->columns;
    }

    public function getColumn(string $property): ?array
    {
        return $this->columns[$property] ?? null;
    }

    public function getColumnName(string $property): string
    {
        return $this->propertyToColumn[$property] ?? $property;
    }

    public function getPropertyName(string $column): string
    {
        return $this->columnToProperty[$column] ?? $column;
    }

    public function getRelations(): array
    {
        return $this->relations;
    }

    public function getRelation(string $property): ?array
    {
        return $this->relations[$property] ?? null;
    }

    public function hasRelation(string $property): bool
    {
        return isset($this->relations[$property]);
    }

    public function getIndexes(): array
    {
        return $this->indexes;
    }

    public function getPropertyToColumnMap(): array
    {
        return $this->propertyToColumn;
    }

    public function getColumnToPropertyMap(): array
    {
        return $this->columnToProperty;
    }

    public function hasProperty(string $property): bool
    {
        return isset($this->columns[$property]) || isset($this->relations[$property]);
    }

    public function getAllProperties(): array
    {
        return array_keys($this->propertyToColumn);
    }

    public function isRelation(string $property): bool
    {
        return isset($this->relations[$property]);
    }

    public function getRelationProperties(): array
    {
        return array_keys($this->relations);
    }

    public function getColumnProperties(): array
    {
        return array_keys($this->columns);
    }

    public static function get(string $className): self
    {
        if (!isset(self::$cache[$className])) {
            self::$cache[$className] = new self($className);
        }
        return self::$cache[$className];
    }

    public static function clearCache(): void
    {
        self::$cache = [];
    }
}
