<?php

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Atlas\Database\Drivers\PostgresDriver;
use Tests\Support\TestDb;

class PostgresDatabaseIntrospectionTest extends TestCase
{
    private PDO $pdo;
    private PostgresDriver $driver;

    protected function setUp(): void
    {
        $this->pdo = TestDb::postgresPdo();
        $this->driver = new PostgresDriver($this->pdo);

        // Create test_users table for introspection tests
        $this->pdo->exec("DROP TABLE IF EXISTS test_users CASCADE");
        $this->pdo->exec("
            CREATE TABLE test_users (
                id BIGSERIAL PRIMARY KEY,
                email VARCHAR(255) NOT NULL,
                full_name VARCHAR(100),
                is_active BOOLEAN NOT NULL DEFAULT true,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                bio TEXT
            )
        ");
    }

    protected function tearDown(): void
    {
        // Clean up test tables in correct order (child tables first due to foreign keys)
        $this->pdo->exec('DROP TABLE IF EXISTS test_child CASCADE');
        $this->pdo->exec('DROP TABLE IF EXISTS test_parent CASCADE');
        $this->pdo->exec('DROP TABLE IF EXISTS test_multi_fk CASCADE');
        $this->pdo->exec('DROP TABLE IF EXISTS test_parent1 CASCADE');
        $this->pdo->exec('DROP TABLE IF EXISTS test_parent2 CASCADE');
        $this->pdo->exec('DROP TABLE IF EXISTS test_users_fk CASCADE');
        $this->pdo->exec('DROP TABLE IF EXISTS test_roles CASCADE');
        $this->pdo->exec('DROP TABLE IF EXISTS test_indexes CASCADE');
        $this->pdo->exec('DROP TABLE IF EXISTS test_users CASCADE');
        $this->pdo->exec('DROP TABLE IF EXISTS test_composite_pk CASCADE');
        $this->pdo->exec('DROP TABLE IF EXISTS test_unique_constraint CASCADE');
        $this->pdo->exec('DROP TABLE IF EXISTS test_types CASCADE');
        $this->pdo->exec('DROP TABLE IF EXISTS test_identity CASCADE');
        $this->pdo->exec('DROP TABLE IF EXISTS test_defaults CASCADE');
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

        // BIGSERIAL creates a sequence
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

        $this->assertEquals('true', $table->columns['is_active']->defaultValue);
    }

    #[Test]
    public function testIntrospectDataTypes(): void
    {
        $schema = $this->driver->getCurrentSchema();
        $table = $schema['test_users'];

        // Check normalized types
        $this->assertEquals('bigint', $table->columns['id']->sqlType);
        $this->assertEquals('varchar(255)', $table->columns['email']->sqlType);
        $this->assertEquals('varchar(100)', $table->columns['full_name']->sqlType);
        $this->assertEquals('boolean', $table->columns['is_active']->sqlType);
        $this->assertEquals('timestamp', $table->columns['created_at']->sqlType);
        $this->assertEquals('text', $table->columns['bio']->sqlType);
    }

    #[Test]
    public function testIntrospectForeignKeys(): void
    {
        $this->pdo->exec("CREATE TABLE test_roles (id SERIAL PRIMARY KEY, name VARCHAR(50))");
        $this->pdo->exec("
            CREATE TABLE test_users_fk (
                id SERIAL PRIMARY KEY,
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
                id SERIAL PRIMARY KEY,
                name VARCHAR(255),
                status VARCHAR(50)
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
                id SERIAL PRIMARY KEY,
                email VARCHAR(255)
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
                assigned_at TIMESTAMP,
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
                id SERIAL PRIMARY KEY,
                email VARCHAR(255) UNIQUE
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
                id SERIAL PRIMARY KEY,
                small_num SMALLINT,
                regular_num INTEGER,
                big_num BIGINT,
                price NUMERIC(10,2),
                rating REAL,
                precise_value DOUBLE PRECISION,
                description TEXT,
                data JSONB,
                unique_id UUID,
                raw_data BYTEA,
                birth_date DATE,
                work_time TIME,
                updated_at TIMESTAMP
            )
        ");

        $schema = $this->driver->getCurrentSchema();
        $table = $schema['test_types'];

        $this->assertEquals('integer', $table->columns['id']->sqlType);
        $this->assertEquals('smallint', $table->columns['small_num']->sqlType);
        $this->assertEquals('integer', $table->columns['regular_num']->sqlType);
        $this->assertEquals('bigint', $table->columns['big_num']->sqlType);
        $this->assertEquals('numeric(10,2)', $table->columns['price']->sqlType);
        $this->assertEquals('real', $table->columns['rating']->sqlType);
        $this->assertEquals('double precision', $table->columns['precise_value']->sqlType);
        $this->assertEquals('text', $table->columns['description']->sqlType);
        $this->assertEquals('jsonb', $table->columns['data']->sqlType);
        $this->assertEquals('uuid', $table->columns['unique_id']->sqlType);
        $this->assertEquals('bytea', $table->columns['raw_data']->sqlType);
        $this->assertEquals('date', $table->columns['birth_date']->sqlType);
        $this->assertEquals('time', $table->columns['work_time']->sqlType);
        $this->assertEquals('timestamp', $table->columns['updated_at']->sqlType);
    }

    #[Test]
    public function testIntrospectIdentityColumn(): void
    {
        // Test IDENTITY column (newer PostgreSQL feature)
        $this->pdo->exec("
            CREATE TABLE test_identity (
                id INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                name VARCHAR(100)
            )
        ");

        $schema = $this->driver->getCurrentSchema();
        $table = $schema['test_identity'];

        $this->assertTrue($table->columns['id']->isAutoIncrement());
        $this->assertTrue($table->columns['id']->isPrimaryKey());
    }

    #[Test]
    public function testMultipleForeignKeysOnSameColumn(): void
    {
        // This tests an edge case where a column might have multiple FKs (rare but possible)
        $this->pdo->exec("CREATE TABLE test_parent1 (id SERIAL PRIMARY KEY)");
        $this->pdo->exec("CREATE TABLE test_parent2 (id SERIAL PRIMARY KEY)");
        $this->pdo->exec("
            CREATE TABLE test_multi_fk (
                id SERIAL PRIMARY KEY,
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
                id SERIAL PRIMARY KEY,
                status VARCHAR(20) DEFAULT 'active',
                count INTEGER DEFAULT 0,
                is_enabled BOOLEAN DEFAULT false,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");

        $schema = $this->driver->getCurrentSchema();
        $table = $schema['test_defaults'];

        $this->assertEquals('active', $table->columns['status']->defaultValue);
        $this->assertEquals('0', $table->columns['count']->defaultValue);
        $this->assertEquals('false', $table->columns['is_enabled']->defaultValue);
        // CURRENT_TIMESTAMP might be returned as now() or similar
        $this->assertNotNull($table->columns['created_at']->defaultValue);
    }

    #[Test]
    public function testCascadingForeignKeyActions(): void
    {
        $this->pdo->exec("CREATE TABLE test_parent (id SERIAL PRIMARY KEY)");
        $this->pdo->exec("
            CREATE TABLE test_child (
                id SERIAL PRIMARY KEY,
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
        $this->pdo->exec("CREATE TABLE test_parent (id SERIAL PRIMARY KEY)");
        $this->pdo->exec("
            CREATE TABLE test_child (
                id SERIAL PRIMARY KEY,
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
}
