<?php

namespace SchemaOps\Database;

use SchemaOps\Definition\TableDefinition;

interface DriverInterface
{
    /**
     * @return TableDefinition[] Keyed by table name
     */
    public function getCurrentSchema(): array;
}