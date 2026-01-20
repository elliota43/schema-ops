<?php

namespace Atlas\Comparison;

use Atlas\Analysis\DestructiveChangeAnalyzerInterface;
use Atlas\Analysis\DestructivenessLevel;
use Atlas\Schema\Definition\ColumnDefinition;

class ColumnComparator
{
    public function __construct(
        protected ColumnDefinition $current,
        protected ColumnDefinition $desired,
        protected ?DestructiveChangeAnalyzerInterface $analyzer = null
    ) {}

    /**
     * Check if there are any changes between the columns.
     */
    public function hasChanges(): bool
    {
        return $this->hasTypeChanged()
            || $this->hasNullabilityChanged()
            || $this->hasAutoIncrementChanged()
            || $this->hasDefaultChanged()
            || $this->hasOnUpdateChanged()
            || $this->hasUniqueChanged()
            || $this->hasForeignKeysChanged();
    }

    /**
     * Check if the column type has changed.
     */
    public function hasTypeChanged(): bool
    {
        return $this->current->sqlType() !== $this->desired->sqlType();
    }

    /**
     * Check if the nullability has changed.
     */
    public function hasNullabilityChanged(): bool
    {
        return $this->current->isNullable() !== $this->desired->isNullable();
    }

    /**
     * Check if auto-increment has changed.
     */
    public function hasAutoIncrementChanged(): bool
    {
        return $this->current->isAutoIncrement() !== $this->desired->isAutoIncrement();
    }

    /**
     * Check if the default value has changed
     */
    public function hasDefaultChanged(): bool
    {
        return $this->current->defaultValue() !== $this->desired->defaultValue();
    }

    /**
     * Check if the ON UPDATE clause has changed.
     *
     * @return bool
     */
    public function hasOnUpdateChanged(): bool
    {
        $currentOnUpdate = $this->normalizeOnUpdate($this->current->onUpdate);
        $desiredOnUpdate = $this->normalizeOnUpdate($this->desired->onUpdate);

        return $currentOnUpdate !== $desiredOnUpdate;
    }

    /**
     * Check if the unique constraint has changed.
     *
     * @return bool
     */
    public function hasUniqueChanged(): bool
    {
        return $this->current->isUnique() !== $this->desired->isUnique();
    }

    /**
     * Checks if foreignKeys changed
     *
     * @return bool
     */
    public function hasForeignKeysChanged(): bool
    {
        return $this->foreignKeysAreDifferent(
            $this->current->foreignKeys(),
            $this->desired->foreignKeys()
        );
    }


    /**
     * Gets array of changes
     *
     * @return array
     */
    public function getChanges(): array
    {
        $changes = [];

        if ($this->hasTypeChanged()) {
            $changes[] = [
                'property' => 'type',
                'from' => $this->current->sqlType(),
                'to' => $this->desired->sqlType()
            ];
        }

        if ($this->hasNullabilityChanged()) {
            $changes[] = [
                'property' => 'nullable',
                'from' => $this->current->isNullable(),
                'to' => $this->desired->isNullable(),
            ];
        }

        if ($this->hasAutoIncrementChanged()) {
            $changes[] = [
                'property' => 'auto_increment',
                'from' => $this->current->isAutoIncrement(),
                'to' => $this->desired->isAutoIncrement(),
            ];
        }

        if ($this->hasDefaultChanged()) {
            $changes[] = [
                'property' => 'default',
                'from' => $this->current->defaultValue(),
                'to' => $this->desired->defaultValue(),
            ];
        }

        if ($this->hasOnUpdateChanged()) {
            $changes[] = [
                'property' => 'on_update',
                'from' => $this->current->onUpdate,
                'to' => $this->desired->onUpdate,
            ];
        }

        if ($this->hasUniqueChanged()) {
            $changes[] = [
                'property' => 'unique',
                'from' => $this->current->isUnique(),
                'to' => $this->desired->isUnique(),
            ];
        }

        if ($this->hasForeignKeysChanged()) {
            $changes[] = [
                'property' => 'foreign_keys',
                'from' => $this->formatForeignKeys($this->current->foreignKeys()),
                'to' => $this->formatForeignKeys($this->desired->foreignKeys()),
            ];
        }

        return $changes;
    }

