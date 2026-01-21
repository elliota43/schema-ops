<?php

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Atlas\Database\Drivers\SQLiteDriver;
use Tests\Support\TestDb;

class SQLiteDatabaseIntrospectionTest extends TestCase
{
    private PDO $pdo;
    private SQLiteDriver $driver;

    protected function setUp(): void
    {
        $this->pdo = TestDb::sqlitePdo();
        $this->driver = new SQLiteDriver($this->pdo);

        // Create test_users table for introspection tests
        $this->pdo->exec("
            CREATE TABLE test_users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                email TEXT NOT NULL UNIQUE,
                full_name TEXT,
                is_active INTEGER NOT NULL DEFAULT 1,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                bio TEXT
            )
        ");
    }

    protected function tearDown(): void
    {
        // SQLite in-memory database is destroyed automatically
        // But let's be explicit for file-based tests
        $this->pdo->exec('DROP TABLE IF EXISTS test_child');
        $this->pdo->exec('DROP TABLE IF EXISTS test_parent');
        $this->pdo->exec('DROP TABLE IF EXISTS test_multi_fk');
        $this->pdo->exec('DROP TABLE IF EXISTS test_parent1');
        $this->pdo->exec('DROP TABLE IF EXISTS test_parent2');
        $this->pdo->exec('DROP TABLE IF EXISTS test_users_fk');
        $this->pdo->exec('DROP TABLE IF EXISTS test_roles');
        $this->pdo->exec('DROP TABLE IF EXISTS test_indexes');
        $this->pdo->exec('DROP TABLE IF EXISTS test_users');
        $this->pdo->exec('DROP TABLE IF EXISTS test_composite_pk');
        $this->pdo->exec('DROP TABLE IF EXISTS test_unique_constraint');
        $this->pdo->exec('DROP TABLE IF EXISTS test_types');
        $this->pdo->exec('DROP TABLE IF EXISTS test_defaults');
    }

    #[Test]
    public function testCanIntrospectBasicTable(): void
    {
        $schema = $this->driver->getCurrentSchema();

        $this->assertArrayHasKey('test_users', $schema);

        $table = $schema['test_users'];
        $this->assertEquals('test_users', $table->tableName);

        // Assert specific columns exist
        $this->assertArrayHasKey('email', $table->columns);
        $this->assertArrayHasKey('full_name', $table->columns);
        $this->assertArrayHasKey('is_active', $table->columns);
        $this->assertArrayHasKey('created_at', $table->columns);
        $this->assertArrayHasKey('bio', $table->columns);
    }

    #[Test]
    public function testIntrospectPrimaryKey(): void
    {
        $schema = $this->driver->getCurrentSchema();
        $table = $schema['test_users'];

        $this->assertTrue($table->columns['id']->isPrimaryKey());
        $this->assertFalse($table->columns['email']->isPrimaryKey());
    }

    #[Test]
    public function testIntrospectAutoIncrement(): void
    {
        $schema = $this->driver->getCurrentSchema();
        $table = $schema['test_users'];

        // INTEGER PRIMARY KEY AUTOINCREMENT
        $this->assertTrue($table->columns['id']->isAutoIncrement());
        $this->assertFalse($table->columns['email']->isAutoIncrement());
    }

    #[Test]
    public function testIntrospectNullability(): void
    {
        $schema = $this->driver->getCurrentSchema();
        $table = $schema['test_users'];

        $this->assertFalse($table->columns['email']->isNullable());
        $this->assertTrue($table->columns['full_name']->isNullable());
    }

    #[Test]
    public function testIntrospectDefaultValues(): void
    {
        $schema = $this->driver->getCurrentSchema();
        $table = $schema['test_users'];

        $this->assertEquals('1', $table->columns['is_active']->defaultValue);
        $this->assertEquals('CURRENT_TIMESTAMP', $table->columns['created_at']->defaultValue);
    }

    #[Test]
    public function testIntrospectDataTypes(): void
    {
        $schema = $this->driver->getCurrentSchema();
        $table = $schema['test_users'];

        // Check normalized types
        $this->assertEquals('integer', $table->columns['id']->sqlType);
        $this->assertEquals('text', $table->columns['email']->sqlType);
        $this->assertEquals('text', $table->columns['full_name']->sqlType);
        $this->assertEquals('integer', $table->columns['is_active']->sqlType);
        $this->assertEquals('text', $table->columns['created_at']->sqlType);
        $this->assertEquals('text', $table->columns['bio']->sqlType);
    }

