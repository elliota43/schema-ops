<?php

namespace SchemaOps\Schema\Parser;

use ReflectionClass;
use ReflectionProperty;
use RuntimeException;
use SchemaOps\Attributes\Column;
use SchemaOps\Attributes\ForeignKey;
use SchemaOps\Attributes\Id;
use SchemaOps\Attributes\Index;
use SchemaOps\Attributes\PrimaryKey;
use SchemaOps\Attributes\SoftDeletes;
use SchemaOps\Attributes\Table;
use SchemaOps\Attributes\Timestamps;
use SchemaOps\Attributes\Uuid;
use SchemaOps\Schema\Definition\ColumnDefinition;
use SchemaOps\Schema\Definition\TableDefinition;

class SchemaParser
{
    /**
     * Parse a class into a table definition.
     */
    public function parse(string $className): TableDefinition
    {
        $reflection = $this->reflectClass($className);

        $tableAttribute = $this->extractTableAttribute($reflection);

        return $this->buildTableDefinition($reflection, $tableAttribute);
    }

    /**
     * Create a reflection instance for the given class.
     */
    protected function reflectClass(string $className): ReflectionClass
    {
        if (! class_exists($className)) {
            throw new RuntimeException(
                "Class '{$className}' not found. Make sure it's loaded or autoloadable."
            );
        }

        return new ReflectionClass($className);
    }

    /**
     * Extract the Table attribute from a reflected class.
     */
    protected function extractTableAttribute(ReflectionClass $reflection): Table
    {
        $attribute = $this->getAttribute($reflection, Table::class);

        if (! $attribute) {
            throw new RuntimeException(
                "Class '{$reflection->getName()}' is missing the #[Table] attribute."
            );
        }

        return $attribute;
    }

    /**
     * Build a complete table definition from reflection and attribute.
     */
    protected function buildTableDefinition(
        ReflectionClass $reflection,
        Table $tableAttribute
    ): TableDefinition {
        $definition = new TableDefinition($tableAttribute->name);

        $this->addConvenienceColumns($reflection, $definition);

        foreach ($this->getSchemaProperties($reflection) as $property) {
            if ($column = $this->buildColumnDefinition($property)) {
                $definition->addColumn($column);
            }
        }

        $this->addTimestampColumns($reflection, $definition);
        
        $this->handleCompositePrimaryKey($reflection, $definition);
        
        $this->parseIndexes($reflection, $definition);

        return $definition;
    }

    /**
     * Checks for #[Id] / #[Uuid] columns, and adds them to the TableDefinition
     * if it exists.
     *
     * @param ReflectionClass $reflection
     * @param TableDefinition $definition
     * @return void
     */
    protected function addConvenienceColumns(ReflectionClass $reflection, TableDefinition $definition): void
    {
        if ($id = $this->getAttribute($reflection, Id::class)) {
            $definition->addColumn(($this->buildIdColumn($id)));
        }

        if ($uuid = $this->getAttribute($reflection, Uuid::class)) {
            $definition->addColumn($this->buildUuidColumn($uuid));
        }
    }

    /**
     * Checks for #[Timestamps] / #[SoftDeletes] attributes, and adds them to the TableDefinition
     * if they exist.
     *
     * @param ReflectionClass $reflection
     * @param TableDefinition $definition
     * @return void
     */
    protected function addTimestampColumns(ReflectionClass $reflection, TableDefinition $definition): void
    {
        if ($timestamps = $this->getAttribute($reflection, Timestamps::class)) {
            $this->addCreatedUpdatedColumns($definition, $timestamps);
        }

        if ($softDeletes = $this->getAttribute($reflection, SoftDeletes::class)) {
            $this->addSoftDeleteColumn($definition, $softDeletes);
        }
    }

    /**
     * Get all public properties that should be considered for schema.
     */
    protected function getSchemaProperties(ReflectionClass $reflection): array
    {
        return $reflection->getProperties(ReflectionProperty::IS_PUBLIC);
    }

    /**
     * Build a column definition from a reflected property.
     */
    protected function buildColumnDefinition(ReflectionProperty $property): ?ColumnDefinition
    {
        $attribute = $this->getAttribute($property, Column::class);

        if (! $attribute) {
            return null;
        }

        return new ColumnDefinition(
            name: $property->getName(),
            sqlType: $this->resolveSqlType($attribute),
            isNullable: $attribute->nullable,
            isAutoIncrement: $attribute->autoIncrement,
            isPrimaryKey: $attribute->primaryKey,
            defaultValue: $attribute->default,
            onUpdate: $attribute->onUpdate,
            isUnique: $attribute->unique,
            foreignKeys: $this->parseForeignKeys($property)
        );
    }

    /**
     * Get an attribute instance from a reflection object.
     */
    protected function getAttribute(
        ReflectionClass|ReflectionProperty $reflector,
        string $attributeClass
    ): ?object {
        $attributes = $reflector->getAttributes($attributeClass);

        if (empty($attributes)) {
            return null;
        }

        return $attributes[0]->newInstance();
    }

