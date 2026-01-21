<?php

namespace Atlas\Schema\Generation;

use Atlas\Schema\Definition\TableDefinition;
use Atlas\Schema\Definition\ColumnDefinition;

class SchemaClassGenerator
{
    public function __construct(
        private string $namespace
    ) {}

    public function generate(string $className, TableDefinition $table): string
    {
        $code = $this->generateFileHeader();
        $code .= $this->generateClassLevelAttributes($table);
        $code .= $this->generateClassDeclaration($className);
        $code .= $this->generateProperties($table);
        $code .= "}\n";

        return $code;
    }

    protected function generateFileHeader(): string
    {
        return "<?php\n\nnamespace {$this->namespace};\n\n" .
               $this->generateImports() . "\n\n";
    }

    protected function generateImports(): string
    {
        return <<<PHP
use Atlas\Attributes\Column;
use Atlas\Attributes\ForeignKey;
use Atlas\Attributes\Id;
use Atlas\Attributes\Index;
use Atlas\Attributes\PrimaryKey;
use Atlas\Attributes\SoftDeletes;
use Atlas\Attributes\Table;
use Atlas\Attributes\Timestamps;
use Atlas\Attributes\Uuid;
PHP;
    }

    protected function generateClassLevelAttributes(TableDefinition $table): string
    {
        $attributes = [];

        // #[Table] - always required
        $attributes[] = "#[Table(name: '{$table->tableName}')]";

        // Detect convenience attributes
        $patterns = $this->detectConveniencePatterns($table);

        if ($patterns['hasId']) {
            $attributes[] = "#[Id]";
        } elseif ($patterns['hasUuid']) {
            $attributes[] = "#[Uuid]";
        }

        if ($patterns['hasTimestamps']) {
            $attributes[] = "#[Timestamps]";
        }

        if ($patterns['hasSoftDeletes']) {
            $attributes[] = "#[SoftDeletes]";
        }

        // Composite primary key
        if ($table->compositePrimaryKey && count($table->compositePrimaryKey) > 1) {
            $cols = "'" . implode("', '", $table->compositePrimaryKey) . "'";
            $attributes[] = "#[PrimaryKey(columns: [{$cols}])]";
        }

        // Indexes
        foreach ($table->indexes as $index) {
            $cols = "'" . implode("', '", $index['columns']) . "'";
            $params = ["columns: [{$cols}]"];

            if ($index['unique']) {
                $params[] = 'unique: true';
            }
            if (isset($index['name'])) {
                $params[] = "name: '{$index['name']}'";
            }

            $attributes[] = "#[Index(" . implode(', ', $params) . ")]";
        }

        return implode("\n", $attributes) . "\n";
    }

    protected function detectConveniencePatterns(TableDefinition $table): array
    {
        $hasId = false;
        $hasUuid = false;
        $hasTimestamps = false;
        $hasSoftDeletes = false;

        // Detect #[Id] pattern: id column, bigint/int, auto-increment, primary key
        if (isset($table->columns['id'])) {
            $id = $table->columns['id'];
            if ($id->isAutoIncrement && $id->isPrimaryKey &&
                in_array($id->sqlType, ['bigint', 'int'])) {
                $hasId = true;
            }
        }

        // Detect #[Uuid] pattern: id column, char(36), primary key
        if (isset($table->columns['id']) && !$hasId) {
            $id = $table->columns['id'];
            if ($id->isPrimaryKey && $id->sqlType === 'char(36)') {
                $hasUuid = true;
            }
        }

        // Detect #[Timestamps]: created_at and updated_at both present
        if (isset($table->columns['created_at']) && isset($table->columns['updated_at'])) {
            $hasTimestamps = true;
        }

        // Detect #[SoftDeletes]: deleted_at present and nullable
        if (isset($table->columns['deleted_at']) &&
            $table->columns['deleted_at']->isNullable) {
            $hasSoftDeletes = true;
        }

        return compact('hasId', 'hasUuid', 'hasTimestamps', 'hasSoftDeletes');
    }

    protected function generateClassDeclaration(string $className): string
    {
        return "class {$className}\n{\n";
    }

    protected function generateProperties(TableDefinition $table): string
    {
        $properties = [];
        $patterns = $this->detectConveniencePatterns($table);

        // Skip columns handled by convenience attributes
        $skipColumns = [];
        if ($patterns['hasId'] || $patterns['hasUuid']) {
            $skipColumns[] = 'id';
        }
        if ($patterns['hasTimestamps']) {
            $skipColumns[] = 'created_at';
            $skipColumns[] = 'updated_at';
        }
        if ($patterns['hasSoftDeletes']) {
            $skipColumns[] = 'deleted_at';
        }

        foreach ($table->columns as $columnName => $column) {
            if (in_array($columnName, $skipColumns)) {
                continue;
            }

            $properties[] = $this->generateProperty($column);
        }

        return implode("\n", $properties);
    }

