<?php

namespace Atlas\Schema\Generation;

use Atlas\Schema\Definition\ColumnDefinition;
use Atlas\Schema\Definition\TableDefinition;

final class SchemaClassUpdater
{
    public function __construct(
        private SchemaAttributeGenerator $attributeGenerator = new SchemaAttributeGenerator()
    ) {}

    public function update(string $content, TableDefinition $table, string $className): string
    {
        $lines = $this->explodeLines($content);

        $lines = $this->ensureAttributeImports($lines);
        $lines = $this->ensureTableAttribute($lines, $table, $className);
        $lines = $this->ensureProperties($lines, $table);

        return implode("\n", $lines);
    }

    protected function ensureAttributeImports(array $lines): array
    {
        $imports = [
            'Atlas\\Attributes\\Table',
            'Atlas\\Attributes\\Column',
            'Atlas\\Attributes\\ForeignKey',
        ];

        $existing = $this->extractUseStatements($lines);

        $missing = array_values(array_filter($imports, fn ($import) => ! in_array($import, $existing, true)));

        if (empty($missing)) {
            return $lines;
        }

        return $this->insertImports($lines, $missing);
    }

    protected function ensureTableAttribute(array $lines, TableDefinition $table, string $className): array
    {
        $classIndex = $this->findClassDeclarationIndex($lines, $className);

        if ($classIndex === null) {
            return $lines;
        }

        if ($this->hasTableAttribute($lines, $classIndex)) {
            return $lines;
        }

        $attribute = $this->attributeGenerator->generateTableAttribute($table);

        array_splice($lines, $classIndex, 0, [$attribute]);

        return $lines;
    }

    protected function ensureProperties(array $lines, TableDefinition $table): array
    {
        $existingProperties = $this->collectPropertyNames($lines);

        $lines = $this->addAttributesToExistingProperties($lines, $table);
        $lines = $this->appendMissingProperties($lines, $table, $existingProperties);

        return $lines;
    }

    protected function addAttributesToExistingProperties(array $lines, TableDefinition $table): array
    {
        for ($index = 0; $index < count($lines); $index++) {
            $line = $lines[$index];
            $propertyName = $this->extractPropertyName($line);

            if (! $propertyName) {
                continue;
            }

            if (! isset($table->columns[$propertyName])) {
                continue;
            }

            $column = $table->columns[$propertyName];
            $attributeLines = $this->attributeGenerator->generateColumnAttributes($column);
            $indent = $this->extractIndentation($line);

            $block = $this->getAttributeBlock($lines, $index);
            $insert = $this->missingAttributes($attributeLines, $block);

            if (empty($insert)) {
                continue;
            }

            $indented = array_map(fn ($attr) => "{$indent}{$attr}", $insert);
            array_splice($lines, $index, 0, $indented);
            $index += count($indented);
        }

        return $lines;
    }

    protected function appendMissingProperties(
        array $lines,
        TableDefinition $table,
        array $existingProperties
    ): array {
        $missing = array_diff($table->columnNames(), $existingProperties);

        if (empty($missing)) {
            return $lines;
        }

        $insertIndex = $this->findClassClosingBraceIndex($lines);

        if ($insertIndex === null) {
            return $lines;
        }

        $blocks = $this->buildPropertyBlocks($table, $missing);

        array_splice($lines, $insertIndex, 0, $blocks);

        return $lines;
    }

    protected function buildPropertyBlocks(TableDefinition $table, array $missing): array
    {
        $blocks = [];

        foreach ($missing as $columnName) {
            $column = $table->columns[$columnName];
            $blocks = array_merge($blocks, $this->buildPropertyBlock($column));
        }

        return $blocks;
    }

    protected function buildPropertyBlock(ColumnDefinition $column): array
    {
        $lines = [];
        $attributes = $this->attributeGenerator->generateColumnAttributes($column);

        foreach ($attributes as $attribute) {
            $lines[] = "    {$attribute}";
        }

        $lines[] = '    ' . $this->attributeGenerator->generatePropertyDeclaration($column);
        $lines[] = '';

        return $lines;
    }

