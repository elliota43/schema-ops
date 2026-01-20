<?php

namespace SchemaOps\Diff;

use SchemaOps\Definition\ColumnDefinition;

class TableDiff
{
    /** @var ColumnDefinition[] */
    public array $addedColumns = [];

    /** @var string[] */
    public array $removedColumns = [];

    /** @var ColumnDefinition[] */
    public array $modifiedColumns = [];

    public function __construct(public string $tableName) {}

    public function isEmpty(): bool
    {
        return empty($this->addedColumns)
            && empty($this->removedColumns)
            && empty($this->modifiedColumns);
    }
}