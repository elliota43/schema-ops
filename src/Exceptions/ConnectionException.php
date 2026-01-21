<?php

namespace Atlas\Exceptions;

use RuntimeException;

class ConnectionException extends RuntimeException
{
    public static function connectionNotFound(string $connectionName): self
    {
        return new self("Database connection '{$connectionName}' not found");
    }

    public static function invalidConfiguration(string $connectionName, string $reason): self
    {
        return new self("Invalid configuration for connection '{$connectionName}': {$reason}");
    }

    public static function connectionFailed(string $connectionName, string $reason): self
    {
        return new self("Failed to connect to '{$connectionName}': {$reason}");
    }
}