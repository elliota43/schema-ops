<?php

namespace Atlas\Exceptions;

use RuntimeException;

class SchemaException extends RuntimeException
{
    public static function duplicateTableDefinition(string $tableName, string $firstLocation, string $secondLocation): self
    {
        return new self(
            "Duplicate table definition '{$tableName}' found in:\n" .
            "  - {$firstLocation}\n" . 
            "  - {$secondLocation}"
        );
    }

    public static function invalidYamlFile(string $path): self
    {
        return new self("YAML schema file not found: {$path}");
    }

    public static function unreadableFile(string $path): self
    {
        return new self("Failed to read YAML schema file: {$path}");
    }

    public static function invalidYamlStructure(string $sourceLabel, string $reason): self
    {
        return new self("Invalid YAML structure in {$sourceLabel}: {$reason}");
    }

    public static function missingRequiredKey(string $sourceLabel, string $key): self
    {
        return new self("Missing required key '{$key}' in {$sourceLabel}");
    }

    public static function invalidTableName(string $sourceLabel): self
    {
        return new self("Table name must be a non-empty string in {$sourceLabel}");
    }

    public static function noSchemaDefinitionsFound(): self
    {
        return new self('No schema definitions found in the specified location');
    }

    public static function invalidYamlContent(string $sourceLabel, string $reason): self
    {
        return new self("Invalid YAML in {$sourceLabel}: {$reason}");
    }

    public static function yamlRootMustBeMap(string $sourceLabel): self
    {
        return new self("YAML root must be a map/object in {$sourceLabel}.");
    }

    public static function missingTablesKey(string $sourceLabel): self
    {
        return new self("Missing or invalid 'tables' map in {$sourceLabel}.");
    }

    public static function tableMustBeMap(string $tableName, string $sourceLabel): self
    {
        return new self("Table '{$tableName}' must be a map/object in {$sourceLabel}.");
    }

    public static function primaryKeyMustBeList(string $tableName): self
    {
        return new self("tables.{$tableName}.primaryKey must be a list of column names.");
    }

    public static function columnsMustBeMap(string $tableName): self
    {
        return new self("tables.{$tableName}.columns must be a map/object.");
    }

    public static function columnNameMustBeString(string $tableName): self
    {
        return new self("tables.{$tableName}.columns keys must be non-empty strings.");
    }

    public static function columnMustBeMap(string $tableName, string $columnName): self
    {
        return new self("tables.{$tableName}.columns.{$columnName} must be a map/object.");
    }

    public static function indexesMustBeList(string $tableName): self
    {
        return new self("tables.{$tableName}.indexes must be a list.");
    }

    public static function indexMustBeMap(string $tableName, int $index): self
    {
        return new self("tables.{$tableName}.indexes[{$index}] must be a map/object.");
    }

    public static function columnTypeMissing(string $tableName, string $columnName): self
    {
        return new self("tables.{$tableName}.columns.{$columnName}.type is required and must be a string.");
    }

    public static function foreignKeysMustBeList(string $tableName, string $columnName): self
    {
        return new self("tables.{$tableName}.columns.{$columnName}.foreignKeys must be a list.");
    }

    public static function foreignKeyMustBeMap(string $tableName, string $columnName, int $index): self
    {
        return new self("tables.{$tableName}.columns.{$columnName}.foreignKeys[{$index}] must be a map/object.");
    }

    public static function lengthMustBeInteger(): self
    {
        return new self('Column length must be an integer.');
    }

    public static function precisionMustBeInteger(): self
    {
        return new self('Column precision must be an integer.');
    }

    public static function scaleMustBeInteger(): self
    {
        return new self('Column scale must be an integer.');
    }

    public static function foreignKeyReferencesMissing(string $tableName, string $columnName, int $index): self
    {
        return new self("tables.{$tableName}.columns.{$columnName}.foreignKeys[{$index}].references is required.");
    }

    public static function indexColumnsMissing(string $tableName, int $index): self
    {
        return new self("tables.{$tableName}.indexes[{$index}].columns is required and must be a non-empty list.");
    }

    public static function indexColumnsMustBeStrings(string $tableName, int $index): self
    {
        return new self("tables.{$tableName}.indexes[{$index}].columns must contain only strings.");
    }

    public static function directoryNotFound(string $path): self
    {
        return new self("Schema directory not found: {$path}");
    }

    public static function classNotFound(string $className): self
    {
        return new self("Class '{$className}' not found. Make sure it's loaded or autoloadable.");
    }

    public static function missingTableAttribute(string $className): self
    {
        return new self("Class '{$className}' is missing the #[Table] attribute.");
    }
}