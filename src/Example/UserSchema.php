<?php

namespace Atlas\Example;

use Atlas\Attributes\Column;
use Atlas\Attributes\Id;
use Atlas\Attributes\SoftDeletes;
use Atlas\Attributes\Table;
use Atlas\Attributes\Timestamps;

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