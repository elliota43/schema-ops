<?php

namespace Atlas\Analysis;

use Atlas\Schema\Definition\ColumnDefinition;

class MySqlDestructiveChangeAnalyzer extends BaseDestructiveChangeAnalyzer
{
    /**
     * MySQL numeric type hierarchy (smallest to largest).
     */
    private const NUMERIC_HIERARCHY = [
        'tinyint' => 1,
        'smallint' => 2,
        'mediumint' => 3,
        'int' => 4,
        'integer' => 4,
        'bigint' => 5,
    ];

    /**
     * MySQL string type hierarchy (smallest to largest).
     */
    private const STRING_HIERARCHY = [
        'char' => 1,
        'varchar' => 2,
        'tinytext' => 3,
        'text' => 4,
        'mediumtext' => 5,
        'longtext' => 6,
    ];

    /**
     * Analyzes column type change and returns DestructivenessLevel.
     *
     * @param ColumnDefinition $from
     * @param ColumnDefinition $to
     * @return DestructivenessLevel|null
     */
    protected function analyzeTypeChange(ColumnDefinition $from, ColumnDefinition $to): ?DestructivenessLevel
    {
        $fromType = $this->parseBaseType($from->sqlType());
        $toType = $this->parseBaseType($to->sqlType());

        if ($fromType === $toType) {
            return $this->analyzeSameTypeChange($from, $to);
        }

        if ($this->isNumericType($fromType) && $this->isNumericType($toType)) {
            return $this->analyzeNumericChange($fromType, $toType, $from, $to);
        }

        if ($this->isStringType($fromType) && $this->isStringType($toType)) {
            return $this->analyzeStringChange($fromType, $toType, $from, $to);
        }

        return DestructivenessLevel::DEFINITELY_DESTRUCTIVE;
    }

    /**
     * Analyzes changes within the same type (e.g. varchar(255) -> varchar(50))
     *
     * @param ColumnDefinition $from
     * @param ColumnDefinition $to
     * @return DestructivenessLevel|null
     */
    protected function analyzeSameTypeChange(ColumnDefinition $from, ColumnDefinition $to): ?DestructivenessLevel
    {
        $fromType = $this->parseBaseType($from->sqlType());

        if (in_array($fromType, ['varchar', 'char'])) {
            $fromLength = $this->extractLength($from->sqlType());
            $toLength = $this->extractLength($to->sqlType());

            if ($fromLength && $toLength && $toLength < $fromLength) {
                return DestructivenessLevel::DEFINITELY_DESTRUCTIVE; // will truncate
            }
        }

        // check decimal precision reduction
        if ($fromType === 'decimal') {
            $fromPrecision = $this->extractDecimalPrecision($from->sqlType());
            $toPrecision = $this->extractDecimalPrecision($to->sqlType());

            if ($fromPrecision && $toPrecision) {
                [$fromP, $fromS] = $fromPrecision;
                [$toP, $toS] = $toPrecision;

                if ($toP < $fromP || $toS < $fromS) {
                    return DestructivenessLevel::DEFINITELY_DESTRUCTIVE;
                }
            }
        }

        // Check unsigned changes
        if ($this->isNumericType($fromType)) {
            $fromUnsigned = str_contains(strtolower($from->sqlType()), 'unsigned');
            $toUnsigned = str_contains(strtolower($to->sqlType()), 'unsigned');

            // moving from signed to unsigned can lose negative values / cause migration to fail.
            if (! $fromUnsigned && $toUnsigned) {
                return DestructivenessLevel::POTENTIALLY_DESTRUCTIVE;
            }
        }

        return DestructivenessLevel::SAFE;
    }

    /**
     * Analyze numeric type change.
     *
     * @param string $fromType
     * @param string $toType
     * @param ColumnDefinition $from
     * @param ColumnDefinition $to
     * @return DestructivenessLevel
     */
    protected function analyzeNumericChange(string $fromType, string $toType, ColumnDefinition $from, ColumnDefinition $to): DestructivenessLevel
    {
        $fromRank = self::NUMERIC_HIERARCHY[$fromType] ?? 0;
        $toRank = self::NUMERIC_HIERARCHY[$toType] ?? 0;

        if ($toRank > $fromRank) {
            return DestructivenessLevel::SAFE;
        }

        if ($toRank < $fromRank) {
            return DestructivenessLevel::POTENTIALLY_DESTRUCTIVE;
        }

        return DestructivenessLevel::SAFE;
    }

    protected function analyzeStringChange(string $fromType, string $toType, ColumnDefinition $from, ColumnDefinition $to): DestructivenessLevel
    {
        $fromRank = self::STRING_HIERARCHY[$fromType] ?? 0;
        $toRank = self::STRING_HIERARCHY[$toType] ?? 0;

        // varchar -> text is safe (widening)
        if ($toRank > $fromRank) {
            return DestructivenessLevel::SAFE;
        }

        // text -> varchar is potentially destructive
        if ($toRank < $fromRank) {
            return DestructivenessLevel::POTENTIALLY_DESTRUCTIVE;
        }

        return DestructivenessLevel::SAFE;
    }

    /**
     * Checks if a type is numeric.
     *
     * @param string $type
     * @return bool
     */
    protected function isNumericType(string $type): bool
    {
        return isset(self::NUMERIC_HIERARCHY[$type])
            || in_array($type, ['float', 'double', 'decimal']);
    }

    /**
     * Checks if type is string.
     *
     * @param string $type
     * @return bool
     */
    protected function isStringType(string $type): bool
    {
        return isset(self::STRING_HIERARCHY[$type]);
    }

}