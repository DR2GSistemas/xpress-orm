<?php

declare(strict_types=1);

namespace Xpress\Orm\Schema;

use Xpress\Orm\Connection\XConnection;
use Xpress\Orm\Entity\XEntityMap;

final class XAutoMigrate
{
    private XSchemaManager $schemaManager;
    private bool $safeMode = true;
    private bool $verbose = false;
    private array $logs = [];

    public function __construct(
        private readonly XConnection $connection
    ) {
        $this->schemaManager = new XSchemaManager($connection);
    }

    public function setSafeMode(bool $safe): self
    {
        $this->safeMode = $safe;
        return $this;
    }

    public function setVerbose(bool $verbose): self
    {
        $this->verbose = $verbose;
        return $this;
    }

    public function updateSchema(array $classes): array
    {
        $this->logs = [];

        foreach ($classes as $class) {
            $this->updateEntity($class);
        }

        $this->createRelations($classes);

        return $this->logs;
    }

    public function createSchema(array $classes): array
    {
        $this->logs = [];

        foreach ($classes as $class) {
            $this->createEntity($class);
        }

        $this->createRelations($classes);

        return $this->logs;
    }

    public function dropSchema(array $classes): array
    {
        $this->logs = [];

        foreach ($classes as $class) {
            $this->dropEntity($class);
        }

        return $this->logs;
    }

    public function createEntity(string $class): void
    {
        $map = XEntityMap::get($class);

        if ($this->schemaManager->tableExists($map->getTable())) {
            $this->log("Table {$map->getTable()} already exists");
            return;
        }

        $this->schemaManager->createTable($class);
        $this->log("Created table {$map->getTable()}");
    }

    public function updateEntity(string $class): void
    {
        $map = XEntityMap::get($class);
        $table = $map->getTable();

        if (!$this->schemaManager->tableExists($table)) {
            $this->schemaManager->createTable($class);
            $this->log("Created table {$table}");
            return;
        }

        $existingColumns = $this->schemaManager->getTableColumns($table);
        $existingColumnNames = array_column($existingColumns, 'COLUMN_NAME');
        $existingIndexes = $this->schemaManager->getTableIndexes($table);

        $entityColumns = $map->getColumns();
        $entityColumnNames = array_column($entityColumns, 'column');

        foreach ($entityColumns as $property => $column) {
            $columnName = $column['column'];

            if (!in_array($columnName, $existingColumnNames)) {
                $this->addColumn($table, $property, $column);
                $this->log("Added column {$columnName} to {$table}");
            } else {
                $this->updateColumn($table, $property, $column, $existingColumns);
            }
        }

        $this->updateIndexes($table, $map->getIndexes(), $existingIndexes);

        $this->log("Updated table {$table}");
    }

    public function dropEntity(string $class): void
    {
        $map = XEntityMap::get($class);

        if (!$this->schemaManager->tableExists($map->getTable())) {
            $this->log("Table {$map->getTable()} does not exist");
            return;
        }

        $this->schemaManager->dropTable($class);
        $this->log("Dropped table {$map->getTable()}");
    }

    public function createRelations(array $classes): void
    {
        foreach ($classes as $class) {
            $map = XEntityMap::get($class);

            foreach ($map->getRelations() as $property => $relation) {
                if ($relation['type'] === 'manyToOne') {
                    $this->createManyToOneRelation($class, $property, $relation);
                } elseif ($relation['type'] === 'manyToMany') {
                    $this->createManyToManyRelation($class, $property, $relation);
                }
            }
        }
    }

    public function getLog(): array
    {
        return $this->logs;
    }

    public function clearLog(): void
    {
        $this->logs = [];
    }

    private function addColumn(string $table, string $property, array $column): void
    {
        $columnName = $column['column'];
        $definition = $this->buildColumnDefinition($column);

        $sql = "ALTER TABLE {$table} ADD COLUMN {$columnName} {$definition}";

        if (!$column['nullable']) {
            $sql .= " NOT NULL";
        }

        if ($column['default'] !== null) {
            $default = is_string($column['default']) ? "'{$column['default']}'" : $column['default'];
            $sql .= " DEFAULT {$default}";
        }

        $this->connection->execute($sql);
    }

    private function updateColumn(string $table, string $property, array $column, array $existingColumns): void
    {
        $columnName = $column['column'];

        foreach ($existingColumns as $existing) {
            if ($existing['COLUMN_NAME'] !== $columnName) {
                continue;
            }

            $needsUpdate = false;

            if ($existing['IS_NULLABLE'] === 'YES' && !$column['nullable']) {
                $needsUpdate = true;
            }

            if ($existing['IS_NULLABLE'] === 'NO' && $column['nullable']) {
                $needsUpdate = true;
            }

            if ($column['default'] !== null && $existing['COLUMN_DEFAULT'] !== $column['default']) {
                $needsUpdate = true;
            }

            if ($needsUpdate && !$this->safeMode) {
                $this->modifyColumn($table, $columnName, $column);
                $this->log("Updated column {$columnName} in {$table}");
            }
        }
    }

