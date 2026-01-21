<?php

declare(strict_types=1);

namespace Tests\Helpers;

use Atlas\Schema\Definition\ColumnDefinition;
use Atlas\Schema\Definition\TableDefinition;

/**
 * Factory class for creating test fixtures.
 * 
 * Follows Laravel's factory pattern for clean, reusable test data.
 */
final class SchemaFactory
{
    /**
     * Create a basic table definition.
     */
    public static function table(string $name = 'users'): TableDefinition
    {
        return new TableDefinition($name);
    }

    /**
     * Create a table with an ID column.
     */
    public static function tableWithId(string $name = 'users'): TableDefinition
    {
        $table = new TableDefinition($name);
        $table->addColumn(static::idColumn());
        
        return $table;
    }

    /**
     * Create a standard ID column.
     */
    public static function idColumn(string $name = 'id'): ColumnDefinition
    {
        return new ColumnDefinition(
            name: $name,
            sqlType: 'bigint unsigned',
            isNullable: false,
            isAutoIncrement: true,
            isPrimaryKey: true,
            defaultValue: null
        );
    }

    /**
     * Create a standard varchar column.
     */
    public static function varcharColumn(
        string $name,
        int $length = 255,
        bool $nullable = false,
        ?string $default = null
    ): ColumnDefinition {
        return new ColumnDefinition(
            name: $name,
            sqlType: "varchar({$length})",
            isNullable: $nullable,
            isAutoIncrement: false,
            isPrimaryKey: false,
            defaultValue: $default
        );
    }

    /**
     * Create an integer column.
     */
    public static function intColumn(
        string $name,
        bool $unsigned = false,
        bool $nullable = false,
        ?int $default = null
    ): ColumnDefinition {
        $type = $unsigned ? 'int unsigned' : 'int';
        
        return new ColumnDefinition(
            name: $name,
            sqlType: $type,
            isNullable: $nullable,
            isAutoIncrement: false,
            isPrimaryKey: false,
            defaultValue: $default
        );
    }

    /**
     * Create a timestamp column.
     */
    public static function timestampColumn(
        string $name,
        bool $nullable = true,
        ?string $onUpdate = null
    ): ColumnDefinition {
        return new ColumnDefinition(
            name: $name,
            sqlType: 'timestamp',
            isNullable: $nullable,
            isAutoIncrement: false,
            isPrimaryKey: false,
            defaultValue: null,
            onUpdate: $onUpdate
        );
    }

    /**
     * Create a text column.
     */
    public static function textColumn(string $name, bool $nullable = true): ColumnDefinition
    {
        return new ColumnDefinition(
            name: $name,
            sqlType: 'text',
            isNullable: $nullable,
            isAutoIncrement: false,
            isPrimaryKey: false,
            defaultValue: null
        );
    }

    /**
     * Create a decimal column.
     */
    public static function decimalColumn(
        string $name,
        int $precision = 10,
        int $scale = 2,
        bool $nullable = false
    ): ColumnDefinition {
        return new ColumnDefinition(
            name: $name,
            sqlType: "decimal({$precision}, {$scale})",
            isNullable: $nullable,
            isAutoIncrement: false,
            isPrimaryKey: false,
            defaultValue: null
        );
    }

    /**
     * Create a column with a foreign key.
     */
    public static function foreignKeyColumn(
        string $name,
        string $references,
        ?string $onDelete = null,
        ?string $onUpdate = null
    ): ColumnDefinition {
        return new ColumnDefinition(
            name: $name,
            sqlType: 'bigint unsigned',
            isNullable: false,
            isAutoIncrement: false,
            isPrimaryKey: false,
            defaultValue: null,
            onUpdate: null,
            isUnique: false,
            foreignKeys: [[
                'references' => $references,
                'onDelete' => $onDelete,
                'onUpdate' => $onUpdate,
                'name' => null,
            ]]
        );
    }

    /**
     * Create a complete users table for testing.
     */
    public static function usersTable(): TableDefinition
    {
        $table = new TableDefinition('users');
        
        $table->addColumn(static::idColumn());
        $table->addColumn(static::varcharColumn('email', 255));
        $table->addColumn(static::varcharColumn('name', 255));
        $table->addColumn(static::timestampColumn('created_at'));
        $table->addColumn(static::timestampColumn('updated_at', true, 'CURRENT_TIMESTAMP'));
        
        return $table;
    }

    /**
     * Create a complete posts table with foreign key for testing.
     */
    public static function postsTable(): TableDefinition
    {
        $table = new TableDefinition('posts');
        
        $table->addColumn(static::idColumn());
        $table->addColumn(static::varcharColumn('title', 255));
        $table->addColumn(static::textColumn('body'));
        $table->addColumn(static::foreignKeyColumn('user_id', 'users.id', 'CASCADE'));
        $table->addColumn(static::timestampColumn('created_at'));
        $table->addColumn(static::timestampColumn('updated_at'));
        
        return $table;
    }
}
