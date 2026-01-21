<?php

declare(strict_types=1);

namespace Tests\Unit;

use Atlas\Changes\TableChanges;
use Atlas\Schema\Definition\ColumnDefinition;
use Atlas\Schema\Definition\TableDefinition;
use Atlas\Schema\Grammars\PostgresGrammar;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PostgresGrammarTest extends TestCase
{
    private PostgresGrammar $grammar;

    protected function setUp(): void
    {
        $this->grammar = new PostgresGrammar();
    }

    #[Test]
    public function it_creates_tables_with_constraints_and_defaults(): void
    {
        $table = new TableDefinition('users');
        $table->addColumn($this->makeColumn('id', 'int', isAutoIncrement: true, isPrimaryKey: true));
        $table->addColumn($this->makeColumn('email', 'varchar(255)', defaultValue: 'guest', isUnique: true));
        $table->addColumn($this->makeColumn(
            'profile_id',
            'int',
            isNullable: true,
            foreignKeys: [[
                'references' => 'profiles.id',
                'onDelete' => 'CASCADE',
                'onUpdate' => 'SET NULL',
            ]]
        ));

        $sql = $this->grammar->createTable($table);

        $this->assertStringContainsString('CREATE TABLE "users"', $sql);
        $this->assertStringContainsString('"id" SERIAL NOT NULL', $sql);
        $this->assertStringContainsString('PRIMARY KEY ("id")', $sql);
        $this->assertStringContainsString('"email" VARCHAR(255) NOT NULL DEFAULT \'guest\'', $sql);
        $this->assertStringContainsString('UNIQUE ("email")', $sql);
        $this->assertStringContainsString(
            'CONSTRAINT "users_profile_id_foreign" FOREIGN KEY ("profile_id") REFERENCES "profiles" ("id") ON DELETE CASCADE ON UPDATE SET NULL',
            $sql
        );
    }

    #[Test]
    public function it_generates_alter_statements_for_add_modify_and_drop(): void
    {
        $changes = new TableChanges('users');
        $changes->removedColumns = ['legacy'];
        $changes->addedColumns = [$this->makeColumn('age', 'int')];
        $changes->modifiedColumns = [$this->makeColumn('name', 'varchar(100)', isNullable: true)];

        $statements = $this->grammar->generateAlter($changes);

        $this->assertSame([
            'ALTER TABLE "users" DROP COLUMN "legacy";',
            'ALTER TABLE "users" ADD COLUMN "age" INT NOT NULL;',
            'ALTER TABLE "users" ALTER COLUMN "name" TYPE VARCHAR(100);',
        ], $statements);
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
