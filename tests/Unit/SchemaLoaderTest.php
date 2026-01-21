<?php

declare(strict_types=1);

namespace Tests\Unit;

use Atlas\Database\Drivers\MySqlTypeNormalizer;
use Atlas\Exceptions\SchemaException;
use Atlas\Schema\Discovery\ClassFinder;
use Atlas\Schema\Loader\SchemaLoader;
use Atlas\Schema\Parser\SchemaParser;
use Atlas\Schema\Parser\YamlSchemaParser;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SchemaLoaderTest extends TestCase
{
    private SchemaLoader $loader;
    private string $tempDir;

    protected function setUp(): void
    {
        $normalizer = new MySqlTypeNormalizer();
        
        $this->loader = new SchemaLoader(
            new YamlSchemaParser($normalizer),
            new SchemaParser($normalizer),
            new ClassFinder(),
            $normalizer
        );

        $this->tempDir = sys_get_temp_dir() . '/schema_loader_test_' . uniqid();
        mkdir($this->tempDir, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->deleteDirectory($this->tempDir);
    }

    #[Test]
    public function it_loads_schema_from_yaml_file(): void
    {
        $yamlFile = $this->tempDir . '/test.schema.yaml';
        
        file_put_contents($yamlFile, <<<YAML
tables:
  users:
    columns:
      id:
        type: int
        primaryKey: true
YAML);

        $definitions = $this->loader->load($yamlFile);

        $this->assertArrayHasKey('users', $definitions);
    }

    #[Test]
    public function it_loads_schemas_from_yaml_directory(): void
    {
        file_put_contents($this->tempDir . '/users.schema.yaml', "tables:\n  users:\n    columns:\n      id:\n        type: int");
        file_put_contents($this->tempDir . '/posts.schema.yaml', "tables:\n  posts:\n    columns:\n      id:\n        type: int");

        $definitions = $this->loader->loadFromYamlDirectory($this->tempDir);

        $this->assertCount(2, $definitions);
    }

    #[Test]
    public function it_returns_empty_array_when_no_yaml_files_found(): void
    {
        $definitions = $this->loader->loadFromYamlDirectory($this->tempDir);

        $this->assertIsArray($definitions);
        $this->assertEmpty($definitions);
    }

    #[Test]
    public function it_uses_custom_pattern_to_find_yaml_files(): void
    {
        file_put_contents($this->tempDir . '/test1.table.yaml', "tables:\n  test1:\n    columns:\n      id:\n        type: int");
        file_put_contents($this->tempDir . '/test2.schema.yaml', "tables:\n  test2:\n    columns:\n      id:\n        type: int");

        $definitions = $this->loader->loadFromYamlDirectory($this->tempDir, '*.table.yaml');

        $this->assertCount(1, $definitions);
    }

    #[Test]
    public function it_throws_exception_when_php_directory_not_found(): void
    {
        $this->expectException(SchemaException::class);
        $this->expectExceptionMessage('Schema directory not found');

        $this->loader->load('/non/existent/path', true);
    }

    #[Test]
    public function it_throws_exception_for_duplicate_yaml_tables(): void
    {
        file_put_contents($this->tempDir . '/users1.schema.yaml', "tables:\n  users:\n    columns:\n      id:\n        type: int");
        file_put_contents($this->tempDir . '/users2.schema.yaml', "tables:\n  users:\n    columns:\n      id:\n        type: int");

        $this->expectException(SchemaException::class);
        $this->expectExceptionMessage("Duplicate table definition 'users'");

        $this->loader->loadFromYamlDirectory($this->tempDir);
    }

    #[Test]
    public function it_loads_from_php_classes_when_use_php_is_true(): void
    {
        $fixturesPath = __DIR__ . '/../Fixtures/Schemas';

        $definitions = $this->loader->load($fixturesPath, true);

        $this->assertNotEmpty($definitions);
    }

    #[Test]
    public function it_merges_definitions_from_multiple_yaml_files(): void
    {
        file_put_contents($this->tempDir . '/users.schema.yaml', <<<YAML
tables:
  users:
    columns:
      id:
        type: int
      email:
        type: varchar
        length: 255
YAML);

        file_put_contents($this->tempDir . '/posts.schema.yaml', <<<YAML
tables:
  posts:
    columns:
      id:
        type: int
      title:
        type: varchar
        length: 255
YAML);

        $definitions = $this->loader->loadFromYamlDirectory($this->tempDir);

        $this->assertCount(2, $definitions);
        
        // Verify users table has 2 columns
        $users = array_values(array_filter($definitions, fn($d) => $d->tableName === 'users'))[0];
        $this->assertCount(2, $users->columns);
        
        // Verify posts table has 2 columns
        $posts = array_values(array_filter($definitions, fn($d) => $d->tableName === 'posts'))[0];
        $this->assertCount(2, $posts->columns);
    }

    #[Test]
    public function it_throws_exception_when_yaml_file_invalid(): void
    {
        $invalidFile = $this->tempDir . '/invalid.yaml';
        file_put_contents($invalidFile, 'invalid: yaml: syntax:');

        $this->expectException(SchemaException::class);

        $this->loader->load($invalidFile);
    }

    protected function deleteDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $file) {
            $file->isDir() ? rmdir($file->getRealPath()) : unlink($file->getRealPath());
        }

        rmdir($dir);
    }
}
