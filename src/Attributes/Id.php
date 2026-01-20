<?php

namespace Atlas\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class Id
{
    public function __construct(
        public string $name = 'id',
        public string $type = 'bigint',
        public bool $unsigned = true,
    ) {}
}
