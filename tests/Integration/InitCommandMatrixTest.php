<?php

declare(strict_types=1);

namespace Tests\Integration;

use Atlas\Console\Commands\InitCommand;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Tests\Support\DriverMatrixHelpers;

final class InitCommandMatrixTest extends TestCase
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
    #[DataProvider('driverProvider')]
    public function testInitCommandWorksAcrossDrivers(string $driver): void
    {
        $connectionManager = $this->createConnectionManager($driver);
        $pdo = $connectionManager->connection('default');

        $this->resetDatabase($pdo, $driver);
        $this->createBasicTable($pdo, $driver, 'matrix_init');

        $outputPath = $this->createTemporaryDirectory('atlas_init_matrix_');
        $this->directories[] = $outputPath;
        $commandTester = $this->createCommandTester($connectionManager);

        $commandTester->execute([
            '--path' => $outputPath,
            '--namespace' => 'Tests\\Generated\\Schema',
            '--dry-run' => true,
            '--connection' => 'default',
        ]);

        $this->assertSame(0, $commandTester->getStatusCode());
        $this->assertStringContainsString('Dry run mode', $commandTester->getDisplay());
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
        $command = new InitCommand($connectionManager);
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