    /**
     * Resolve the SQL type from a column attribute.
     */
    protected function resolveSqlType(Column $attribute): string
    {
        $type = $attribute->type;
        
        // Handle types with length
        if (in_array($type, ['varchar', 'char']) && $attribute->length) {
            $type .= "({$attribute->length})";
        }
        
        // Handle decimal with precision/scale
        if ($type === 'decimal' && $attribute->precision) {
            if ($attribute->scale !== null) {
                $type .= "({$attribute->precision}, {$attribute->scale})";
            } else {
                $type .= "({$attribute->precision})";
            }
        }
        
        // Handle enum with values
        if ($type === 'enum' && $attribute->values) {
            $values = array_map(fn($v) => "'{$v}'", $attribute->values);
            $type .= '(' . implode(', ', $values) . ')';
        }
        
        // Handle unsigned modifier
        if ($attribute->unsigned && in_array($attribute->type, ['tinyint', 'smallint', 'mediumint', 'int', 'integer', 'bigint'])) {
            $type .= ' unsigned';
        }

        return $type;
    }

    /**
     * Build #[Id] ColumnDefinition
     * @param Id $attr
     * @return ColumnDefinition
     */
    protected function buildIdColumn(Id $attr): ColumnDefinition
    {
        $type = $attr->type;
        if ($attr->unsigned) {
            $type .= ' unsigned';
        }
        
        return new ColumnDefinition(
            name: $attr->name,
            sqlType: $type,
            isNullable: false,
            isAutoIncrement: true,
            isPrimaryKey: true,
            defaultValue: null,
            onUpdate: null,
            isUnique: false,
            foreignKeys: []
        );
    }

    /**
     * Build #[Uuid] ColumnDefinition.
     * @param Uuid $attr
     * @return ColumnDefinition
     */
    protected function buildUuidColumn(Uuid $attr): ColumnDefinition
    {
        return new ColumnDefinition(
            name: $attr->name,
            sqlType: 'char(36)',
            isNullable: false,
            isAutoIncrement: false,
            isPrimaryKey: $attr->primaryKey,
            defaultValue: null,
            onUpdate: null,
            isUnique: false,
            foreignKeys: []
        );
    }

    /**
     * Adds created_at, updated_at ColumnDefinition to TableDefinition
     * For the #[Timestamps] attribute.
     *
     * @param TableDefinition $definition
     * @param Timestamps $attr
     * @return void
     */
    protected function addCreatedUpdatedColumns(TableDefinition $definition, Timestamps $attr): void
    {
        $definition->addColumn(new ColumnDefinition(
            name: $attr->createdAtColumn,
            sqlType: 'timestamp',
            isNullable: $attr->nullable,
            isAutoIncrement: false,
            isPrimaryKey: false,
            defaultValue: 'CURRENT_TIMESTAMP',
            onUpdate: null,
            isUnique: false,
            foreignKeys: []
        ));

        $definition->addColumn(new ColumnDefinition(
            name: $attr->updatedAtColumn,
            sqlType: 'timestamp',
            isNullable: $attr->nullable,
            isAutoIncrement: false,
            isPrimaryKey: false,
            defaultValue: 'CURRENT_TIMESTAMP',
            onUpdate: 'CURRENT_TIMESTAMP',
            isUnique: false,
            foreignKeys: []
        ));
    }

    /**
     * Add column for soft deletes to specified TableDefinition
     * defaults to 'deleted_at'
     * For #[SoftDeletes] attribute.
     *
     * @param TableDefinition $definition
     * @param SoftDeletes $attr
     * @return void
     */
    protected function addSoftDeleteColumn(TableDefinition $definition, SoftDeletes $attr): void
    {
        $definition->addColumn(new ColumnDefinition(
            name: $attr->column,
            sqlType: 'timestamp',
            isNullable: true,
            isAutoIncrement: false,
            isPrimaryKey: false,
            defaultValue: null,
            onUpdate: null,
            isUnique: false,
            foreignKeys: []
        ));
    }

    /**
     * Handle composite primary key attribute.
     *
     * @param ReflectionClass $reflection
     * @param TableDefinition $definition
     * @return void
     */
    protected function handleCompositePrimaryKey(ReflectionClass $reflection, TableDefinition $definition): void
    {
        $pkAttr = $this->getAttribute($reflection, PrimaryKey::class);
        
        if ($pkAttr) {
            $definition->compositePrimaryKey = $pkAttr->columns;
        }
    }

    /**
     * Parse foreign key attributes from a property.
     *
     * @param ReflectionProperty $property
     * @return array
     */
    protected function parseForeignKeys(ReflectionProperty $property): array
    {
        $foreignKeys = [];
        $attributes = $property->getAttributes(ForeignKey::class);

        foreach ($attributes as $attribute) {
            $fk = $attribute->newInstance();
            $foreignKeys[] = [
                'references' => $fk->references,
                'onDelete' => $fk->onDelete,
                'onUpdate' => $fk->onUpdate,
                'name' => $fk->name,
            ];
        }

        return $foreignKeys;
    }

    /**
     * Parse index attributes from class.
     *
     * @param ReflectionClass $reflection
     * @param TableDefinition $definition
     * @return void
     */
    protected function parseIndexes(ReflectionClass $reflection, TableDefinition $definition): void
    {
        $attributes = $reflection->getAttributes(Index::class);

        foreach ($attributes as $attribute) {
            $index = $attribute->newInstance();
            $definition->addIndex([
                'columns' => $index->columns,
                'name' => $index->name,
                'unique' => $index->unique,
                'type' => $index->type,
                'length' => $index->length,
            ]);
        }
    }
}