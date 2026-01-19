<?php

namespace SchemaOps\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class Table {
    public function __construct(
        public string $name,
        public string $charset = 'utf8mb4',
        public string $collation = 'utf8mb4_unicode_ci'
    ) {}
}