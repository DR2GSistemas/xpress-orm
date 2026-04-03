<?php

declare(strict_types=1);

namespace App\Models;

use Xpress\Orm\Attributes\Entity\{XEntity, XColumn, XId, XIndex};
use Xpress\Orm\Traits\TimestampableTrait;

#[XEntity(table: 'categories')]
#[XIndex(columns: ['slug'], unique: true)]
class Category
{
    use TimestampableTrait;

    #[XId]
    #[XColumn(type: 'int', increment: true)]
    private ?int $id = null;

    #[XColumn(type: 'varchar', length: 100)]
    private string $name;

    #[XColumn(type: 'varchar', length: 100)]
    private string $slug;

    #[XColumn(type: 'text', nullable: true)]
    private ?string $description = null;

    #[XColumn(type: 'varchar', length: 255, nullable: true)]
    private ?string $image = null;

    #[XColumn(type: 'int', default: 0)]
    private int $parentId = 0;

    #[XColumn(type: 'int', default: 0)]
    private int $sortOrder = 0;

    #[XColumn(type: 'boolean', default: true)]
    private bool $isActive = true;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
        if (empty($this->slug)) {
            $this->slug = $this->generateSlug($name);
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

    private function generateSlug(string $name): string
    {
        $slug = strtolower(trim($name));
        $slug = preg_replace('/[^a-z0-9-]/', '-', $slug);
        $slug = preg_replace('/-+/', '-', $slug);
        return trim($slug, '-');
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function setImage(?string $image): void
    {
        $this->image = $image;
    }

    public function getParentId(): int
    {
        return $this->parentId;
    }

    public function setParentId(int $parentId): void
    {
        $this->parentId = $parentId;
    }

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    public function setSortOrder(int $sortOrder): void
    {
        $this->sortOrder = $sortOrder;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): void
    {
        $this->isActive = $isActive;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'image' => $this->image,
            'parent_id' => $this->parentId,
            'sort_order' => $this->sortOrder,
            'is_active' => $this->isActive,
            'created_at' => $this->createdAt?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt?->format('Y-m-d H:i:s'),
        ];
    }
}
