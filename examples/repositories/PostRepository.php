<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Post;
use Xpress\Orm\Attributes\Repository\XRepository;
use Xpress\Orm\Repository\XBaseRepository;

#[XRepository(entity: Post::class)]
class PostRepository extends XBaseRepository
{
    public function findBySlug(string $slug): ?Post
    {
        return $this->findOneByColumn('slug', $slug);
    }

    public function findPublished(array $options = []): array
    {
        $defaultOptions = [
            'orderBy' => ['publishedAt' => 'DESC']
        ];
        
        return $this->findBy(
            array_merge(['status' => 'published'], $options['criteria'] ?? []),
            array_merge($defaultOptions, $options)
        );
    }

    public function findDrafts(): array
    {
        return $this->findBy(['status' => 'draft'], [
            'orderBy' => ['updatedAt' => 'DESC']
        ]);
    }

    public function findByCategory(int $categoryId, array $options = []): array
    {
        return $this->findBy(
            array_merge(['categoryId' => $categoryId, 'status' => 'published'], $options['criteria'] ?? []),
            $options
        );
    }

    public function findByAuthor(int $authorId, array $options = []): array
    {
        return $this->findBy(
            array_merge(['userId' => $authorId], $options['criteria'] ?? []),
            $options
        );
    }

    public function searchPosts(string $query, array $options = []): array
    {
        return $this->search($query, ['title', 'content', 'slug'], array_merge([
            'criteria' => ['status' => 'published']
        ], $options));
    }

    public function findFeatured(): array
    {
        return $this->findBy([
            'isFeatured' => true,
            'status' => 'published'
        ], ['orderBy' => ['publishedAt' => 'DESC']]);
    }

    public function findPopular(int $limit = 10): array
    {
        return $this->createQueryBuilder()
            ->select('*')
            ->from($this->getTable())
            ->where('status', 'published')
            ->orderBy('views', 'DESC')
            ->limit($limit)
            ->getResult();
    }

    public function findWithRelations(int $id): ?Post
    {
        return $this->findByIdWith($id, ['author', 'category', 'tags', 'comments']);
    }

    public function paginatePublished(int $page = 1, int $perPage = 10): array
    {
        return $this->paginate($page, $perPage, [
            'status' => 'published'
        ], [
            'orderBy' => ['publishedAt' => 'DESC']
        ]);
    }

    public function incrementViews(int $id): void
    {
        $this->createQueryBuilder()
            ->update($this->getTable())
            ->set('views', 'views + 1')
            ->where('id', $id)
            ->execute();
    }

    public function getPublishedCount(): int
    {
        return $this->count(['status' => 'published']);
    }

    public function getTotalViews(): int
    {
        $result = $this->createQueryBuilder()
            ->select('SUM(views) as total')
            ->from($this->getTable())
            ->getOne();

        return (int) ($result['total'] ?? 0);
    }

    public function getPostsGroupedByMonth(int $year): array
    {
        $results = $this->createQueryBuilder()
            ->select("DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as count")
            ->from($this->getTable())
            ->whereRaw("YEAR(created_at) = ?", [$year])
            ->groupBy("DATE_FORMAT(created_at, '%Y-%m')")
            ->orderBy('month', 'ASC')
            ->getResult();

        return $results;
    }

    public function archiveOldPosts(int $days = 365): int
    {
        $date = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        return $this->createQueryBuilder()
            ->update($this->getTable())
            ->set('status', "'archived'")
            ->whereRaw("status = 'published' AND published_at < ?", [$date])
            ->execute();
    }

    public function findByDateRange(\DateTimeInterface $from, \DateTimeInterface $to, array $options = []): array
    {
        $qb = $this->createQueryBuilder()
            ->select('*')
            ->from($this->getTable())
            ->whereBetween('created_at', $from->format('Y-m-d H:i:s'), $to->format('Y-m-d H:i:s'));

        if (isset($options['limit'])) {
            $qb->limit($options['limit']);
        }

        if (isset($options['orderBy'])) {
            $qb->orderBy($options['orderBy']);
        }

        return $this->getEntityManager()
            ->getHydrator()
            ->hydrateAll($this->getEntityClass(), $qb->getResult());
    }
}
