<?php

namespace Atlas\Database\Drivers;

use PDO;
use Atlas\Schema\Definition\ColumnDefinition;
use Atlas\Schema\Definition\TableDefinition;

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
            $primaryKeys = $this->getPrimaryKeys($tableName);
            
            $tables[$tableName] = new TableDefinition(
                tableName: $tableName,
                columns: $this->getColumns($tableName),
                compositePrimaryKey: count($primaryKeys) > 1 ? $primaryKeys : null
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
                onUpdate: null,
                isUnique: $row['COLUMN_KEY'] === 'UNI',
                foreignKeys: []
            );
        }
        return $definitions;
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
}