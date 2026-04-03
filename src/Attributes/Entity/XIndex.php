<?php

declare(strict_types=1);

namespace Xpress\Orm\Attributes\Entity;

#[Attribute(Attribute::TARGET_PROPERTY)]
final class XIndex
{
    public function __construct(
        public readonly ?string $name = null,
        public readonly array $columns = [],
        public readonly bool $unique = false
    ) {}
}