    protected function missingAttributes(array $attributes, array $block): array
    {
        $existing = array_map(fn ($line) => trim($line), $block);
        $missing = [];

        foreach ($attributes as $attribute) {
            if (in_array($attribute, $existing, true)) {
                continue;
            }

            if ($this->isColumnAttribute($attribute) && $this->blockHasColumnAttribute($existing)) {
                continue;
            }

            $missing[] = $attribute;
        }

        return $missing;
    }

    protected function blockHasColumnAttribute(array $block): bool
    {
        foreach ($block as $line) {
            if (str_contains($line, '#[Column')) {
                return true;
            }
        }

        return false;
    }

    protected function isColumnAttribute(string $attribute): bool
    {
        return str_contains($attribute, '#[Column');
    }

    protected function getAttributeBlock(array $lines, int $propertyIndex): array
    {
        $block = [];
        $index = $propertyIndex - 1;

        while ($index >= 0) {
            $line = trim($lines[$index]);

            if ($line === '') {
                break;
            }

            if (! str_starts_with($line, '#[')) {
                break;
            }

            $block[] = $lines[$index];
            $index--;
        }

        return array_reverse($block);
    }

    protected function extractPropertyName(string $line): ?string
    {
        if (! preg_match('/^\s*(public|protected|private)\s+[^$]*\$(\w+)/', $line, $matches)) {
            return null;
        }

        return $matches[2];
    }

    protected function collectPropertyNames(array $lines): array
    {
        $properties = [];

        foreach ($lines as $line) {
            if ($name = $this->extractPropertyName($line)) {
                $properties[] = $name;
            }
        }

        return array_values(array_unique($properties));
    }

    protected function findClassDeclarationIndex(array $lines, string $className): ?int
    {
        foreach ($lines as $index => $line) {
            if (preg_match("/\\bclass\\s+{$className}\\b/", $line)) {
                return $index;
            }
        }

        return null;
    }

    protected function findClassClosingBraceIndex(array $lines): ?int
    {
        for ($i = count($lines) - 1; $i >= 0; $i--) {
            if (trim($lines[$i]) === '}') {
                return $i;
            }
        }

        return null;
    }

    protected function hasTableAttribute(array $lines, int $classIndex): bool
    {
        for ($i = $classIndex - 1; $i >= 0; $i--) {
            $line = trim($lines[$i]);

            if ($line === '') {
                continue;
            }

            if (str_starts_with($line, '#[') && str_contains($line, 'Table')) {
                return true;
            }

            if (! str_starts_with($line, '#[')) {
                break;
            }
        }

        return false;
    }

    protected function extractUseStatements(array $lines): array
    {
        $uses = [];

        foreach ($lines as $line) {
            if (preg_match('/^use\s+([^;]+);/', trim($line), $matches)) {
                $uses[] = $matches[1];
            }
        }

        return $uses;
    }

    protected function insertImports(array $lines, array $imports): array
    {
        $insertIndex = $this->findImportInsertIndex($lines);
        $imports = array_map(fn ($import) => "use {$import};", $imports);

        array_splice($lines, $insertIndex, 0, $imports);

        return $lines;
    }

    protected function findImportInsertIndex(array $lines): int
    {
        $lastUse = null;
        $namespaceIndex = null;

        foreach ($lines as $index => $line) {
            $trimmed = trim($line);

            if (str_starts_with($trimmed, 'namespace ')) {
                $namespaceIndex = $index;
            }

            if (str_starts_with($trimmed, 'use ')) {
                $lastUse = $index;
            }
        }

        if ($lastUse !== null) {
            return $lastUse + 1;
        }

        if ($namespaceIndex !== null) {
            return $namespaceIndex + 1;
        }

        return 0;
    }

    protected function extractIndentation(string $line): string
    {
        if (preg_match('/^(\s*)/', $line, $matches)) {
            return $matches[1];
        }

        return '';
    }

    protected function explodeLines(string $content): array
    {
        return preg_split("/\r\n|\n|\r/", $content);
    }
}
