<?php

declare(strict_types=1);

namespace App\Models;

use Xpress\Orm\Attributes\Entity\{XEntity, XColumn, XId, XRelation, XIndex};
use Xpress\Orm\Traits\TimestampableTrait;

#[XEntity(table: 'comments')]
#[XIndex(columns: ['post_id', 'created_at'])]
class Comment
{
    use TimestampableTrait;

    #[XId]
    #[XColumn(type: 'int', increment: true)]
    private ?int $id = null;

    #[XColumn(type: 'text')]
    private string $content;

    #[XColumn(type: 'int')]
    private int $postId = 0;

    #[XColumn(type: 'int')]
    private int $userId = 0;

    #[XColumn(type: 'int', default: 0)]
    private int $parentId = 0;

    #[XColumn(type: 'enum', enum: ['pending', 'approved', 'rejected'], default: 'pending')]
    private string $status = 'pending';

    #[XRelation(manyToOne: Post::class, joinColumn: 'post_id')]
    private ?Post $post = null;

    #[XRelation(manyToOne: User::class, joinColumn: 'user_id')]
    private ?User $user = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function setContent(string $content): void
    {
        $this->content = $content;
    }

    public function getPostId(): int
    {
        return $this->postId;
    }

    public function setPostId(int $postId): void
    {
        $this->postId = $postId;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function setUserId(int $userId): void
    {
        $this->userId = $userId;
    }

    public function getParentId(): int
    {
        return $this->parentId;
    }

    public function setParentId(int $parentId): void
    {
        $this->parentId = $parentId;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function approve(): void
    {
        $this->status = 'approved';
    }

    public function reject(): void
    {
        $this->status = 'rejected';
    }

    public function getPost(): ?Post
    {
        return $this->post;
    }

    public function setPost(?Post $post): void
    {
        $this->post = $post;
        if ($post !== null) {
            $this->postId = $post->getId() ?? 0;
        }
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): void
    {
        $this->user = $user;
        if ($user !== null) {
            $this->userId = $user->getId() ?? 0;
        }
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'content' => $this->content,
            'post_id' => $this->postId,
            'user_id' => $this->userId,
            'parent_id' => $this->parentId,
            'status' => $this->status,
            'created_at' => $this->createdAt?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt?->format('Y-m-d H:i:s'),
        ];
    }
}
