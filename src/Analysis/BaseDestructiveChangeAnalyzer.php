<?php

namespace Atlas\Analysis;

use Atlas\Schema\Definition\ColumnDefinition;

abstract class BaseDestructiveChangeAnalyzer implements DestructiveChangeAnalyzerInterface
{
    public function analyze(ColumnDefinition $from, ColumnDefinition $to): DestructivenessLevel
    {
        if ($nullabilityLevel = $this->analyzeNullabilityChange($from, $to)) {
            return $nullabilityLevel;
        }

        if ($typeLevel = $this->analyzeTypeChange($from, $to)) {
            return $typeLevel;
        }

        return DestructivenessLevel::SAFE;
    }

    /**
     * Analyze nullability changes.
     *
     * @param ColumnDefinition $from
     * @param ColumnDefinition $to
     * @return DestructivenessLevel|null
     */
    protected function analyzeNullabilityChange(ColumnDefinition $from, ColumnDefinition $to): ?DestructivenessLevel
    {
        // NOT NULL -> NULL = Safe
        if (! $from->isNullable() && $to->isNullable()) {
            return DestructivenessLevel::SAFE;
        }

        // NULL -> NOT NULL potentially destructive
        if ($from->isNullable() && !$to->isNullable()) {
            return DestructivenessLevel::POTENTIALLY_DESTRUCTIVE;
        }

        return null;
    }

    /**
     * Analyzes type changes.
     *
     * @param ColumnDefinition $from
     * @param ColumnDefinition $to
     * @return DestructivenessLevel|null
     */
    abstract protected function analyzeTypeChange(ColumnDefinition $from, ColumnDefinition $to): ?DestructivenessLevel;

    /**
     * Parse base type from full type string (i.e. "varchar(255) -> "varchar" )
     * @param string $fullType
     * @return string
     */
    protected function parseBaseType(string $fullType): string
    {
        if (preg_match('/^(\w+)/', $fullType, $matches)) {
            return strtolower($matches[1]);
        }
        return strtolower($fullType);
    }

    /**
     * Extract length from type string (e.g., "varchar(255)" -> 255)
     * @param string $fullType
     * @return int|null
     */
    protected function extractLength(string $fullType): ?int
    {
        if (preg_match('/\((\d+)\)/', $fullType, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }

    /**
     * Extract precision and scale from decimal (e.g., "decimal(10,2)" -> [10, 2]).
     * @param string $fullType
     * @return int[]|null
     */
    protected function extractDecimalPrecision(string $fullType): ?array
    {
        if (preg_match('/\((\d+),\s*(\d+)\)/', $fullType, $matches)) {
            return [(int) $matches[1], (int) $matches[2]];
        }
        return null;
    }

    /**
     * Determines if a column change can be safely made without any data loss.
     *
     * @param ColumnDefinition $from
     * @param ColumnDefinition $to
     * @return bool
     */
    public function canAutoConvert(ColumnDefinition $from, ColumnDefinition $to): bool
    {
        return $this->analyze($from, $to)->isSafe();
    }
}