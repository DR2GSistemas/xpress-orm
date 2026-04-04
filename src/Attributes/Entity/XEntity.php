<?php

declare(strict_types=1);

namespace Xpress\Orm\Attributes\Entity;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final class XEntity
{
    public function __construct(
        public readonly string $table,
        public readonly ?string $schema = null,
        public readonly ?string $comment = null
    ) {}
}
