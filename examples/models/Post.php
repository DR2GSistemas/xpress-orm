<?php

declare(strict_types=1);

namespace App\Models;

use Xpress\Orm\Attributes\Entity\{XEntity, XColumn, XId, XRelation, XIndex};
use Xpress\Orm\Traits\TimestampableTrait;
use Xpress\Orm\Traits\SoftDeleteTrait;

#[XEntity(table: 'posts')]
#[XIndex(columns: ['slug'], unique: true)]
#[XIndex(columns: ['status', 'created_at'])]
#[XIndex(columns: ['category_id', 'status'])]
class Post
{
    use TimestampableTrait;
    use SoftDeleteTrait;

    #[XId]
    #[XColumn(type: 'int', increment: true)]
    private ?int $id = null;

    #[XColumn(type: 'varchar', length: 255)]
    private string $title;

    #[XColumn(type: 'varchar', length: 255)]
    private string $slug;

    #[XColumn(type: 'text')]
    private ?string $content = null;

    #[XColumn(type: 'text', nullable: true)]
    private ?string $excerpt = null;

    #[XColumn(type: 'varchar', length: 255, nullable: true)]
    private ?string $featuredImage = null;

    #[XColumn(type: 'enum', enum: ['draft', 'published', 'archived'], default: 'draft')]
    private string $status = 'draft';

    #[XColumn(type: 'int')]
    private int $views = 0;

    #[XColumn(type: 'int')]
    private int $userId = 0;

    #[XColumn(type: 'int', nullable: true)]
    private ?int $categoryId = null;

    #[XColumn(type: 'boolean', default: false)]
    private bool $isFeatured = false;

    #[XColumn(type: 'datetime', nullable: true)]
    private ?\DateTimeImmutable $publishedAt = null;

    #[XRelation(manyToOne: User::class, joinColumn: 'user_id')]
    private ?User $author = null;

    #[XRelation(manyToOne: Category::class, joinColumn: 'category_id')]
    private ?Category $category = null;

    #[XRelation(oneToMany: Comment::class, mappedBy: 'post')]
    private ?Collection $comments = null;

    #[XRelation(manyToMany: Tag::class, joinTable: 'post_tags')]
    private ?Collection $tags = null;

    public function __construct()
    {
        $this->comments = new Collection();
        $this->tags = new Collection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
        if (empty($this->slug)) {
            $this->slug = $this->generateSlug($title);
        }
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): void
    {
        $this->slug = $slug;
    }

    private function generateSlug(string $title): string
    {
        $slug = strtolower(trim($title));
        $slug = preg_replace('/[^a-z0-9-]/', '-', $slug);
        $slug = preg_replace('/-+/', '-', $slug);
        return trim($slug, '-');
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(?string $content): void
    {
        $this->content = $content;
    }

    public function getExcerpt(int $length = 150): ?string
    {
        if ($this->excerpt) {
            return $this->excerpt;
        }

        if (!$this->content) {
            return null;
        }

        return mb_substr(strip_tags($this->content), 0, $length) . '...';
    }

    public function setExcerpt(?string $excerpt): void
    {
        $this->excerpt = $excerpt;
    }

    public function getFeaturedImage(): ?string
    {
        return $this->featuredImage;
    }

    public function setFeaturedImage(?string $featuredImage): void
    {
        $this->featuredImage = $featuredImage;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    public function publish(): void
    {
        $this->status = 'published';
        if ($this->publishedAt === null) {
            $this->publishedAt = new \DateTimeImmutable();
        }
    }

    public function getViews(): int
    {
        return $this->views;
    }

    public function incrementViews(): void
    {
        $this->views++;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function setUserId(int $userId): void
    {
        $this->userId = $userId;
    }

    public function getCategoryId(): ?int
    {
        return $this->categoryId;
    }

    public function setCategoryId(?int $categoryId): void
    {
        $this->categoryId = $categoryId;
    }

    public function isFeatured(): bool
    {
        return $this->isFeatured;
    }

    public function setIsFeatured(bool $isFeatured): void
    {
        $this->isFeatured = $isFeatured;
    }

    public function getPublishedAt(): ?\DateTimeImmutable
    {
        return $this->publishedAt;
    }

    public function setPublishedAt(?\DateTimeImmutable $publishedAt): void
    {
        $this->publishedAt = $publishedAt;
    }

    public function getAuthor(): ?User
    {
        return $this->author;
    }

    public function setAuthor(?User $author): void
    {
        $this->author = $author;
        if ($author !== null) {
            $this->userId = $author->getId() ?? 0;
        }
    }

    public function getCategory(): ?Category
    {
        return $this->category;
    }

    public function setCategory(?Category $category): void
    {
        $this->category = $category;
        if ($category !== null) {
            $this->categoryId = $category->getId();
        }
    }

    public function getComments(): Collection
    {
        return $this->comments ?? new Collection();
    }

    public function getTags(): Collection
    {
        return $this->tags ?? new Collection();
    }

    public function addTag(Tag $tag): void
    {
        $this->tags[] = $tag;
    }

    public function removeTag(Tag $tag): void
    {
        $key = array_search($tag, $this->tags->toArray(), true);
        if ($key !== false) {
            unset($this->tags[$key]);
        }
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'content' => $this->content,
            'excerpt' => $this->excerpt,
            'featured_image' => $this->featuredImage,
            'status' => $this->status,
            'views' => $this->views,
            'user_id' => $this->userId,
            'category_id' => $this->categoryId,
            'is_featured' => $this->isFeatured,
            'published_at' => $this->publishedAt?->format('Y-m-d H:i:s'),
            'created_at' => $this->createdAt?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt?->format('Y-m-d H:i:s'),
        ];
    }
}
