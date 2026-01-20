<?php

namespace Atlas\Example;

use Atlas\Attributes\Column;
use Atlas\Attributes\PrimaryKey;
use Atlas\Attributes\Table;
use Atlas\Attributes\Timestamps;

#[Table(name: 'order_items')]
#[PrimaryKey(columns: ['order_id', 'product_id'])]
#[Timestamps]
class OrderItemSchema
{
    #[Column(type: 'bigint', unsigned: true)]
    public int $order_id;

    #[Column(type: 'char', length: 36)]
    public string $product_id;

    #[Column(type: 'integer', unsigned: true)]
    public int $quantity;

    #[Column(type: 'decimal', precision: 10, scale: 2)]
    public string $unit_price;

    #[Column(type: 'decimal', precision: 10, scale: 2)]
    public string $subtotal;
}
