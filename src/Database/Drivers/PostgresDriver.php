<?php

namespace Atlas\Database\Drivers;

use Atlas\Database\Normalizers\PostgresTypeNormalizer;
use Atlas\Schema\Definition\ColumnDefinition;
use Atlas\Schema\Definition\TableDefinition;
use PDO;

class PostgresDriver implements DriverInterface
{
    /**
     * @var PostgresTypeNormalizer
     */
    protected PostgresTypeNormalizer $normalizer;

    public function __construct(private PDO $pdo)
    {
        $this->normalizer = new PostgresTypeNormalizer();
    }

    /**
     * Gets the current database connection's schema.
     *
     * @return array|TableDefinition[]
     */
    public function getCurrentSchema(): array
    {
        $tables = [];

        $stmt = $this->pdo->query("
            SELECT table_name
            FROM information_schema.tables
            WHERE table_schema = 'public'
            AND table_type = 'BASE TABLE'
        ");

        $tableNames = $stmt->fetchAll(PDO::FETCH_COLUMN);

        foreach ($tableNames as $tableName) {
            $columns = $this->getColumns($tableName);
            $foreignKeysByColumn = $this->getForeignKeys($tableName);
            $indexes = $this->getIndexes($tableName);
            $primaryKeys = $this->getPrimaryKeys($tableName);

            foreach ($foreignKeysByColumn as $columnName => $fk) {
                if (isset($columns[$columnName])) {
                    $old = $columns[$columnName];
                    $columns[$columnName] = new ColumnDefinition(
                        name: $old->name,
                        sqlType: $old->sqlType,
                        isNullable: $old->isNullable,
                        isPrimaryKey: $old->isPrimaryKey,
                        isAutoIncrement: $old->isAutoIncrement,
                        defaultValue: $old->defaultValue,
                        onUpdate: $old->onUpdate,
                        isUnique: $old->isUnique,
                        foreignKeys: $fk
                    );
                }
            }

            $tables[$tableName] = new TableDefinition(
                tableName: $tableName,
                columns: $columns,
                compositePrimaryKey: count($primaryKeys) > 1 ? $primaryKeys : null,
                indexes: $indexes
            );
        }

        return $tables;
    }

    private function getColumns(string $tableName): array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                c.column_name,
                c.data_type,
                c.character_maximum_length,
                c.numeric_precision,
                c.numeric_scale,
                c.is_nullable,
                c.column_default,
                c.udt_name
            FROM information_schema.columns c
            WHERE c.table_name = :table
            AND c.table_schema = 'public'
            ORDER BY c.ordinal_position
        ");

        $stmt->execute(['table' => $tableName]);
        $rawColumns = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get primary keys for this table
        $primaryKeys = $this->getPrimaryKeys($tableName);

        // Get unique constraints
        $uniqueColumns = $this->getUniqueColumns($tableName);

        // Get auto-increment columns (serial/identity)
        $autoIncrementColumns = $this->getAutoIncrementColumns($tableName);

        $definitions = [];

        foreach ($rawColumns as $row) {
            $name = $row['column_name'];

            $isNullable = $row['is_nullable'] === 'YES';
            $isPrimaryKey = in_array($name, $primaryKeys);
            $isAutoIncrement = in_array($name, $autoIncrementColumns);
            $isUnique = in_array($name, $uniqueColumns);

            // Build the full type string
            $sqlType = $this->buildTypeString($row);

            $definitions[$name] = new ColumnDefinition(
                name: $name,
                sqlType: $this->normalizer->normalize($sqlType),
                isNullable: $isNullable,
                isAutoIncrement: $isAutoIncrement,
                isPrimaryKey: $isPrimaryKey,
                defaultValue: $this->parseDefaultValue($row['column_default']),
                onUpdate: null, // PostgreSQL doesn't have ON UPDATE
                isUnique: $isUnique,
                foreignKeys: []
            );
        }

        return $definitions;
    }

    /**
     * Build the complete type string from column information
     */
    private function buildTypeString(array $row): string
    {
        $dataType = $row['data_type'];

        // Handle character types
        if (in_array($dataType, ['character varying', 'varchar', 'character', 'char'])) {
            if ($row['character_maximum_length']) {
                return "{$dataType}({$row['character_maximum_length']})";
            }
            return $dataType;
        }

        // Handle numeric types with precision/scale
        if (in_array($dataType, ['numeric', 'decimal'])) {
            if ($row['numeric_precision'] && $row['numeric_scale'] !== null) {
                return "{$dataType}({$row['numeric_precision']},{$row['numeric_scale']})";
            }
            return $dataType;
        }

        // Use udt_name for user-defined types and base types
        return $row['udt_name'] ?? $dataType;
    }

