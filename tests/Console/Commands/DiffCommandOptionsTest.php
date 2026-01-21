<?php

declare(strict_types=1);

namespace Tests\Console\Commands;

use Atlas\Console\Commands\DiffCommand;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Yaml\Yaml;
use Tests\Support\DriverMatrixHelpers;

final class DiffCommandOptionsTest extends TestCase
{
    use DriverMatrixHelpers;

    private array $directories = [];

    protected function tearDown(): void
    {
        foreach ($this->directories as $directory) {
            $this->removeDirectory($directory);
        }

        $this->directories = [];
    }

    #[Test]
    public function it_supports_connection_and_output_options(): void
    {
        $connectionManager = $this->createConnectionManager('sqlite');
        $pdo = $connectionManager->connection('default');

        $this->resetDatabase($pdo, 'sqlite');

        $yamlPath = $this->createYamlSchemaDirectory();
        $outputFile = "{$yamlPath}/migration.sql";

        $commandTester = $this->createCommandTester($connectionManager);
        $commandTester->execute([
            '--yaml-path' => $yamlPath,
            '--connection' => 'default',
            '--output' => $outputFile,
        ]);

        $this->assertSame(0, $commandTester->getStatusCode());
        $this->assertFileExists($outputFile);
        $this->assertStringContainsString('CREATE TABLE', file_get_contents($outputFile));
    }

    protected function createYamlSchemaDirectory(): string
    {
        $path = $this->createTemporaryDirectory('atlas_diff_options_');
        $this->directories[] = $path;

        $content = [
            'tables' => [
                'diff_users' => [
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
        file_put_contents("{$path}/diff_users.schema.yaml", $yaml);

        return $path;
    }

    protected function createCommandTester($connectionManager): CommandTester
    {
        $command = new DiffCommand($connectionManager);
        $application = new Application();
        $application->add($command);

        return new CommandTester($command);
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
