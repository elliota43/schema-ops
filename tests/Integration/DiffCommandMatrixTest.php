<?php

declare(strict_types=1);

namespace Tests\Integration;

use Atlas\Console\Commands\DiffCommand;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Yaml\Yaml;
use Tests\Support\DriverMatrixHelpers;

final class DiffCommandMatrixTest extends TestCase
{
    use DriverMatrixHelpers;

    private array $yamlDirectories = [];

    protected function tearDown(): void
    {
        foreach ($this->yamlDirectories as $directory) {
            $this->removeDirectory($directory);
        }

        $this->yamlDirectories = [];
    }

    #[Test]
    #[DataProvider('driverProvider')]
    public function testDiffCommandWorksAcrossDrivers(string $driver): void
    {
        $connectionManager = $this->createConnectionManager($driver);
        $pdo = $connectionManager->connection('default');

        $this->resetDatabase($pdo, $driver);

        $yamlPath = $this->createYamlSchema();

        $commandTester = $this->createCommandTester($connectionManager);
        $commandTester->execute([
            '--yaml-path' => $yamlPath,
            '--connection' => 'default',
        ]);

        $this->assertSame(0, $commandTester->getStatusCode());
        $this->assertStringContainsString('Migration SQL', $commandTester->getDisplay());
        $this->assertStringContainsString('CREATE TABLE', $commandTester->getDisplay());
    }

    public static function driverProvider(): array
    {
        return [
            'mysql' => ['mysql'],
            'pgsql' => ['pgsql'],
            'sqlite' => ['sqlite'],
        ];
    }

    protected function createCommandTester($connectionManager): CommandTester
    {
        $command = new DiffCommand($connectionManager);
        $application = new Application();
        $application->add($command);

        return new CommandTester($command);
    }

    protected function createYamlSchema(): string
    {
        $path = $this->createTemporaryDirectory('atlas_diff_matrix_');
        $content = [
            'tables' => [
                'matrix_diff' => [
                    'columns' => [
                        'id' => [
                            'type' => 'integer',
                            'auto_increment' => true,
                            'primary' => true,
                        ],
                        'email' => [
                            'type' => 'varchar',
                            'length' => 255,
                            'nullable' => false,
                        ],
                    ],
                ],
            ],
        ];

        $yaml = Yaml::dump($content, 10, 2);
        file_put_contents("{$path}/matrix_diff.schema.yaml", $yaml);

        $this->yamlDirectories[] = $path;

        return $path;
    }

    protected function removeDirectory(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }

        $files = array_diff(scandir($path), ['.', '..']);

        foreach ($files as $file) {
            $filePath = "{$path}/{$file}";

            if (is_dir($filePath)) {
                $this->removeDirectory($filePath);
                continue;
            }

            unlink($filePath);
        }

        rmdir($path);
    }
}
