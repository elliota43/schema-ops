<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Atlas\Comparison\ColumnComparator;
use Atlas\Schema\Definition\ColumnDefinition;

class ColumnComparatorTest extends TestCase
{
    #[Test]
    public function testDetectsOnUpdateChange(): void
    {
        $current = $this->makeColumn('timestamp', onUpdate: null);
        $desired = $this->makeColumn('timestamp', onUpdate: 'CURRENT_TIMESTAMP');

        $comparator = new ColumnComparator($current, $desired);

        $this->assertTrue($comparator->hasOnUpdateChanged());
        $this->assertTrue($comparator->hasChanges());
    }

    #[Test]
    public function testIgnoresOnUpdateCaseAndWhitespace(): void
    {
        $current = $this->makeColumn('timestamp', onUpdate: '  current_timestamp  ');
        $desired = $this->makeColumn('timestamp', onUpdate: 'CURRENT_TIMESTAMP');

        $comparator = new ColumnComparator($current, $desired);

        $this->assertFalse($comparator->hasOnUpdateChanged());
    }

    #[Test]
    public function testDetectsUniqueChange(): void
    {
        $current = $this->makeColumn('varchar(255)', isUnique: false);
        $desired = $this->makeColumn('varchar(255)', isUnique: true);

        $comparator = new ColumnComparator($current, $desired);

        $this->assertTrue($comparator->hasUniqueChanged());
        $this->assertTrue($comparator->hasChanges());
    }

    #[Test]
    public function testDetectsForeignKeyAddition(): void
    {
        $current = $this->makeColumn('int', foreignKeys: []);
        $desired = $this->makeColumn('int', foreignKeys: [
            ['references' => 'users.id', 'onDelete' => 'CASCADE', 'onUpdate' => 'NO ACTION']
        ]);

        $comparator = new ColumnComparator($current, $desired);

        $this->assertTrue($comparator->hasForeignKeysChanged());
        $this->assertTrue($comparator->hasChanges());
    }

    #[Test]
    public function testDetectsForeignKeyReferenceChange(): void
    {
        $current = $this->makeColumn('int', foreignKeys: [
            ['references' => 'users.id', 'onDelete' => 'CASCADE', 'onUpdate' => 'NO ACTION']
        ]);
        $desired = $this->makeColumn('int', foreignKeys: [
            ['references' => 'roles.id', 'onDelete' => 'CASCADE', 'onUpdate' => 'NO ACTION']
        ]);

        $comparator = new ColumnComparator($current, $desired);

        $this->assertTrue($comparator->hasForeignKeysChanged());
    }

    #[Test]
    public function testIgnoresForeignKeyActionCaseDifference(): void
    {
        $current = $this->makeColumn('int', foreignKeys: [
            ['references' => 'users.id', 'onDelete' => 'cascade', 'onUpdate' => 'no action']
        ]);
        $desired = $this->makeColumn('int', foreignKeys: [
            ['references' => 'users.id', 'onDelete' => 'CASCADE', 'onUpdate' => 'NO ACTION']
        ]);

        $comparator = new ColumnComparator($current, $desired);

        $this->assertFalse($comparator->hasForeignKeysChanged());
    }

    /**
     * Helper to create a ColumnDefinition for testing.
     */
    protected function makeColumn(
        string $type,
        bool $nullable = false,
        bool $autoIncrement = false,
        mixed $defaultValue = null,
        ?string $onUpdate = null,
        bool $isUnique = false,
        array $foreignKeys = []
    ): ColumnDefinition {
        return new ColumnDefinition(
            name: 'test_column',
            sqlType: $type,
            isNullable: $nullable,
            isAutoIncrement: $autoIncrement,
            isPrimaryKey: false,
            defaultValue: $defaultValue,
            onUpdate: $onUpdate,
            isUnique: $isUnique,
            foreignKeys: $foreignKeys
        );
    }
}