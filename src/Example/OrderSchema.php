<?php

namespace Atlas\Example;

use Atlas\Attributes\Column;
use Atlas\Attributes\Id;
use Atlas\Attributes\Table;
use Atlas\Attributes\Timestamps;

#[Table(name: 'orders')]
#[Id]
#[Timestamps]
class OrderSchema
{
    #[Column(type: 'bigint', unsigned: true)]
    public int $user_id;

    #[Column(type: 'varchar', length: 50, unique: true)]
    public string $order_number;

    #[Column(type: 'enum', values: ['pending', 'processing', 'completed', 'cancelled'], default: 'pending')]
    public string $status;

    #[Column(type: 'decimal', precision: 10, scale: 2)]
    public string $total_amount;

    #[Column(type: 'text', nullable: true)]
    public ?string $notes;
}
