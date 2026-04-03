<?php

declare(strict_types=1);

namespace Xpress\Orm\Traits;

use DateTimeImmutable;
use Xpress\Orm\Attributes\Entity\XColumn;

trait SoftDeleteTrait
{
    #[XColumn(type: 'datetime', name: 'deleted_at', nullable: true)]
    private ?DateTimeImmutable $deletedAt = null;

    public function getDeletedAt(): ?DateTimeImmutable
    {
        return $this->deletedAt;
    }

    public function setDeletedAt(?DateTimeImmutable $deletedAt): void
    {
        $this->deletedAt = $deletedAt;
    }

    public function getDeletedAtString(string $format = 'Y-m-d H:i:s'): ?string
    {
        return $this->deletedAt?->format($format);
    }

    public function isDeleted(): bool
    {
        return $this->deletedAt !== null;
    }

    public function softDelete(): void
    {
        $this->deletedAt = new DateTimeImmutable();
    }

    public function restore(): void
    {
        $this->deletedAt = null;
    }

    public function markAsDeleted(): void
    {
        $this->softDelete();
    }

    public function markAsNotDeleted(): void
    {
        $this->restore();
    }
}
