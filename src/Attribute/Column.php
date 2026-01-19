<?php

namespace SchemaOps\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Column
{
    public function __construct(
        public string $type, // e.g. 'integer', 'varchar'
        public ?int $length = null,
        public bool $nullable = false,
        public bool $autoIncrement = false,
        public bool $primaryKey = false,
        public mixed $default = null,
    ) {}
}