<?php

namespace Atlas\Analysis;

use Atlas\Schema\Definition\ColumnDefinition;

interface DestructiveChangeAnalyzerInterface
{
    /**
     * Analyzes if changing from one column to another is destructive.
     *
     * @param ColumnDefinition $from
     * @param ColumnDefinition $to
     * @return DestructivenessLevel
     */
    public function analyze(ColumnDefinition $from, ColumnDefinition $to): DestructivenessLevel;

    /**
     * Check if the database can automatically convert without data loss.
     *
     * @param ColumnDefinition $from
     * @param ColumnDefinition $to
     * @return bool
     */
    public function canAutoConvert(ColumnDefinition $from, ColumnDefinition $to): bool;
}