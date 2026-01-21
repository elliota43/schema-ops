<?php

declare(strict_types=1);

namespace Tests\Unit;

use Atlas\Support\ProjectRootFinder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ProjectRootFinderTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/root_finder_test_' . uniqid();
        mkdir($this->tempDir, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->deleteDirectory($this->tempDir);
    }

    #[Test]
    public function it_finds_project_root_with_composer_json(): void
    {
        $this->createComposerJson($this->tempDir);

        $root = ProjectRootFinder::find($this->tempDir);

        $this->assertEquals(realpath($this->tempDir), $root);
    }

    #[Test]
    public function it_traverses_up_to_find_composer_json(): void
    {
        $nestedDir = $this->tempDir . '/src/Models';
        mkdir($nestedDir, 0777, true);
        
        $this->createComposerJson($this->tempDir);

        $root = ProjectRootFinder::find($nestedDir);

        $this->assertEquals(realpath($this->tempDir), $root);
    }

    #[Test]
    public function it_skips_composer_json_inside_vendor_directory(): void
    {
        $projectDir = $this->tempDir . '/my-project';
        $vendorDir = $projectDir . '/vendor/package';
        
        mkdir($vendorDir, 0777, true);
        
        $this->createComposerJson($projectDir);
        $this->createComposerJson($vendorDir);

        $root = ProjectRootFinder::find($vendorDir);

        $this->assertEquals(realpath($projectDir), $root);
    }

    #[Test]
    public function it_returns_null_when_no_composer_json_found(): void
    {
        $root = ProjectRootFinder::find($this->tempDir);

        $this->assertNull($root);
    }

    #[Test]
    public function it_returns_null_when_start_path_is_invalid(): void
    {
        $root = ProjectRootFinder::find('/non/existent/path');

        $this->assertNull($root);
    }

    #[Test]
    public function it_uses_current_working_directory_when_no_path_provided(): void
    {
        $originalCwd = getcwd();
        
        chdir($this->tempDir);
        $this->createComposerJson($this->tempDir);

        $root = ProjectRootFinder::find();

        $this->assertEquals(realpath($this->tempDir), $root);

        chdir($originalCwd);
    }

    #[Test]
    public function it_stops_at_filesystem_root(): void
    {
        $deepDir = $this->tempDir . '/a/b/c/d/e/f';
        mkdir($deepDir, 0777, true);

        $root = ProjectRootFinder::find($deepDir);

        $this->assertNull($root);
    }

    #[Test]
    public function it_finds_nearest_composer_json_in_hierarchy(): void
    {
        $outerProject = $this->tempDir . '/outer';
        $innerProject = $outerProject . '/packages/inner';
        
        mkdir($innerProject, 0777, true);
        
        $this->createComposerJson($outerProject);
        $this->createComposerJson($innerProject);

        $root = ProjectRootFinder::find($innerProject);

        $this->assertEquals(realpath($innerProject), $root);
    }

    protected function createComposerJson(string $directory): void
    {
        file_put_contents(
            $directory . '/composer.json',
            json_encode(['name' => 'test/package'])
        );
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
