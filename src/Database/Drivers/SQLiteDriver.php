<?php

namespace Atlas\Database\Drivers;

use Atlas\Database\Normalizers\SQLiteTypeNormalizer;
use Atlas\Schema\Definition\ColumnDefinition;
use Atlas\Schema\Definition\TableDefinition;
use PDO;

class SQLiteDriver implements DriverInterface
{
    /**
     * @var SQLiteTypeNormalizer
     */
    protected SQLiteTypeNormalizer $normalizer;

    /**
     * Create a driver instance for the given PDO connection.
     */
    public function __construct(private PDO $pdo)
    {
        $this->normalizer = new SQLiteTypeNormalizer();

        $this->enableForeignKeys();
    }

    /**
     * Gets the current database connection's schema.
     *
     * @return array|TableDefinition[]
     */
    public function getCurrentSchema(): array
    {
        $tables = [];
        $tableNames = $this->getTableNames();

        foreach ($tableNames as $tableName) {
            $tables[$tableName] = $this->buildTableDefinition($tableName);
        }

        return $tables;
    }

    /**
     * Enable foreign key enforcement for the connection.
     */
    protected function enableForeignKeys(): void
    {
        $this->pdo->exec('PRAGMA foreign_keys = ON');
    }

    /**
     * Get all user-defined table names in the database.
     *
     * @return string[]
     */
    protected function getTableNames(): array
    {
        $stmt = $this->pdo->query("
            SELECT name
            FROM sqlite_master
            WHERE type = 'table'
            AND name NOT LIKE 'sqlite_%'
            ORDER BY name
        ");

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Build a table definition from SQLite metadata.
     */
    protected function buildTableDefinition(string $tableName): TableDefinition
    {
        $columns = $this->getColumns($tableName);
        $foreignKeysByColumn = $this->getForeignKeys($tableName);
        $indexes = $this->getIndexes($tableName);
        $primaryKeys = $this->getPrimaryKeys($tableName);

        $columns = $this->applyForeignKeysToColumns($columns, $foreignKeysByColumn);

        return new TableDefinition(
            tableName: $tableName,
            columns: $columns,
            compositePrimaryKey: count($primaryKeys) > 1 ? $primaryKeys : null,
            indexes: $indexes
        );
    }

    /**
     * Merge foreign keys into column definitions.
     *
     * @param array<string, ColumnDefinition> $columns
     * @param array<string, array<int, array<string, string>>> $foreignKeysByColumn
     * @return array<string, ColumnDefinition>
     */
    protected function applyForeignKeysToColumns(array $columns, array $foreignKeysByColumn): array
    {
        foreach ($foreignKeysByColumn as $columnName => $foreignKeys) {
            if (! isset($columns[$columnName])) {
                continue;
            }

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
                foreignKeys: $foreignKeys
            );
        }

        return $columns;
    }

    /**
     * Get all column definitions for a table.
     *
     * @return array<string, ColumnDefinition>
     */
    protected function getColumns(string $tableName): array
    {
        $rawColumns = $this->getTableInfo($tableName);
        $uniqueColumns = $this->getUniqueColumns($tableName);

        $definitions = [];

        foreach ($rawColumns as $row) {
            $column = $this->buildColumnDefinition($tableName, $row, $uniqueColumns);
            $definitions[$column->name()] = $column;
        }

        return $definitions;
    }

    /**
     * Check if a column is auto-increment
     *
     * In SQLite:
     * - INTEGER PRIMARY KEY automatically gets ROWID alias (auto-increment behavior)
     * - Can explicitly use AUTOINCREMENT keyword
     */
    protected function isAutoIncrement(string $tableName, string $columnName, string $type, bool $isPrimaryKey): bool
    {
        if (! $isPrimaryKey) {
            return false;
        }

        if (! $this->isIntegerType($type)) {
            return false;
        }

        $createSql = $this->getCreateTableSql($tableName);

        if (! $createSql) {
            return false;
        }

        if ($this->columnUsesAutoIncrement($columnName, $createSql)) {
            return true;
        }

        return ! $this->isWithoutRowIdTable($createSql);
    }

    /**
     * Build a column definition from raw SQLite metadata.
     *
     * @param array<string, mixed> $row
     * @param string[] $uniqueColumns
     */
    protected function buildColumnDefinition(string $tableName, array $row, array $uniqueColumns): ColumnDefinition
    {
        $name = $row['name'];
        $type = $row['type'];

        $isPrimaryKey = (int) $row['pk'] > 0;
        $isNullable = $this->resolveNullability($row, $isPrimaryKey);
        $isAutoIncrement = $this->isAutoIncrement($tableName, $name, $type, $isPrimaryKey);
        $isUnique = in_array($name, $uniqueColumns, true);

        return new ColumnDefinition(
            name: $name,
            sqlType: $this->normalizer->normalize($type),
            isNullable: $isNullable,
            isAutoIncrement: $isAutoIncrement,
            isPrimaryKey: $isPrimaryKey,
            defaultValue: $this->parseDefaultValue($row['dflt_value']),
            onUpdate: null,
            isUnique: $isUnique,
            foreignKeys: []
        );
    }

    /**
     * Resolve nullability for SQLite columns, accounting for primary keys.
     *
     * @param array<string, mixed> $row
     */
    protected function resolveNullability(array $row, bool $isPrimaryKey): bool
    {
        if ($isPrimaryKey) {
            return false;
        }

        return (int) $row['notnull'] === 0;
    }

    /**
     * Determine if a SQLite type should be treated as integer.
     */
    protected function isIntegerType(string $type): bool
    {
        $normalizedType = strtolower(trim($type));

        return str_contains($normalizedType, 'int');
    }

    /**
     * Fetch the CREATE TABLE SQL for a table.
     */
    protected function getCreateTableSql(string $tableName): ?string
    {
        $stmt = $this->pdo->prepare("
            SELECT sql
            FROM sqlite_master
            WHERE type = 'table' AND name = :table
        ");
        $stmt->execute(['table' => $tableName]);

        return $stmt->fetchColumn() ?: null;
    }

    /**
     * Check if the column explicitly declares AUTOINCREMENT.
     */
    protected function columnUsesAutoIncrement(string $columnName, string $createSql): bool
    {
        $escaped = preg_quote($columnName, '/');
        $pattern = "/\\b{$escaped}\\b[^,\\)]*\\bAUTOINCREMENT\\b/i";

        return (bool) preg_match($pattern, $createSql);
    }

    /**
     * Determine whether the table is defined WITHOUT ROWID.
     */
    protected function isWithoutRowIdTable(string $createSql): bool
    {
        return (bool) preg_match('/\bWITHOUT\s+ROWID\b/i', $createSql);
    }

    /**
     * Parse SQLite default value
     */
    protected function parseDefaultValue(?string $default): ?string
    {
        if ($default === null || $default === 'NULL') {
            return null;
        }

        // Remove quotes from string literals
        if (preg_match("/^'(.+)'$/", $default, $matches)) {
            return $matches[1];
        }

        if (preg_match('/^"(.+)"$/', $default, $matches)) {
            return $matches[1];
        }

        return $default;
    }

    /**
     * Gets foreign keys for a table, grouped by column name.
     */
    protected function getForeignKeys(string $tableName): array
    {
        $rows = $this->getForeignKeyList($tableName);

        $foreignKeys = [];
        foreach ($rows as $row) {
            $columnName = $row['from'];

            if (! isset($foreignKeys[$columnName])) {
                $foreignKeys[$columnName] = [];
            }

            $foreignKeys[$columnName][] = [
                'references' => "{$row['table']}.{$row['to']}",
                'onUpdate' => $row['on_update'],
                'onDelete' => $row['on_delete']
            ];
        }

        return $foreignKeys;
    }

    /**
     * Gets indexes for a table.
     */
    protected function getIndexes(string $tableName): array
    {
        $indexList = $this->getIndexList($tableName);

        $indexes = [];
        foreach ($indexList as $indexRow) {
            if ($indexRow['origin'] !== 'c') {
                continue;
            }

            $indexName = $indexRow['name'];
            $isUnique = (int) $indexRow['unique'] === 1;
            $columns = $this->getIndexColumns($indexName);

            $indexes[] = [
                'name' => $indexName,
                'columns' => $columns,
                'unique' => $isUnique
            ];
        }

        return $indexes;
    }

    /**
     * Get primary key columns for a table.
     */
    protected function getPrimaryKeys(string $tableName): array
    {
        $columns = $this->getTableInfo($tableName);

        $primaryKeys = [];
        foreach ($columns as $column) {
            if ((int) $column['pk'] > 0) {
                $primaryKeys[(int) $column['pk']] = $column['name'];
            }
        }

        ksort($primaryKeys);

        return array_values($primaryKeys);
    }

    /**
     * Get unique constraint columns (excluding primary keys)
     */
    protected function getUniqueColumns(string $tableName): array
    {
        $indexList = $this->getIndexList($tableName);

        $uniqueColumns = [];
        foreach ($indexList as $indexRow) {
            if ((int) $indexRow['unique'] !== 1) {
                continue;
            }

            if ($indexRow['origin'] === 'u' || $indexRow['origin'] === 'pk') {
                $columns = $this->getIndexInfo($indexRow['name']);

                $column = $this->getSingleColumnUnique($columns);
                if ($column) {
                    $uniqueColumns[] = $column;
                }
            }
        }

        return $uniqueColumns;
    }

    /**
     * Get PRAGMA table info rows for the given table.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function getTableInfo(string $tableName): array
    {
        $stmt = $this->pdo->query("PRAGMA table_info({$this->quoteIdentifier($tableName)})");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get PRAGMA foreign_key_list rows for the given table.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function getForeignKeyList(string $tableName): array
    {
        $stmt = $this->pdo->query("PRAGMA foreign_key_list({$this->quoteIdentifier($tableName)})");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get PRAGMA index_list rows for the given table.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function getIndexList(string $tableName): array
    {
        $stmt = $this->pdo->query("PRAGMA index_list({$this->quoteIdentifier($tableName)})");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get PRAGMA index_info rows for the given index.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function getIndexInfo(string $indexName): array
    {
        $stmt = $this->pdo->query("PRAGMA index_info({$this->quoteIdentifier($indexName)})");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get columns for the given index.
     *
     * @return string[]
     */
    protected function getIndexColumns(string $indexName): array
    {
        $columns = [];
        foreach ($this->getIndexInfo($indexName) as $colRow) {
            $columns[] = $colRow['name'];
        }

        return $columns;
    }

    /**
     * Extract the column name if a unique index is single-column.
     *
     * @param array<int, array<string, mixed>> $columns
     */
    protected function getSingleColumnUnique(array $columns): ?string
    {
        if (count($columns) !== 1) {
            return null;
        }

        return $columns[0]['name'];
    }

    /**
     * Quote an identifier for PRAGMA statements.
     */
    protected function quoteIdentifier(string $identifier): string
    {
        return $this->pdo->quote($identifier);
    }
}
