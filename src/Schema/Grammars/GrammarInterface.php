<?php

namespace Atlas\Schema\Grammars;

use Atlas\Changes\TableChanges;
use Atlas\Schema\Definition\ColumnDefinition;
use Atlas\Schema\Definition\TableDefinition;

interface GrammarInterface
{
    public function createTable(TableDefinition $table): string;

    public function generateAlter(TableChanges $diff): array;

    public function generateAddColumn(string $tableName, ColumnDefinition $column): string;

    public function generateModifyColumn(string $tableName, ColumnDefinition $column): string;

    public function generateDropColumn(string $tableName, string $columnName): string;

    public function generatePreviewQuery(string $tableName, string $columnName, int $limit = 10): string;

    public function dropTable(string $tableName): string;
}
