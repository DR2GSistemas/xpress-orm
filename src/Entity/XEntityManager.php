<?php

declare(strict_types=1);

namespace Xpress\Orm\Entity;

use DateTimeImmutable;
use Xpress\Orm\Connection\XConnection;
use Xpress\Orm\Connection\XQueryBuilder;
use Xpress\Orm\Hydrator\XHydrator;
use Xpress\Orm\Repository\XRepositoryFactory;
use ReflectionProperty;

final class XEntityManager
{
    private XHydrator $hydrator;
    private XRepositoryFactory $repositoryFactory;
    private array $identityMap = [];
    private array $unitOfWork = [];
    private array $toInsert = [];
    private array $toUpdate = [];
    private array $toDelete = [];
    private bool $inTransaction = false;

    public function __construct(
        private readonly XConnection $connection
    ) {
        $this->hydrator = new XHydrator($this);
        $this->repositoryFactory = new XRepositoryFactory($this);
    }

    public function getConnection(): XConnection
    {
        return $this->connection;
    }

    public function getHydrator(): XHydrator
    {
        return $this->hydrator;
    }

    public function getRepositoryFactory(): XRepositoryFactory
    {
        return $this->repositoryFactory;
    }

    public function find(string $className, mixed $id, array $options = []): ?object
    {
        $map = XEntityMap::get($className);
        $pkColumn = $map->getPrimaryKeyColumn();
        $pkProperty = $map->getPrimaryKey();

        $cacheKey = "{$className}:{$id}";

        if (isset($this->identityMap[$cacheKey])) {
            return $this->identityMap[$cacheKey];
        }

        $qb = $this->connection->createQueryBuilder()
            ->select('*')
            ->from($map->getTable())
            ->where($pkColumn, $id)
            ->limit(1);

        if ($map->hasRelation('deletedAt')) {
            $qb->whereNull('deleted_at');
        }

        $this->applyOptions($qb, $options, $map);

        $data = $qb->getOne();

        if ($data === null) {
            return null;
        }

        $entity = $this->hydrator->hydrate($className, $data);
        $this->identityMap[$cacheKey] = $entity;

        $this->loadRelations($entity, $options, $map);

        return $entity;
    }

    public function findOneBy(string $className, array $criteria, array $options = []): ?object
    {
        $map = XEntityMap::get($className);

        $qb = $this->connection->createQueryBuilder()
            ->select('*')
            ->from($map->getTable())
            ->limit(1);

        $this->buildCriteria($qb, $criteria, $map);

        if ($map->hasRelation('deletedAt')) {
            $qb->whereNull('deleted_at');
        }

        $this->applyOptions($qb, $options, $map);

        $data = $qb->getOne();

        if ($data === null) {
            return null;
        }

        $entity = $this->hydrator->hydrate($className, $data);

        $pkColumn = $map->getPrimaryKeyColumn();
        $pkValue = $data[$pkColumn] ?? null;
        if ($pkValue !== null) {
            $cacheKey = "{$className}:{$pkValue}";
            $this->identityMap[$cacheKey] = $entity;
        }

        return $entity;
    }

    public function findBy(string $className, array $criteria = [], array $options = [], int $limit = 0, int $offset = 0): array
    {
        $map = XEntityMap::get($className);

        $qb = $this->connection->createQueryBuilder()
            ->select('*')
            ->from($map->getTable());

        $this->buildCriteria($qb, $criteria, $map);

        if ($map->hasRelation('deletedAt')) {
            $qb->whereNull('deleted_at');
        }

        $this->applyOptions($qb, $options, $map);

        if ($limit > 0) {
            $qb->limit($limit);
        }

        if ($offset > 0) {
            $qb->offset($offset);
        }

        $results = $qb->getResult();

        return $this->hydrator->hydrateAll($className, $results);
    }

    public function findAll(string $className, array $options = []): array
    {
        return $this->findBy($className, [], $options);
    }

    public function count(string $className, array $criteria = []): int
    {
        $map = XEntityMap::get($className);

        $qb = $this->connection->createQueryBuilder()
            ->select('COUNT(*)')
            ->from($map->getTable());

        $this->buildCriteria($qb, $criteria, $map);

        if ($map->hasRelation('deletedAt')) {
            $qb->whereNull('deleted_at');
        }

        return (int) $qb->getColumn();
    }

