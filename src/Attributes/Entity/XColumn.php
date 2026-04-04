<?php

declare(strict_types=1);

namespace Xpress\Orm\Attributes\Entity;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
final class XColumn
{
    public const TYPE_INT = 'int';
    public const TYPE_BIGINT = 'bigint';
    public const TYPE_VARCHAR = 'varchar';
    public const TYPE_TEXT = 'text';
    public const TYPE_MEDIUMTEXT = 'mediumtext';
    public const TYPE_LONGTEXT = 'longtext';
    public const TYPE_BOOLEAN = 'boolean';
    public const TYPE_DECIMAL = 'decimal';
    public const TYPE_FLOAT = 'float';
    public const TYPE_DOUBLE = 'double';
    public const TYPE_DATE = 'date';
    public const TYPE_DATETIME = 'datetime';
    public const TYPE_TIMESTAMP = 'timestamp';
    public const TYPE_TIME = 'time';
    public const TYPE_ENUM = 'enum';
    public const TYPE_JSON = 'json';
    public const TYPE_BLOB = 'blob';
    public const TYPE_MEDIUMBLOB = 'mediumblob';
    public const TYPE_LONGBLOB = 'longblob';

    public function __construct(
        public readonly string $type = self::TYPE_VARCHAR,
        public readonly ?string $name = null,
        public readonly int $length = 255,
        public readonly bool $nullable = false,
        public readonly mixed $default = null,
        public readonly bool $increment = false,
        public readonly bool $unique = false,
        public readonly ?string $comment = null,
        public readonly ?array $enum = null,
        public readonly ?int $precision = null,
        public readonly ?int $scale = null
    ) {}
}
