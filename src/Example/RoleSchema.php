<?php

namespace SchemaOps\Example;

use SchemaOps\Attributes\Column;
use SchemaOps\Attributes\Id;
use SchemaOps\Attributes\Table;
use SchemaOps\Attributes\Timestamps;

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