    public function exists(string $className, mixed $id): bool
    {
        $map = XEntityMap::get($className);
        $pkColumn = $map->getPrimaryKeyColumn();

        $qb = $this->connection->createQueryBuilder()
            ->select('1')
            ->from($map->getTable())
            ->where($pkColumn, $id)
            ->limit(1);

        if ($map->hasRelation('deletedAt')) {
            $qb->whereNull('deleted_at');
        }

        return $qb->exists();
    }

    public function save(object $entity): object
    {
        $className = $entity::class;
        $map = XEntityMap::get($className);

        $this->updateTimestamps($entity, $map);

        if ($this->isNew($entity, $map)) {
            return $this->insert($entity, $map);
        }

        return $this->update($entity, $map);
    }

    public function saveAll(array $entities): array
    {
        foreach ($entities as $entity) {
            $this->save($entity);
        }
        return $entities;
    }

    public function delete(object $entity, bool $hard = false): void
    {
        $className = $entity::class;
        $map = XEntityMap::get($className);

        if (!$hard && $this->hasSoftDelete($map)) {
            $this->softDelete($entity, $map);
            return;
        }

        $this->hardDelete($entity, $map);
    }

    public function deleteById(string $className, mixed $id, bool $hard = false): bool
    {
        $entity = $this->find($className, $id);

        if ($entity === null) {
            return false;
        }

        $this->delete($entity, $hard);
        return true;
    }

    public function deleteAll(string $className, array $criteria = [], bool $hard = false): int
    {
        $map = XEntityMap::get($className);

        if (!$hard && $this->hasSoftDelete($map)) {
            return $this->softDeleteAll($className, $criteria, $map);
        }

        $qb = $this->connection->createQueryBuilder()
            ->delete($map->getTable());

        $this->buildCriteria($qb, $criteria, $map);

        return $this->connection->execute($qb->getSQL(), $qb->getParameters());
    }

    public function refresh(object $entity): void
    {
        $className = $entity::class;
        $map = XEntityMap::get($className);
        $pkValue = $this->getPrimaryKeyValue($entity, $map);

        if ($pkValue === null) {
            throw new \RuntimeException('Cannot refresh a new entity without primary key');
        }

        $freshEntity = $this->find($className, $pkValue);

        if ($freshEntity === null) {
            throw new \RuntimeException('Entity no longer exists in database');
        }

        $this->hydrator->hydrateInto($entity, $this->hydrator->extract($freshEntity));
    }

    public function detach(object $entity): void
    {
        $className = $entity::class;
        $map = XEntityMap::get($className);
        $pkValue = $this->getPrimaryKeyValue($entity, $map);

        if ($pkValue !== null) {
            unset($this->identityMap["{$className}:{$pkValue}"]);
        }

        unset($this->toInsert[spl_object_id($entity)]);
        unset($this->toUpdate[spl_object_id($entity)]);
        unset($this->toDelete[spl_object_id($entity)]);
    }

    public function clear(): void
    {
        $this->identityMap = [];
        $this->toInsert = [];
        $this->toUpdate = [];
        $this->toDelete = [];
    }

    public function beginTransaction(): void
    {
        if ($this->inTransaction) {
            return;
        }

        $this->connection->beginTransaction();
        $this->inTransaction = true;
    }

    public function commit(): void
    {
        if (!$this->inTransaction) {
            return;
        }

        $this->connection->commit();
        $this->inTransaction = false;
    }

    public function rollback(): void
    {
        if (!$this->inTransaction) {
            return;
        }

        $this->connection->rollback();
        $this->inTransaction = false;
    }

    public function createQuery(string $className): XQueryBuilder
    {
        $map = XEntityMap::get($className);

        return $this->connection->createQueryBuilder()
            ->select('*')
            ->from($map->getTable());
    }

    public function getRepository(string $repositoryClass): object
    {
        return $this->repositoryFactory->getRepository($repositoryClass);
    }

    public function getRepositoryForEntity(string $className): object
    {
        return $this->repositoryFactory->getRepositoryForEntity($className);
    }

    public function extract(object $entity): array
    {
        return $this->hydrator->extract($entity);
    }

