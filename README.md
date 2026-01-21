# Atlas

A PHP library for defining database schemas using attributes. Define your tables with PHP classes and attributes, then generate migrations, compare schemas, and manage your database structure declaratively.

## Installation

```bash
composer require elliota43/atlas
```

## Basic Usage

Define your schema using PHP attributes:

```php
use Atlas\Attributes\Column;
use Atlas\Attributes\Id;
use Atlas\Attributes\Table;
use Atlas\Attributes\Timestamps;

#[Table(name: 'users')]
#[Id]
#[Timestamps]
class UserSchema
{
    #[Column(type: 'varchar', length: 255, unique: true)]
    public string $email;

    #[Column(type: 'varchar', length: 255)]
    public string $name;

    #[Column(type: 'enum', values: ['active', 'inactive'], default: 'active')]
    public string $status;
}
```

## Convenience Attributes

The library provides Laravel-style convenience attributes for common patterns:

```php
#[Id]                    // Adds auto-incrementing BIGINT UNSIGNED primary key
#[Uuid]                  // Adds CHAR(36) UUID primary key
#[Timestamps]            // Adds created_at and updated_at with auto-updating
#[SoftDeletes]           // Adds deleted_at timestamp column
#[PrimaryKey(columns: ['user_id', 'role_id'])]  // Composite primary key
```

## Advanced Features

### Indexes

```php
#[Table(name: 'posts')]
#[Index(columns: ['user_id', 'created_at'])]
#[Index(columns: ['slug'], unique: true)]
class PostSchema
{
    #[Column(type: 'varchar', length: 255)]
    public string $slug;
}
```

### Foreign Keys

```php
#[Column(type: 'bigint', unsigned: true)]
#[ForeignKey(references: 'users.id', onDelete: 'CASCADE', onUpdate: 'CASCADE')]
public int $user_id;
```

### Complex Column Types

```php
// Decimal with precision/scale
#[Column(type: 'decimal', precision: 10, scale: 2)]
public string $price;

// Enum with values
#[Column(type: 'enum', values: ['draft', 'published', 'archived'])]
public string $status;

// Unsigned integers
#[Column(type: 'bigint', unsigned: true)]
public int $count;

// JSON columns
#[Column(type: 'json', nullable: true)]
public ?string $metadata;

// Text types
#[Column(type: 'text')]
public string $description;
```

## CLI Commands

### Check Schema Differences

```bash
./bin/atlas schema:diff [path]
```

Shows differences between your attribute-based schema definitions and the current database state.

### Check Status

```bash
./bin/atlas schema:status
```

Shows current schema operations status and configuration.

## Programmatic Usage

```php
use Atlas\Schema\Parser\SchemaParser;
use Atlas\Schema\Grammars\MySqlGrammar;
use Atlas\Database\Drivers\MySqlDriver;
use Atlas\Database\Drivers\PostgresDriver;
use Atlas\Database\Drivers\SQLiteDriver;

// Parse schema from attributes
$parser = new SchemaParser();
$definition = $parser->parse(UserSchema::class);

// Generate SQL (MySQL)
$grammar = new MySqlGrammar();
$sql = $grammar->createTable($definition);

// Introspect existing database (MySQL)
$driver = new MySqlDriver($pdo);
$currentSchema = $driver->getCurrentSchema();

// Or use PostgreSQL
$postgresDriver = new PostgresDriver($postgresPdo);
$currentSchema = $postgresDriver->getCurrentSchema();

// Or use SQLite
$sqliteDriver = new SQLiteDriver($sqlitePdo);
$currentSchema = $sqliteDriver->getCurrentSchema();

// Compare schemas
$comparator = new TableComparator();
$changes = $comparator->compare($definition, $currentSchema['users']);
```

## Supported Column Types

- Integer types: `tinyint`, `smallint`, `mediumint`, `int`, `integer`, `bigint`
- Decimal types: `decimal`, `float`, `double`
- String types: `char`, `varchar`, `text`, `mediumtext`, `longtext`
- Date/Time: `date`, `datetime`, `timestamp`, `time`, `year`
- Binary: `binary`, `varbinary`, `blob`
- Other: `json`, `enum`, `set`, `boolean` (stored as `tinyint(1)`)

