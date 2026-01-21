<?php

namespace Atlas\Console\Commands;

use Atlas\Connection\ConnectionManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class StatusCommand extends Command
{
    public function __construct(
        private ?ConnectionManager $connectionManager = null,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('schema:status')
            ->setDescription('Show current schema status')
            ->addOption('connection', 'c', InputOption::VALUE_REQUIRED, 'Connection name to use', 'default');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('<info>Atlas Status</info>');
        $output->writeln('Version: 0.1.0');
        $output->writeln('');

        $this->displayLockfileStatus($output);
        $this->displayConnectionStatus($input, $output);

        return Command::SUCCESS;
    }

    protected function displayLockfileStatus(OutputInterface $output): void
    {
        if ($this->lockfileExists()) {
            $output->writeln('<fg=green>✓</> Lockfile found');
            return;
        }

        $output->writeln('<fg=yellow>!</> No lockfile found');
    }

    protected function lockfileExists(): bool
    {
        return file_exists(getcwd() . '/schema.lock');
    }

    protected function displayConnectionStatus(InputInterface $input, OutputInterface $output): void
    {
        $connectionName = $input->getOption('connection');
        $driver = $this->getDriverName($connectionName);
        $database = $this->getDatabaseName();
        $host = $this->getHost();

        $output->writeln("Driver: {$driver}");
        $output->writeln("Database: {$database}");
        $output->writeln("Host: {$host}");
    }

    protected function getDriverName(string $connectionName): string
    {
        if ($this->connectionManager) {
            return $this->connectionManager->getDriverName($connectionName);
        }

        return getenv('DB_DRIVER') ?: 'mysql';
    }

    protected function getDatabaseName(): string
    {
        return getenv('DB_DATABASE') ?: 'test';
    }

    protected function getHost(): string
    {
        return getenv('DB_HOST') ?: '127.0.0.1';
    }
}
