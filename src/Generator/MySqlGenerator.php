<?php

namespace SchemaOps\Generator;

use SchemaOps\Definition\ColumnDefinition;
use SchemaOps\Definition\TableDefinition;

class MySqlGenerator
{
    public function createTable(TableDefinition $table): string
    {
        $lines = [];

        foreach ($table->columns as $column) {
            $lines[] = $this->compileColumn($column);
        }

        $primaryKeys = $this->getPrimaryKeys($table);
        if (!empty($primaryKeys)) {
            $cols = implode('`, `', $primaryKeys);
            $lines[] = "PRIMARY KEY (`{$cols}`)";
        }

        $body = implode(",\n    ", $lines);

        return "CREATE TABLE `{$table->tableName}` (\n    {$body}\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    }

    public function compileColumn(ColumnDefinition $col): string
    {
        $parts = ["`{$col->name}`", strtoupper($col->sqlType)];

        if (!$col->isNullable) {
            $parts[] = 'NOT NULL';
        }

        if ($col->isAutoIncrement) {
            $parts[] = 'AUTO_INCREMENT';
        }

        if ($col->defaultValue !== null) {
            $default = is_string($col->defaultValue) ? "'{$col->defaultValue}'" : $col->defaultValue;
            $parts[] = "DEFAULT {$default}";
        }

        return implode(' ', $parts);
    }

    private function getPrimaryKeys(TableDefinition $table): array
    {
        $pks = [];
        foreach ($table->columns as $col) {
            if ($col->isPrimaryKey) {
                $pks[] = $col->name;
            }
        }

        return $pks;
    }
}