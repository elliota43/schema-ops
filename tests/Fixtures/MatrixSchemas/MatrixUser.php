<?php

declare(strict_types=1);

namespace Tests\Fixtures\MatrixSchemas;

use Atlas\Attributes\Column;
use Atlas\Attributes\Table;

#[Table('matrix_users')]
final class MatrixUser
{
    #[Column(type: 'integer', autoIncrement: true, primaryKey: true)]
    public int $id;

    #[Column(type: 'varchar', length: 255, nullable: false)]
    public string $email;

    #[Column(type: 'varchar', length: 100, nullable: false)]
    public string $name;

    #[Column(type: 'boolean', default: true)]
    public bool $is_active;
}
