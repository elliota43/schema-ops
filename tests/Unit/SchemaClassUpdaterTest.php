<?php

declare(strict_types=1);

namespace Tests\Unit;

use Atlas\Schema\Definition\ColumnDefinition;
use Atlas\Schema\Definition\TableDefinition;
use Atlas\Schema\Generation\SchemaClassUpdater;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SchemaClassUpdaterTest extends TestCase
{
    private SchemaClassUpdater $updater;

    protected function setUp(): void
    {
        $this->updater = new SchemaClassUpdater();
    }

    #[Test]
    public function it_is_idempotent(): void
    {
        $content = <<<'PHP'
<?php

namespace App\Schema;

class UserSchema
{
    public string $email;
}
PHP;

        $table = $this->makeTable('users', [
            $this->makeColumn('id', 'int', isAutoIncrement: true, isPrimaryKey: true),
            $this->makeColumn('email', 'varchar(255)'),
        ]);

        $first = $this->updater->update($content, $table, 'UserSchema');
        $second = $this->updater->update($first, $table, 'UserSchema');

        $this->assertSame($first, $second);
        $this->assertStringContainsString("#[Table(name: 'users')]", $second);
        $this->assertStringContainsString("#[Column(type: 'varchar', length: 255)]", $second);
    }

    #[Test]
    public function it_preserves_user_code_outside_generated_regions(): void
    {
        $content = <<<'PHP'
<?php

namespace App\Schema;

class UserSchema
{
    public string $email;

    public function custom(): void
    {
        $this->touch();
    }
}
PHP;

        $table = $this->makeTable('users', [
            $this->makeColumn('email', 'varchar(255)'),
        ]);

        $updated = $this->updater->update($content, $table, 'UserSchema');

        $this->assertStringContainsString("public function custom(): void\n    {\n        \$this->touch();\n    }", $updated);
    }

    #[Test]
    public function it_updates_changed_columns_without_reordering_unrelated_code(): void
    {
        $content = <<<'PHP'
<?php

namespace App\Schema;

class UserSchema
{
    public string $email;

    public function custom(): void
    {
        $this->touch();
    }
}
PHP;

        $table = $this->makeTable('users', [
            $this->makeColumn('email', 'varchar(255)'),
            $this->makeColumn('name', 'varchar(100)'),
        ]);

        $updated = $this->updater->update($content, $table, 'UserSchema');

        $this->assertStringContainsString("#[Column(type: 'varchar', length: 255)]\n    public string \$email;", $updated);
        $this->assertStringContainsString('public function custom(): void', $updated);
        $this->assertStringContainsString('public string $name;', $updated);

        $emailPos = strpos($updated, '$email;');
        $methodPos = strpos($updated, 'function custom');

        $this->assertNotFalse($emailPos);
        $this->assertNotFalse($methodPos);
        $this->assertLessThan($methodPos, $emailPos);
    }

    private function makeTable(string $name, array $columns): TableDefinition
    {
        $table = new TableDefinition($name);

        foreach ($columns as $column) {
            $table->addColumn($column);
        }

        return $table;
    }

    private function makeColumn(
        string $name,
        string $type,
        bool $isNullable = false,
        bool $isAutoIncrement = false,
        bool $isPrimaryKey = false,
        mixed $defaultValue = null,
        ?string $onUpdate = null,
        bool $isUnique = false,
        array $foreignKeys = []
    ): ColumnDefinition {
        return new ColumnDefinition(
            name: $name,
            sqlType: $type,
            isNullable: $isNullable,
            isAutoIncrement: $isAutoIncrement,
            isPrimaryKey: $isPrimaryKey,
            defaultValue: $defaultValue,
            onUpdate: $onUpdate,
            isUnique: $isUnique,
            foreignKeys: $foreignKeys,
        );
    }
}
