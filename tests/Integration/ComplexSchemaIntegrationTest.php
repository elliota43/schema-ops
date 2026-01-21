<?php

namespace Tests\Integration;

use Atlas\Database\Drivers\MySqlDriver;
use Atlas\Database\MySqlTypeNormalizer;
use Atlas\Example\OrderItemSchema;
use Atlas\Example\ProductSchema;
use Atlas\Example\UserSchema;
use Atlas\Schema\Grammars\MySqlGrammar;
use Atlas\Schema\Parser\SchemaParser;
use PDO;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Support\TestDb;

class ComplexSchemaIntegrationTest extends TestCase
{
    private PDO $pdo;
    private MySqlDriver $driver;
    private SchemaParser $parser;
    private MySqlGrammar $grammar;

    protected function setUp(): void
    {
        $this->pdo = TestDb::pdo();

        $this->driver = new MySqlDriver($this->pdo);
        $this->parser = new SchemaParser(new MySqlTypeNormalizer());
        $this->grammar = new MySqlGrammar();
    }

    protected function tearDown(): void
    {
        $this->pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
        $tables = ['order_items', 'products', 'users', 'orders', 'user_roles', 'roles'];
        foreach ($tables as $table) {
            $this->pdo->exec("DROP TABLE IF EXISTS `{$table}`");
        }
        $this->pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    }

    #[Test]
    public function testCreateAndIntrospectUserTable(): void
    {
        // Create table from schema
        $definition = $this->parser->parse(UserSchema::class);
        $sql = $this->grammar->createTable($definition);
        $this->pdo->exec($sql);

        // Introspect it back
        $allTables = $this->driver->getCurrentSchema();
        $introspected = $allTables['users'];

        // Verify structure
        $this->assertTrue($introspected->hasColumn('id'));
        $this->assertTrue($introspected->hasColumn('email'));
        $this->assertTrue($introspected->hasColumn('created_at'));
        $this->assertTrue($introspected->hasColumn('deleted_at'));

        $id = $introspected->getColumn('id');
        $this->assertTrue($id->isPrimaryKey());
        $this->assertTrue($id->isAutoIncrement());
    }

    #[Test]
    public function testCreateAndIntrospectProductTableWithUuid(): void
    {
        $definition = $this->parser->parse(ProductSchema::class);
        $sql = $this->grammar->createTable($definition);
        $this->pdo->exec($sql);

        $allTables = $this->driver->getCurrentSchema();
        $introspected = $allTables['products'];

        $id = $introspected->getColumn('id');
        $this->assertTrue($id->isPrimaryKey());
        $this->assertFalse($id->isAutoIncrement());

        $price = $introspected->getColumn('price');
        $this->assertEquals('decimal(10,2)', $price->sqlType());
    }

    #[Test]
    public function testCreateAndIntrospectCompositePrimaryKey(): void
    {
        // Need to create dependencies first
        $userDef = $this->parser->parse(UserSchema::class);
        $this->pdo->exec($this->grammar->createTable($userDef));
        
        $productDef = $this->parser->parse(ProductSchema::class);
        $this->pdo->exec($this->grammar->createTable($productDef));
        
        // Now create order_items
        $definition = $this->parser->parse(OrderItemSchema::class);
        $sql = $this->grammar->createTable($definition);
        $this->pdo->exec($sql);

        $allTables = $this->driver->getCurrentSchema();
        $introspected = $allTables['order_items'];

        // Should have both columns in composite key
        $this->assertNotNull($introspected->compositePrimaryKey);
        $this->assertCount(2, $introspected->compositePrimaryKey);
        $this->assertContains('order_id', $introspected->compositePrimaryKey);
        $this->assertContains('product_id', $introspected->compositePrimaryKey);
    }
}