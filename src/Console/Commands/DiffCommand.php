<?php

declare(strict_types=1);

namespace Atlas\Console\Commands;

use Atlas\Connection\ConnectionManager;
use Atlas\Database\MySqlDriver;
use Atlas\Exceptions\ConnectionException;
use Atlas\Schema\Comparison\SchemaComparator;
use Atlas\Schema\Grammars\MySqlGrammar;
use Atlas\Schema\Loader\SchemaLoader;
use Atlas\Support\ProjectRootFinder;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class DiffCommand extends Command
{
    public function __construct(
        private ConnectionManager $connectionManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('diff')
            ->setDescription('Generate SQL to migrate database to match schema definitions')
            ->addOption('path', 'p', InputOption::VALUE_OPTIONAL, 'Path to scan for schema classes')
            ->addOption('yaml-path', 'y', InputOption::VALUE_OPTIONAL, 'Path to scan for YAML schema files')
            ->addOption('namespace', null, InputOption::VALUE_OPTIONAL, 'Namespace to scan for schema classes', 'App')
            ->addOption('connection', 'c', InputOption::VALUE_OPTIONAL, 'Database connection name', 'default')
            ->addOption('output', 'o', InputOption::VALUE_OPTIONAL, 'Output file path (optional)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $definitions = $this->loadSchemaDefinitions($input, $io);

            if (empty($definitions)) {
                $io->warning('No schema definitions found.');
                return Command::SUCCESS;
            }

            $io->note(sprintf('Found %d table definition(s)', count($definitions)));

            $connection = $this->getConnection($input);
            $changes = $this->compareSchemas($definitions, $connection);
            $sql = $this->generateMigrationSql($changes);

            $this->displayResults($io, $sql, $input);

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }
    }

    protected function loadSchemaDefinitions(InputInterface $input, SymfonyStyle $io): array
    {
        $loader = $this->createSchemaLoader();
        $path = $input->getOption('path');
        $yamlPath = $input->getOption('yaml-path');
        $namespace = $input->getOption('namespace');

        if ($path !== null || $yamlPath !== null) {
            return $this->loadFromSpecifiedPaths($loader, $path, $yamlPath, $namespace);
        }

        return $this->loadFromProjectRoot($loader, $namespace, $io);
    }

    protected function loadFromSpecifiedPaths(
        SchemaLoader $loader,
        ?string $path,
        ?string $yamlPath,
        string $namespace
    ): array {
        $definitions = [];

        if ($path !== null) {
            $definitions = array_merge(
                $definitions,
                $loader->loadFromDirectory($path, $namespace)
            );
        }

        if ($yamlPath !== null) {
            $definitions = array_merge(
                $definitions,
                $loader->loadFromYamlDirectory($yamlPath)
            );
        }

        return $definitions;
    }

    protected function loadFromProjectRoot(
        SchemaLoader $loader,
        string $namespace,
        SymfonyStyle $io
    ): array {
        $projectRoot = ProjectRootFinder::find();

        if ($projectRoot === null) {
            throw ConnectionException::invalidConfiguration(
                'project-root',
                'Could not find project root (composer.json). Please specify --path or --yaml-path explicitly.'
            );
        }

        $io->note("Scanning project root: {$projectRoot}");

        return array_merge(
            $loader->loadFromDirectory($projectRoot, $namespace),
            $loader->loadFromYamlDirectory($projectRoot)
        );
    }

    protected function getConnection(InputInterface $input): \PDO
    {
        $connectionName = $input->getOption('connection');

        return $this->connectionManager->connection($connectionName);
    }

    protected function compareSchemas(array $definitions, \PDO $connection): array
    {
        $driver = new MySqlDriver($connection);
        $comparator = new SchemaComparator($driver);

        return $comparator->compare($definitions);
    }

    protected function generateMigrationSql(array $changes): array
    {
        $grammar = new MySqlGrammar();
        $sql = [];

        foreach ($changes as $change) {
            $sql = array_merge($sql, $grammar->compile($change));
        }

        return $sql;
    }

    protected function displayResults(SymfonyStyle $io, array $sql, InputInterface $input): void
    {
        if (empty($sql)) {
            $io->success('Database schema is up to date!');
            return;
        }

        $this->displaySqlStatements($io, $sql);
        $this->saveToFileIfRequested($io, $sql, $input);
    }

    protected function displaySqlStatements(SymfonyStyle $io, array $sql): void
    {
        $io->section('Migration SQL');

        foreach ($sql as $statement) {
            $io->writeln($statement . ';');
        }

        $io->newLine();
        $io->note(sprintf('Generated %d SQL statement(s)', count($sql)));
    }

    protected function saveToFileIfRequested(SymfonyStyle $io, array $sql, InputInterface $input): void
    {
        $outputPath = $input->getOption('output');

        if ($outputPath === null) {
            return;
        }

        $this->saveSqlToFile($outputPath, $sql);
        $io->success("SQL saved to: {$outputPath}");
    }

    protected function saveSqlToFile(string $path, array $sql): void
    {
        $content = implode(";\n", $sql) . ";\n";
        file_put_contents($path, $content);
    }

    protected function createSchemaLoader(): SchemaLoader
    {
        return $this->connectionManager->getSchemaLoader();
    }
}