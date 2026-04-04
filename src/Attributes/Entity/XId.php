<?php

declare(strict_types=1);

namespace Xpress\Orm\Attributes\Entity;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
final class XId
{
    public function __construct(
        public readonly string $generator = 'auto'
    ) {}
}
