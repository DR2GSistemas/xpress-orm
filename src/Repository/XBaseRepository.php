<?php

declare(strict_types=1);

namespace Xpress\Orm\Repository;

use Xpress\Orm\Connection\XConnection;
use Xpress\Orm\Connection\XQueryBuilder;
use Xpress\Orm\Entity\XEntityManager;
use Xpress\Orm\Entity\XEntityMap;
use Xpress\Orm\Hydrator\XHydrator;
use ReflectionClass;

abstract class XBaseRepository
{
    protected XEntityManager $entityManager;
    protected XConnection $connection;
    protected XHydrator $hydrator;
    protected XEntityMap $map;
    protected string $entityClass;

    public function __construct(XEntityManager $entityManager)
    {
        $this->entityManager = $entityManager;
        $this->connection = $entityManager->getConnection();
        $this->hydrator = $entityManager->getHydrator();
        $this->entityClass = $this->resolveEntityClass();
        $this->map = XEntityMap::get($this->entityClass);
    }

    protected function resolveEntityClass(): string
    {
        $class = static::class;
        $reflection = new ReflectionClass($class);

        $attributes = $reflection->getAttributes(\Xpress\Orm\Attributes\Repository\XRepository::class);

        if (!empty($attributes)) {
            $attr = $attributes[0]->newInstance();
            return $attr->entity;
        }

        throw new \RuntimeException(
            "Repository " . static::class . " must have #[XRepository] attribute with entity class"
        );
    }

    public function find(mixed $id): ?object
    {
        return $this->entityManager->find($this->entityClass, $id);
    }

    public function findOne(array $criteria = [], array $options = []): ?object
    {
        return $this->entityManager->findOneBy($this->entityClass, $criteria, $options);
    }

    public function findBy(array $criteria = [], array $options = [], int $limit = 0, int $offset = 0): array
    {
        return $this->entityManager->findBy($this->entityClass, $criteria, $options, $limit, $offset);
    }

    public function findAll(array $options = []): array
    {
        return $this->entityManager->findAll($this->entityClass, $options);
    }

    public function count(array $criteria = []): int
    {
        return $this->entityManager->count($this->entityClass, $criteria);
    }

    public function exists(mixed $id): bool
    {
        return $this->entityManager->exists($this->entityClass, $id);
    }

    public function save(object $entity): object
    {
        return $this->entityManager->save($entity);
    }

    public function saveAll(array $entities): array
    {
        return $this->entityManager->saveAll($entities);
    }

    public function delete(object $entity, bool $hard = false): void
    {
        $this->entityManager->delete($entity, $hard);
    }

    public function deleteById(mixed $id, bool $hard = false): bool
    {
        return $this->entityManager->deleteById($this->entityClass, $id, $hard);
    }

    public function deleteAll(array $criteria = [], bool $hard = false): int
    {
        return $this->entityManager->deleteAll($this->entityClass, $criteria, $hard);
    }

    public function refresh(object $entity): void
    {
        $this->entityManager->refresh($entity);
    }

    public function detach(object $entity): void
    {
        $this->entityManager->detach($entity);
    }

    public function createQueryBuilder(): XQueryBuilder
    {
        return $this->entityManager->createQuery($this->entityClass);
    }

    public function createQuery(): XQueryBuilder
    {
        return $this->createQueryBuilder();
    }

    public function qb(): XQueryBuilder
    {
        return $this->createQueryBuilder();
    }

    public function findWith(array $relations): ?object
    {
        return $this->findOneBy([], ['with' => $relations]);
    }

    public function findByWith(array $criteria, array $relations, array $options = [], int $limit = 0, int $offset = 0): array
    {
        $options = array_merge($options, ['with' => $relations]);
        return $this->findBy($criteria, $options, $limit, $offset);
    }

    public function findByIdWith(mixed $id, array $relations): ?object
    {
        return $this->entityManager->find($this->entityClass, $id, ['with' => $relations]);
    }

    public function findAllWith(array $relations, array $options = []): array
    {
        $options = array_merge($options, ['with' => $relations]);
        return $this->findAll($options);
    }

    public function paginate(int $page = 1, int $perPage = 20, array $criteria = [], array $options = []): array
    {
        $page = max(1, $page);
        $offset = ($page - 1) * $perPage;

        $items = $this->findBy($criteria, $options, $perPage, $offset);
        $total = $this->count($criteria);

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'pages' => (int) ceil($total / $perPage),
            'has_next' => $page * $perPage < $total,
            'has_prev' => $page > 1,
        ];
    }

    public function first(array $criteria = [], array $options = []): ?object
    {
        $result = $this->findBy($criteria, $options, 1);
        return $result[0] ?? null;
    }

    public function last(array $criteria = [], array $options = []): ?object
    {
        $options = array_merge($options, ['orderBy' => ['id' => 'DESC']]);
        return $this->first($criteria, $options);
    }

    public function findOneByColumn(string $column, mixed $value): ?object
    {
        return $this->findOne([$column => $value]);
    }

    public function findByColumn(string $column, mixed $value, array $options = []): array
    {
        return $this->findBy([$column => $value], $options);
    }

    public function findByIds(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        return $this->findBy([
            'id' => $ids
        ]);
    }

    public function search(string $query, array $fields = [], array $options = []): array
    {
        if (empty($fields)) {
            $fields = ['name', 'email'];
        }

        if (empty($query)) {
            return [];
        }

        $qb = $this->createQueryBuilder();

        foreach ($fields as $i => $field) {
            if ($i === 0) {
                $qb->whereLike($field, $query);
            } else {
                $qb->orWhereLike($field, $query);
            }
        }

        if (isset($options['limit'])) {
            $qb->limit($options['limit']);
        }

        if (isset($options['offset'])) {
            $qb->offset($options['offset']);
        }

        if (isset($options['orderBy'])) {
            $qb->orderBy($options['orderBy']);
        }

        return $this->hydrator->hydrateAll($this->entityClass, $qb->getResult());
    }

    public function bulkSave(array $entities): array
    {
        return $this->saveAll($entities);
    }

    public function bulkDelete(array $entities, bool $hard = false): void
    {
        foreach ($entities as $entity) {
            $this->delete($entity, $hard);
        }
    }

    public function existsBy(array $criteria): bool
    {
        $qb = $this->createQueryBuilder()
            ->select('1')
            ->limit(1);

        foreach ($criteria as $column => $value) {
            if (is_array($value)) {
                $qb->whereIn($column, $value);
            } elseif ($value === null) {
                $qb->whereNull($column);
            } else {
                $qb->where($column, $value);
            }
        }

        return $qb->exists();
    }

    public function getEntityClass(): string
    {
        return $this->entityClass;
    }

    public function getTable(): string
    {
        return $this->map->getTable();
    }

    public function getEntityManager(): XEntityManager
    {
        return $this->entityManager;
    }

    protected function addWhereLike(XQueryBuilder $qb, string $column, string $value, string $type = 'AND'): void
    {
        $paramName = "{$column}_search";
        $qb->where("{$column} LIKE :{$paramName}", null, $type === 'OR' ? 'OR' : 'AND');
        $qb->setParameter($paramName, "%{$value}%");
    }
}
