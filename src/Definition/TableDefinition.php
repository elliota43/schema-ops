<?php

namespace SchemaOps\Definition;

class TableDefinition {

    /**
     * @param string $tableName
     * @param array $columns = []
     */
    public function __construct(
        public string $tableName,
        public array $columns = []
    ) {}

    public function addColumn(ColumnDefinition $column): void
    {
        $this->columns[$column->name] = $column;
    }
}