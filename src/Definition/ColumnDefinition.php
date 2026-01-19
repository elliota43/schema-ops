<?php

namespace SchemaOps\Definition;

class ColumnDefinition
{
    public function __construct(
        public string $name,
        public string $sqlType,
        public bool $isNullable,
        public bool $isAutoIncrement,
        public bool $isPrimaryKey,
        public mixed $defaultValue
    ) {}
}