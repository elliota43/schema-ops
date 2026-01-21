<?php

namespace Tests\Support;

use PDO;

final class TestDb
{
    public static function pdo(?string $dbName = null): PDO
    {
        $host = getenv('DB_HOST') ?: '127.0.0.1';
        $port = getenv('DB_PORT') ?: '3306';
        $db   = $dbName ?? (getenv('DB_DATABASE') ?: 'test_schema');
        $user = getenv('DB_USERNAME') ?: 'atlas';
        $pass = getenv('DB_PASSWORD') ?: 'atlas';

        $dsn = "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4";

        return new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
    }

    public static function postgresPdo(?string $dbName = null): PDO
    {
        $host = getenv('POSTGRES_HOST') ?: '127.0.0.1';
        $port = getenv('POSTGRES_PORT') ?: '5433';
        $db   = $dbName ?? (getenv('POSTGRES_DATABASE') ?: 'test_schema');
        $user = getenv('POSTGRES_USERNAME') ?: 'atlas';
        $pass = getenv('POSTGRES_PASSWORD') ?: 'atlas';

        $dsn = "pgsql:host={$host};port={$port};dbname={$db}";

        return new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
    }

    public static function sqlitePdo(?string $path = null): PDO
    {
        // Use in-memory database by default for tests
        // Or specify a path for persistent testing
        $dbPath = $path ?? ':memory:';

        $dsn = "sqlite:{$dbPath}";

        $pdo = new PDO($dsn, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);

        // Enable foreign key support (disabled by default in SQLite)
        $pdo->exec('PRAGMA foreign_keys = ON');

        return $pdo;
    }
}
