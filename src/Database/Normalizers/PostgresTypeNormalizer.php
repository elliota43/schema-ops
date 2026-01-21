<?php

namespace Atlas\Database\Normalizers;

/**
 * Normalizes generic SQL types to PostgreSQL-specific types.
 *
 * Handles type mapping:
 * - tinyint -> smallint (PostgreSQL has no tinyint)
 * - varchar(255) -> varchar(255)
 * - json -> jsonb (for better performance)
 */
class PostgresTypeNormalizer implements TypeNormalizerInterface
{
    /**
     * Normalizes PostgreSQL type for comparison
     *
     * @param string $type The PostgreSQL type string (e.g., 'varchar(255)', 'integer')
     * @return string The normalized PostgreSQL type
     */
    public function normalize(string $type): string
    {
        $type = strtolower(trim($type));

        // Extract length/precision if present
        // Match multi-word types like "character varying" and "double precision"
        $length = null;
        if (preg_match('/^([\w\s]+?)\(([^)]+)\)/', $type, $matches)) {
            $baseType = trim($matches[1]);
            $length = $matches[2];
            $type = $baseType;
        }

        $normalized = $this->normalizeBaseType($type);

        return $this->applyLength($normalized, $length);
    }

    /**
     * Maps generic SQL types to the PostgreSQL base type
     *
     * @param string $type The generic SQL type
     * @return string Normalized type
     */
    protected function normalizeBaseType(string $type): string
    {
        return match (strtolower($type)) {
            'tinyint', 'smallint', 'int2' => 'smallint',
            'mediumint', 'int', 'integer', 'int4' => 'integer',
            'bigint', 'int8' => 'bigint',
            'varchar', 'string', 'character varying' => 'varchar',
            'text', 'mediumtext', 'longtext' => 'text',
            'boolean', 'bool' => 'boolean',
            'datetime' => 'timestamp',
            'timestamp' => 'timestamp',
            'date' => 'date',
            'time' => 'time',
            'decimal', 'numeric' => 'numeric',
            'float', 'float4' => 'real',
            'double', 'float8' => 'double precision',
            'json' => 'jsonb',
            'uuid' => 'uuid',
            'binary', 'blob' => 'bytea',
            'char' => 'character',
            default => $type,
        };
    }

    /**
     * Apply length specification to types that support it.
     *
     * Handles varchar, numeric, and character types with lengths/precision
     *
     * @param string $type
     * @param string|null $length
     * @return string
     */
    protected function applyLength(string $type, ?string $length): string
    {
        if ($length === null) {
            return $type;
        }

        // Types that should preserve their length/precision
        if (in_array($type, ['varchar', 'character varying', 'char', 'character', 'numeric', 'decimal'])) {
            return "{$type}({$length})";
        }

        return $type;
    }
}