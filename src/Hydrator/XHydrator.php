<?php

declare(strict_types=1);

namespace Xpress\Orm\Hydrator;

use DateTimeImmutable;
use ReflectionClass;
use ReflectionProperty;
use Xpress\Orm\Entity\XEntityManager;
use Xpress\Orm\Entity\XEntityMap;

final class XHydrator
{
    public function __construct(
        private readonly XEntityManager $entityManager
    ) {}

    public function hydrate(string $className, array $data): object
    {
        if (empty($data)) {
            return $this->createEntity($className);
        }

        $map = XEntityMap::get($className);
        $entity = $this->createEntity($className);

        foreach ($data as $column => $value) {
            $property = $map->getPropertyName($column);

            if (!$map->hasProperty($property)) {
                continue;
            }

            if ($map->isRelation($property)) {
                continue;
            }

            $value = $this->transformValue($value, $map->getColumn($property)['type'] ?? 'varchar');
            $this->setPropertyValue($entity, $property, $value);
        }

        return $entity;
    }

    public function hydrateAll(string $className, array $results): array
    {
        $entities = [];

        foreach ($results as $data) {
            $entities[] = $this->hydrate($className, $data);
        }

        return $entities;
    }

    public function hydrateInto(object $entity, array $data): void
    {
        $className = $entity::class;
        $map = XEntityMap::get($className);

        foreach ($data as $column => $value) {
            $property = $map->getPropertyName($column);

            if (!$map->hasProperty($property)) {
                continue;
            }

            if ($map->isRelation($property)) {
                continue;
            }

            $value = $this->transformValue($value, $map->getColumn($property)['type'] ?? 'varchar');
            $this->setPropertyValue($entity, $property, $value);
        }
    }

    public function extract(object $entity, bool $withRelations = false): array
    {
        $className = $entity::class;
        $map = XEntityMap::get($className);
        $data = [];

        foreach ($map->getColumns() as $property => $columnInfo) {
            $value = $this->getPropertyValue($entity, $property);

            if ($value instanceof DateTimeImmutable) {
                $value = $value->format('Y-m-d H:i:s');
            }

            $data[$columnInfo['column']] = $value;
        }

        if ($withRelations) {
            foreach ($map->getRelations() as $property => $relationInfo) {
                $relationEntity = $this->getPropertyValue($entity, $property);

                if ($relationEntity === null) {
                    continue;
                }

                if (is_object($relationEntity)) {
                    $data[$property] = $this->extract($relationEntity);
                } elseif (is_array($relationEntity)) {
                    $data[$property] = array_map(
                        fn($item) => $this->extract($item),
                        $relationEntity
                    );
                }
            }
        }

        return $data;
    }

    public function extractToArray(object $entity, array $columns): array
    {
        $className = $entity::class;
        $map = XEntityMap::get($className);
        $data = [];

        foreach ($columns as $column) {
            $property = $map->getPropertyName($column);
            $value = $this->getPropertyValue($entity, $property);

            if ($value instanceof DateTimeImmutable) {
                $value = $value->format('Y-m-d H:i:s');
            }

            $data[$column] = $value;
        }

        return $data;
    }

    public function merge(object $entity, array $data): object
    {
        $this->hydrateInto($entity, $data);
        return $entity;
    }

    private function createEntity(string $className): object
    {
        return new $className();
    }

    private function transformValue(mixed $value, string $type): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            'int', 'bigint' => (int) $value,
            'float', 'double' => (float) $value,
            'decimal' => (float) $value,
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'date', 'datetime', 'timestamp' => $this->parseDateTime($value),
            'json' => is_string($value) ? json_decode($value, true) : $value,
            default => $value
        };
    }

    private function parseDateTime(mixed $value): ?DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof DateTimeImmutable) {
            return $value;
        }

        try {
            return new DateTimeImmutable((string) $value);
        } catch (\Exception) {
            return null;
        }
    }

    private function getPropertyValue(object $entity, string $property): mixed
    {
        $reflection = new ReflectionProperty($entity::class, $property);
        $reflection->setAccessible(true);
        return $reflection->getValue($entity);
    }

    private function setPropertyValue(object $entity, string $property, mixed $value): void
    {
        $reflection = new ReflectionProperty($entity::class, $property);
        $reflection->setAccessible(true);
        $reflection->setValue($entity, $value);
    }

    public function toArray(object $entity, array $options = []): array
    {
        $withRelations = $options['withRelations'] ?? false;
        $only = $options['only'] ?? [];
        $exclude = $options['exclude'] ?? [];

        $data = $this->extract($entity, $withRelations);

        if (!empty($only)) {
            $data = array_filter($data, fn($key) => in_array($key, $only), ARRAY_FILTER_USE_KEY);
        }

        if (!empty($exclude)) {
            $data = array_filter($data, fn($key) => !in_array($key, $exclude), ARRAY_FILTER_USE_KEY);
        }

        return $data;
    }

    public function fromArray(string $className, array $data, bool $strict = false): object
    {
        if ($strict) {
            return $this->hydrate($className, $data);
        }

        $entity = $this->createEntity($className);
        $this->hydrateInto($entity, $data);

        return $entity;
    }
}
