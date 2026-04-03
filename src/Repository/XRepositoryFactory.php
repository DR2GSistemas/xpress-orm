<?php

declare(strict_types=1);

namespace Xpress\Orm\Repository;

use ReflectionClass;
use Xpress\Orm\Entity\XEntityManager;
use Xpress\Orm\Attributes\Repository\XRepository;

final class XRepositoryFactory
{
    private array $repositories = [];
    private array $entityToRepository = [];

    public function __construct(
        private readonly XEntityManager $entityManager
    ) {}

    public function getRepository(string $repositoryClass): object
    {
        if (isset($this->repositories[$repositoryClass])) {
            return $this->repositories[$repositoryClass];
        }

        if (!class_exists($repositoryClass)) {
            throw new \InvalidArgumentException("Repository class {$repositoryClass} does not exist");
        }

        $reflection = new ReflectionClass($repositoryClass);

        if (!$reflection->isSubclassOf(XBaseRepository::class)) {
            throw new \InvalidArgumentException(
                "Repository class {$repositoryClass} must extend XBaseRepository"
            );
        }

        $repository = $reflection->newInstance($this->entityManager);
        $this->repositories[$repositoryClass] = $repository;

        return $repository;
    }

    public function getRepositoryForEntity(string $entityClass): object
    {
        if (isset($this->entityToRepository[$entityClass])) {
            $repoClass = $this->entityToRepository[$entityClass];
            return $this->getRepository($repoClass);
        }

        $repositoryClass = $this->findRepositoryForEntity($entityClass);

        if ($repositoryClass === null) {
            return $this->createDefaultRepository($entityClass);
        }

        $this->entityToRepository[$entityClass] = $repositoryClass;
        return $this->getRepository($repositoryClass);
    }

    private function findRepositoryForEntity(string $entityClass): ?string
    {
        $possiblePaths = [
            str_replace('\\Entity\\', '\\Repository\\', $entityClass) . 'Repository',
            str_replace('\\Entities\\', '\\Repositories\\', $entityClass) . 'Repository',
            $entityClass . 'Repository',
        ];

        foreach ($possiblePaths as $class) {
            if (class_exists($class)) {
                $reflection = new ReflectionClass($class);

                if ($reflection->isSubclassOf(XBaseRepository::class)) {
                    return $class;
                }
            }
        }

        return null;
    }

    private function createDefaultRepository(string $entityClass): object
    {
        $repoClass = $entityClass . 'Repository';

        if (!class_exists($repoClass)) {
            eval("
                namespace " . str_replace('\\Entity\\', '\\Repository\\', dirname($entityClass)) . ";
                
                #[\\Xpress\\Orm\\Attributes\\Repository\\XRepository(entity: '{$entityClass}')]
                class " . basename($repoClass) . " extends \\Xpress\\Orm\\Repository\\XBaseRepository {}
            ");
        }

        return $this->getRepository($repoClass);
    }

    public function registerRepository(string $entityClass, string $repositoryClass): void
    {
        $this->entityToRepository[$entityClass] = $repositoryClass;
    }

    public function clear(): void
    {
        $this->repositories = [];
        $this->entityToRepository = [];
    }
}
