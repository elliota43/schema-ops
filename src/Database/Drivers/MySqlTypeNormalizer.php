<?php

namespace Atlas\Database\Drivers;

use Atlas\Database\TypeNormalizerInterface;
class MySqlTypeNormalizer implements TypeNormalizerInterface
{

    /**
     * Type aliases MySQL treats as equivalent
     */
    private const ALIASES = [
        'integer' => 'int',
        'boolean' => 'tinyint',
        'bool' => 'tinyint',
    ];

    /**
     * Normalizes MySQL type for comparison.
     * @param string $type
     * @return string
     */
    public function normalize(string $type): string
    {
        $type = strtolower(trim($type));

        // keep lengths for char/varchar, remove for numeric types
        if ($this->shouldRemoveDisplayWidth($type)) {
            $type = preg_replace('/\((\d+)\)/', '', $type);
        }

        // Normalize tinyint(1) to tinyint for boolean comparison
        if (str_starts_with($type, 'tinyint(1)')) {
            $type = str_replace('tinyint(1)', 'tinyint', $type);
        }

        $type = $this->resolveAliases($type);

        // normalize spacing around unsigned / zerofill
        $type = preg_replace('/\s+/', ' ', $type);
        $type = trim($type);

        return $type;
    }

    /**
     * Check if we should remove display width for this type.
     *
     * MySQL quirk: int(11) is just a display width (meaningless, deprecated),
     * but varchar(255) is actual max length (meaningful).
     * We remove display widths but preserve actual constrains.
     *
     * @param string $type
     * @return bool
     */
    protected function shouldRemoveDisplayWidth(string $type): bool
    {
        // Don't remove lengths from these types.
        if (preg_match('/^(varchar|char|varbinary|binary)\(/', $type)) {
            return false;
        }

        // Don't remove precision/scale from decimal
        if (preg_match('/^decimal\(/', $type)) {
            return false;
        }

        // Remove display width from numeric types
        return preg_match('/^(tinyint|smallint|mediumint|int|integer|bigint)\(/', $type);
    }

    /**
     * Resolves type aliases.
     *
     * @param string $type
     * @return string
     */
    protected function resolveAliases(string $type): string
    {
        // handle base type (before spaces/modifiers)
        foreach (self::ALIASES as $alias => $canonical) {
            if (str_starts_with($type, $alias)) {
                $type = str_replace($alias, $canonical, $type);
            }
        }

        return $type;
    }
}