<?php

namespace Atlas\Example;

use Atlas\Attributes\Column;
use Atlas\Attributes\ForeignKey;
use Atlas\Attributes\Id;
use Atlas\Attributes\Index;
use Atlas\Attributes\SoftDeletes;
use Atlas\Attributes\Table;
use Atlas\Attributes\Timestamps;

#[Table(name: 'posts')]
#[Id]
#[Timestamps]
#[SoftDeletes]
#[Index(columns: ['user_id', 'created_at'])]
#[Index(columns: ['slug'], unique: true)]
#[Index(columns: ['status', 'published_at'])]
class PostSchema
{
    #[Column(type: 'bigint', unsigned: true)]
    #[ForeignKey(references: 'users.id', onDelete: 'CASCADE')]
    public int $user_id;

    #[Column(type: 'varchar', length: 255, unique: true)]
    public string $slug;

    #[Column(type: 'varchar', length: 255)]
    public string $title;

    #[Column(type: 'text')]
    public string $content;

    #[Column(type: 'enum', values: ['draft', 'published', 'archived'], default: 'draft')]
    public string $status;

    #[Column(type: 'timestamp', nullable: true)]
    public ?string $published_at;

    #[Column(type: 'integer', unsigned: true, default: 0)]
    public int $view_count;
}
