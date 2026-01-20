<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Atlas\Comparison\TableComparator;
use Atlas\Schema\Definition\ColumnDefinition;
use Atlas\Schema\Definition\TableDefinition;

class TableComparatorTest extends TestCase
{
    #[Test]
    public function testDetectsChangesBetweenDefinitions(): void
    {
        $current = new TableDefinition('users');
        $current->addColumn(new ColumnDefinition('id', 'integer', false, true, true, null));
        $current->addColumn(new ColumnDefinition('status', 'varchar(10)', false, false, false, 'active'));
        $current->addColumn(new ColumnDefinition('old_col', 'int', false, false, false, null));

        $desired = new TableDefinition('users');
        $desired->addColumn(new ColumnDefinition('id', 'integer', false, true, true, null));
        $desired->addColumn(new ColumnDefinition('status', 'varchar(50)', false, false, false, 'active'));
        $desired->addColumn(new ColumnDefinition('email', 'varchar(255)', false, false, false, null));

        $comparator = new TableComparator();
        $diff = $comparator->compare($current, $desired);

        $this->assertEquals('users', $diff->tableName);
        $this->assertCount(1, $diff->addedColumns);
        $this->assertEquals('email', $diff->addedColumns[0]->name());
        $this->assertCount(1, $diff->modifiedColumns);
        $this->assertEquals('status', $diff->modifiedColumns[0]->name());
        $this->assertEquals('varchar(50)', $diff->modifiedColumns[0]->sqlType());
        $this->assertCount(1, $diff->removedColumns);
        $this->assertEquals('old_col', $diff->removedColumns[0]);
    }
}