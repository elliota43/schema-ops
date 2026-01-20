<?php

namespace Atlas\Example;

use Atlas\Attributes\Column;
use Atlas\Attributes\PrimaryKey;
use Atlas\Attributes\Table;
use Atlas\Attributes\Timestamps;

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
