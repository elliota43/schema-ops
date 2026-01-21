<?php

declare(strict_types=1);

namespace Tests\Fixtures\Schemas;

use Atlas\Attributes\Column;
use Atlas\Attributes\Table;

#[Table('users')]
final class User
{
    #[Column(type: 'bigint', unsigned: true, autoIncrement: true, primaryKey: true)]
    public int $id;

    #[Column(type: 'varchar', length: 255, nullable: false, unique: true)]
    public string $email;

    #[Column(type: 'varchar', length: 255, nullable: false)]
    public string $name;

    #[Column(type: 'timestamp', nullable: true)]
    public ?string $created_at;

    #[Column(type: 'timestamp', nullable: true)]
    public ?string $updated_at;
}