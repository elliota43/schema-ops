<?php

namespace SchemaOps\Example;

use SchemaOps\Attributes\Column;
use SchemaOps\Attributes\SoftDeletes;
use SchemaOps\Attributes\Table;
use SchemaOps\Attributes\Timestamps;
use SchemaOps\Attributes\Uuid;

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
