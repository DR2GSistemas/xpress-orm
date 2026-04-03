<?php

declare(strict_types=1);

namespace Xpress\Orm\Result;

use Xpress\Orm\Entity\XEntityManager;
use Xpress\Orm\Repository\XBaseRepository;

trait XResultRepository
{
    protected abstract function getRepository(): XBaseRepository;
    
    protected abstract function getEntityManager(): XEntityManager;
    
    protected abstract function getEntityClass(): string;

    protected function ok(mixed $data = null, int $code = 200): XResult
    {
        return XResult::ok($data, $code);
    }

    protected function created(mixed $data = null): XResult
    {
        return XResult::ok($data, 201);
    }

    protected function noContent(): XResult
    {
        return XResult::ok(null, 204);
    }

    protected function notFound(string $message = 'Entity not found', array $data = []): XResult
    {
        return XResult::fail($message, 404, $data);
    }

    protected function conflict(string $message = 'Entity already exists', array $data = []): XResult
    {
        return XResult::fail($message, 409, $data);
    }

    protected function fail(string $message, int $code = 500, array $data = []): XResult
    {
        return XResult::fail($message, $code, $data);
    }

    protected function error(string $message = 'Internal Server Error', int $code = 500): XResult
    {
        return XResult::fail($message, $code);
    }

    protected function fromThrowable(\Throwable $e, bool $hideInternal = true): XResult
    {
        return XResult::fromThrowable($e, $hideInternal);
    }

    protected function try(callable $callback): XResult
    {
        try {
            $result = $callback();
            if ($result instanceof XResult) {
                return $result;
            }
            return XResult::ok($result);
        } catch (\Throwable $e) {
            return XResult::fromThrowable($e);
        }
    }

    protected function findResult(mixed $id): XResult
    {
        return $this->try(function() use ($id) {
            $entity = $this->getRepository()->find($id);
            
            if ($entity === null) {
                return $this->notFound(
                    $this->getEntityClass() . ' not found',
                    ['id' => $id]
                );
            }
            
            return $this->ok($entity);
        });
    }

    protected function findOneResult(array $criteria = [], array $options = []): XResult
    {
        return $this->try(function() use ($criteria, $options) {
            $entity = $this->getRepository()->findOne($criteria, $options);
            
            if ($entity === null) {
                return $this->notFound('Entity not found', ['criteria' => $criteria]);
            }
            
            return $this->ok($entity);
        });
    }

    protected function saveResult(object $entity): XResult
    {
        return $this->try(function() use ($entity) {
            $saved = $this->getEntityManager()->save($entity);
            return $this->created($saved);
        });
    }

    protected function deleteResult(object $entity, bool $hard = false): XResult
    {
        return $this->try(function() use ($entity, $hard) {
            $this->getEntityManager()->delete($entity, $hard);
            return $this->noContent();
        });
    }

    protected function deleteByIdResult(mixed $id, bool $hard = false): XResult
    {
        return $this->try(function() use ($id, $hard) {
            $deleted = $this->getRepository()->deleteById($id, $hard);
            
            if (!$deleted) {
                return $this->notFound(
                    $this->getEntityClass() . ' not found',
                    ['id' => $id]
                );
            }
            
            return $this->noContent();
        });
    }

    protected function existsResult(mixed $id): XResult
    {
        return $this->try(function() use ($id) {
            $exists = $this->getRepository()->exists($id);
            return $this->ok(['exists' => $exists]);
        });
    }

    protected function paginateResult(int $page = 1, int $perPage = 20, array $criteria = [], array $options = []): XResult
    {
        return $this->try(function() use ($page, $perPage, $criteria, $options) {
            $result = $this->getRepository()->paginate($page, $perPage, $criteria, $options);
            return $this->ok($result);
        });
    }

    protected function searchResult(string $query, array $fields = [], array $options = []): XResult
    {
        return $this->try(function() use ($query, $fields, $options) {
            $results = $this->getRepository()->search($query, $fields, $options);
            return $this->ok([
                'items' => $results,
                'total' => count($results),
                'query' => $query
            ]);
        });
    }
}
