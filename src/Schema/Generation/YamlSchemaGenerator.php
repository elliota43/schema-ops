<?php

namespace Atlas\Schema\Generation;

use Atlas\Schema\Definition\ColumnDefinition;
use Atlas\Schema\Definition\TableDefinition;
use Atlas\Schema\Yaml\YamlFormatting;
use Symfony\Component\Yaml\Yaml;

final class YamlSchemaGenerator
{
    /**
     * Generate a YAML schema document from table definitions.
     *
     * Intended to produce a stable, human-readable schema file that can be
     * committed to version control.  Tables and columns are ordered deterministically
     * to keep diffs clean.
     *
     * @param array<string, TableDefinition> $tables Table definitions keyed by table name.
     * @return string The YAML schema document
     */
    public function generate(array $tables): string
    {
        $payload = $this->buildRoot($tables);

        return Yaml::dump($payload, YamlFormatting::INLINE_LEVEL, YamlFormatting::INDENT);
    }

    /**
     * Build the root YAML payload for the schema.
     *
     * The returned array becomes the root YAML mapping, typically
     * "version" and "tables".
     *
     * @param array $tables
     * @return array
     */
    private function buildRoot(array $tables): array
    {
        $tables = $this->sortTablesByName($tables);

        $mapped = [];

        foreach ($tables as $name => $table) {
            $mapped[$name] = $this->buildTable($table);
        }

        return [
            'version' => 1,
            'table' => $mapped,
        ];
    }

    /**
     * Builds YAML payload for a single table.
     *
     * @param TableDefinition $table
     * @return array<string, mixed>
     */
    private function buildTable(TableDefinition $table): array
    {
        $payload = [];

        if ($table->compositePrimaryKey !== null) {
            $payload['primaryKey'] = array_values($table->compositePrimaryKey);
        }

        $payload['columns'] = $this->buildColumns($table);

        $indexes = $this->buildIndexes($table);

        if ($indexes !== []) {
            $payload['indexes'] = $indexes;
        }

        return $payload;
    }

    /**
     * Build the YAML payload for all columns in a table.
     *
     * Columns are sorted by name.
     *
     * @param TableDefinition $table
     * @return array<string, array<string, mixed>> column payloads keyed by column name.
     */
    private function buildColumns(TableDefinition $table): array
    {
        $columns = $table->columns();
        ksort($columns);

        $mapped = [];

        foreach ($columns as $name => $column) {
            $mapped[$name] = $this->buildColumn($column);
        }

        return $mapped;
    }

    /**
     * Build the YAML payload for a single column.
     *
     * The column type is stored as a normalized SQL-ish type string
     *  (e.g. "bigint unsigned").  This format is normalized for the
     * Diff engine to convert to SQL.
     *
     * @param ColumnDefinition $column
     * @return array
     */
    private function buildColumn(ColumnDefinition $column): array
    {
        $payload = [
            'type' => $column->sqlType(),
            'nullable' => $column->isNullable(),
            'primaryKey' => $column->isPrimaryKey(),
            'autoIncrement' => $column->isAutoIncrement(),
        ];

        if ($column->isUnique()) {
            $payload['unique'] = true;
        }

        if ($column->defaultValue() !== null) {
            $payload['default'] = $column->defaultValue();
        }

        if ($column->onUpdate !== null) {
            $payload['onUpdate'] = $column->onUpdate;
        }

        $foreignKeys = $column->foreignKeys();

        if ($foreignKeys !== []) {
            $payload['foreignKeys'] = array_values($foreignKeys);
        }

        return $payload;
    }

    /**
     * Build the YAML payload for indexes on a table.
     *
     * This method assumes indexes are already stored in the normalized array shape used
     * by the Schema diff engine (e.g. ['columns' => [...], 'unique' => bool, 'name' => ...])
     *
     * @param TableDefinition $table
     * @return array<int, array<string, mixed>> List of index payloads.
     */
    private function buildIndexes(TableDefinition $table): array
    {
        $indexes = $table->indexes ?? [];

        if ($indexes === []) {
            return [];
        }

        usort($indexes, function (array $a, array $b): int {
            $aCols = implode(',', $a['columns'] ?? []);
            $bCols = implode(',', $b['columns'] ?? []);

            $cmp = $aCols <=> $bCols;

            if ($cmp !== 0) {
                return $cmp;
            }

            return (string) ($a['name'] ?? '') <=> (string) ($b['name'] ?? '');
        });

        return array_values($indexes);
    }

    /**
     * Sort table definitions by their array key (table name).
     *
     * @param array<string, TableDefinition> $tables
     * @return array<string, TableDefinition>
     */
    private function sortTablesByName(array $tables): array
    {
        ksort($tables);

        return $tables;
    }
}