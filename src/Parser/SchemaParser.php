<?php

namespace SchemaOps\Parser;

use ReflectionClass;
use ReflectionProperty;
use SchemaOps\Attribute\Column;
use SchemaOps\Attribute\Table;
use SchemaOps\Definition\ColumnDefinition;
use SchemaOps\Definition\TableDefinition;
use RuntimeException;

class SchemaParser
{
    public function parse(string $className): TableDefinition
    {
        if (! class_exists($className)) {
            throw new RuntimeException("Class '$className' not found.");
        }

        $reflection = new ReflectionClass($className);

        $tableAttr = $this->getAttribute($reflection, Table::class);

        if (!$tableAttr) {
            throw new RuntimeException("Class '$className' is missing the #[Table] attribute.");
        }

        $definition = new TableDefinition($tableAttr->name);

        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            $columnAttr = $this->getAttribute($property, Column::class);

            if (!$columnAttr) {
                continue;
            }

            $colDef = new ColumnDefinition(
                name: $property->getName(),
                sqlType: $this->resolveSqlType($columnAttr),
                isNullable: $columnAttr->nullable,
                isAutoIncrement: $columnAttr->autoIncrement,
                isPrimaryKey: $columnAttr->primaryKey,
                defaultValue: $columnAttr->default
            );

            $definition->addColumn($colDef);
        }

        return $definition;
    }

    public function getAttribute(ReflectionClass|ReflectionProperty $reflector, string $attributeClass): ?object
    {
        $attributes = $reflector->getAttributes($attributeClass);

        if (empty($attributes)) {
            return null;
        }

        return $attributes[0]->newInstance();
    }

    /**
     * Normalize SQL type
     */
    private function resolveSqlType(Column $attr): string
    {
        if ($attr->type === 'varchar' && $attr->length) {
            return "varchar({$attr->length})";
        }

        return $attr->type;
    }
}