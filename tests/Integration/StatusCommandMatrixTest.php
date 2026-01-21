<?php

declare(strict_types=1);

namespace Tests\Integration;

use Atlas\Console\Commands\StatusCommand;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Tests\Support\DriverMatrixHelpers;

final class StatusCommandMatrixTest extends TestCase
{
    use DriverMatrixHelpers;

    #[Test]
    #[DataProvider('driverProvider')]
    public function testStatusCommandShowsDriver(string $driver): void
    {
        $connectionManager = $this->createConnectionManager($driver);
        $commandTester = $this->createCommandTester($connectionManager);

        $commandTester->execute([
            '--connection' => 'default',
        ]);

        $this->assertSame(0, $commandTester->getStatusCode());
        $this->assertStringContainsString("Driver: {$driver}", $commandTester->getDisplay());
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
        $command = new StatusCommand($connectionManager);
        $application = new Application();
        $application->add($command);

        return new CommandTester($command);
    }
}