    protected function generateProperty(ColumnDefinition $column): string
    {
        $lines = [];

        // Generate #[Column] attribute
        $lines[] = '    ' . $this->generateColumnAttribute($column);

        // Generate #[ForeignKey] attributes
        foreach ($column->foreignKeys as $fk) {
            $lines[] = '    ' . $this->generateForeignKeyAttribute($fk);
        }

        // Generate property declaration
        $phpType = $this->sqlTypeToPhpType($column->sqlType, $column->isNullable);
        $lines[] = "    private {$phpType} \${$column->name};";
        $lines[] = "";  // blank line

        return implode("\n", $lines);
    }

    protected function generateColumnAttribute(ColumnDefinition $column): string
    {
        $params = ["type: '{$this->extractBaseType($column->sqlType)}'"];

        // Extract length from types like varchar(255)
        if (preg_match('/\((\d+)\)/', $column->sqlType, $matches)) {
            $baseType = $this->extractBaseType($column->sqlType);
            // Only add length for types that need it (not decimal)
            if (!in_array($baseType, ['decimal'])) {
                $params[] = "length: {$matches[1]}";
            }
        }

        // Extract precision/scale from decimal(10,2)
        if (preg_match('/decimal\((\d+),\s*(\d+)\)/', $column->sqlType, $matches)) {
            $params[] = "precision: {$matches[1]}";
            $params[] = "scale: {$matches[2]}";
        }

        // Nullable
        if ($column->isNullable) {
            $params[] = 'nullable: true';
        }

        if ($column->isAutoIncrement) {
            $params[] = 'autoIncrement: true';
        }

        if ($column->isPrimaryKey) {
            $params[] = 'primaryKey: true';
        }

        // Default value
        if ($column->defaultValue !== null) {
            $default = $this->formatDefaultValue($column->defaultValue);
            $params[] = "default: {$default}";
        }

        // Unsigned
        if (str_contains($column->sqlType, 'unsigned')) {
            $params[] = 'unsigned: true';
        }

        // Unique
        if ($column->isUnique) {
            $params[] = 'unique: true';
        }

        // On Update
        if ($column->onUpdate) {
            $params[] = "onUpdate: '{$column->onUpdate}'";
        }

        return '#[Column(' . implode(', ', $params) . ')]';
    }

    protected function generateForeignKeyAttribute(array $fk): string
    {
        $params = ["references: '{$fk['references']}'"];

        if (isset($fk['onDelete'])) {
            $params[] = "onDelete: '{$fk['onDelete']}'";
        }
        if (isset($fk['onUpdate']) && $fk['onUpdate']) {
            $params[] = "onUpdate: '{$fk['onUpdate']}'";
        }

        return '#[ForeignKey(' . implode(', ', $params) . ')]';
    }

    protected function extractBaseType(string $sqlType): string
    {
        // Extract base type from "varchar(255)" → "varchar"
        // Also handle "bigint unsigned" → "bigint"
        $type = preg_replace('/\(.*\)/', '', $sqlType);
        $type = str_replace(' unsigned', '', $type);
        return trim($type);
    }

    protected function sqlTypeToPhpType(string $sqlType, bool $nullable): string
    {
        $baseType = $this->extractBaseType($sqlType);

        $phpType = match(true) {
            str_starts_with($baseType, 'int') => 'int',
            str_starts_with($baseType, 'bigint') => 'int',
            str_starts_with($baseType, 'tinyint') => 'int',
            str_starts_with($baseType, 'smallint') => 'int',
            str_starts_with($baseType, 'mediumint') => 'int',
            $baseType === 'decimal' => 'string',  // avoid float precision issues
            $baseType === 'float' || $baseType === 'double' => 'float',
            $baseType === 'boolean' || $baseType === 'bool' => 'bool',
            default => 'string',
        };

        return $nullable ? "?{$phpType}" : $phpType;
    }

    protected function formatDefaultValue(mixed $value): string
    {
        if (is_string($value)) {
            // SQL keywords/functions
            if (in_array(strtoupper($value), ['CURRENT_TIMESTAMP', 'NULL'])) {
                return "'{$value}'";
            }
            // Regular string
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
