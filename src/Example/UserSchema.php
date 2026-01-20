<?php

namespace SchemaOps\Example;

use SchemaOps\Attributes\Column;
use SchemaOps\Attributes\Id;
use SchemaOps\Attributes\SoftDeletes;
use SchemaOps\Attributes\Table;
use SchemaOps\Attributes\Timestamps;

#[Table(name: 'users')]
#[Id]
#[Timestamps]
#[SoftDeletes]
class UserSchema
{
    #[Column(type: 'varchar', length: 255, unique: true)]
    public string $email;

    #[Column(type: 'varchar', length: 255)]
    public string $name;

    #[Column(type: 'varchar', length: 255)]
    public string $password;

    #[Column(type: 'enum', values: ['active', 'suspended', 'banned'], default: 'active')]
    public string $status;
}