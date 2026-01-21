<?php

namespace Atlas\Database\Drivers;

use Atlas\Database\MySqlTypeNormalizer;
use Atlas\Schema\Definition\ColumnDefinition;
use Atlas\Schema\Definition\TableDefinition;
use PDO;

class MySqlDriver implements DriverInterface
{

    /**
     * @var MySqlTypeNormalizer
     */
    protected MySqlTypeNormalizer $normalizer;
    public function __construct(private PDO $pdo)
    {
        $this->normalizer = new MySqlTypeNormalizer();
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
            WHERE table_schema = DATABASE()
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
                COLUMN_NAME,
                COLUMN_TYPE,
                IS_NULLABLE,
                COLUMN_KEY,
                COLUMN_DEFAULT,
                EXTRA
            FROM information_schema.columns
            WHERE table_name = :table
            AND table_schema = DATABASE()
        ");

        $stmt->execute(['table' => $tableName]);
        $rawColumns = $stmt->fetchall(PDO::FETCH_ASSOC);

        $definitions = [];

        foreach ($rawColumns as $row) {
            $name = $row['COLUMN_NAME'];

            $isNullable = $row['IS_NULLABLE'] === 'YES';

            $isAutoIncrement = str_contains($row['EXTRA'], 'auto_increment');

            $isPrimaryKey = $row['COLUMN_KEY'] === 'PRI';

            $definitions[$name] = new ColumnDefinition(
                name: $name,
                sqlType: $this->normalizer->normalize($row['COLUMN_TYPE']),
                isNullable: $isNullable,
                isAutoIncrement: $isAutoIncrement,
                isPrimaryKey: $isPrimaryKey,
                defaultValue: $row['COLUMN_DEFAULT'],
                onUpdate: $this->parseOnUpdate($row['EXTRA']),
                isUnique: $row['COLUMN_KEY'] === 'UNI',
                foreignKeys: []
            );
        }
        return $definitions;
    }

    /**
     * Gets foreign keys for a table, grouped by column name.
     *
     * @param string $tableName
     * @return array
     */
    private function getForeignKeys(string $tableName): array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                kcu.COLUMN_NAME,
                kcu.REFERENCED_TABLE_NAME,
                kcu.REFERENCED_COLUMN_NAME,
                rc.UPDATE_RULE,
                rc.DELETE_RULE
            FROM information_schema.KEY_COLUMN_USAGE kcu
            JOIN information_schema.REFERENTIAL_CONSTRAINTS rc
                ON kcu.CONSTRAINT_NAME = rc.CONSTRAINT_NAME
                AND kcu.TABLE_SCHEMA = rc.CONSTRAINT_SCHEMA
            WHERE kcu.TABLE_SCHEMA = DATABASE()
                AND kcu.TABLE_NAME = :table
                AND kcu.REFERENCED_TABLE_NAME IS NOT NULL
        ");

        $stmt->execute(['table' => $tableName]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $foreignKeys = [];
        foreach ($rows as $row) {
            $columnName = $row['COLUMN_NAME'];

            if (!isset($foreignKeys[$columnName])) {
                $foreignKeys[$columnName] = [];
            }

            $foreignKeys[$columnName][] = [
                'references' => $row['REFERENCED_TABLE_NAME'] . '.' . $row['REFERENCED_COLUMN_NAME'],
                'onUpdate' => $row['UPDATE_RULE'],
                'onDelete' => $row['DELETE_RULE']
            ];
        }

        return $foreignKeys;
    }

    /**
     * Gets indexes for a table.
     *
     * @param string $tableName
     * @return array
     */
    private function getIndexes(string $tableName): array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                INDEX_NAME,
                COLUMN_NAME,
                NON_UNIQUE,
                SEQ_IN_INDEX
            FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = :table
                AND INDEX_NAME != 'PRIMARY'
            ORDER BY INDEX_NAME, SEQ_IN_INDEX
        ");

        $stmt->execute(['table' => $tableName]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // group  by index
        $indexes = [];
        foreach ($rows as $row) {
            $indexName = $row['INDEX_NAME'];

            if (!isset($indexes[$indexName])) {
                $indexes[$indexName] = [
                    'name' => $indexName,
                    'columns' => [],
                    'unique' => (int)$row['NON_UNIQUE'] === 0
                ];
            }

            $indexes[$indexName]['columns'][] = $row['COLUMN_NAME'];
        }

        return array_values($indexes);
    }

    /**
     * Get primary key columns for a table.
     */
    private function getPrimaryKeys(string $tableName): array
    {
        $stmt = $this->pdo->prepare("
            SELECT COLUMN_NAME
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE table_schema = DATABASE()
            AND table_name = :table
            AND CONSTRAINT_NAME = 'PRIMARY'
            ORDER BY ORDINAL_POSITION
        ");

        $stmt->execute(['table' => $tableName]);

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Parses ON UPDATE clause from EXTRA column.
     *
     * @param string $extra
     * @return string|null
     */
    private function parseOnUpdate(string $extra): ?string
    {
        if (preg_match('/on update ([\w()]+)/i', $extra, $matches)) {
            return $matches[1];
        }

        return null;
    }
}