<?php

namespace Atlas\Exceptions;

use RuntimeException;

class ComparisonException extends RuntimeException
{
    public static function tableNotFound(string $tableName): self
    {
        return new self("Table '{$tableName}' not found in database schema");
    }

    public static function columnNotFound(string $tableName, string $columnName): self
    {
        return new self("Column '{$columnName}' not found in table '{$tableName}'");
    }

    public static function unsupportedOperation(string $operation): self
    {
        return new self("Unsupported schema operation: {$operation}");
    }
}