    #[Test]
    public function testIntrospectForeignKeys(): void
    {
        $this->pdo->exec("CREATE TABLE test_roles (id INTEGER PRIMARY KEY, name TEXT)");
        $this->pdo->exec("
            CREATE TABLE test_users_fk (
                id INTEGER PRIMARY KEY,
                role_id INTEGER,
                FOREIGN KEY (role_id) REFERENCES test_roles(id)
                    ON DELETE CASCADE
                    ON UPDATE RESTRICT
            )
        ");

        $schema = $this->driver->getCurrentSchema();
        $table = $schema['test_users_fk'];

        $roleIdColumn = $table->columns['role_id'];
        $this->assertCount(1, $roleIdColumn->foreignKeys);

        $fk = $roleIdColumn->foreignKeys[0];
        $this->assertEquals('test_roles.id', $fk['references']);
        $this->assertEquals('CASCADE', $fk['onDelete']);
        $this->assertEquals('RESTRICT', $fk['onUpdate']);
    }

    #[Test]
    public function testIntrospectIndexes(): void
    {
        $this->pdo->exec("
            CREATE TABLE test_indexes (
                id INTEGER PRIMARY KEY,
                name TEXT,
                status TEXT
            )
        ");
        $this->pdo->exec("CREATE INDEX idx_name ON test_indexes (name)");
        $this->pdo->exec("CREATE INDEX idx_composite ON test_indexes (name, status)");

        $schema = $this->driver->getCurrentSchema();
        $table = $schema['test_indexes'];

        $this->assertCount(2, $table->indexes);

        // Find composite index
        $compositeIndex = array_filter($table->indexes, fn($idx) => $idx['name'] === 'idx_composite');
        $this->assertCount(1, $compositeIndex);
        $this->assertEquals(['name', 'status'], reset($compositeIndex)['columns']);
    }

    #[Test]
    public function testIntrospectUniqueIndex(): void
    {
        $this->pdo->exec("
            CREATE TABLE test_indexes (
                id INTEGER PRIMARY KEY,
                email TEXT
            )
        ");
        $this->pdo->exec("CREATE UNIQUE INDEX idx_email_unique ON test_indexes (email)");

        $schema = $this->driver->getCurrentSchema();
        $table = $schema['test_indexes'];

        $emailIndex = array_filter($table->indexes, fn($idx) => $idx['name'] === 'idx_email_unique');
        $this->assertCount(1, $emailIndex);
        $this->assertTrue(reset($emailIndex)['unique']);
    }

    #[Test]
    public function testIntrospectCompositePrimaryKey(): void
    {
        $this->pdo->exec("
            CREATE TABLE test_composite_pk (
                user_id INTEGER,
                role_id INTEGER,
                assigned_at TEXT,
                PRIMARY KEY (user_id, role_id)
            )
        ");

        $schema = $this->driver->getCurrentSchema();
        $table = $schema['test_composite_pk'];

        $this->assertNotNull($table->compositePrimaryKey);
        $this->assertCount(2, $table->compositePrimaryKey);
        $this->assertContains('user_id', $table->compositePrimaryKey);
        $this->assertContains('role_id', $table->compositePrimaryKey);
    }

    #[Test]
    public function testIntrospectUniqueConstraint(): void
    {
        $this->pdo->exec("
            CREATE TABLE test_unique_constraint (
                id INTEGER PRIMARY KEY,
                email TEXT UNIQUE
            )
        ");

        $schema = $this->driver->getCurrentSchema();
        $table = $schema['test_unique_constraint'];

        $this->assertTrue($table->columns['email']->isUnique());
    }

    #[Test]
    public function testIntrospectVariousDataTypes(): void
    {
        $this->pdo->exec("
            CREATE TABLE test_types (
                id INTEGER PRIMARY KEY,
                int_col INTEGER,
                text_col TEXT,
                real_col REAL,
                blob_col BLOB,
                numeric_col NUMERIC,
                bool_col INTEGER,
                date_col TEXT,
                json_col TEXT
            )
        ");

        $schema = $this->driver->getCurrentSchema();
        $table = $schema['test_types'];

        $this->assertEquals('integer', $table->columns['id']->sqlType);
        $this->assertEquals('integer', $table->columns['int_col']->sqlType);
        $this->assertEquals('text', $table->columns['text_col']->sqlType);
        $this->assertEquals('real', $table->columns['real_col']->sqlType);
        $this->assertEquals('blob', $table->columns['blob_col']->sqlType);
        $this->assertEquals('numeric', $table->columns['numeric_col']->sqlType);
        $this->assertEquals('integer', $table->columns['bool_col']->sqlType);
        $this->assertEquals('text', $table->columns['date_col']->sqlType);
        $this->assertEquals('text', $table->columns['json_col']->sqlType);
    }

    #[Test]
    public function testIntegerPrimaryKeyAutoIncrement(): void
    {
        // Test that INTEGER PRIMARY KEY without explicit AUTOINCREMENT still behaves as auto-increment
        $this->pdo->exec("
            CREATE TABLE test_auto (
                id INTEGER PRIMARY KEY,
                name TEXT
            )
        ");

        $schema = $this->driver->getCurrentSchema();
        $table = $schema['test_auto'];

        $this->assertTrue($table->columns['id']->isAutoIncrement());
        $this->assertTrue($table->columns['id']->isPrimaryKey());
    }

    #[Test]
    public function testMultipleForeignKeysOnSameColumn(): void
    {
        $this->pdo->exec("CREATE TABLE test_parent1 (id INTEGER PRIMARY KEY)");
        $this->pdo->exec("CREATE TABLE test_parent2 (id INTEGER PRIMARY KEY)");
        $this->pdo->exec("
            CREATE TABLE test_multi_fk (
                id INTEGER PRIMARY KEY,
                parent1_id INTEGER REFERENCES test_parent1(id),
                parent2_id INTEGER REFERENCES test_parent2(id)
            )
        ");

        $schema = $this->driver->getCurrentSchema();
        $table = $schema['test_multi_fk'];

        $this->assertCount(1, $table->columns['parent1_id']->foreignKeys);
        $this->assertCount(1, $table->columns['parent2_id']->foreignKeys);
    }

    #[Test]
    public function testDefaultValueParsing(): void
    {
        $this->pdo->exec("
            CREATE TABLE test_defaults (
                id INTEGER PRIMARY KEY,
                status TEXT DEFAULT 'active',
                count INTEGER DEFAULT 0,
                is_enabled INTEGER DEFAULT 0,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            )
        ");

        $schema = $this->driver->getCurrentSchema();
        $table = $schema['test_defaults'];

        $this->assertEquals('active', $table->columns['status']->defaultValue);
        $this->assertEquals('0', $table->columns['count']->defaultValue);
        $this->assertEquals('0', $table->columns['is_enabled']->defaultValue);
        $this->assertEquals('CURRENT_TIMESTAMP', $table->columns['created_at']->defaultValue);
    }

    #[Test]
    public function testCascadingForeignKeyActions(): void
    {
        $this->pdo->exec("CREATE TABLE test_parent (id INTEGER PRIMARY KEY)");
        $this->pdo->exec("
            CREATE TABLE test_child (
                id INTEGER PRIMARY KEY,
                parent_id INTEGER,
                FOREIGN KEY (parent_id) REFERENCES test_parent(id)
                    ON DELETE SET NULL
                    ON UPDATE CASCADE
            )
        ");

        $schema = $this->driver->getCurrentSchema();
        $table = $schema['test_child'];

        $fk = $table->columns['parent_id']->foreignKeys[0];
        $this->assertEquals('SET NULL', $fk['onDelete']);
        $this->assertEquals('CASCADE', $fk['onUpdate']);
    }

    #[Test]
    public function testNoActionForeignKey(): void
    {
        $this->pdo->exec("CREATE TABLE test_parent (id INTEGER PRIMARY KEY)");
        $this->pdo->exec("
            CREATE TABLE test_child (
                id INTEGER PRIMARY KEY,
                parent_id INTEGER,
                FOREIGN KEY (parent_id) REFERENCES test_parent(id)
                    ON DELETE NO ACTION
                    ON UPDATE NO ACTION
            )
        ");

        $schema = $this->driver->getCurrentSchema();
        $table = $schema['test_child'];

        $fk = $table->columns['parent_id']->foreignKeys[0];
        $this->assertEquals('NO ACTION', $fk['onDelete']);
        $this->assertEquals('NO ACTION', $fk['onUpdate']);
    }

    #[Test]
    public function testTypeAffinity(): void
    {
        // Test that SQLite's type affinity system is respected
        $this->pdo->exec("
            CREATE TABLE test_affinity (
                int_type INT,
                varchar_type VARCHAR(255),
                double_type DOUBLE,
                blob_type BLOB
            )
        ");

        $schema = $this->driver->getCurrentSchema();
        $table = $schema['test_affinity'];

        // Verify types are normalized to SQLite preferred names
        $this->assertEquals('integer', $table->columns['int_type']->sqlType);
        $this->assertEquals('text(255)', $table->columns['varchar_type']->sqlType);
        $this->assertEquals('real', $table->columns['double_type']->sqlType);
        $this->assertEquals('blob', $table->columns['blob_type']->sqlType);
    }

    #[Test]
    public function testWithoutRowIdTable(): void
    {
        // Test WITHOUT ROWID tables (INTEGER PRIMARY KEY doesn't auto-increment)
        $this->pdo->exec("
            CREATE TABLE test_without_rowid (
                id INTEGER PRIMARY KEY,
                name TEXT
            ) WITHOUT ROWID
        ");

        $schema = $this->driver->getCurrentSchema();
        $table = $schema['test_without_rowid'];

        // INTEGER PRIMARY KEY in WITHOUT ROWID table should NOT be auto-increment
        $this->assertFalse($table->columns['id']->isAutoIncrement());
        $this->assertTrue($table->columns['id']->isPrimaryKey());
    }

    #[Test]
    public function testDefaultRestrictForeignKey(): void
    {
        // Test default RESTRICT behavior when no ON DELETE/UPDATE specified
        $this->pdo->exec("CREATE TABLE test_parent (id INTEGER PRIMARY KEY)");
        $this->pdo->exec("
            CREATE TABLE test_child (
                id INTEGER PRIMARY KEY,
                parent_id INTEGER REFERENCES test_parent(id)
            )
        ");

        $schema = $this->driver->getCurrentSchema();
        $table = $schema['test_child'];

        $fk = $table->columns['parent_id']->foreignKeys[0];
        // SQLite defaults to NO ACTION, which behaves like RESTRICT
        $this->assertNotNull($fk['onDelete']);
        $this->assertNotNull($fk['onUpdate']);
    }
}
