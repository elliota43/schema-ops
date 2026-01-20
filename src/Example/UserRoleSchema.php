<?php

namespace SchemaOps\Example;

use SchemaOps\Attributes\Column;
use SchemaOps\Attributes\PrimaryKey;
use SchemaOps\Attributes\Table;
use SchemaOps\Attributes\Timestamps;

#[Table(name: 'user_roles')]
#[PrimaryKey(columns: ['user_id', 'role_id'])]
#[Timestamps]
class UserRoleSchema
{
    #[Column(type: 'bigint', unsigned: true)]
    public int $user_id;

    #[Column(type: 'bigint', unsigned: true)]
    public int $role_id;

    #[Column(type: 'timestamp', nullable: true)]
    public ?string $expires_at;
}
