<?php

namespace Atlas\Schema\Generation;

use Atlas\Schema\Definition\ColumnDefinition;
use Atlas\Schema\Definition\TableDefinition;

final class SchemaAttributeGenerator
{
    public function generateTableAttribute(TableDefinition $table): string
    {
        return "#[Table(name: '{$table->tableName}')]";
    }

    /**
     * @return string[]
     */
    public function generateColumnAttributes(ColumnDefinition $column): array
    {
        $attributes = [];
        $attributes[] = $this->generateColumnAttribute($column);

        foreach ($column->foreignKeys as $foreignKey) {
            $attributes[] = $this->generateForeignKeyAttribute($foreignKey);
        }

        return $attributes;
    }

    public function generatePropertyDeclaration(ColumnDefinition $column): string
    {
        $phpType = $this->sqlTypeToPhpType($column->sqlType, $column->isNullable);

        return "public {$phpType} \${$column->name};";
    }

    protected function generateColumnAttribute(ColumnDefinition $column): string
    {
        $params = ["type: '{$this->extractBaseType($column->sqlType)}'"];

        if (preg_match('/\((\d+)\)/', $column->sqlType, $matches)) {
            $baseType = $this->extractBaseType($column->sqlType);
            if (! in_array($baseType, ['decimal'])) {
                $params[] = "length: {$matches[1]}";
            }
        }

        if (preg_match('/decimal\((\d+),\s*(\d+)\)/', $column->sqlType, $matches)) {
            $params[] = "precision: {$matches[1]}";
            $params[] = "scale: {$matches[2]}";
        }

        if ($column->isNullable) {
            $params[] = 'nullable: true';
        }

        if ($column->isAutoIncrement) {
            $params[] = 'autoIncrement: true';
        }

        if ($column->isPrimaryKey) {
            $params[] = 'primaryKey: true';
        }

        if ($column->defaultValue !== null) {
            $default = $this->formatDefaultValue($column->defaultValue);
            $params[] = "default: {$default}";
        }

        if (str_contains($column->sqlType, 'unsigned')) {
            $params[] = 'unsigned: true';
        }

        if ($column->isUnique) {
            $params[] = 'unique: true';
        }

        if ($column->onUpdate) {
            $params[] = "onUpdate: '{$column->onUpdate}'";
        }

        return '#[Column(' . implode(', ', $params) . ')]';
    }

    protected function generateForeignKeyAttribute(array $foreignKey): string
    {
        $params = ["references: '{$foreignKey['references']}'"];

        if (isset($foreignKey['onDelete'])) {
            $params[] = "onDelete: '{$foreignKey['onDelete']}'";
        }

        if (isset($foreignKey['onUpdate']) && $foreignKey['onUpdate']) {
            $params[] = "onUpdate: '{$foreignKey['onUpdate']}'";
        }

        return '#[ForeignKey(' . implode(', ', $params) . ')]';
    }

    protected function extractBaseType(string $sqlType): string
    {
        $type = preg_replace('/\(.*\)/', '', $sqlType);
        $type = str_replace(' unsigned', '', $type);

        return trim($type);
    }

    protected function sqlTypeToPhpType(string $sqlType, bool $nullable): string
    {
        $baseType = $this->extractBaseType($sqlType);

        $phpType = match (true) {
            str_starts_with($baseType, 'int') => 'int',
            str_starts_with($baseType, 'bigint') => 'int',
            str_starts_with($baseType, 'tinyint') => 'int',
            str_starts_with($baseType, 'smallint') => 'int',
            str_starts_with($baseType, 'mediumint') => 'int',
            $baseType === 'decimal' => 'string',
            $baseType === 'float' || $baseType === 'double' => 'float',
            $baseType === 'boolean' || $baseType === 'bool' => 'bool',
            default => 'string',
        };

        return $nullable ? "?{$phpType}" : $phpType;
    }

    protected function formatDefaultValue(mixed $value): string
    {
        if (is_string($value)) {
            if (in_array(strtoupper($value), ['CURRENT_TIMESTAMP', 'NULL'])) {
                return "'{$value}'";
            }

            return "'" . addslashes($value) . "'";
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return 'null';
    }
}
