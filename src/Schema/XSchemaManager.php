<?php

declare(strict_types=1);

namespace Xpress\Orm\Schema;

use Xpress\Orm\Connection\XConnection;
use Xpress\Orm\Entity\XEntityMap;

final class XSchemaManager
{
    public function __construct(
        private readonly XConnection $connection
    ) {}

    public function createTable(string $className): void
    {
        $map = XEntityMap::get($className);
        $columns = $map->getColumns();
        $indexes = $map->getIndexes();

        $sql = "CREATE TABLE IF NOT EXISTS {$map->getTable()} (\n";

        $columnDefs = [];
        $primaryKeys = [];

        foreach ($columns as $property => $column) {
            $def = $this->buildColumnDefinition($column);
            $columnDefs[] = "    " . $def;

            if ($column['isPrimaryKey']) {
                $primaryKeys[] = $column['column'];
            }
        }

        if (!empty($primaryKeys)) {
            $columnDefs[] = "    PRIMARY KEY (" . implode(', ', $primaryKeys) . ")";
        }

        foreach ($indexes as $index) {
            $indexName = $index->name ?? "idx_{$map->getTable()}_{$index->columns[0]}";
            $unique = $index->unique ? 'UNIQUE' : '';
            $columnDefs[] = "    {$unique} INDEX {$indexName} (" . implode(', ', $index->columns) . ")";
        }

        $sql .= implode(",\n", $columnDefs);
        $sql .= "\n)";

        if ($map->getSchema()) {
            $sql .= " ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        }

        $this->connection->execute($sql);
    }

    public function dropTable(string $className): void
    {
        $map = XEntityMap::get($className);
        $sql = "DROP TABLE IF EXISTS {$map->getTable()}";
        $this->connection->execute($sql);
    }

    public function tableExists(string $table): bool
    {
        return $this->connection->tableExists($table);
    }

    public function getTableColumns(string $table): array
    {
        return $this->connection->getTableColumns($table);
    }

    public function getTableIndexes(string $table): array
    {
        return $this->connection->getTableIndexes($table);
    }

    public function addColumn(string $table, string $columnName, array $definition): void
    {
        $sql = "ALTER TABLE {$table} ADD COLUMN {$columnName} " . $definition;
        $this->connection->execute($sql);
    }

    public function dropColumn(string $table, string $columnName): void
    {
        $sql = "ALTER TABLE {$table} DROP COLUMN {$columnName}";
        $this->connection->execute($sql);
    }

    public function modifyColumn(string $table, string $columnName, array $definition): void
    {
        $sql = "ALTER TABLE {$table} MODIFY COLUMN {$columnName} " . $definition;
        $this->connection->execute($sql);
    }

    public function renameColumn(string $table, string $oldName, string $newName): void
    {
        $sql = "ALTER TABLE {$table} CHANGE COLUMN {$oldName} {$newName}";
        $this->connection->execute($sql);
    }

    public function addIndex(string $table, string $indexName, array $columns, bool $unique = false): void
    {
        $unique = $unique ? 'UNIQUE' : '';
        $sql = "ALTER TABLE {$table} ADD {$unique} INDEX {$indexName} (" . implode(', ', $columns) . ")";
        $this->connection->execute($sql);
    }

    public function dropIndex(string $table, string $indexName): void
    {
        $sql = "ALTER TABLE {$table} DROP INDEX {$indexName}";
        $this->connection->execute($sql);
    }

    public function addForeignKey(
        string $table,
        string $constraintName,
        string $column,
        string $referencedTable,
        string $referencedColumn,
        string $onDelete = 'CASCADE',
        string $onUpdate = 'CASCADE'
    ): void {
        $sql = "ALTER TABLE {$table} ADD CONSTRAINT {$constraintName} 
                FOREIGN KEY ({$column}) REFERENCES {$referencedTable}({$referencedColumn})
                ON DELETE {$onDelete} ON UPDATE {$onUpdate}";

        $this->connection->execute($sql);
    }

    public function dropForeignKey(string $table, string $constraintName): void
    {
        $sql = "ALTER TABLE {$table} DROP FOREIGN KEY {$constraintName}";
        $this->connection->execute($sql);
    }

    public function truncateTable(string $table): void
    {
        $sql = "TRUNCATE TABLE {$table}";
        $this->connection->execute($sql);
    }

    public function getCreateTableSQL(string $table): ?array
    {
        $sql = "SHOW CREATE TABLE {$table}";
        $result = $this->connection->selectOne($sql);

        return $result ?? null;
    }

    private function buildColumnDefinition(array $column): string
    {
        $parts = [];

        $type = $this->mapColumnType($column);
        $parts[] = $type;

        if (!$column['nullable']) {
            $parts[] = 'NOT NULL';
        }

        if ($column['default'] !== null) {
            $default = is_string($column['default']) ? "'{$column['default']}'" : $column['default'];
            $parts[] = "DEFAULT {$default}";
        }

        if ($column['isAutoIncrement']) {
            $parts[] = 'AUTO_INCREMENT';
        }

        if ($column['comment']) {
            $parts[] = "COMMENT '{$column['comment']}'";
        }

        if ($column['unique'] && !$column['isPrimaryKey']) {
            $parts[] = 'UNIQUE';
        }

        return implode(' ', $parts);
    }

    private function mapColumnType(array $column): string
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
}