    public function hydrate(string $className, array $data): object
    {
        return $this->hydrator->hydrate($className, $data);
    }

    private function insert(object $entity, XEntityMap $map): object
    {
        $data = $this->prepareData($entity, $map, false);
        $pkColumn = $map->getPrimaryKeyColumn();

        $this->connection->insert($map->getTable(), $data);

        if ($map->isPrimaryKeyAutoIncrement() && !isset($data[$pkColumn])) {
            $id = $this->connection->lastInsertId();
            $pkProperty = $map->getPrimaryKey();
            $this->setPropertyValue($entity, $pkProperty, (int) $id);
        }

        $pkValue = $this->getPrimaryKeyValue($entity, $map);
        if ($pkValue !== null) {
            $this->identityMap["{get_class($entity)}:{$pkValue}"] = $entity;
        }

        return $entity;
    }

    private function update(object $entity, XEntityMap $map): object
    {
        $data = $this->prepareData($entity, $map, true);

        if (empty($data)) {
            return $entity;
        }

        $pkColumn = $map->getPrimaryKeyColumn();
        $pkValue = $this->getPrimaryKeyValue($entity, $map);

        $this->connection->update($map->getTable(), $data, [$pkColumn => $pkValue]);

        return $entity;
    }

    private function hardDelete(object $entity, XEntityMap $map): void
    {
        $pkColumn = $map->getPrimaryKeyColumn();
        $pkValue = $this->getPrimaryKeyValue($entity, $map);

        if ($pkValue === null) {
            return;
        }

        $this->connection->delete($map->getTable(), [$pkColumn => $pkValue]);

        $this->detach($entity);
    }

    private function softDelete(object $entity, XEntityMap $map): void
    {
        $pkColumn = $map->getPrimaryKeyColumn();
        $pkValue = $this->getPrimaryKeyValue($entity, $map);

        if ($pkValue === null) {
            return;
        }

        $now = new DateTimeImmutable();
        $this->setPropertyValue($entity, 'deletedAt', $now);

        $this->connection->update($map->getTable(), ['deleted_at' => $now->format('Y-m-d H:i:s')], [$pkColumn => $pkValue]);
    }

    private function softDeleteAll(string $className, array $criteria, XEntityMap $map): int
    {
        $now = new DateTimeImmutable();

        $qb = $this->connection->createQueryBuilder()
            ->update($map->getTable())
            ->set('deleted_at', ':deleted_at')
            ->setParameter('deleted_at', $now->format('Y-m-d H:i:s'));

        $this->buildCriteria($qb, $criteria, $map);

        return $this->connection->execute($qb->getSQL(), $qb->getParameters());
    }

    private function prepareData(object $entity, XEntityMap $map, bool $isUpdate): array
    {
        $data = [];
        $reflection = new ReflectionClass($entity::class);

        foreach ($map->getColumns() as $property => $columnInfo) {
            if ($columnInfo['isPrimaryKey'] && $map->isPrimaryKeyAutoIncrement() && !$isUpdate) {
                continue;
            }

            if ($columnInfo['isPrimaryKey']) {
                continue;
            }

            $value = $this->getPropertyValue($entity, $property);

            if ($value instanceof DateTimeImmutable) {
                $value = $value->format('Y-m-d H:i:s');
            }

            if ($value === null && !$columnInfo['nullable'] && $columnInfo['default'] !== null) {
                $value = $columnInfo['default'];
            }

            $data[$columnInfo['column']] = $value;
        }

        return $data;
    }

    private function updateTimestamps(object $entity, XEntityMap $map): void
    {
        if (method_exists($entity, 'setTimestamps')) {
            $entity->setTimestamps();
        }
    }

    private function isNew(object $entity, XEntityMap $map): bool
    {
        $pkProperty = $map->getPrimaryKey();

        if ($pkProperty === null) {
            return true;
        }

        $pkValue = $this->getPropertyValue($entity, $pkProperty);

        return $pkValue === null;
    }

    private function hasSoftDelete(XEntityMap $map): bool
    {
        return $map->hasRelation('deletedAt');
    }

