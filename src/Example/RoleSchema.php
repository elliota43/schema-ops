<?php

namespace Atlas\Example;

use Atlas\Attributes\Column;
use Atlas\Attributes\Id;
use Atlas\Attributes\Table;
use Atlas\Attributes\Timestamps;

#[Table(name: 'roles')]
#[Id]
#[Timestamps]
class RoleSchema
{
    #[Column(type: 'varchar', length: 50, unique: true)]
    public string $name;

    #[Column(type: 'varchar', length: 255, nullable: true)]
    public ?string $description;

    #[Column(type: 'integer', default: 0)]
    public int $level;
}
