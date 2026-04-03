<?php

declare(strict_types=1);

namespace App\Models;

use Xpress\Orm\Attributes\Entity\{XEntity, XColumn, XId, XIndex};
use Xpress\Orm\Traits\TimestampableTrait;

#[XEntity(table: 'tags')]
#[XIndex(columns: ['slug'], unique: true)]
class Tag
{
    use TimestampableTrait;

    #[XId]
    #[XColumn(type: 'int', increment: true)]
    private ?int $id = null;

    #[XColumn(type: 'varchar', length: 50)]
    private string $name;

    #[XColumn(type: 'varchar', length: 50)]
    private string $slug;

    #[XColumn(type: 'varchar', length: 255, nullable: true)]
    private ?string $color = null;

    #[XColumn(type: 'int', default: 0)]
    private int $usageCount = 0;

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

    public function getColor(): ?string
    {
        return $this->color;
    }

    public function setColor(?string $color): void
    {
        $this->color = $color;
    }

    public function getUsageCount(): int
    {
        return $this->usageCount;
    }

    public function incrementUsageCount(): void
    {
        $this->usageCount++;
    }

    public function decrementUsageCount(): void
    {
        if ($this->usageCount > 0) {
            $this->usageCount--;
        }
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'color' => $this->color,
            'usage_count' => $this->usageCount,
            'created_at' => $this->createdAt?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt?->format('Y-m-d H:i:s'),
        ];
    }
}
