<?php

declare(strict_types=1);

namespace Tests\Integration;

use Atlas\Connection\ConnectionManager;
use Atlas\Console\Commands\MigrateCommand;
use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Yaml\Yaml;

final class MigrateCommandMatrixTest extends TestCase
{
    private array $yamlDirectories = [];

    protected function tearDown(): void
    {
        $this->cleanupYamlFixtures();
    }

    #[Test]
    #[DataProvider('matrixProvider')]
    public function testMigratesAcrossDriversAndSchemaLayouts(
        string $driver,
        string $schemaType,
        ?string $yamlLayout
    ): void {
        $connectionManager = $this->createConnectionManager($driver);
        $pdo = $connectionManager->connection('default');

        $this->resetDatabase($pdo, $driver);

        $commandTester = $this->createCommandTester($connectionManager);
        $options = ['--force' => true];

        if ($schemaType === 'attributes') {
            $options['--path'] = $this->getPhpFixturePath();
        }

        if ($schemaType === 'yaml') {
            $options['--yaml-path'] = $this->createYamlLayout($yamlLayout);
        }

        $commandTester->execute($options);

        foreach ($this->expectedTables($schemaType) as $table) {
            $this->assertTableExists($pdo, $driver, $table);
        }

        $this->assertSame(0, $commandTester->getStatusCode());
    }

    public static function matrixProvider(): array
    {
        $drivers = ['mysql', 'pgsql', 'sqlite'];
        $yamlLayouts = ['single_file', 'flat_directory', 'scattered_directories'];

        $cases = [];

        foreach ($drivers as $driver) {
            $cases["{$driver}_attributes"] = [$driver, 'attributes', null];

            foreach ($yamlLayouts as $layout) {
                $cases["{$driver}_yaml_{$layout}"] = [$driver, 'yaml', $layout];
            }
        }

        return $cases;
    }

    protected function createCommandTester(ConnectionManager $connectionManager): CommandTester
    {
        $command = new MigrateCommand($connectionManager);
        $application = new Application();
        $application->add($command);

        return new CommandTester($command);
    }

    protected function createConnectionManager(string $driver): ConnectionManager
    {
        return new ConnectionManager([
            'default' => $this->buildConnectionConfig($driver),
        ]);
    }

    protected function buildConnectionConfig(string $driver): array
    {
        return match ($driver) {
            'pgsql' => [
                'driver' => 'pgsql',
                'host' => getenv('POSTGRES_HOST') ?: '127.0.0.1',
                'port' => getenv('POSTGRES_PORT') ?: 5433,
                'database' => getenv('POSTGRES_DATABASE') ?: 'test_schema',
                'username' => getenv('POSTGRES_USERNAME') ?: 'atlas',
                'password' => getenv('POSTGRES_PASSWORD') ?: 'atlas',
            ],
            'sqlite' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
            ],
            default => [
                'driver' => 'mysql',
                'host' => getenv('DB_HOST') ?: '127.0.0.1',
                'port' => getenv('DB_PORT') ?: 3306,
                'database' => getenv('DB_DATABASE') ?: 'test_schema',
                'username' => getenv('DB_USERNAME') ?: 'atlas',
                'password' => getenv('DB_PASSWORD') ?: 'atlas',
            ],
        };
    }

    protected function expectedTables(string $schemaType): array
    {
        return $schemaType === 'attributes'
            ? ['matrix_users']
            : ['matrix_users', 'matrix_posts'];
    }

    protected function getPhpFixturePath(): string
    {
        return __DIR__ . '/../Fixtures/MatrixSchemas';
    }

    protected function createYamlLayout(?string $layout): string
    {
        $path = $this->createTemporaryDirectory('atlas_yaml_matrix_');
        $tables = $this->yamlTables();
        $layout = $layout ?? 'flat_directory';

        if ($layout === 'single_file') {
            $this->createYamlFixtureFile($path, 'schema.schema.yaml', ['tables' => $tables]);
            return $path;
        }

        if ($layout === 'flat_directory') {
            foreach ($tables as $tableName => $table) {
                $this->createYamlFixtureFile($path, "{$tableName}.schema.yaml", [
                    'tables' => [$tableName => $table],
                ]);
            }

            return $path;
        }

        $usersPath = "{$path}/schemas/users";
        $postsPath = "{$path}/schemas/posts";

        $this->createYamlFixtureFile($usersPath, 'matrix_users.schema.yaml', [
            'tables' => ['matrix_users' => $tables['matrix_users']],
        ]);
        $this->createYamlFixtureFile($postsPath, 'matrix_posts.schema.yaml', [
            'tables' => ['matrix_posts' => $tables['matrix_posts']],
        ]);

        return $path;
    }

    protected function yamlTables(): array
    {
        return [
            'matrix_users' => [
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
                    'name' => [
                        'type' => 'varchar',
                        'length' => 100,
                        'nullable' => false,
                    ],
                    'is_active' => [
                        'type' => 'boolean',
                        'default' => true,
                    ],
                ],
            ],
            'matrix_posts' => [
                'columns' => [
                    'id' => [
                        'type' => 'integer',
                        'auto_increment' => true,
                        'primary' => true,
                    ],
                    'title' => [
                        'type' => 'varchar',
                        'length' => 255,
                        'nullable' => false,
                    ],
                    'is_published' => [
                        'type' => 'boolean',
                        'default' => false,
                    ],
                ],
            ],
        ];
    }

    protected function createYamlFixtureFile(string $path, string $filename, array $content): void
    {
        if (! is_dir($path)) {
            mkdir($path, 0777, true);
        }

        $yaml = Yaml::dump($content, 10, 2);
        file_put_contents("{$path}/{$filename}", $yaml);
    }

    protected function createTemporaryDirectory(string $prefix): string
    {
        $path = sys_get_temp_dir() . "/{$prefix}" . uniqid();
        mkdir($path, 0777, true);

        $this->yamlDirectories[] = $path;

        return $path;
    }

    protected function cleanupYamlFixtures(): void
    {
        foreach (array_unique($this->yamlDirectories) as $directory) {
            $this->removeDirectory($directory);
        }

        $this->yamlDirectories = [];
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

    protected function resetDatabase(PDO $pdo, string $driver): void
    {
        if ($driver === 'mysql') {
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        }

        if ($driver === 'sqlite') {
            $pdo->exec('PRAGMA foreign_keys = OFF');
        }

        foreach ($this->getTables($pdo, $driver) as $table) {
            $pdo->exec($this->dropTableStatement($driver, $table));
        }

        if ($driver === 'mysql') {
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        }

        if ($driver === 'sqlite') {
            $pdo->exec('PRAGMA foreign_keys = ON');
        }
    }

    protected function getTables(PDO $pdo, string $driver): array
    {
        return match ($driver) {
            'pgsql' => $pdo->query("SELECT tablename FROM pg_tables WHERE schemaname = 'public'")
                ->fetchAll(PDO::FETCH_COLUMN),
            'sqlite' => $pdo->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'")
                ->fetchAll(PDO::FETCH_COLUMN),
            default => $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN),
        };
    }

    protected function dropTableStatement(string $driver, string $table): string
    {
        return match ($driver) {
            'pgsql' => "DROP TABLE IF EXISTS \"{$table}\" CASCADE",
            'sqlite' => "DROP TABLE IF EXISTS \"{$table}\"",
            default => "DROP TABLE IF EXISTS `{$table}`",
        };
    }

    protected function assertTableExists(PDO $pdo, string $driver, string $table): void
    {
        $tables = $this->getTables($pdo, $driver);

        $this->assertContains($table, $tables);
    }
}
