<?php

namespace Tests\Unit;

use Atlas\Analysis\DestructivenessLevel;
use Atlas\Analysis\MySqlDestructiveChangeAnalyzer;
use Atlas\Schema\Definition\ColumnDefinition;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;

class MySqlDestructiveChangeAnalyzerTest extends TestCase
{
    private MySqlDestructiveChangeAnalyzer $analyzer;

    protected function setUp(): void
    {
        $this->analyzer = new MySqlDestructiveChangeAnalyzer();
    }

    #[Test]
    public function testNumericWidening(): void
    {
        $from = $this->makeColumn('age', 'int');
        $to = $this->makeColumn('age', 'bigint');

        $level = $this->analyzer->analyze($from, $to);

        $this->assertEquals(DestructivenessLevel::SAFE, $level);
    }

    #[Test]
    public function testNumericNarrowing(): void
    {
        $from = $this->makeColumn('age', 'bigint');
        $to = $this->makeColumn('age', 'int');

        $level = $this->analyzer->analyze($from, $to);

        $this->assertEquals(DestructivenessLevel::POTENTIALLY_DESTRUCTIVE, $level);
    }

    #[Test]
    public function testVarcharTruncation(): void
    {
        $from = $this->makeColumn('name', 'varchar(255)');
        $to = $this->makeColumn('name', 'varchar(50)');

        $level = $this->analyzer->analyze($from, $to);

        $this->assertEquals(DestructivenessLevel::DEFINITELY_DESTRUCTIVE, $level);
    }

    #[Test]
    public function testVarcharExpansion(): void
    {
        $from = $this->makeColumn('name', 'varchar(50)');
        $to = $this->makeColumn('name', 'varchar(255)');

        $level = $this->analyzer->analyze($from, $to);

        $this->assertEquals(DestructivenessLevel::SAFE, $level);
    }

    #[Test]
    public function testIncompatibleTypes(): void
    {
        $from = $this->makeColumn('data', 'json');
        $to = $this->makeColumn('data', 'int');

        $level = $this->analyzer->analyze($from, $to);

        $this->assertEquals(DestructivenessLevel::DEFINITELY_DESTRUCTIVE, $level);
    }

    private function makeColumn(string $name, string $type): ColumnDefinition
    {
        return new ColumnDefinition(
            name: $name,
            sqlType: $type,
            isNullable: false,
            isAutoIncrement: false,
            isPrimaryKey: false,
            defaultValue: null,
            onUpdate: null,
            isUnique: false,
            foreignKeys: []
        );
    }
}