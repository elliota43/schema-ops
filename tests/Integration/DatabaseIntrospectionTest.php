<?php

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Atlas\Database\Drivers\MySqlDriver;

class DatabaseIntrospectionTest extends TestCase
{
    private PDO $pdo;
    private MySqlDriver $driver;

    protected function setUp(): void
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            getenv('DB_HOST'), getenv('DB_PORT'), getenv('DB_DATABASE')
        );

        $this->pdo = new PDO($dsn, getenv('DB_USERNAME'), getenv('DB_PASSWORD'), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);

        $this->driver = new MySqlDriver($this->pdo);
    }

    protected function tearDown(): void
    {
        // Clean up test tables in correct order (child tables first due to foreign keys)
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        $this->pdo->exec('DROP TABLE IF EXISTS test_on_update');
        $this->pdo->exec('DROP TABLE IF EXISTS users_fk');
        $this->pdo->exec('DROP TABLE IF EXISTS roles');
        $this->pdo->exec('DROP TABLE IF EXISTS test_indexes');
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    }

    #[Test]
    public function TestCanIntrospectLegacyUsersTable(): void
    {
        $schema = $this->driver->getCurrentSchema();

        $this->assertArrayHasKey('legacy_users', $schema);

        $table = $schema['legacy_users'];
        $this->assertEquals('legacy_users', $table->tableName);

        // Assert specific columns exist from your init script
        $this->assertArrayHasKey('email', $table->columns);
        $this->assertTrue($table->columns['id']->isPrimaryKey());
        $this->assertTrue($table->columns['id']->isAutoIncrement());
    }

    #[Test]
    public function testIntrospectOnUpdate(): void
    {
        $this->pdo->exec("
            CREATE TABLE test_on_update (
                id INT PRIMARY KEY,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )
        ");

        $schema = $this->driver->getCurrentSchema();
        $table = $schema['test_on_update'];

        $this->assertEquals('CURRENT_TIMESTAMP', $table->columns['updated_at']->onUpdate);
    }

    #[Test]
    public function testIntrospectForeignKeys(): void
    {
        $this->pdo->exec("CREATE TABLE roles (id INT PRIMARY KEY)");
        $this->pdo->exec("
            CREATE TABLE users_fk (
                id INT PRIMARY KEY,
                role_id INT,
                FOREIGN KEY (role_id) REFERENCES roles(id)
                    ON DELETE CASCADE
                    ON UPDATE RESTRICT
            )
        ");

        $schema = $this->driver->getCurrentSchema();
        $table = $schema['users_fk'];

        $roleIdColumn = $table->columns['role_id'];
        $this->assertCount(1, $roleIdColumn->foreignKeys);

        $fk = $roleIdColumn->foreignKeys[0];
        $this->assertEquals('roles.id', $fk['references']);
        $this->assertEquals('CASCADE', $fk['onDelete']);
        $this->assertEquals('RESTRICT', $fk['onUpdate']);
    }

    #[Test]
    public function testIntrospectIndexes(): void
    {
        $this->pdo->exec("
            CREATE TABLE test_indexes (
                id INT PRIMARY KEY,
                name VARCHAR(255),
                status VARCHAR(50),
                INDEX idx_name (name),
                INDEX idx_composite (name, status)
            )
        ");

        $schema = $this->driver->getCurrentSchema();
        $table = $schema['test_indexes'];

        $this->assertCount(2, $table->indexes);

        // Find composite index
        $compositeIndex = array_filter($table->indexes, fn($idx) => $idx['name'] === 'idx_composite');
        $this->assertCount(1, $compositeIndex);
        $this->assertEquals(['name', 'status'], reset($compositeIndex)['columns']);
    }
}