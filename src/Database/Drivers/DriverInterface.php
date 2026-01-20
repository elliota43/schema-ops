<?php

namespace Atlas\Database\Drivers;

use Atlas\Schema\Definition\TableDefinition;

interface DriverInterface
{
    /**
     * @return TableDefinition[] Keyed by table name
     */
    public function getCurrentSchema(): array;
}