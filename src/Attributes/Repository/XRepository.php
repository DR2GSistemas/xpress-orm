<?php

declare(strict_types=1);

namespace Xpress\Orm\Attributes\Repository;

#[Attribute(Attribute::TARGET_CLASS)]
final class XRepository
{
    public function __construct(
        public readonly string $entity,
        public readonly ?string $table = null
    ) {}
}
