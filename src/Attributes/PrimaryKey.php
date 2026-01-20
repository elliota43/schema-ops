<?php

namespace Atlas\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class PrimaryKey
{
    public function __construct(
        public array $columns,
    ) {}
}
