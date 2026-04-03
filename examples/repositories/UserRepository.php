<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\User;
use Xpress\Orm\Attributes\Repository\XRepository;
use Xpress\Orm\Repository\XBaseRepository;

#[XRepository(entity: User::class)]
class UserRepository extends XBaseRepository
{
    public function findByEmail(string $email): ?User
    {
        return $this->findOneByColumn('email', $email);
    }

    public function findByRole(string $role, array $options = []): array
    {
        return $this->findBy(['role' => $role], $options);
    }

    public function findActiveUsers(array $options = []): array
    {
        return $this->findBy(['status' => 'active'], $options);
    }

    public function findAdmins(): array
    {
        return $this->findBy(['role' => 'admin', 'status' => 'active']);
    }

    public function searchUsers(string $query, array $options = []): array
    {
        return $this->search($query, ['name', 'email'], $options);
    }

    public function findWithPosts(int $id): ?User
    {
        return $this->findByIdWith($id, ['posts']);
    }

    public function findPaginated(int $page = 1, int $perPage = 20, array $criteria = []): array
    {
        return $this->paginate($page, $perPage, $criteria, [
            'orderBy' => ['createdAt' => 'DESC']
        ]);
    }

    public function countByRole(): array
    {
        $results = $this->createQueryBuilder()
            ->select('role, COUNT(*) as count')
            ->from($this->getTable())
            ->whereNull('deleted_at')
            ->groupBy('role')
            ->getResult();

        $counts = [];
        foreach ($results as $row) {
            $counts[$row['role']] = (int) $row['count'];
        }

        return $counts;
    }

    public function findRecentlyRegistered(int $limit = 10): array
    {
        return $this->findBy([], ['orderBy' => ['createdAt' => 'DESC']], $limit);
    }

    public function existsByEmail(string $email): bool
    {
        return $this->existsBy(['email' => $email]);
    }

    public function getActiveUsersCount(): int
    {
        return $this->count(['status' => 'active']);
    }
}
