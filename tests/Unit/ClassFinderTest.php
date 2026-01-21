<?php

declare(strict_types=1);

namespace Tests\Unit;

use Atlas\Schema\Discovery\ClassFinder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ClassFinderTest extends TestCase
{
    private ClassFinder $finder;
    private string $fixturesPath;

    protected function setUp(): void
    {
        $this->finder = new ClassFinder();
        $this->fixturesPath = __DIR__ . '/../Fixtures/Schemas';
    }

    #[Test]
    public function it_finds_classes_with_table_attribute(): void
    {
        $classes = $this->finder->findInDirectory($this->fixturesPath);

        $this->assertNotEmpty($classes);
        $this->assertContains('Tests\\Fixtures\\Schemas\\User', $classes);
    }

    #[Test]
    public function it_returns_empty_array_when_directory_does_not_exist(): void
    {
        $classes = $this->finder->findInDirectory('/non/existent/path');

        $this->assertIsArray($classes);
        $this->assertEmpty($classes);
    }

    #[Test]
    public function it_returns_fully_qualified_class_names(): void
    {
        $classes = $this->finder->findInDirectory($this->fixturesPath);

        foreach ($classes as $class) {
            $this->assertStringContainsString('\\', $class);
            $this->assertStringStartsWith('Tests\\', $class);
        }
    }

    #[Test]
    public function it_only_returns_classes_with_table_attribute(): void
    {
        $classes = $this->finder->findInDirectory($this->fixturesPath);

        // Should not include regular PHP classes without #[Table]
        foreach ($classes as $class) {
            $reflection = new \ReflectionClass($class);
            $attributes = $reflection->getAttributes(\Atlas\Attributes\Table::class);
            
            $this->assertNotEmpty($attributes, "Class {$class} should have Table attribute");
        }
    }

    #[Test]
    public function it_finds_classes_in_nested_directories(): void
    {
        $tempDir = sys_get_temp_dir() . '/class_finder_test_' . uniqid();
        $nestedDir = $tempDir . '/Models/User';
        
        mkdir($nestedDir, 0777, true);

        // Create a PHP file with Table attribute
        $content = <<<'PHP'
<?php
namespace Test\Models\User;

use Atlas\Attributes\Table;
use Atlas\Attributes\Column;

#[Table('test')]
class TestSchema
{
    #[Column(type: 'int')]
    public int $id;
}
PHP;

        file_put_contents($nestedDir . '/TestSchema.php', $content);

        $classes = $this->finder->findInDirectory($tempDir);

        $this->assertCount(1, $classes);
        $this->assertContains('Test\\Models\\User\\TestSchema', $classes);

        unlink($nestedDir . '/TestSchema.php');
        rmdir($nestedDir);
        rmdir(dirname($nestedDir));
        rmdir($tempDir);
    }

    #[Test]
    public function it_returns_sorted_class_names(): void
    {
        $tempDir = sys_get_temp_dir() . '/class_finder_test_' . uniqid();
        mkdir($tempDir);

        $this->createTestClass($tempDir, 'CSchema', 'Test\\CSchema');
        $this->createTestClass($tempDir, 'ASchema', 'Test\\ASchema');
        $this->createTestClass($tempDir, 'BSchema', 'Test\\BSchema');

        $classes = $this->finder->findInDirectory($tempDir);

        $this->assertEquals([
            'Test\\ASchema',
            'Test\\BSchema',
            'Test\\CSchema',
        ], $classes);

        unlink($tempDir . '/ASchema.php');
        unlink($tempDir . '/BSchema.php');
        unlink($tempDir . '/CSchema.php');
        rmdir($tempDir);
    }

    #[Test]
    public function it_skips_files_without_table_attribute(): void
    {
        $tempDir = sys_get_temp_dir() . '/class_finder_test_' . uniqid();
        mkdir($tempDir);

        // Create a PHP file WITHOUT Table attribute
        $content = <<<'PHP'
<?php
namespace Test;

class RegularClass
{
    public int $id;
}
PHP;

        file_put_contents($tempDir . '/RegularClass.php', $content);

        $classes = $this->finder->findInDirectory($tempDir);

        $this->assertEmpty($classes);

        unlink($tempDir . '/RegularClass.php');
        rmdir($tempDir);
    }

    protected function createTestClass(string $dir, string $className, string $fqcn): void
    {
        $content = <<<PHP
<?php
namespace {$this->getNamespace($fqcn)};

use Atlas\Attributes\Table;
use Atlas\Attributes\Column;

#[Table('{$this->getTableName($className)}')]
class {$className}
{
    #[Column(type: 'int')]
    public int \$id;
}
PHP;

        file_put_contents($dir . '/' . $className . '.php', $content);
    }

    protected function getNamespace(string $fqcn): string
    {
        $parts = explode('\\', $fqcn);
        array_pop($parts);
        
        return implode('\\', $parts);
    }

    protected function getTableName(string $className): string
    {
        return strtolower(preg_replace('/Schema$/', 's', $className));
    }
}
