<?php

namespace Tests\Unit;

use Atlas\Database\Drivers\MySqlTypeNormalizer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Atlas\Example\OrderItemSchema;
use Atlas\Example\ProductSchema;
use Atlas\Example\UserSchema;
use Atlas\Schema\Grammars\MySqlGrammar;
use Atlas\Schema\Parser\SchemaParser;

class AdvancedFeaturesTest extends TestCase
{
    private SchemaParser $parser;
    private MySqlGrammar $grammar;

    protected function setUp(): void
    {
        $this->parser = new SchemaParser(new MySqlTypeNormalizer());
        $this->grammar = new MySqlGrammar();
    }

    #[Test]
    public function testUniqueConstraintGeneration(): void
    {
        $definition = $this->parser->parse(UserSchema::class);
        $sql = $this->grammar->createTable($definition);

        $this->assertStringContainsString('UNIQUE KEY `email_unique` (`email`)', $sql);
    }

    #[Test]
    public function testMultipleUniqueConstraints(): void
    {
        $definition = $this->parser->parse(ProductSchema::class);
        $sql = $this->grammar->createTable($definition);

        $this->assertStringContainsString('UNIQUE KEY `slug_unique` (`slug`)', $sql);
    }

    #[Test]
    public function testCompositePrimaryKeyGeneration(): void
    {
        $definition = $this->parser->parse(OrderItemSchema::class);
        $sql = $this->grammar->createTable($definition);

        $this->assertStringContainsString('PRIMARY KEY (`order_id`, `product_id`)', $sql);
        $this->assertStringNotContainsString('PRIMARY KEY (`order_id`)', $sql);
        $this->assertStringNotContainsString('PRIMARY KEY (`product_id`)', $sql);
    }

    #[Test]
    public function testTimestampWithCurrentTimestamp(): void
    {
        $definition = $this->parser->parse(UserSchema::class);
        $sql = $this->grammar->createTable($definition);

        $this->assertStringContainsString('DEFAULT CURRENT_TIMESTAMP', $sql);
        $this->assertStringNotContainsString("DEFAULT 'CURRENT_TIMESTAMP'", $sql);
    }

    #[Test]
    public function testOnUpdateCurrentTimestamp(): void
    {
        $definition = $this->parser->parse(UserSchema::class);
        $sql = $this->grammar->createTable($definition);

        $this->assertStringContainsString('ON UPDATE CURRENT_TIMESTAMP', $sql);
    }

    #[Test]
    public function testUnsignedIntegerGeneration(): void
    {
        $userDef = $this->parser->parse(UserSchema::class);
        $userSql = $this->grammar->createTable($userDef);
        
        $productDef = $this->parser->parse(ProductSchema::class);
        $productSql = $this->grammar->createTable($productDef);

        $this->assertStringContainsString('BIGINT UNSIGNED', $userSql);
        $this->assertStringContainsString('INT UNSIGNED', $productSql);
    }

    #[Test]
    public function testDecimalWithPrecisionAndScale(): void
    {
        $definition = $this->parser->parse(OrderItemSchema::class);
        $sql = $this->grammar->createTable($definition);

        $this->assertStringContainsString('DECIMAL(10, 2)', $sql);
    }

    #[Test]
    public function testEnumWithValues(): void
    {
        $definition = $this->parser->parse(UserSchema::class);
        $sql = $this->grammar->createTable($definition);

        $this->assertStringContainsString("ENUM('active', 'suspended', 'banned')", $sql);
    }

    #[Test]
    public function testJsonColumnType(): void
    {
        $definition = $this->parser->parse(ProductSchema::class);
        $sql = $this->grammar->createTable($definition);

        $this->assertStringContainsString('`metadata` JSON NULL', $sql);
    }

    #[Test]
    public function testCharColumnWithLength(): void
    {
        $definition = $this->parser->parse(OrderItemSchema::class);
        $sql = $this->grammar->createTable($definition);

        $this->assertStringContainsString('`product_id` CHAR(36)', $sql);
    }
}
