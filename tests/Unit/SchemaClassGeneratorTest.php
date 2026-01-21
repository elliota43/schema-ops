<?php

declare(strict_types=1);

namespace Tests\Unit;

use Atlas\Schema\Definition\ColumnDefinition;
use Atlas\Schema\Definition\TableDefinition;
use Atlas\Schema\Generation\SchemaClassGenerator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SchemaClassGeneratorTest extends TestCase
{
    private SchemaClassGenerator $generator;

    protected function setUp(): void
    {
        $this->generator = new SchemaClassGenerator('App\\Schema');
    }

    #[Test]
    public function it_emits_id_attribute_and_skips_id_column(): void
    {
        $table = $this->makeTable('users', [
            $this->makeColumn('id', 'bigint', isAutoIncrement: true, isPrimaryKey: true),
            $this->makeColumn('email', 'varchar(255)'),
        ]);

        $code = $this->generator->generate('UserSchema', $table);

        $this->assertStringContainsString('#[Id]', $code);
        $this->assertStringNotContainsString('$id;', $code);
    }

    #[Test]
    public function it_emits_uuid_attribute_instead_of_id_when_char36(): void
    {
        $table = $this->makeTable('users', [
            $this->makeColumn('id', 'char(36)', isPrimaryKey: true),
            $this->makeColumn('email', 'varchar(255)'),
        ]);

        $code = $this->generator->generate('UserSchema', $table);

        $this->assertStringContainsString('#[Uuid]', $code);
        $this->assertStringNotContainsString('#[Id]', $code);
        $this->assertStringNotContainsString('$id;', $code);
    }

    #[Test]
    public function it_emits_timestamps_and_skips_created_updated_columns(): void
    {
        $table = $this->makeTable('users', [
            $this->makeColumn('created_at', 'datetime'),
            $this->makeColumn('updated_at', 'datetime'),
            $this->makeColumn('email', 'varchar(255)'),
        ]);

        $code = $this->generator->generate('UserSchema', $table);

        $this->assertStringContainsString('#[Timestamps]', $code);
        $this->assertStringNotContainsString('$created_at;', $code);
        $this->assertStringNotContainsString('$updated_at;', $code);
    }

    #[Test]
    public function it_emits_soft_deletes_and_skips_deleted_at_column(): void
    {
        $table = $this->makeTable('users', [
            $this->makeColumn('deleted_at', 'datetime', isNullable: true),
            $this->makeColumn('email', 'varchar(255)'),
        ]);

        $code = $this->generator->generate('UserSchema', $table);

        $this->assertStringContainsString('#[SoftDeletes]', $code);
        $this->assertStringNotContainsString('$deleted_at;', $code);
    }

    #[Test]
    public function it_generates_decimal_precision_and_scale(): void
    {
        $table = $this->makeTable('orders', [
            $this->makeColumn('price', 'decimal(10, 2)'),
        ]);

        $code = $this->generator->generate('OrderSchema', $table);

        $this->assertStringContainsString("precision: 10", $code);
        $this->assertStringContainsString("scale: 2", $code);
        $this->assertStringNotContainsString('length: 10', $code);
    }

    #[Test]
    public function it_generates_length_for_varchar_but_not_decimal_length(): void
    {
        $table = $this->makeTable('users', [
            $this->makeColumn('name', 'varchar(255)'),
            $this->makeColumn('price', 'decimal(10, 2)'),
        ]);

        $code = $this->generator->generate('UserSchema', $table);

        $this->assertStringContainsString('length: 255', $code);
        $this->assertStringNotContainsString('length: 10', $code);
    }

    #[Test]
    public function it_formats_default_values_correctly(): void
    {
        $table = $this->makeTable('users', [
            $this->makeColumn('status', 'varchar(50)', defaultValue: "O'Reilly"),
            $this->makeColumn('is_admin', 'boolean', defaultValue: true),
            $this->makeColumn('retries', 'int', defaultValue: 10),
        ]);

        $code = $this->generator->generate('UserSchema', $table);

        $this->assertStringContainsString("default: 'O\\'Reilly'", $code);
        $this->assertStringContainsString('default: true', $code);
        $this->assertStringContainsString('default: 10', $code);
    }

    #[Test]
    public function it_adds_unsigned_unique_and_on_update_flags(): void
    {
        $table = $this->makeTable('users', [
            $this->makeColumn(
                'visits',
                'int unsigned',
                onUpdate: 'CURRENT_TIMESTAMP',
                isUnique: true
            ),
        ]);

        $code = $this->generator->generate('UserSchema', $table);

        $this->assertStringContainsString('unsigned: true', $code);
        $this->assertStringContainsString('unique: true', $code);
        $this->assertStringContainsString("onUpdate: 'CURRENT_TIMESTAMP'", $code);
    }

    private function makeTable(string $name, array $columns): TableDefinition
    {
        $table = new TableDefinition($name);

        foreach ($columns as $column) {
            $table->addColumn($column);
        }

        return $table;
    }

    private function makeColumn(
        string $name,
        string $type,
        bool $isNullable = false,
        bool $isAutoIncrement = false,
        bool $isPrimaryKey = false,
        mixed $defaultValue = null,
        ?string $onUpdate = null,
        bool $isUnique = false,
        array $foreignKeys = []
    ): ColumnDefinition {
        return new ColumnDefinition(
            name: $name,
            sqlType: $type,
            isNullable: $isNullable,
            isAutoIncrement: $isAutoIncrement,
            isPrimaryKey: $isPrimaryKey,
            defaultValue: $defaultValue,
            onUpdate: $onUpdate,
            isUnique: $isUnique,
            foreignKeys: $foreignKeys,
        );
    }
}
