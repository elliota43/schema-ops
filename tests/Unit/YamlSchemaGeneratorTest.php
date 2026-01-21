<?php

declare(strict_types=1);

namespace Tests\Unit;

use Atlas\Schema\Definition\ColumnDefinition;
use Atlas\Schema\Definition\TableDefinition;
use Atlas\Schema\Generation\YamlSchemaGenerator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

final class YamlSchemaGeneratorTest extends TestCase
{
    private YamlSchemaGenerator $generator;

    protected function setUp(): void
    {
        $this->generator = new YamlSchemaGenerator();
    }

    #[Test]
    public function it_sorts_tables_and_columns_deterministically(): void
    {
        $alpha = $this->makeTable('alpha', [
            $this->makeColumn('b', 'varchar(255)'),
            $this->makeColumn('a', 'varchar(255)'),
        ]);

        $zeta = $this->makeTable('zeta', [
            $this->makeColumn('z', 'int'),
        ]);

        $payload = $this->parseYaml($this->generator->generate([
            'zeta' => $zeta,
            'alpha' => $alpha,
        ]));

        $this->assertSame(['alpha', 'zeta'], array_keys($payload['tables']));
        $this->assertSame(['a', 'b'], array_keys($payload['tables']['alpha']['columns']));
    }

    #[Test]
    public function it_omits_empty_indexes_and_foreign_keys(): void
    {
        $table = $this->makeTable('users', [
            $this->makeColumn('email', 'varchar(255)'),
        ]);

        $payload = $this->parseYaml($this->generator->generate(['users' => $table]));

        $this->assertArrayNotHasKey('indexes', $payload['tables']['users']);
        $this->assertArrayNotHasKey('foreignKeys', $payload['tables']['users']['columns']['email']);
    }

    #[Test]
    public function it_includes_defaults_on_update_and_unique_correctly(): void
    {
        $table = $this->makeTable('users', [
            $this->makeColumn('status', 'varchar(50)', defaultValue: 'active', isUnique: true, onUpdate: 'CURRENT_TIMESTAMP'),
            $this->makeColumn('nickname', 'varchar(50)'),
        ]);

        $payload = $this->parseYaml($this->generator->generate(['users' => $table]));

        $status = $payload['tables']['users']['columns']['status'];
        $nickname = $payload['tables']['users']['columns']['nickname'];

        $this->assertSame('active', $status['default']);
        $this->assertSame('CURRENT_TIMESTAMP', $status['onUpdate']);
        $this->assertTrue($status['unique']);

        $this->assertArrayNotHasKey('default', $nickname);
        $this->assertArrayNotHasKey('onUpdate', $nickname);
        $this->assertArrayNotHasKey('unique', $nickname);
    }

    #[Test]
    public function it_sorts_indexes_by_columns_then_name(): void
    {
        $table = $this->makeTable('posts', [
            $this->makeColumn('a', 'int'),
            $this->makeColumn('b', 'int'),
        ]);

        $table->addIndex(['columns' => ['b'], 'unique' => false, 'name' => 'b_idx']);
        $table->addIndex(['columns' => ['a'], 'unique' => false, 'name' => 'z_idx']);
        $table->addIndex(['columns' => ['a'], 'unique' => false, 'name' => 'a_idx']);

        $payload = $this->parseYaml($this->generator->generate(['posts' => $table]));

        $order = array_map(
            fn (array $index) => [$index['columns'], $index['name']],
            $payload['tables']['posts']['indexes']
        );

        $this->assertSame([
            [['a'], 'a_idx'],
            [['a'], 'z_idx'],
            [['b'], 'b_idx'],
        ], $order);
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

    private function parseYaml(string $yaml): array
    {
        return Yaml::parse($yaml);
    }
}
