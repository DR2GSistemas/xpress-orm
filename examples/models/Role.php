<?php

declare(strict_types=1);

namespace App\Models;

use Xpress\Orm\Attributes\Entity\{XEntity, XColumn, XId, XIndex};
use Xpress\Orm\Traits\TimestampableTrait;

#[XEntity(table: 'roles')]
#[XIndex(columns: ['slug'], unique: true)]
class Role
{
    use TimestampableTrait;

    #[XId]
    #[XColumn(type: 'int', increment: true)]
    private ?int $id = null;

    #[XColumn(type: 'varchar', length: 50)]
    private string $name;

    #[XColumn(type: 'varchar', length: 50)]
    private string $slug;

    #[XColumn(type: 'text', nullable: true)]
    private ?string $description = null;

    #[XColumn(type: 'int', default: 0)]
    private int $level = 0;

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
        return strtolower(str_replace(' ', '-', trim($name)));
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function getLevel(): int
    {
        return $this->level;
    }

    public function setLevel(int $level): void
    {
        $this->level = $level;
    }

    public function isHigherThan(Role $role): bool
    {
        return $this->level > $role->level;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'level' => $this->level,
            'created_at' => $this->createdAt?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt?->format('Y-m-d H:i:s'),
        ];
    }
}
