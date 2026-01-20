<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Atlas\Example\OrderItemSchema;
use Atlas\Example\OrderSchema;
use Atlas\Example\ProductSchema;
use Atlas\Example\RoleSchema;
use Atlas\Example\UserRoleSchema;
use Atlas\Example\UserSchema;
use Atlas\Schema\Grammars\MySqlGrammar;
use Atlas\Schema\Parser\SchemaParser;

class ComplexSchemaTest extends TestCase
{
    private SchemaParser $parser;
    private MySqlGrammar $grammar;

    protected function setUp(): void
    {
        $this->parser = new SchemaParser();
        $this->grammar = new MySqlGrammar();
    }

    #[Test]
    public function testUserSchemaWithAllConvenienceAttributes(): void
    {
        $definition = $this->parser->parse(UserSchema::class);

        // Should have Id, Timestamps, SoftDeletes columns
        $this->assertTrue($definition->hasColumn('id'));
        $this->assertTrue($definition->hasColumn('created_at'));
        $this->assertTrue($definition->hasColumn('updated_at'));
        $this->assertTrue($definition->hasColumn('deleted_at'));

        // Check id column is primary key and auto-increment
        $idCol = $definition->getColumn('id');
        $this->assertTrue($idCol->isPrimaryKey());
        $this->assertTrue($idCol->isAutoIncrement());
        $this->assertEquals('bigint unsigned', $idCol->sqlType());

        // Check timestamps
        $createdAt = $definition->getColumn('created_at');
        $this->assertEquals('timestamp', $createdAt->sqlType());
        $this->assertFalse($createdAt->isNullable());

        $updatedAt = $definition->getColumn('updated_at');
        $this->assertEquals('timestamp', $updatedAt->sqlType());
        $this->assertFalse($updatedAt->isNullable());

        // Check soft delete column is nullable
        $deletedAt = $definition->getColumn('deleted_at');
        $this->assertEquals('timestamp', $deletedAt->sqlType());
        $this->assertTrue($deletedAt->isNullable());

        // Check enum column
        $status = $definition->getColumn('status');
        $this->assertEquals("enum('active', 'suspended', 'banned')", $status->sqlType());
        $this->assertEquals('active', $status->defaultValue());
    }

    #[Test]
    public function testProductSchemaWithUuid(): void
    {
        $definition = $this->parser->parse(ProductSchema::class);

        // Should have UUID as primary key
        $this->assertTrue($definition->hasColumn('id'));
        $idCol = $definition->getColumn('id');
        $this->assertTrue($idCol->isPrimaryKey());
        $this->assertFalse($idCol->isAutoIncrement());
        $this->assertEquals('char(36)', $idCol->sqlType());

        // Check decimal precision/scale
        $price = $definition->getColumn('price');
        $this->assertEquals('decimal(10, 2)', $price->sqlType());

        // Check JSON column
        $metadata = $definition->getColumn('metadata');
        $this->assertEquals('json', $metadata->sqlType());
        $this->assertTrue($metadata->isNullable());
    }

    #[Test]
    public function testOrderItemSchemaWithCompositePrimaryKey(): void
    {
        $definition = $this->parser->parse(OrderItemSchema::class);

        // Should have composite primary key
        $this->assertNotNull($definition->compositePrimaryKey);
        $this->assertEquals(['order_id', 'product_id'], $definition->compositePrimaryKey);

        // Individual columns should NOT have isPrimaryKey set
        $orderId = $definition->getColumn('order_id');
        $productId = $definition->getColumn('product_id');
        $this->assertFalse($orderId->isPrimaryKey());
        $this->assertFalse($productId->isPrimaryKey());

        // Should have timestamps
        $this->assertTrue($definition->hasColumn('created_at'));
        $this->assertTrue($definition->hasColumn('updated_at'));
    }