    /**
     * Parse PostgreSQL default value
     */
    private function parseDefaultValue(?string $default): ?string
    {
        if ($default === null) {
            return null;
        }

        // Remove PostgreSQL's type casting (e.g., '1'::integer -> 1)
        $default = preg_replace("/::[a-zA-Z ]+$/", '', $default);

        // Remove quotes from string literals
        if (preg_match("/^'(.+)'$/", $default, $matches)) {
            return $matches[1];
        }

        // Handle special values
        if (strtoupper($default) === 'NULL') {
            return null;
        }

        return $default;
    }

    /**
     * Gets foreign keys for a table, grouped by column name.
     */
    private function getForeignKeys(string $tableName): array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                kcu.column_name,
                ccu.table_name AS referenced_table,
                ccu.column_name AS referenced_column,
                rc.update_rule,
                rc.delete_rule
            FROM information_schema.table_constraints tc
            JOIN information_schema.key_column_usage kcu
                ON tc.constraint_name = kcu.constraint_name
                AND tc.table_schema = kcu.table_schema
            JOIN information_schema.constraint_column_usage ccu
                ON ccu.constraint_name = tc.constraint_name
                AND ccu.table_schema = tc.table_schema
            JOIN information_schema.referential_constraints rc
                ON rc.constraint_name = tc.constraint_name
                AND rc.constraint_schema = tc.table_schema
            WHERE tc.constraint_type = 'FOREIGN KEY'
                AND tc.table_schema = 'public'
                AND tc.table_name = :table
        ");

        $stmt->execute(['table' => $tableName]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $foreignKeys = [];
        foreach ($rows as $row) {
            $columnName = $row['column_name'];

            if (!isset($foreignKeys[$columnName])) {
                $foreignKeys[$columnName] = [];
            }

            $foreignKeys[$columnName][] = [
                'references' => $row['referenced_table'] . '.' . $row['referenced_column'],
                'onUpdate' => $row['update_rule'],
                'onDelete' => $row['delete_rule']
            ];
        }

        return $foreignKeys;
    }

    /**
     * Gets indexes for a table.
     */
    private function getIndexes(string $tableName): array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                i.relname AS index_name,
                a.attname AS column_name,
                ix.indisunique AS is_unique,
                a.attnum AS column_position
            FROM pg_class t
            JOIN pg_index ix ON t.oid = ix.indrelid
            JOIN pg_class i ON i.oid = ix.indexrelid
            JOIN pg_attribute a ON a.attrelid = t.oid AND a.attnum = ANY(ix.indkey)
            WHERE t.relkind = 'r'
                AND t.relnamespace = (SELECT oid FROM pg_namespace WHERE nspname = 'public')
                AND t.relname = :table
                AND NOT ix.indisprimary
            ORDER BY i.relname, a.attnum
        ");

        $stmt->execute(['table' => $tableName]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Group by index
        $indexes = [];
        foreach ($rows as $row) {
            $indexName = $row['index_name'];

            if (!isset($indexes[$indexName])) {
                $indexes[$indexName] = [
                    'name' => $indexName,
                    'columns' => [],
                    'unique' => (bool)$row['is_unique']
                ];
            }

            $indexes[$indexName]['columns'][] = $row['column_name'];
        }

        return array_values($indexes);
    }

    /**
     * Get primary key columns for a table.
     */
    private function getPrimaryKeys(string $tableName): array
    {
        $stmt = $this->pdo->prepare("
            SELECT a.attname AS column_name
            FROM pg_index i
            JOIN pg_attribute a ON a.attrelid = i.indrelid AND a.attnum = ANY(i.indkey)
            WHERE i.indrelid = :table::regclass
                AND i.indisprimary
            ORDER BY a.attnum
        ");

        $stmt->execute(['table' => $tableName]);

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Get unique constraint columns (excluding primary keys)
     */
    private function getUniqueColumns(string $tableName): array
    {
        $stmt = $this->pdo->prepare("
            SELECT kcu.column_name
            FROM information_schema.table_constraints tc
            JOIN information_schema.key_column_usage kcu
                ON tc.constraint_name = kcu.constraint_name
                AND tc.table_schema = kcu.table_schema
            WHERE tc.constraint_type = 'UNIQUE'
                AND tc.table_schema = 'public'
                AND tc.table_name = :table
        ");

        $stmt->execute(['table' => $tableName]);

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Get auto-increment columns (serial types or identity columns)
     */
    private function getAutoIncrementColumns(string $tableName): array
    {
        $stmt = $this->pdo->prepare("
            SELECT column_name
            FROM information_schema.columns
            WHERE table_schema = 'public'
                AND table_name = :table
                AND (
                    column_default LIKE 'nextval(%'
                    OR is_identity = 'YES'
                )
        ");

        $stmt->execute(['table' => $tableName]);

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}
