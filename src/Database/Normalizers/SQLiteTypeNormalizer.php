<?php

namespace Atlas\Database\Normalizers;

/**
 * Normalizes generic SQL types to SQLite-specific types.
 *
 * SQLite uses type affinity rather than strict types. This normalizer
 * maps generic SQL types to SQLite's preferred type names while
 * respecting SQLite's type affinity system.
 *
 * SQLite Type Affinities:
 * - INTEGER: int, integer, tinyint, smallint, mediumint, bigint, etc.
 * - TEXT: varchar, text, char, character, clob, etc.
 * - REAL: real, double, float, numeric, decimal
 * - BLOB: blob, binary
 * - NUMERIC: numeric, decimal (when not mapped to REAL)
 */
class SQLiteTypeNormalizer implements TypeNormalizerInterface
{
    /**
     * Normalizes SQLite type for comparison
     *
     * @param string $type The SQLite type string (e.g., 'varchar(255)', 'INTEGER')
     * @return string The normalized SQLite type
     */
    public function normalize(string $type): string
    {
        $type = strtolower(trim($type));

        // Extract length/precision if present
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
     * Maps generic SQL types to SQLite's preferred type names
     *
     * @param string $type The generic SQL type
     * @return string Normalized type
     */
    protected function normalizeBaseType(string $type): string
    {
        return match (strtolower($type)) {
            // INTEGER affinity
            'tinyint', 'smallint', 'mediumint', 'int', 'integer', 'bigint' => 'integer',
            'int2', 'int4', 'int8' => 'integer',

            // TEXT affinity
            'varchar', 'string', 'character varying' => 'text',
            'char', 'character' => 'text',
            'text', 'mediumtext', 'longtext', 'clob' => 'text',

            // REAL affinity
            'real', 'double', 'double precision', 'float' => 'real',
            'float4', 'float8' => 'real',

            // NUMERIC affinity (SQLite stores as INTEGER or REAL)
            'numeric', 'decimal' => 'numeric',

            // BLOB affinity
            'blob', 'binary', 'varbinary', 'bytea' => 'blob',

            // Boolean (stored as INTEGER 0/1)
            'boolean', 'bool' => 'integer',

            // Date/Time types (SQLite has no native date types)
            // Typically stored as TEXT (ISO8601), REAL (Julian day), or INTEGER (Unix timestamp)
            'date', 'datetime', 'timestamp', 'time' => 'text',

            // JSON (stored as TEXT)
            'json', 'jsonb' => 'text',

            // UUID (stored as TEXT)
            'uuid' => 'text',

            default => $type,
        };
    }

    /**
     * Apply length specification to types that support it.
     *
     * Note: SQLite doesn't enforce length constraints, but we preserve
     * them for schema definition purposes.
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

        // TEXT types can have length for documentation purposes
        // even though SQLite doesn't enforce them
        if (in_array($type, ['text', 'varchar'])) {
            return "{$type}({$length})";
        }

        // NUMERIC types preserve precision/scale
        if ($type === 'numeric') {
            return "numeric({$length})";
        }

        return $type;
    }
}
