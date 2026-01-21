<?php

declare(strict_types=1);

namespace Tests\Console\Commands;

use Atlas\Console\Commands\InitCommand;
use PDO;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Tests\Support\DriverMatrixHelpers;

final class InitCommandTest extends TestCase
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
    public function it_generates_yaml_schema_by_default(): void
    {
        $connectionManager = $this->createConnectionManager('sqlite');
        $pdo = $connectionManager->connection('default');

        $this->resetDatabase($pdo, 'sqlite');
        $this->createBasicTable($pdo, 'sqlite', 'users');

        $outputPath = $this->createTemporaryDirectory('atlas_init_yaml_');
        $this->directories[] = $outputPath;

        $commandTester = $this->createCommandTester($connectionManager);
        $commandTester->execute([
            '--path' => $outputPath,
            '--connection' => 'default',
        ]);

        $this->assertSame(0, $commandTester->getStatusCode());
        $this->assertFileExists("{$outputPath}/atlas.schema.yaml");
        $this->assertStringContainsString('tables:', file_get_contents("{$outputPath}/atlas.schema.yaml"));
    }

    #[Test]
    public function it_updates_existing_classes_when_attributes_flag_is_used(): void
    {
        $connectionManager = $this->createConnectionManager('sqlite');
        $pdo = $connectionManager->connection('default');

        $this->resetDatabase($pdo, 'sqlite');
        $this->createInitTable($pdo);

        $outputPath = $this->createTemporaryDirectory('atlas_init_attributes_');
        $this->directories[] = $outputPath;

        $this->seedExistingClass($outputPath);

        $commandTester = $this->createCommandTester($connectionManager);
        $commandTester->execute([
            '--path' => $outputPath,
            '--namespace' => 'Tests\\Fixtures\\Init',
            '--attributes' => true,
            '--connection' => 'default',
        ]);

        $this->assertSame(0, $commandTester->getStatusCode());

        $contents = file_get_contents("{$outputPath}/User.php");
        $this->assertStringContainsString('#[Table(name: \'users\')]', $contents);
        $this->assertMatchesRegularExpression('/#\[Column[^\]]*\]\s+public string \$email;/', $contents);
        $this->assertStringContainsString('public string $name;', $contents);
        $this->assertStringContainsString('public int $id;', $contents);
    }

    protected function createInitTable(PDO $pdo): void
    {
        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, email TEXT NOT NULL, name TEXT NOT NULL)');
    }

    protected function seedExistingClass(string $path): void
    {
        $content = <<<PHP
<?php

namespace Tests\\Fixtures\\Init;

class User
{
    public string \$email;
}
PHP;

        file_put_contents("{$path}/User.php", $content);
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
