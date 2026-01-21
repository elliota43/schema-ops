<?php

declare(strict_types=1);

namespace Tests\Unit;

use Atlas\Database\Drivers\MySqlTypeNormalizer;
use Atlas\Exceptions\SchemaException;
use Atlas\Schema\Parser\YamlSchemaParser;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class YamlSchemaParserTest extends TestCase
{
    private YamlSchemaParser $parser;

    protected function setUp(): void
    {
        $this->parser = new YamlSchemaParser(new MySqlTypeNormalizer());
    }

    #[Test]
    public function it_parses_yaml_into_table_and_column_definitions(): void
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

        $tables = $this->parser->parseString($yaml, 'inline.yaml');

        $this->assertArrayHasKey('users', $tables);

        $users = $tables['users'];

        $this->assertSame(['id'], $users->compositePrimaryKey);
        $this->assertCount(3, $users->columns);

        // Assert ID column
        $id = $users->columns['id'];
        $this->assertSame('id', $id->name());
        $this->assertSame('bigint unsigned', $id->sqlType());
        $this->assertTrue($id->isPrimaryKey());
        $this->assertTrue($id->isAutoIncrement());
        $this->assertFalse($id->isNullable());

        // Assert email column
        $email = $users->columns['email'];
        $this->assertSame('varchar(255)', $email->sqlType());
        $this->assertTrue($email->isUnique());
        $this->assertFalse($email->isNullable());

        // Assert role_id column with foreign key
        $roleId = $users->columns['role_id'];
        $this->assertSame('bigint unsigned', $roleId->sqlType());
        $this->assertFalse($roleId->isNullable());

        $fks = $roleId->foreignKeys();
        $this->assertCount(1, $fks);
        $this->assertSame([
            'references' => 'roles.id',
            'onDelete' => 'CASCADE',
            'onUpdate' => 'CASCADE',
            'name' => 'fk_users_role_id',
        ], $fks[0]);

        // Assert index
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
    public function it_rejects_yaml_missing_tables_key(): void
    {
        $this->expectException(SchemaException::class);
        $this->expectExceptionMessage("Missing or invalid 'tables' map");

        $this->parser->parseString("version: 1\n", 'bad.yaml');
    }

    #[Test]
    public function it_parses_multiple_yaml_files(): void
    {
        $tempDir = sys_get_temp_dir() . '/yaml_parser_test_' . uniqid();
        mkdir($tempDir);

        $usersFile = $tempDir . '/users.schema.yaml';
        $postsFile = $tempDir . '/posts.schema.yaml';

        file_put_contents($usersFile, "tables:\n  users:\n    columns:\n      id:\n        type: int");
        file_put_contents($postsFile, "tables:\n  posts:\n    columns:\n      id:\n        type: int");

        $definitions = $this->parser->parseFiles([$usersFile, $postsFile]);

        $this->assertCount(2, $definitions);
        
        unlink($usersFile);
        unlink($postsFile);
        rmdir($tempDir);
    }

    #[Test]
    public function it_throws_exception_when_duplicate_tables_found(): void
    {
        $tempDir = sys_get_temp_dir() . '/yaml_parser_test_' . uniqid();
        mkdir($tempDir);

        $file1 = $tempDir . '/users1.schema.yaml';
        $file2 = $tempDir . '/users2.schema.yaml';

        file_put_contents($file1, "tables:\n  users:\n    columns:\n      id:\n        type: int");
        file_put_contents($file2, "tables:\n  users:\n    columns:\n      id:\n        type: int");

        $this->expectException(SchemaException::class);
        $this->expectExceptionMessage("Duplicate table definition 'users'");

        $this->parser->parseFiles([$file1, $file2]);

        unlink($file1);
        unlink($file2);
        rmdir($tempDir);
    }

    #[Test]
    public function it_normalizes_column_types_using_type_normalizer(): void
    {
        $yaml = <<<YAML
tables:
  test:
    columns:
      id:
        type: INT
        unsigned: true
YAML;

        $tables = $this->parser->parseString($yaml, 'test.yaml');

        $this->assertSame('int unsigned', $tables['test']->columns['id']->sqlType());
    }

    #[Test]
    public function it_handles_nullable_columns(): void
    {
        $yaml = <<<YAML
tables:
  test:
    columns:
      required:
        type: varchar
        length: 255
        nullable: false
      optional:
        type: varchar
        length: 255
        nullable: true
YAML;

        $tables = $this->parser->parseString($yaml, 'test.yaml');

        $this->assertFalse($tables['test']->columns['required']->isNullable());
        $this->assertTrue($tables['test']->columns['optional']->isNullable());
    }

    #[Test]
    public function it_handles_column_defaults(): void
    {
        $yaml = <<<YAML
tables:
  test:
    columns:
      status:
        type: varchar
        length: 50
        default: active
YAML;

        $tables = $this->parser->parseString($yaml, 'test.yaml');

        $this->assertSame('active', $tables['test']->columns['status']->defaultValue());
    }

    #[Test]
    public function it_throws_exception_for_invalid_yaml_syntax(): void
    {
        $this->expectException(SchemaException::class);
        $this->expectExceptionMessage('Invalid YAML');

        $this->parser->parseString("invalid: yaml: syntax:", 'bad.yaml');
    }

    #[Test]
    public function it_throws_exception_when_yaml_root_is_not_array(): void
    {
        $this->expectException(SchemaException::class);
        $this->expectExceptionMessage('YAML root must be a map/object');

        $this->parser->parseString("just a string", 'bad.yaml');
    }

    #[Test]
    public function it_throws_exception_when_table_name_is_not_string(): void
    {
        $yaml = <<<YAML
tables:
  123:
    columns:
      id:
        type: int
YAML;

        $this->expectException(SchemaException::class);
        $this->expectExceptionMessage('Table name must be a non-empty string');

        $this->parser->parseString($yaml, 'bad.yaml');
    }

    #[Test]
    public function it_throws_exception_when_column_type_missing(): void
    {
        $yaml = <<<YAML
tables:
  users:
    columns:
      id:
        nullable: false
YAML;

        $this->expectException(SchemaException::class);
        $this->expectExceptionMessage('type is required');

        $this->parser->parseString($yaml, 'bad.yaml');
    }

    #[Test]
    public function it_parses_file_successfully(): void
    {
        $tempFile = sys_get_temp_dir() . '/test_' . uniqid() . '.yaml';
        
        file_put_contents($tempFile, "tables:\n  test:\n    columns:\n      id:\n        type: int");

        $tables = $this->parser->parseFile($tempFile);

        $this->assertArrayHasKey('test', $tables);
        
        unlink($tempFile);
    }

    #[Test]
    public function it_throws_exception_when_file_does_not_exist(): void
    {
        $this->expectException(SchemaException::class);
        $this->expectExceptionMessage('YAML schema file not found');

        $this->parser->parseFile('/non/existent/file.yaml');
    }
}