    private function modifyColumn(string $table, string $columnName, array $column): void
    {
        $definition = $this->buildColumnDefinition($column);

        $sql = "ALTER TABLE {$table} MODIFY COLUMN {$columnName} {$definition}";

        if (!$column['nullable']) {
            $sql .= " NOT NULL";
        }

        $this->connection->execute($sql);
    }

    private function updateIndexes(string $table, array $entityIndexes, array $existingIndexes): void
    {
        foreach ($entityIndexes as $index) {
            $indexName = $index->name ?? "idx_{$table}_{$index->columns[0]}";

            $exists = false;
            foreach ($existingIndexes as $existing) {
                if ($existing['INDEX_NAME'] === $indexName) {
                    $exists = true;
                    break;
                }
            }

            if (!$exists) {
                $unique = $index->unique ? 'UNIQUE' : '';
                $columns = implode(', ', $index->columns);
                $sql = "ALTER TABLE {$table} ADD {$unique} INDEX {$indexName} ({$columns})";
                $this->connection->execute($sql);
                $this->log("Added index {$indexName} to {$table}");
            }
        }
    }

    private function createManyToOneRelation(string $class, string $property, array $relation): void
    {
        $map = XEntityMap::get($class);
        $table = $map->getTable();

        if (!$this->schemaManager->tableExists($table)) {
            return;
        }

        $targetMap = XEntityMap::get($relation['targetEntity']);
        $foreignKey = $relation['foreignKey'] ?? strtolower(basename(str_replace('\\', '/', $relation['targetEntity']))) . '_id';

        $existingColumns = $this->schemaManager->getTableColumns($table);
        $columnNames = array_column($existingColumns, 'COLUMN_NAME');

        if (in_array($foreignKey, $columnNames)) {
            return;
        }

        $targetPk = $targetMap->getPrimaryKeyColumn() ?? 'id';

        $sql = "ALTER TABLE {$table} ADD COLUMN {$foreignKey} INT NULL";
        $this->connection->execute($sql);

        $constraintName = "fk_{$table}_{$foreignKey}";
        $sql = "ALTER TABLE {$table} ADD CONSTRAINT {$constraintName} 
                FOREIGN KEY ({$foreignKey}) REFERENCES {$targetMap->getTable()}({$targetPk})
                ON DELETE SET NULL ON UPDATE CASCADE";

        try {
            $this->connection->execute($sql);
            $this->log("Created many-to-one relation: {$table}.{$foreignKey} -> {$targetMap->getTable()}");
        } catch (\Exception) {
            $this->log("Note: Foreign key constraint could not be created for {$table}.{$foreignKey}");
        }
    }

    private function createManyToManyRelation(string $class, string $property, array $relation): void
    {
        $map = XEntityMap::get($class);
        $targetMap = XEntityMap::get($relation['targetEntity']);

        $joinTable = $relation['joinTable'] ?? $this->generateJoinTableName($map->getTable(), $targetMap->getTable());

        if ($this->schemaManager->tableExists($joinTable)) {
            return;
        }

        $sourcePk = $map->getPrimaryKeyColumn() ?? 'id';
        $targetPk = $targetMap->getPrimaryKeyColumn() ?? 'id';
        $sourceColumn = $relation['joinColumn'] ?? $map->getTable() . '_id';
        $targetColumn = $relation['inverseJoinColumn'] ?? $targetMap->getTable() . '_id';

        $sql = "CREATE TABLE {$joinTable} (
            {$sourceColumn} INT NOT NULL,
            {$targetColumn} INT NOT NULL,
            PRIMARY KEY ({$sourceColumn}, {$targetColumn}),
            INDEX idx_{$sourceColumn} ({$sourceColumn}),
            INDEX idx_{$targetColumn} ({$targetColumn})
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $this->connection->execute($sql);
        $this->log("Created many-to-many join table: {$joinTable}");
    }

    private function generateJoinTableName(string $table1, string $table2): string
    {
        $tables = [$table1, $table2];
        sort($tables);
        return implode('_', $tables);
    }

    private function buildColumnDefinition(array $column): string
    {
        $type = strtolower($column['type']);
        $length = $column['length'];
        $precision = $column['precision'];
        $scale = $column['scale'];

        return match ($type) {
            'int' => "INT({$length})",
            'bigint' => "BIGINT({$length})",
            'varchar' => "VARCHAR({$length})",
            'text' => 'TEXT',
            'mediumtext' => 'MEDIUMTEXT',
            'longtext' => 'LONGTEXT',
            'boolean' => 'TINYINT(1)',
            'decimal' => "DECIMAL({$precision}, {$scale})",
            'float' => 'FLOAT',
            'double' => 'DOUBLE',
            'date' => 'DATE',
            'datetime' => 'DATETIME',
            'timestamp' => 'TIMESTAMP',
            'time' => 'TIME',
            'enum' => 'ENUM(' . implode(', ', array_map(fn($v) => "'{$v}'", $column['enum'] ?? [])) . ')',
            'json' => 'JSON',
            'blob' => 'BLOB',
            'mediumblob' => 'MEDIUMBLOB',
            'longblob' => 'LONGBLOB',
            default => $type
        };
    }

    private function log(string $message): void
    {
        $this->logs[] = $message;

        if ($this->verbose) {
            echo $message . PHP_EOL;
        }
    }
}
