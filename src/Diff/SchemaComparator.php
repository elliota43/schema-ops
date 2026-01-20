<?php

namespace SchemaOps\Diff;

use SchemaOps\Definition\TableDefinition;
use SchemaOps\Definition\ColumnDefinition;

class SchemaComparator
{
    public function compare(TableDefinition $current, TableDefinition $desired): TableDiff
    {
        $diff = new TableDiff($desired->tableName);

        // added columns
        foreach ($desired->columns as $colName => $desiredCol ) {
            if (!isset($current->columns[$colName])) {
                $diff->addedColumns[] = $desiredCol;
            } else {
                if ($this->hasChanged($current->columns[$colName], $desiredCol)) {
                    $diff->modifiedColumns[] = $desiredCol;
                }
            }
        }

        // removed columns
        foreach ($current->columns as $colName => $currentCol) {
            if (! isset($desired->columns[$colName])) {
                $diff->removedColumns[] = $colName;
            }
        }

        return $diff;
    }

    private function hasChanged(ColumnDefinition $a, ColumnDefinition $b): bool
    {
        return $a->sqlType() !== $b->sqlType()
            || $a->isNullable() !== $b->isNullable()
            || $a->isAutoIncrement() !== $b->isAutoIncrement()
            || $a->defaultValue() !== $b->defaultValue();
    }
}