<?php

namespace Atlas\Console\Commands;

use Atlas\Connection\ConnectionManager;
use Atlas\Database\Drivers\DriverInterface;
use Atlas\Database\Drivers\MySqlDriver;
use Atlas\Database\Drivers\PostgresDriver;
use Atlas\Database\Drivers\SQLiteDriver;
use Atlas\Database\MySqlTypeNormalizer;
use Atlas\Database\Normalizers\PostgresTypeNormalizer;
use Atlas\Database\Normalizers\SQLiteTypeNormalizer;
use Atlas\Database\Normalizers\TypeNormalizerInterface;
use Atlas\Schema\Generation\SchemaClassGenerator;
use Atlas\Schema\Generation\SchemaClassUpdater;
use Atlas\Schema\Generation\YamlSchemaGenerator;
use Atlas\Schema\Definition\TableDefinition;
use PDO;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'schema:init', description: 'Generate schema classes from existing database')]
class InitCommand extends Command
{
    public function __construct(
        private ?ConnectionManager $connectionManager = null,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('path', 'p', InputOption::VALUE_REQUIRED,
                'Path to store schema output', 'src/Schema')
            ->addOption('namespace', null, InputOption::VALUE_REQUIRED,
                'Namespace for schema classes', 'App\\Schema')
            ->addOption('attributes', null, InputOption::VALUE_NONE,
                'Generate PHP schema classes instead of YAML')
            ->addOption('force', 'f', InputOption::VALUE_NONE,
                'Overwrite existing files without prompting')
            ->addOption('dry-run', null, InputOption::VALUE_NONE,
                'Show what would be generated without writing files')
            ->addOption('connection', 'c', InputOption::VALUE_REQUIRED,
                'Connection name to use', 'default');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $connectionName = $input->getOption('connection');

        // Connect and introspect
        $pdo = $this->getConnection($connectionName);
        $driver = $this->getDriver($connectionName, $pdo);
        $normalizer = $this->getNormalizer($connectionName);

        $io->section('Introspecting Database Schema');
        $tables = $driver->getCurrentSchema();
        $io->text(sprintf('Found %d table(s) in database', count($tables)));

        $outputPath = $input->getOption('path');
        $dryRun = (bool) $input->getOption('dry-run');

        if ($input->getOption('attributes')) {
            $this->generateAttributeSchemas($io, $tables, $outputPath, $input, $dryRun);
            return Command::SUCCESS;
        }

        $this->generateYamlSchema($io, $tables, $outputPath, $dryRun);

        return Command::SUCCESS;
    }

    protected function tableNameToClassName(string $tableName): string
    {
        // users → User
        // order_items → OrderItem
        // Convert snake_case to PascalCase, singularize
        $singular = $this->singularize($tableName);
        $parts = explode('_', $singular);
        return implode('', array_map('ucfirst', $parts));
    }

    protected function singularize(string $word): string
    {
        // Simple singularization (basic rules)
        if (str_ends_with($word, 'ies')) {
            return substr($word, 0, -3) . 'y';
        }
        if (str_ends_with($word, 'ses') || str_ends_with($word, 'xes')) {
            return substr($word, 0, -2);
        }
        if (str_ends_with($word, 's') && !str_ends_with($word, 'ss')) {
            return substr($word, 0, -1);
        }
        return $word;
    }

    protected function generateAttributeSchemas(
        SymfonyStyle $io,
        array $tables,
        string $outputPath,
        InputInterface $input,
        bool $dryRun
    ): void {
        $namespace = $input->getOption('namespace');

        $this->ensureDirectoryExists($outputPath, $dryRun);

        $generator = new SchemaClassGenerator($namespace);
        $updater = new SchemaClassUpdater();

        $stats = ['created' => 0, 'updated' => 0, 'skipped' => 0];

        foreach ($tables as $tableName => $tableDefinition) {
            $io->section("Processing table: `{$tableName}`");
            $className = $this->tableNameToClassName($tableName);

            $classContent = $generator->generate($className, $tableDefinition);
            $filePath = "{$outputPath}/{$className}.php";

            if (file_exists($filePath) && ! $input->getOption('force')) {
                $io->text("Updating existing class: {$className}");
                $updated = $this->updateExistingClass($updater, $filePath, $tableDefinition, $className, $dryRun);
                $this->recordAttributeUpdate($io, $filePath, $updated, $dryRun, $stats);
                continue;
            }

            $io->text("Generating new class: {$className}");

            if ($dryRun) {
                $io->block($classContent, null, 'fg=gray', ' ', true);
            } else {
                file_put_contents($filePath, $classContent);
                $io->text("<fg=green>✓ Created {$filePath}</>");
            }

            $stats['created']++;
        }

        $this->displaySummary($io, $stats, $dryRun);
    }

