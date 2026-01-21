<?php

declare(strict_types=1);

namespace Tests\Unit;

use Atlas\Schema\Discovery\YamlSchemaFinder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class YamlSchemaFinderTest extends TestCase
{
    private string $tempDir;
    private YamlSchemaFinder $finder;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/yaml_finder_test_' . uniqid();
        mkdir($this->tempDir, 0777, true);
        
        $this->finder = new YamlSchemaFinder();
    }

    protected function tearDown(): void
    {
        $this->deleteDirectory($this->tempDir);
    }

    #[Test]
    public function it_finds_yaml_files_matching_default_pattern(): void
    {
        $this->createTestFile('users.schema.yaml');
        $this->createTestFile('posts.schema.yaml');
        $this->createTestFile('other.yaml');

        $files = $this->finder->findInDirectory($this->tempDir);

        $this->assertCount(2, $files);
        $this->assertStringContainsString('posts.schema.yaml', $files[0]);
        $this->assertStringContainsString('users.schema.yaml', $files[1]);
    }

    #[Test]
    public function it_finds_yaml_files_matching_custom_pattern(): void
    {
        $this->createTestFile('table1.yaml');
        $this->createTestFile('table2.yaml');
        $this->createTestFile('users.schema.yaml');

        $files = $this->finder->findInDirectory($this->tempDir, '*.yaml');

        $this->assertCount(3, $files);
    }

    #[Test]
    public function it_finds_yaml_files_in_nested_directories(): void
    {
        mkdir($this->tempDir . '/models', 0777, true);
        mkdir($this->tempDir . '/schemas/user', 0777, true);
        
        $this->createTestFile('root.schema.yaml');
        $this->createTestFile('models/user.schema.yaml');
        $this->createTestFile('schemas/user/profile.schema.yaml');

        $files = $this->finder->findInDirectory($this->tempDir);

        $this->assertCount(3, $files);
    }

    #[Test]
    public function it_returns_empty_array_when_no_files_match(): void
    {
        $this->createTestFile('test.yml');
        $this->createTestFile('schema.txt');

        $files = $this->finder->findInDirectory($this->tempDir);

        $this->assertEmpty($files);
    }

    #[Test]
    public function it_returns_empty_array_when_directory_does_not_exist(): void
    {
        $files = $this->finder->findInDirectory('/non/existent/path');

        $this->assertEmpty($files);
    }

    #[Test]
    public function it_returns_sorted_file_paths(): void
    {
        $this->createTestFile('c.schema.yaml');
        $this->createTestFile('a.schema.yaml');
        $this->createTestFile('b.schema.yaml');

        $files = $this->finder->findInDirectory($this->tempDir);

        $this->assertCount(3, $files);
        $this->assertStringContainsString('a.schema.yaml', $files[0]);
        $this->assertStringContainsString('b.schema.yaml', $files[1]);
        $this->assertStringContainsString('c.schema.yaml', $files[2]);
    }

    #[Test]
    public function it_returns_absolute_paths(): void
    {
        $this->createTestFile('users.schema.yaml');

        $files = $this->finder->findInDirectory($this->tempDir);

        $this->assertCount(1, $files);
        $this->assertStringStartsWith('/', $files[0]);
    }

    #[Test]
    public function it_handles_wildcard_patterns_correctly(): void
    {
        $this->createTestFile('user.table.yaml');
        $this->createTestFile('post.table.yaml');
        $this->createTestFile('users.schema.yaml');

        $files = $this->finder->findInDirectory($this->tempDir, '*.table.yaml');

        $this->assertCount(2, $files);
    }

    protected function createTestFile(string $relativePath): void
    {
        $fullPath = $this->tempDir . '/' . $relativePath;
        $directory = dirname($fullPath);

        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        file_put_contents($fullPath, 'tables: {}');
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
