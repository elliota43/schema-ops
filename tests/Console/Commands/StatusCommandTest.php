<?php

declare(strict_types=1);

namespace Tests\Console\Commands;

use Atlas\Console\Commands\StatusCommand;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Tests\Support\DriverMatrixHelpers;

final class StatusCommandTest extends TestCase
{
    use DriverMatrixHelpers;

    #[Test]
    public function it_displays_driver_from_connection(): void
    {
        $connectionManager = $this->createConnectionManager('sqlite');
        $commandTester = $this->createCommandTester($connectionManager);

        $commandTester->execute([
            '--connection' => 'default',
        ]);

        $this->assertSame(0, $commandTester->getStatusCode());
        $this->assertStringContainsString('Driver: sqlite', $commandTester->getDisplay());
    }

    protected function createCommandTester($connectionManager): CommandTester
    {
        $command = new StatusCommand($connectionManager);
        $application = new Application();
        $application->add($command);

        return new CommandTester($command);
    }
}
