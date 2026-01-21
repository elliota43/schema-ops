<?php

declare(strict_types=1);

namespace Tests\Unit;

use Atlas\Database\Drivers\MySqlTypeNormalizer;
use Atlas\Exceptions\SchemaException;
use Atlas\Schema\Grammars\MySqlGrammar;
use Atlas\Schema\Parser\SchemaParser;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Atlas\Attributes\Column;
use Atlas\Attributes\Table;

// Fixture Classes for Testing
#[Table(name: 'users')]
class TestUserSchema
{
    #[Column(type: 'integer', autoIncrement: true, primaryKey: true)]
    public int $id;

    #[Column(type: 'varchar', length: 255)]
    public string $email;

    public string $ignoredProperty = 'temp';
}

#[Table('test_private')]
class PrivatePropertySchema
{
    #[Column(type: 'varchar', length: 255)]
    private string $password;

    #[Column(type: 'varchar', length: 255)]
    protected string $apiKey;
}

class ParserTest extends TestCase
{
    private SchemaParser $parser;
    private MySqlGrammar $grammar;

    protected function setUp(): void
    {
        $this->parser = new SchemaParser(new MySqlTypeNormalizer());
        $this->grammar = new MySqlGrammar();
    }

    #[Test]
    public function it_parses_php_attributes_into_table_definition(): void
    {
        $definition = $this->parser->parse(TestUserSchema::class);

        $this->assertEquals('users', $definition->tableName);
        $this->assertCount(2, $definition->columns);
    }

    #[Test]
    public function it_parses_column_attributes(): void
    {
        $definition = $this->parser->parse(TestUserSchema::class);

        $id = $definition->columns['id'];
        $this->assertEquals('id', $id->name());
        $this->assertTrue($id->isPrimaryKey());
        $this->assertTrue($id->isAutoIncrement());

        $email = $definition->columns['email'];
        $this->assertEquals('email', $email->name());
        $this->assertEquals('varchar(255)', $email->sqlType());
    }

    #[Test]
    public function it_ignores_properties_without_column_attribute(): void
    {
        $definition = $this->parser->parse(TestUserSchema::class);

        $this->assertArrayNotHasKey('ignoredProperty', $definition->columns);
    }

    #[Test]
    public function it_generates_create_table_sql(): void
    {
        $definition = $this->parser->parse(TestUserSchema::class);
        $sql = $this->grammar->createTable($definition);

        $this->assertStringContainsString('CREATE TABLE `users`', $sql);
        $this->assertStringContainsString('`id` INT NOT NULL AUTO_INCREMENT', $sql);
        $this->assertStringContainsString('PRIMARY KEY (`id`)', $sql);
    }

    #[Test]
    public function it_supports_private_and_protected_properties(): void
    {
        $definition = $this->parser->parse(PrivatePropertySchema::class);

        $this->assertCount(2, $definition->columns);
        $this->assertArrayHasKey('password', $definition->columns);
        $this->assertArrayHasKey('apiKey', $definition->columns);
        
        $this->assertEquals('varchar(255)', $definition->columns['password']->sqlType());
        $this->assertEquals('varchar(255)', $definition->columns['apiKey']->sqlType());
    }

    #[Test]
    public function it_throws_exception_when_class_not_found(): void
    {
        $this->expectException(SchemaException::class);
        $this->expectExceptionMessage('Class');
        $this->expectExceptionMessage('not found');

        $this->parser->parse('NonExistent\\Class');
    }

    #[Test]
    public function it_throws_exception_when_table_attribute_missing(): void
    {
        $this->expectException(SchemaException::class);
        $this->expectExceptionMessage('missing the #[Table] attribute');

        $this->parser->parse(NoTableAttribute::class);
    }

    #[Test]
    public function it_normalizes_column_types(): void
    {
        $definition = $this->parser->parse(TestUserSchema::class);

        // 'integer' should be normalized to 'int'
        $this->assertStringContainsString('INT', strtoupper($definition->columns['id']->sqlType()));
    }
}

// Fixture class without Table attribute
class NoTableAttribute
{
    #[Column(type: 'varchar', length: 255)]
    public string $name;
}