    #[Test]
    public function testUserRoleSchemaWithCompositePrimaryKey(): void
    {
        $definition = $this->parser->parse(UserRoleSchema::class);

        // Should have composite primary key
        $this->assertNotNull($definition->compositePrimaryKey);
        $this->assertEquals(['user_id', 'role_id'], $definition->compositePrimaryKey);

        // Check nullable timestamp
        $expiresAt = $definition->getColumn('expires_at');
        $this->assertTrue($expiresAt->isNullable());
    }

    #[Test]
    public function testGenerateSqlForUserSchema(): void
    {
        $definition = $this->parser->parse(UserSchema::class);
        $sql = $this->grammar->createTable($definition);

        $this->assertStringContainsString('CREATE TABLE `users`', $sql);
        $this->assertStringContainsString('`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT', $sql);
        $this->assertStringContainsString('`email` VARCHAR(255) NOT NULL', $sql);
        $this->assertStringContainsString("`status` ENUM('active', 'suspended', 'banned')", $sql);
        $this->assertStringContainsString("DEFAULT 'active'", $sql);
        $this->assertStringContainsString('`created_at` TIMESTAMP NOT NULL', $sql);
        $this->assertStringContainsString('`deleted_at` TIMESTAMP NULL', $sql);
        $this->assertStringContainsString('PRIMARY KEY (`id`)', $sql);
    }

    #[Test]
    public function testGenerateSqlForProductSchema(): void
    {
        $definition = $this->parser->parse(ProductSchema::class);
        $sql = $this->grammar->createTable($definition);

        $this->assertStringContainsString('CREATE TABLE `products`', $sql);
        $this->assertStringContainsString('`id` CHAR(36) NOT NULL', $sql);
        $this->assertStringContainsString('`slug` VARCHAR(255) NOT NULL', $sql);
        $this->assertStringContainsString('`price` DECIMAL(10, 2) NOT NULL', $sql);
        $this->assertStringContainsString('`stock_quantity` INTEGER UNSIGNED NOT NULL DEFAULT 0', $sql);
        $this->assertStringContainsString('`metadata` JSON NULL', $sql);
        $this->assertStringContainsString('PRIMARY KEY (`id`)', $sql);
    }

    #[Test]
    public function testGenerateSqlForCompositePrimaryKey(): void
    {
        $definition = $this->parser->parse(OrderItemSchema::class);
        $sql = $this->grammar->createTable($definition);

        $this->assertStringContainsString('CREATE TABLE `order_items`', $sql);
        $this->assertStringContainsString('`order_id` BIGINT UNSIGNED NOT NULL', $sql);
        $this->assertStringContainsString('`product_id` CHAR(36) NOT NULL', $sql);
        $this->assertStringContainsString('PRIMARY KEY (`order_id`, `product_id`)', $sql);
    }

    #[Test]
    public function testMultipleSchemasParsing(): void
    {
        $schemas = [
            UserSchema::class,
            ProductSchema::class,
            OrderSchema::class,
            OrderItemSchema::class,
            UserRoleSchema::class,
            RoleSchema::class,
        ];

        $definitions = [];
        foreach ($schemas as $schemaClass) {
            $definitions[] = $this->parser->parse($schemaClass);
        }

        $this->assertCount(6, $definitions);

        // Verify each table has correct name
        $this->assertEquals('users', $definitions[0]->tableName);
        $this->assertEquals('products', $definitions[1]->tableName);
        $this->assertEquals('orders', $definitions[2]->tableName);
        $this->assertEquals('order_items', $definitions[3]->tableName);
        $this->assertEquals('user_roles', $definitions[4]->tableName);
        $this->assertEquals('roles', $definitions[5]->tableName);
    }

    #[Test]
    public function testEnumValuesInOrderSchema(): void
    {
        $definition = $this->parser->parse(OrderSchema::class);
        $sql = $this->grammar->createTable($definition);

        $this->assertStringContainsString(
            "`status` ENUM('pending', 'processing', 'completed', 'cancelled')",
            $sql
        );
        $this->assertStringContainsString("DEFAULT 'pending'", $sql);
    }
}