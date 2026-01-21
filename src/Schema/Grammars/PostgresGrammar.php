<?php

namespace Atlas\Schema\Grammars;

use Atlas\Changes\TableChanges;
use Atlas\Schema\Definition\ColumnDefinition;
use Atlas\Schema\Definition\TableDefinition;

class PostgresGrammar implements GrammarInterface
{
    protected array $sqlKeywords = [
        'NULL',
        'TRUE',
        'FALSE',
        'CURRENT_TIMESTAMP',
        'CURRENT_DATE',
        'CURRENT_TIME',
        'NOW()',
    ];

    public function createTable(TableDefinition $table): string
    {
        $lines = [];

        foreach ($table->columns as $column) {
            $lines[] = $this->compileColumn($column);
        }

        $primaryKeys = $this->getPrimaryKeys($table);
        if (! empty($primaryKeys)) {
            $lines[] = $this->compilePrimaryKey($primaryKeys);
        }

        foreach ($this->compileUniqueConstraints($table) as $constraint) {
            $lines[] = $constraint;
        }

        foreach ($this->compileForeignKeys($table) as $foreignKey) {
            $lines[] = $foreignKey;
        }

        $body = implode(",\n    ", $lines);

        return "CREATE TABLE {$this->wrap($table->tableName)} (\n    {$body}\n);";
    }

    public function generateAlter(TableChanges $diff): array
    {
        $statements = [];

        foreach ($diff->removedColumns as $colName) {
            $statements[] = $this->generateDropColumn($diff->tableName, $colName) . ';';
        }

        foreach ($diff->addedColumns as $column) {
            $statements[] = $this->generateAddColumn($diff->tableName, $column) . ';';
        }

        foreach ($diff->modifiedColumns as $column) {
            $statements[] = $this->generateModifyColumn($diff->tableName, $column) . ';';
        }

        return $statements;
    }

    /**
     * Generate SQL to add a single column to a table.
     */
    public function generateAddColumn(string $tableName, ColumnDefinition $column): string
    {
        $definition = $this->compileColumn($column);

        return "ALTER TABLE {$this->wrap($tableName)} ADD COLUMN {$definition}";
    }

    /**
     * Generate SQL to modify a single column in a table.
     */
    public function generateModifyColumn(string $tableName, ColumnDefinition $column): string
    {
        $type = $this->formatType($column);

        return "ALTER TABLE {$this->wrap($tableName)} ALTER COLUMN {$this->wrap($column->name())} TYPE {$type}";
    }

    /**
     * Generate SQL to drop a single column from a table.
     */
    public function generateDropColumn(string $tableName, string $columnName): string
    {
        return "ALTER TABLE {$this->wrap($tableName)} DROP COLUMN {$this->wrap($columnName)}";
    }

    /**
     * Generate preview query to show sample data from a column.
     */
    public function generatePreviewQuery(string $tableName, string $columnName, int $limit = 10): string
    {
        return "SELECT {$this->wrap($columnName)} FROM {$this->wrap($tableName)} WHERE {$this->wrap($columnName)} IS NOT NULL LIMIT {$limit}";
    }

    /**
     * Generate SQL to drop a table.
     */
    public function dropTable(string $tableName): string
    {
        return "DROP TABLE {$this->wrap($tableName)}";
    }

    protected function compileColumn(ColumnDefinition $column): string
    {
        $type = $this->formatType($column);
        $parts = [$this->wrap($column->name()), $type];

        if (! $column->isNullable()) {
            $parts[] = 'NOT NULL';
        }

        if ($column->defaultValue() !== null && ! $column->isAutoIncrement()) {
            $default = $this->compileDefaultValue($column->defaultValue());
            $parts[] = "DEFAULT {$default}";
        }

        return implode(' ', $parts);
    }

    protected function formatType(ColumnDefinition $column): string
    {
        if (! $column->isAutoIncrement()) {
            return strtoupper($column->sqlType());
        }

        return $this->autoIncrementType($column->sqlType());
    }

    protected function autoIncrementType(string $type): string
    {
        $normalized = strtolower(trim($type));

        return match ($normalized) {
            'smallint' => 'SMALLSERIAL',
            'bigint' => 'BIGSERIAL',
            default => 'SERIAL',
        };
    }

    protected function compilePrimaryKey(array $columns): string
    {
        $cols = implode(', ', array_map([$this, 'wrap'], $columns));

        return "PRIMARY KEY ({$cols})";
    }

    protected function getPrimaryKeys(TableDefinition $table): array
    {
        if ($table->compositePrimaryKey !== null) {
            return $table->compositePrimaryKey;
        }

        $primaryKeys = [];
        foreach ($table->columns as $column) {
            if ($column->isPrimaryKey()) {
                $primaryKeys[] = $column->name();
            }
        }

        return $primaryKeys;
    }

    /**
     * Compile unique constraints from columns.
     */
    protected function compileUniqueConstraints(TableDefinition $table): array
    {
        $constraints = [];

        foreach ($table->columns as $column) {
            if ($column->isUnique() && ! $column->isPrimaryKey()) {
                $constraints[] = "UNIQUE ({$this->wrap($column->name())})";
            }
        }

        return $constraints;
    }

    /**
     * Compile foreign key constraints.
     */
    protected function compileForeignKeys(TableDefinition $table): array
    {
        $constraints = [];

        foreach ($table->columns as $column) {
            foreach ($column->foreignKeys() as $fk) {
                $name = $fk['name'] ?? $this->generateForeignKeyName($table->tableName, $column->name());
                [$refTable, $refColumn] = $this->parseReference($fk['references']);

                $constraint = "CONSTRAINT {$this->wrap($name)} FOREIGN KEY ({$this->wrap($column->name())}) REFERENCES {$this->wrap($refTable)} ({$this->wrap($refColumn)})";

                if ($fk['onDelete']) {
                    $constraint .= " ON DELETE {$fk['onDelete']}";
                }

                if ($fk['onUpdate']) {
                    $constraint .= " ON UPDATE {$fk['onUpdate']}";
                }

                $constraints[] = $constraint;
            }
        }

        return $constraints;
    }

    /**
     * Generate automatic foreign key constraint name.
     */
    protected function generateForeignKeyName(string $table, string $column): string
    {
        return "{$table}_{$column}_foreign";
    }

    /**
     * Parse foreign key reference string.
     */
    protected function parseReference(string $reference): array
    {
        if (str_contains($reference, '.')) {
            return explode('.', $reference, 2);
        }

        throw new \InvalidArgumentException("Foreign key reference must be in format 'table.column'");
    }

    /**
     * Compile default values for PostgreSQL.
     */
    protected function compileDefaultValue(mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        if (is_bool($value)) {
            return $value ? 'TRUE' : 'FALSE';
        }

        if (is_string($value)) {
            $upper = strtoupper(trim($value));

            if (in_array($upper, $this->sqlKeywords, true)) {
                return $upper;
            }

            if (str_contains($value, '(')) {
                return $value;
            }

            $escaped = str_replace("'", "''", $value);
            return "'{$escaped}'";
        }

        return (string) $value;
    }

    protected function wrap(string $identifier): string
    {
        $escaped = str_replace('"', '""', $identifier);

        return "\"{$escaped}\"";
    }
}
