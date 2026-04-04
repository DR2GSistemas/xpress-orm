<?php

declare(strict_types=1);

namespace Xpress\Orm\Attributes\Entity;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
final class XRelation
{
    public const ONE_TO_ONE = 'oneToOne';
    public const ONE_TO_MANY = 'oneToMany';
    public const MANY_TO_ONE = 'manyToOne';
    public const MANY_TO_MANY = 'manyToMany';

    public function __construct(
        public readonly string $type,
        public readonly ?string $targetEntity = null,
        public readonly ?string $mappedBy = null,
        public readonly ?string $inversedBy = null,
        public readonly ?string $foreignKey = null,
        public readonly ?string $joinColumn = null,
        public readonly ?string $joinTable = null,
        public readonly ?string $joinTableName = null,
        public readonly ?array $joinColumns = null,
        public readonly ?array $inverseJoinColumns = null,
        public readonly ?array $cascade = null,
        public readonly bool $eager = false,
        public readonly bool $orphanRemoval = false
    ) {
        $this->cascade = $cascade ?? ['persist', 'remove'];
    }

    public static function oneToOne(
        string $targetEntity,
        ?string $mappedBy = null,
        ?string $inversedBy = null,
        ?string $joinColumn = null,
        bool $eager = false
    ): self {
        return new self(
            type: self::ONE_TO_ONE,
            targetEntity: $targetEntity,
            mappedBy: $mappedBy,
            inversedBy: $inversedBy,
            joinColumn: $joinColumn,
            eager: $eager
        );
    }

    public static function oneToMany(
        string $targetEntity,
        string $mappedBy,
        ?string $inversedBy = null,
        bool $eager = false,
        bool $orphanRemoval = false
    ): self {
        return new self(
            type: self::ONE_TO_MANY,
            targetEntity: $targetEntity,
            mappedBy: $mappedBy,
            inversedBy: $inversedBy,
            eager: $eager,
            orphanRemoval: $orphanRemoval
        );
    }

    public static function manyToOne(
        string $targetEntity,
        ?string $joinColumn = null,
        ?string $inversedBy = null,
        bool $eager = false
    ): self {
        return new self(
            type: self::MANY_TO_ONE,
            targetEntity: $targetEntity,
            inversedBy: $inversedBy,
            joinColumn: $joinColumn,
            eager: $eager
        );
    }

    public static function manyToMany(
        string $targetEntity,
        ?string $joinTable = null,
        ?string $joinColumn = null,
        ?string $inverseJoinColumn = null,
        ?string $inversedBy = null,
        bool $eager = false
    ): self {
        return new self(
            type: self::MANY_TO_MANY,
            targetEntity: $targetEntity,
            inversedBy: $inversedBy,
            joinTableName: $joinTable,
            joinColumn: $joinColumn,
            inverseJoinColumns: $inverseJoinColumn !== null ? [$inverseJoinColumn] : null,
            eager: $eager
        );
    }
}
