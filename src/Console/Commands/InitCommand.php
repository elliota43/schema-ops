<?php

namespace Atlas\Console\Commands;

use Atlas\Database\Drivers\MySqlDriver;
use Atlas\Database\Drivers\MySqlTypeNormalizer;
use Atlas\Schema\Discovery\ClassFinder;
use Atlas\Schema\Generation\SchemaClassGenerator;
use Atlas\Schema\Parser\SchemaParser;
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
    protected function configure(): void
    {
        $this
            ->addOption('path', 'p', InputOption::VALUE_REQUIRED,
                'Path to store schema classes', 'src/Schema')
            ->addOption('namespace', null, InputOption::VALUE_REQUIRED,
                'Namespace for schema classes', 'App\\Schema')
            ->addOption('force', 'f', InputOption::VALUE_NONE,
                'Overwrite existing files without prompting')
            ->addOption('dry-run', null, InputOption::VALUE_NONE,
                'Show what would be generated without writing files');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Connect and introspect
        $pdo = $this->createDatabaseConnection();
        $driver = new MySqlDriver($pdo);

        $io->section('Introspecting Database Schema');
        $tables = $driver->getCurrentSchema();
        $io->text(sprintf('Found %d table(s) in database', count($tables)));

        // Setup paths
        $outputPath = $input->getOption('path');
        $namespace = $input->getOption('namespace');
        $dryRun = $input->getOption('dry-run');

        if (!is_dir($outputPath) && !$dryRun) {
            mkdir($outputPath, 0755, true);
        }

        // Find existing schema classes
        $existingMap = $this->mapExistingClasses($outputPath);

        // Generate/update schemas
        $generator = new SchemaClassGenerator($namespace);

        $stats = ['created' => 0, 'updated' => 0, 'skipped' => 0];

        foreach ($tables as $tableName => $tableDefinition) {
            $io->section("Processing table: `{$tableName}`");

            // Check if class already exists
            if (isset($existingMap[$tableName])) {
                // For MVP: skip existing classes (updating is complex)
                $existingClass = $existingMap[$tableName];
                $io->text("Found existing class: {$existingClass}");
                $io->text("<fg=yellow>⊘ Skipped (already exists)</>");
                $stats['skipped']++;
            } else {
                // Generate new class
                $className = $this->tableNameToClassName($tableName);
                $io->text("Generating new class: {$className}");

                $classContent = $generator->generate($className, $tableDefinition);
                $filePath = $outputPath . '/' . $className . '.php';

                if ($dryRun) {
                    $io->block($classContent, null, 'fg=gray', ' ', true);
                } else {
                    file_put_contents($filePath, $classContent);
                    $io->text("<fg=green>✓ Created {$filePath}</>");
                }

                $stats['created']++;
            }
        }

        // Summary
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
        } else {
            $io->success('Schema generation completed!');
        }

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

    protected function mapExistingClasses(string $basePath): array
    {
        if (!is_dir($basePath)) {
            return [];
        }

        $map = [];
        $parser = new SchemaParser(new MySqlTypeNormalizer());

        try {
            $finder = new ClassFinder();
            $existingClasses = $finder->findInDirectory($basePath);

            foreach ($existingClasses as $className) {
                try {
                    $tableDefinition = $parser->parse($className);
                    $map[$tableDefinition->tableName] = $className;
                } catch (\Exception $e) {
                    // Skip classes that can't be parsed
                    continue;
                }
            }
        } catch (\Exception $e) {
            // If ClassFinder fails, return empty map
        }

        return $map;
    }

    protected function createDatabaseConnection(): PDO
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            getenv('DB_HOST') ?: 'localhost',
            getenv('DB_PORT') ?: '3306',
            getenv('DB_DATABASE') ?: 'test'
        );

        return new PDO(
            $dsn,
            getenv('DB_USERNAME') ?: 'root',
            getenv('DB_PASSWORD') ?: '',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
    }
}
