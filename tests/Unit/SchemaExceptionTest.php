<?php

declare(strict_types=1);

namespace Tests\Unit;

use Atlas\Exceptions\SchemaException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SchemaExceptionTest extends TestCase
{
    #[Test]
    public function it_creates_duplicate_table_definition_exception(): void
    {
        $exception = SchemaException::duplicateTableDefinition(
            'users',
            '/path/to/users.schema.yaml',
            '/path/to/another/users.schema.yaml'
        );

        $this->assertStringContainsString('Duplicate table definition', $exception->getMessage());
        $this->assertStringContainsString('users', $exception->getMessage());
        $this->assertStringContainsString('/path/to/users.schema.yaml', $exception->getMessage());
        $this->assertStringContainsString('/path/to/another/users.schema.yaml', $exception->getMessage());
    }

    #[Test]
    public function it_creates_invalid_yaml_file_exception(): void
    {
        $exception = SchemaException::invalidYamlFile('/path/to/missing.yaml');

        $this->assertStringContainsString('YAML schema file not found', $exception->getMessage());
        $this->assertStringContainsString('/path/to/missing.yaml', $exception->getMessage());
    }

    #[Test]
    public function it_creates_unreadable_file_exception(): void
    {
        $exception = SchemaException::unreadableFile('/path/to/file.yaml');

        $this->assertStringContainsString('Failed to read', $exception->getMessage());
        $this->assertStringContainsString('/path/to/file.yaml', $exception->getMessage());
    }

    #[Test]
    public function it_creates_yaml_root_must_be_map_exception(): void
    {
        $exception = SchemaException::yamlRootMustBeMap('test.yaml');

        $this->assertStringContainsString('YAML root must be a map/object', $exception->getMessage());
        $this->assertStringContainsString('test.yaml', $exception->getMessage());
    }

    #[Test]
    public function it_creates_missing_tables_key_exception(): void
    {
        $exception = SchemaException::missingTablesKey('test.yaml');

        $this->assertStringContainsString("Missing or invalid 'tables' map", $exception->getMessage());
    }

    #[Test]
    public function it_creates_column_type_missing_exception(): void
    {
        $exception = SchemaException::columnTypeMissing('users', 'email');

        $this->assertStringContainsString('tables.users.columns.email.type is required', $exception->getMessage());
    }

    #[Test]
    public function it_creates_directory_not_found_exception(): void
    {
        $exception = SchemaException::directoryNotFound('/invalid/path');

        $this->assertStringContainsString('Schema directory not found', $exception->getMessage());
        $this->assertStringContainsString('/invalid/path', $exception->getMessage());
    }

    #[Test]
    public function it_creates_class_not_found_exception(): void
    {
        $exception = SchemaException::classNotFound('App\\Models\\NonExistent');

        $this->assertStringContainsString('Class', $exception->getMessage());
        $this->assertStringContainsString('not found', $exception->getMessage());
        $this->assertStringContainsString('App\\Models\\NonExistent', $exception->getMessage());
    }

    #[Test]
    public function it_creates_missing_table_attribute_exception(): void
    {
        $exception = SchemaException::missingTableAttribute('App\\Models\\User');

        $this->assertStringContainsString('missing the #[Table] attribute', $exception->getMessage());
        $this->assertStringContainsString('App\\Models\\User', $exception->getMessage());
    }

    #[Test]
    public function it_creates_foreign_key_references_missing_exception(): void
    {
        $exception = SchemaException::foreignKeyReferencesMissing('users', 'role_id', 0);

        $this->assertStringContainsString('tables.users.columns.role_id.foreignKeys[0].references is required', $exception->getMessage());
    }

    #[Test]
    public function it_creates_index_columns_missing_exception(): void
    {
        $exception = SchemaException::indexColumnsMissing('users', 0);

        $this->assertStringContainsString('tables.users.indexes[0].columns is required', $exception->getMessage());
    }

    #[Test]
    public function exception_messages_are_descriptive_and_actionable(): void
    {
        $exception = SchemaException::columnTypeMissing('posts', 'title');

        $message = $exception->getMessage();

        // Should include table name, column name, and what's wrong
        $this->assertStringContainsString('posts', $message);
        $this->assertStringContainsString('title', $message);
        $this->assertStringContainsString('type', $message);
        $this->assertStringContainsString('required', $message);
    }
}
