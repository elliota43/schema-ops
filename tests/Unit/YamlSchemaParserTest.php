<?php

namespace Tests\Unit;

use Atlas\Database\Drivers\MySqlTypeNormalizer;
use Atlas\Schema\Parser\YamlSchemaParser;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class YamlSchemaParserTest extends TestCase
{

    #[Test]
    public function TestParsesYamlIntoTableAndColumnDefinitions(): void
    {
        $yaml = <<<YAML
version: 1
tables:
  users:
    primaryKey: [id]
    columns:
      id:
        type: bigint
        unsigned: true
        primaryKey: true
        autoIncrement: true
        nullable: false

      email:
        type: varchar
        length: 255
        unique: true
        nullable: false

      role_id:
        type: bigint
        unsigned: true
        nullable: false
        foreignKeys:
          - references: roles.id
            onDelete: CASCADE
            onUpdate: CASCADE
            name: fk_users_role_id

    indexes:
      - columns: [email]
        unique: true
        name: users_email_unique
        type: BTREE
        length: null
YAML;

        $parser = new YamlSchemaParser(new MySqlTypeNormalizer());
        $tables = $parser->parseString($yaml, 'inline.yaml');

        $this->assertArrayHasKey('users', $tables);

        $users = $tables['users'];

        $this->assertSame(['id'], $users->compositePrimaryKey);

        $this->assertCount(3, $users->columns);

        $this->assertArrayHasKey('id', $users->columns);
        $this->assertSame('id', $users->columns['id']->name());
        $this->assertSame('bigint unsigned', $users->columns['id']->sqlType());
        $this->assertTrue($users->columns['id']->isPrimaryKey());
        $this->assertTrue($users->columns['id']->isAutoIncrement());
        $this->assertFalse($users->columns['id']->isNullable());

        $this->assertArrayHasKey('email', $users->columns());
        $this->assertSame('varchar(255)', $users->columns['email']->sqlType());
        $this->assertTrue($users->columns['email']->isUnique());
        $this->assertFalse($users->columns['email']->isNullable());

        $this->assertArrayHasKey('role_id', $users->columns);
        $this->assertSame('varchar(255)', $users->columns['email']->sqlType());
        $this->assertTrue($users->columns['email']->isUnique());
        $this->assertFalse($users->columns['email']->isNullable());

        $this->assertArrayHasKey('role_id', $users->columns);
        $this->assertSame('bigint unsigned', $users->columns['role_id']->sqlType());
        $this->assertFalse($users->columns['role_id']->isNullable());

        $fks = $users->columns['role_id']->foreignKeys();
        $this->assertCount(1, $fks);

        $this->assertSame([
            'references' => 'roles.id',
            'onDelete' => 'CASCADE',
            'onUpdate' => 'CASCADE',
            'name' => 'fk_users_role_id',
        ], $fks[0]);

        $this->assertCount(1, $users->indexes);
        $this->assertSame([
            'columns' => ['email'],
            'name' => 'users_email_unique',
            'unique' => true,
            'type' => 'BTREE',
            'length'=> null,
        ], $users->indexes[0]);
    }

    #[Test]
    public function TestRejectsMissingTablesKey(): void
    {
        $parser = new YamlSchemaParser(new MySqlTypeNormalizer());

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Missing or invalid 'tables' map");

        $parser->parseString("version: 1\n", 'bad.yaml');
    }
}