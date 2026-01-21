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
}