    /**
     * Get a summary string of all changes.
     */
    public function getSummary(): string
    {
        $parts = [];

        foreach ($this->getChanges() as $change) {
            $from = $this->formatValue($change['from']);
            $to = $this->formatValue($change['to']);
            $parts[] = "{$change['property']}: {$from} → {$to}";
        }

        return implode(', ', $parts);
    }

    /**
     * Normalizes ON UPDATE clause for comparison.
     * Handles null, case differences, and whitespace.
     *
     * @param string|null $onUpdate
     * @return string|null
     */
    protected function normalizeOnUpdate(?string $onUpdate): ?string
    {
        if ($onUpdate === null) return null;

        return strtoupper(trim($onUpdate));
    }

    /**
     * Compare foreign keys arrays.
     * Returns true if any are different.
     *
     * @param array $current
     * @param array $desired
     * @return bool
     */
    protected function foreignKeysAreDifferent(array $current, array $desired): bool
    {
        if (count($current) !== count($desired)) {
            return true;
        }

        if (empty($current) && empty($desired)) {
            return false;
        }

        // Sort for consistent comparison
        $currentSorted = $this->sortForeignKeys($current);
        $desiredSorted = $this->sortForeignKeys($desired);

        foreach ($currentSorted as $index => $currentFk) {
            $desiredFk = $desiredSorted[$index] ?? null;

            if ($desiredFk === null) return true;

            // compare references (e.g., "users.id")
            if (($currentFk['references'] ?? null) !== ($desiredFk['references'] ?? null)) {
                return true;
            }

            // compare onDelete (case-insensitive)
            $currentOnDelete = strtoupper($currentFk['onDelete'] ?? 'NO ACTION');
            $desiredOnDelete = strtoupper($desiredFk['onDelete'] ?? 'NO ACTION');
            if ($currentOnDelete !== $desiredOnDelete) {
                return true;
            }

            // Compare onUpdate (case-insensitive)
            $currentOnUpdate = strtoupper($currentFk['onUpdate'] ?? 'NO ACTION');
            $desiredOnUpdate = strtoupper($desiredFk['onUpdate'] ?? 'NO ACTION');
            if ($currentOnUpdate !== $desiredOnUpdate) {
                return true;
            }
        }

        return false;
    }

    /**
     * Sort foreign keys by reference for consistent comparison.
     *
     * @param array $foreignKeys
     * @return array
     */
    protected function sortForeignKeys(array $foreignKeys): array
    {
        usort($foreignKeys, function ($a, $b) {
            return ($a['references'] ?? '') <=> ($b['references'] ?? '');
        });

        return $foreignKeys;
    }

    /**
     * Formats foreign keys array for display.
     *
     * @param array $foreignKeys
     * @return string
     */
    protected function formatForeignKeys(array $foreignKeys): string
    {
        if (empty($foreignKeys)) {
            return 'none';
        }

        $formatted = array_map(function ($fk) {
            $ref = $fk['references'] ?? 'unknown';
            $onDelete = $fk['onDelete'] ?? 'NO ACTION';
            $onUpdate = $fk['onUpdate'] ?? 'NO ACTION';
            return "{$ref} (DELETE: {$onDelete}, UPDATE: {$onUpdate}";
        }, $foreignKeys);

        return implode('; ', $formatted);
    }

    /**
     * Format a value for display.
     */
    protected function formatValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'YES' : 'NO';
        }

        if (is_null($value)) {
            return 'NULL';
        }

        return (string) $value;
    }

    /**
     * Checks if the change is destructive.
     *
     * @return bool
     */
    public function isDestructive(): bool
    {
        if (! $this->analyzer) {
            return false;
        }

        $level = $this->analyzer->analyze($this->current, $this->desired);
        return $level->isDestructive();
    }

    /**
     * Gets DestructivenessLevel
     *
     * @return DestructivenessLevel
     */
    public function getDestructivenessLevel(): DestructivenessLevel
    {
        if (! $this->analyzer) {
            return DestructivenessLevel::SAFE;
        }

        return $this->analyzer->analyze($this->current, $this->desired);
    }
}