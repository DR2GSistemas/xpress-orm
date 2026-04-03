<?php

declare(strict_types=1);

namespace Xpress\Orm\Traits;

use DateTime;
use DateTimeImmutable;
use Xpress\Orm\Attributes\Entity\XColumn;

trait TimestampableTrait
{
    #[XColumn(type: 'datetime', name: 'created_at', nullable: true)]
    private ?DateTimeImmutable $createdAt = null;

    #[XColumn(type: 'datetime', name: 'updated_at', nullable: true)]
    private ?DateTimeImmutable $updatedAt = null;

    public function getCreatedAt(): ?DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setCreatedAt(?DateTimeImmutable $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function setUpdatedAt(?DateTimeImmutable $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }

    public function getCreatedAtString(string $format = 'Y-m-d H:i:s'): ?string
    {
        return $this->createdAt?->format($format);
    }

    public function getUpdatedAtString(string $format = 'Y-m-d H:i:s'): ?string
    {
        return $this->updatedAt?->format($format);
    }

    public function setTimestamps(): void
    {
        $now = new DateTimeImmutable();

        if ($this->createdAt === null) {
            $this->createdAt = $now;
        }

        $this->updatedAt = $now;
    }

    public function touch(): void
    {
        $this->updatedAt = new DateTimeImmutable();
    }
}
