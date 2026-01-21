<?php

namespace Tests\Integration;

use Atlas\Attributes\Column;
use Atlas\Attributes\Table;
use Atlas\Comparison\TableComparator;
use Atlas\Database\Drivers\MySqlDriver;
use Atlas\Database\MySqlTypeNormalizer;
use Atlas\Schema\Grammars\MySqlGrammar;
use Atlas\Schema\Parser\SchemaParser;
use PDO;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Support\TestDb;

// Fixture: This matches the Docker DB 'legacy_users' table BUT has one extra column
#[Table(name: 'legacy_users')]
class TriangulationTarget {
    // Matches DB
    #[Column(type: 'bigint', autoIncrement: true, primaryKey: true)]
    public int $id;

    #[Column(type: 'varchar', length: 255)]
    public string $email;

    #[Column(type: 'varchar', length: 100, nullable: true)]
    public string $full_name;

    #[Column(type: 'tinyint', length: 1, nullable: false, default: 1)]
    public int $is_active;

    #[Column(type: 'timestamp', nullable: false)]
    public string $created_at;

    #[Column(type: 'text', nullable: true)]
    public ?string $bio;

    // NEW COLUMN (This should trigger an ALTER)
    #[Column(type: 'varchar', length: 50, nullable: true)]
    public string $github_handle;
}

class TriangulationTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = TestDb::pdo();

        // Create legacy_users table for triangulation test
        $this->pdo->exec("DROP TABLE IF EXISTS legacy_users");
        $this->pdo->exec("
            CREATE TABLE legacy_users (
                id BIGINT PRIMARY KEY AUTO_INCREMENT,
                email VARCHAR(255) NOT NULL,
                full_name VARCHAR(100),
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                bio TEXT
            ) ENGINE=InnoDB
        ");
    }

    #[Test]
    public function TestFullTriangulationFlow(): void
    {
        // 1. Introspect Real DB (State A)
        $driver = new MySqlDriver($this->pdo);
        $currentSchema = $driver->getCurrentSchema();
        $this->assertArrayHasKey('legacy_users', $currentSchema);
        $stateA = $currentSchema['legacy_users'];

        // 2. Parse PHP Class (State B)
        $parser = new SchemaParser(new MySqlTypeNormalizer());
        $stateB = $parser->parse(TriangulationTarget::class);

        // 3. Compare
        $comparator = new TableComparator();
        $diff = $comparator->compare($stateA, $stateB);

        // 4. Assert Drift Detected
        $this->assertFalse($diff->isEmpty(), "Drift should be detected because 'github_handle' is missing in DB.");
        $this->assertCount(1, $diff->addedColumns);
        $this->assertEquals('github_handle', $diff->addedColumns[0]->name());

        // 5. Generate Fix
        $grammar = new MySqlGrammar();
        $queries = $grammar->generateAlter($diff);

        // 6. Verify SQL
        $this->assertNotEmpty($queries);
        $this->assertStringContainsString('ALTER TABLE `legacy_users` ADD COLUMN `github_handle` VARCHAR(50)', $queries[0]);
    }
}