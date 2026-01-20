<?php

namespace Atlas\Example;

use Atlas\Attributes\Column;
use Atlas\Attributes\SoftDeletes;
use Atlas\Attributes\Table;
use Atlas\Attributes\Timestamps;
use Atlas\Attributes\Uuid;

#[Table(name: 'products')]
#[Uuid]
#[Timestamps]
#[SoftDeletes]
class ProductSchema
{
    #[Column(type: 'varchar', length: 255, unique: true)]
    public string $slug;

    #[Column(type: 'varchar', length: 255)]
    public string $name;

    #[Column(type: 'text', nullable: true)]
    public ?string $description;

    #[Column(type: 'decimal', precision: 10, scale: 2)]
    public string $price;

    #[Column(type: 'integer', unsigned: true, default: 0)]
    public int $stock_quantity;

    #[Column(type: 'json', nullable: true)]
    public ?string $metadata;
}