All integer types support `unsigned` and `zerofill` modifiers.

## Architecture

The library follows a clear separation of concerns:

- **Attributes** (`src/Attributes/`): PHP 8 attributes for schema definition
- **Schema** (`src/Schema/`): Abstract schema representation and parsing
  - `Definition/`: Value objects representing tables and columns
  - `Parser/`: Converts PHP attributes to schema definitions
  - `Grammars/`: Generates database-specific SQL (currently MySQL)
- **Database** (`src/Database/Drivers/`): Database introspection and interaction
- **Comparison** (`src/Comparison/`): Schema comparison logic
- **Changes** (`src/Changes/`): Represents detected differences

This design makes it easy to add support for additional databases (PostgreSQL, SQLite, SQL Server).

## TODO

### High Priority

- [ ] Implement `schema:migrate` command with safety features
- [ ] Production safety: environment detection and confirmation prompts
- [ ] Drift detection: warn when database differs from lockfile
- [ ] Two-step destructive operations (mark for deletion, then confirm)
- [ ] Automatic backup before destructive operations
- [ ] Migration history tracking table

### Schema Features

- [ ] Column rename detection using `#[RenamedFrom]` attribute
- [ ] Index modification detection and ALTER statements
- [ ] Foreign key modification detection
- [ ] Column order/positioning support
- [ ] Table comments and column comments in SQL output
- [ ] Charset and collation per-column support
- [ ] Generated/computed columns
- [ ] Spatial types and indexes
- [ ] Fulltext indexes

### Additional Drivers

- [x] PostgreSQL driver and grammar
- [x] SQLite driver and grammar
- [ ] SQL Server driver and grammar

### Type System

- [ ] Type aliases (e.g., `string` -> `varchar(255)`, `text` -> `text`)
- [ ] Type normalization for cross-database compatibility
- [ ] Custom type mapping configuration

### Developer Experience

- [ ] Schema validation with helpful error messages
- [ ] Dry-run mode for migrations
- [ ] Schema diff output formatting options (JSON, colored terminal)
- [ ] Configuration file support (database connections, paths)
- [ ] Better error messages with file/line information

### Testing & Documentation

- [ ] More edge case tests for schema comparison
- [ ] Test coverage for all column type combinations
- [ ] Performance tests with large schemas
- [ ] Documentation for custom driver implementation
- [ ] Examples for common patterns (multi-tenancy, audit columns, etc.)

## Running Tests

```bash
# Run all tests
composer test

# Run only unit tests
composer test:unit

# Run only integration tests
composer test:integration

# Run with coverage report
composer test:coverage
```

Integration tests require Docker for MySQL and PostgreSQL. SQLite tests use an in-memory database.

```bash
# Start databases (MySQL and PostgreSQL)
docker-compose up -d

# Run all integration tests (MySQL, PostgreSQL, and SQLite)
./vendor/bin/phpunit

# Run only MySQL integration tests
./vendor/bin/phpunit tests/Integration/DatabaseIntrospectionTest.php

# Run only PostgreSQL integration tests
./vendor/bin/phpunit tests/Integration/PostgresDatabaseIntrospectionTest.php

# Run only SQLite integration tests (no Docker needed)
./vendor/bin/phpunit tests/Integration/SQLiteDatabaseIntrospectionTest.php

# Stop databases
docker-compose down
```

The docker-compose.yml file includes:
- **MySQL 8.0** on port 3306 (user: root, password: root, database: test_schema)
- **PostgreSQL 16** on port 5433 (user: atlas, password: atlas, database: test_schema)
- **SQLite** uses in-memory database (no Docker container needed)

## Requirements

- PHP 8.1 or higher
- PDO extension
- MySQL 8.0 or higher (for MySQL support)
- PostgreSQL 12 or higher (for PostgreSQL support)
- SQLite 3.8 or higher (for SQLite support)

## License

MIT