    private function getPrimaryKeyValue(object $entity, XEntityMap $map): mixed
    {
        $pkProperty = $map->getPrimaryKey();

        if ($pkProperty === null) {
            return null;
        }

        return $this->getPropertyValue($entity, $pkProperty);
    }

    public function getPropertyValue(object $entity, string $property): mixed
    {
        $reflection = new ReflectionProperty($entity::class, $property);
        $reflection->setAccessible(true);
        return $reflection->getValue($entity);
    }

    public function setPropertyValue(object $entity, string $property, mixed $value): void
    {
        $reflection = new ReflectionProperty($entity::class, $property);
        $reflection->setAccessible(true);
        $reflection->setValue($entity, $value);
    }

    private function buildCriteria(XQueryBuilder $qb, array $criteria, XEntityMap $map): void
    {
        foreach ($criteria as $column => $value) {
            $property = $map->getPropertyName($column);
            $columnName = $map->getColumnName($property);

            if (is_array($value)) {
                $qb->whereIn($columnName, $value);
            } elseif ($value === null) {
                $qb->whereNull($columnName);
            } else {
                $qb->where($columnName, $value);
            }
        }
    }

    private function applyOptions(XQueryBuilder $qb, array $options, XEntityMap $map): void
    {
        if (isset($options['orderBy'])) {
            $orderBy = $options['orderBy'];
            if (is_string($orderBy)) {
                $parts = explode(' ', trim($orderBy));
                $column = $parts[0];
                $direction = $parts[1] ?? 'ASC';
            } else {
                $column = key($orderBy);
                $direction = current($orderBy);
            }

            $property = $map->getPropertyName($column);
            $columnName = $map->getColumnName($property);
            $qb->orderBy($columnName, strtoupper($direction));
        }

        if (isset($options['with'])) {
            $with = (array) $options['with'];
            foreach ($with as $relation) {
                $this->applyRelationJoin($qb, $relation, $map);
            }
        }
    }

    private function applyRelationJoin(XQueryBuilder $qb, string $relation, XEntityMap $map): void
    {
        $relationInfo = $map->getRelation($relation);

        if ($relationInfo === null) {
            return;
        }

        $targetMap = XEntityMap::get($relationInfo['targetEntity']);
        $foreignKey = $relationInfo['foreignKey'] ?? $map->getTable() . '_id';

        $alias = $targetMap->getTable();
        $qb->leftJoin($targetMap->getTable(), "{$alias}.id = {$map->getTable()}.{$foreignKey}", $alias);
    }

    private function loadRelations(object $entity, array $options, XEntityMap $map): void
    {
        if (!isset($options['with'])) {
            return;
        }

        $with = (array) $options['with'];

        foreach ($with as $relation) {
            $relationInfo = $map->getRelation($relation);

            if ($relationInfo === null || !$relationInfo['eager']) {
                continue;
            }
        }
    }

    public function isInTransaction(): bool
    {
        return $this->inTransaction;
    }

    public function getIdentityMap(): array
    {
        return $this->identityMap;
    }

    public function findOrFail(string $className, mixed $id, array $options = []): \Xpress\Orm\Result\XResult
    {
        return \Xpress\Orm\Result\XResult::ok($this->find($className, $id, $options))->andThen(function($entity) use ($id) {
            if ($entity === null) {
                return \Xpress\Orm\Result\XResult::fail(
                    $className . ' not found',
                    404,
                    ['id' => $id]
                );
            }
            return \Xpress\Orm\Result\XResult::ok($entity);
        });
    }

    public function saveOrFail(object $entity): \Xpress\Orm\Result\XResult
    {
        try {
            $saved = $this->save($entity);
            $isNew = !in_array(spl_object_id($entity), array_keys($this->identityMap));
            return \Xpress\Orm\Result\XResult::ok($saved, $isNew ? 201 : 200);
        } catch (\Throwable $e) {
            return \Xpress\Orm\Result\XResult::fromThrowable($e);
        }
    }

    public function deleteOrFail(object $entity, bool $hard = false): \Xpress\Orm\Result\XResult
    {
        try {
            $this->delete($entity, $hard);
            return \Xpress\Orm\Result\XResult::ok(null, 204);
        } catch (\Throwable $e) {
            return \Xpress\Orm\Result\XResult::fromThrowable($e);
        }
    }
}
