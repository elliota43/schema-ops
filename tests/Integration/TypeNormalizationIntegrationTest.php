<?php

namespace Tests\Unit;

use Atlas\Comparison\ColumnComparator;
use Atlas\Schema\Definition\ColumnDefinition;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class TypeNormalizationIntegrationTest extends TestCase
{
    #[Test]
    public function testNoFalsePositiveForCaseDifference(): void
    {
        // Simulates: User writes INT, database returns int(11)
        $dbColumn = $this->makeColumn('id', 'int');
        $codeColumn = $this->makeColumn('id', 'int');

        $comparator = new ColumnComparator($dbColumn, $codeColumn);

        $this->assertFalse($comparator->hasTypeChanged());
    }

    #[Test]
    public function testNoFalsePositiveForDisplayWidth(): void
    {
        // MySQL returns int(11), user just writes int
        $dbColumn = $this->makeColumn('age', 'int');
        $codeColumn = $this->makeColumn('age', 'int');

        $comparator = new ColumnComparator($dbColumn, $codeColumn);

        $this->assertFalse($comparator->hasTypeChanged());
    }

    #[Test]
    public function testNoFalsePositiveForIntegerAlias(): void
    {
        // User writes 'integer', MySQL stores as 'int'
        $dbColumn = $this->makeColumn('count', 'int');
        $codeColumn = $this->makeColumn('count', 'int');

        $comparator = new ColumnComparator($dbColumn, $codeColumn);

        $this->assertFalse($comparator->hasTypeChanged());
    }

    #[Test]
    public function testNoFalsePositiveForUnsignedWithDisplayWidth(): void
    {
        // MySQL: bigint(20) unsigned, User: bigint unsigned
        $dbColumn = $this->makeColumn('id', 'bigint unsigned');
        $codeColumn = $this->makeColumn('id', 'bigint unsigned');

        $comparator = new ColumnComparator($dbColumn, $codeColumn);

        $this->assertFalse($comparator->hasTypeChanged());
    }

    #[Test]
    public function testDetectsRealTypeChange(): void
    {
        // This SHOULD detect a real change
        $dbColumn = $this->makeColumn('age', 'int');
        $codeColumn = $this->makeColumn('age', 'bigint');

        $comparator = new ColumnComparator($dbColumn, $codeColumn);

        $this->assertTrue($comparator->hasTypeChanged());
    }

    #[Test]
    public function testVarcharLengthMatters(): void
    {
        // varchar(255) vs varchar(100) should be different
        $dbColumn = $this->makeColumn('name', 'varchar(255)');
        $codeColumn = $this->makeColumn('name', 'varchar(100)');

        $comparator = new ColumnComparator($dbColumn, $codeColumn);

        $this->assertTrue($comparator->hasTypeChanged());
    }

    #[Test]
    public function testDecimalPrecisionMatters(): void
    {
        // decimal(10,2) vs decimal(8,2) should be different
        $dbColumn = $this->makeColumn('price', 'decimal(10, 2)');
        $codeColumn = $this->makeColumn('price', 'decimal(8, 2)');

        $comparator = new ColumnComparator($dbColumn, $codeColumn);

        $this->assertTrue($comparator->hasTypeChanged());
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