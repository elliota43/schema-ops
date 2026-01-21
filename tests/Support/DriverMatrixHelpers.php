<?php

declare(strict_types=1);

namespace Tests\Support;

use Atlas\Connection\ConnectionManager;
use PDO;

trait DriverMatrixHelpers
{
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

    protected function createBasicTable(PDO $pdo, string $driver, string $tableName): void
    {
        $sql = match ($driver) {
            'pgsql' => "CREATE TABLE \"{$tableName}\" (id SERIAL PRIMARY KEY, email VARCHAR(255) NOT NULL)",
            'sqlite' => "CREATE TABLE \"{$tableName}\" (id INTEGER PRIMARY KEY AUTOINCREMENT, email TEXT NOT NULL)",
            default => "CREATE TABLE `{$tableName}` (id BIGINT AUTO_INCREMENT PRIMARY KEY, email VARCHAR(255) NOT NULL)",
        };

        $pdo->exec($sql);
    }

    protected function createTemporaryDirectory(string $prefix): string
    {
        $path = sys_get_temp_dir() . "/{$prefix}" . uniqid();
        mkdir($path, 0777, true);

        return $path;
    }
}
