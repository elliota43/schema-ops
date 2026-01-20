<?php

namespace SchemaOps\Example;

use SchemaOps\Attributes\{Table, Column};

#[Table(name: 'users')]
class UserSchema
{
    #[Column(type: 'integer', autoIncrement: true, primaryKey: true)]
    public int $id;

    #[Column(type: 'string', length: 255)]
    public string $email;
}