    protected function generateYamlSchema(
        SymfonyStyle $io,
        array $tables,
        string $outputPath,
        bool $dryRun
    ): void {
        $generator = new YamlSchemaGenerator();
        $yaml = $generator->generate($tables);

        $targetPath = $this->resolveYamlOutputPath($outputPath);

        $io->section("Generating YAML schema: {$targetPath}");

        if ($dryRun) {
            $io->block($yaml, null, 'fg=gray', ' ', true);
            $io->note('Dry run mode - no files were modified');
            return;
        }

        $this->ensureDirectoryExists(dirname($targetPath), false);
        file_put_contents($targetPath, $yaml);
        $io->success("Schema YAML saved to: {$targetPath}");
    }

    protected function resolveYamlOutputPath(string $path): string
    {
        if ($this->isYamlFilePath($path)) {
            return $path;
        }

        return "{$path}/atlas.schema.yaml";
    }

    protected function isYamlFilePath(string $path): bool
    {
        return str_ends_with($path, '.yaml') || str_ends_with($path, '.yml');
    }

    protected function ensureDirectoryExists(string $path, bool $dryRun): void
    {
        if (is_dir($path) || $dryRun) {
            return;
        }

        mkdir($path, 0755, true);
    }

    protected function displaySummary(SymfonyStyle $io, array $stats, bool $dryRun): void
    {
        $io->newLine();
        $io->section('Summary');
        $io->table(
            ['Action', 'Count'],
            [
                ['Created', $stats['created']],
                ['Updated', $stats['updated']],
                ['Skipped', $stats['skipped']],
            ]
        );

        if ($dryRun) {
            $io->note('Dry run mode - no files were modified');
            return;
        }

        $io->success('Schema generation completed!');
    }

    protected function updateExistingClass(
        SchemaClassUpdater $updater,
        string $filePath,
        TableDefinition $tableDefinition,
        string $className,
        bool $dryRun
    ): bool {
        $existingContent = file_get_contents($filePath);
        $updatedContent = $updater->update($existingContent, $tableDefinition, $className);

        if ($updatedContent === $existingContent) {
            return false;
        }

        if (! $dryRun) {
            file_put_contents($filePath, $updatedContent);
        }

        return true;
    }

    protected function recordAttributeUpdate(
        SymfonyStyle $io,
        string $filePath,
        bool $updated,
        bool $dryRun,
        array &$stats
    ): void {
        if (! $updated) {
            $io->text("<fg=yellow>⊘ Skipped (already up to date)</>");
            $stats['skipped']++;
            return;
        }

        if ($dryRun) {
            $io->text("<fg=green>✓ Updated {$filePath} (dry run)</>");
        } else {
            $io->text("<fg=green>✓ Updated {$filePath}</>");
        }

        $stats['updated']++;
    }

    protected function getConnection(string $connectionName): PDO
    {
        if ($this->connectionManager) {
            return $this->connectionManager->connection($connectionName);
        }

        return $this->createDatabaseConnection();
    }

    protected function getDriver(string $connectionName, PDO $pdo): DriverInterface
    {
        if ($this->connectionManager) {
            return $this->connectionManager->getDriver($connectionName);
        }

        return $this->createDriverFromEnv($pdo);
    }

    protected function getNormalizer(string $connectionName): TypeNormalizerInterface
    {
        if ($this->connectionManager) {
            return $this->connectionManager->getNormalizer($connectionName);
        }

        return $this->createNormalizerFromEnv();
    }

    protected function createDatabaseConnection(): PDO
    {
        $driver = $this->getEnvDriver();
        $dsn = $this->buildDsnFromEnv($driver);

        return new PDO($dsn, getenv('DB_USERNAME') ?: 'root', getenv('DB_PASSWORD') ?: '', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
    }

    protected function createDriverFromEnv(PDO $pdo): DriverInterface
    {
        return match ($this->getEnvDriver()) {
            'pgsql' => new PostgresDriver($pdo),
            'sqlite' => new SQLiteDriver($pdo),
            default => new MySqlDriver($pdo),
        };
    }

    protected function createNormalizerFromEnv(): TypeNormalizerInterface
    {
        return match ($this->getEnvDriver()) {
            'pgsql' => new PostgresTypeNormalizer(),
            'sqlite' => new SQLiteTypeNormalizer(),
            default => new MySqlTypeNormalizer(),
        };
    }

    protected function getEnvDriver(): string
    {
        return getenv('DB_DRIVER') ?: 'mysql';
    }

    protected function buildDsnFromEnv(string $driver): string
    {
        return match ($driver) {
            'pgsql' => $this->buildPostgresDsn(),
            'sqlite' => $this->buildSqliteDsn(),
            default => $this->buildMySqlDsn(),
        };
    }

    protected function buildMySqlDsn(): string
    {
        $host = getenv('DB_HOST') ?: 'localhost';
        $port = getenv('DB_PORT') ?: '3306';
        $database = getenv('DB_DATABASE') ?: 'test';

        return "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";
    }

    protected function buildPostgresDsn(): string
    {
        $host = getenv('DB_HOST') ?: 'localhost';
        $port = getenv('DB_PORT') ?: '5432';
        $database = getenv('DB_DATABASE') ?: 'test';

        return "pgsql:host={$host};port={$port};dbname={$database}";
    }

    protected function buildSqliteDsn(): string
    {
        $database = getenv('DB_DATABASE') ?: ':memory:';

        return "sqlite:{$database}";
    }
}
