<?php

namespace Tests\Unit;

use Atlas\Database\Drivers\MySqlTypeNormalizer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Atlas\Example\PostSchema;
use Atlas\Schema\Grammars\MySqlGrammar;
use Atlas\Schema\Parser\SchemaParser;

class IndexAndForeignKeyTest extends TestCase
{
    private SchemaParser $parser;
    private MySqlGrammar $grammar;

    protected function setUp(): void
    {
        $this->parser = new SchemaParser(new MySqlTypeNormalizer());
        $this->grammar = new MySqlGrammar();
    }

    #[Test]
    public function testForeignKeyGeneration(): void
    {
        $definition = $this->parser->parse(PostSchema::class);
        $sql = $this->grammar->createTable($definition);

        $this->assertStringContainsString('CONSTRAINT', $sql);
        $this->assertStringContainsString('FOREIGN KEY (`user_id`)', $sql);
        $this->assertStringContainsString('REFERENCES `users` (`id`)', $sql);
        $this->assertStringContainsString('ON DELETE CASCADE', $sql);
    }

    #[Test]
    public function testMultipleIndexGeneration(): void
    {
        $definition = $this->parser->parse(PostSchema::class);
        $sql = $this->grammar->createTable($definition);

        $this->assertStringContainsString('KEY `posts_user_id_created_at_index`', $sql);
        $this->assertStringContainsString('KEY `posts_status_published_at_index`', $sql);
    }

    #[Test]
    public function testUniqueIndexFromAttribute(): void
    {
        $definition = $this->parser->parse(PostSchema::class);
        $sql = $this->grammar->createTable($definition);

        $this->assertStringContainsString('UNIQUE KEY `slug_unique`', $sql);
        $this->assertStringContainsString('UNIQUE KEY `posts_slug_index`', $sql);
    }

    #[Test]
    public function testCompositeIndex(): void
    {
        $definition = $this->parser->parse(PostSchema::class);
        $sql = $this->grammar->createTable($definition);

        $this->assertStringContainsString('(`user_id`, `created_at`)', $sql);
        $this->assertStringContainsString('(`status`, `published_at`)', $sql);
    }

    #[Test]
    public function testForeignKeyHasAutomaticName(): void
    {
        $definition = $this->parser->parse(PostSchema::class);
        $sql = $this->grammar->createTable($definition);

        $this->assertStringContainsString('posts_user_id_foreign', $sql);
    }
}
