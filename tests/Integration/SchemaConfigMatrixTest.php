<?php

declare(strict_types=1);

namespace Tests\Integration;

use Atlas\Attributes\Column;
use Atlas\Attributes\Table;
use Atlas\Comparison\TableComparator;
use Atlas\Database\MySqlTypeNormalizer;
use Atlas\Database\Normalizers\PostgresTypeNormalizer;
use Atlas\Database\Normalizers\SQLiteTypeNormalizer;
use Atlas\Database\Normalizers\TypeNormalizerInterface;
use Atlas\Schema\Definition\TableDefinition;
use Atlas\Schema\Parser\SchemaParser;
use Atlas\Schema\Parser\YamlSchemaParser;
use Atlas\Changes\TableChanges;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Table(name: 'matrix_users')]
final class MatrixUserSchema
{
    #[Column(type: 'int', autoIncrement: true, primaryKey: true)]
    public int $id;

    #[Column(type: 'varchar', length: 255, nullable: false, unique: true)]
    public string $email;

    #[Column(type: 'varchar', length: 100, nullable: false)]
    public string $name;

    #[Column(type: 'boolean', default: true)]
    public bool $is_active;

    #[Column(type: 'timestamp', nullable: true)]
    public ?string $created_at;
}

final class SchemaConfigMatrixTest extends TestCase
{
    #[Test]
    #[DataProvider('sqlFlavorProvider')]
    public function testYamlAndAttributesStayInSyncAcrossFlavors(string $driver, TypeNormalizerInterface $normalizer): void
    {
        $yamlTable = $this->loadYamlSchema($normalizer);
        $attributeTable = $this->loadAttributeSchema($normalizer);

        $diff = $this->compareSchemas($yamlTable, $attributeTable);

        $this->assertTrue(
            $diff->isEmpty(),
            "Schema mismatch for {$driver} between YAML and attributes."
        );
    }

    public static function sqlFlavorProvider(): array
    {
        return [
            'mysql' => ['mysql', new MySqlTypeNormalizer()],
            'pgsql' => ['pgsql', new PostgresTypeNormalizer()],
            'sqlite' => ['sqlite', new SQLiteTypeNormalizer()],
        ];
    }

    protected function loadYamlSchema(TypeNormalizerInterface $normalizer): TableDefinition
    {
        $parser = new YamlSchemaParser($normalizer);
        $tables = $parser->parseString($this->yamlFixture(), 'matrix.schema.yaml');

        if (! isset($tables['matrix_users'])) {
            $this->fail('Matrix users table missing from YAML fixture.');
        }

        return $tables['matrix_users'];
    }

    protected function loadAttributeSchema(TypeNormalizerInterface $normalizer): TableDefinition
    {
        $parser = new SchemaParser($normalizer);

        return $parser->parse(MatrixUserSchema::class);
    }

    protected function compareSchemas(
        TableDefinition $yamlTable,
        TableDefinition $attributeTable
    ): TableChanges {
        $comparator = new TableComparator();

        return $comparator->compare($yamlTable, $attributeTable);
    }

    protected function yamlFixture(): string
    {
        return <<<YAML
version: 1
tables:
  matrix_users:
    columns:
      id:
        type: int
        auto_increment: true
        primary: true
      email:
        type: varchar
        length: 255
        nullable: false
        unique: true
      name:
        type: varchar
        length: 100
        nullable: false
      is_active:
        type: boolean
        default: true
      created_at:
        type: timestamp
        nullable: true
YAML;
    }
}
