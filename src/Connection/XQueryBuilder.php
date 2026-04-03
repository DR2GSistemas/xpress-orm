<?php

declare(strict_types=1);

namespace Xpress\Orm\Connection;

use PDOStatement;

final class XQueryBuilder
{
    private string $type = 'SELECT';
    private array $selectColumns = ['*'];
    private ?string $fromTable = null;
    private ?string $fromAlias = null;
    private array $joins = [];
    private array $wheres = [];
    private array $groupBy = [];
    private array $havings = [];
    private array $orderBy = [];
    private ?int $limit = null;
    private ?int $offset = null;
    private array $params = [];
    private ?string $forUpdate = null;
    private ?string $lockMode = null;

    public function __construct(
        private readonly XConnection $connection
    ) {}

    public function select(string|array $columns = '*'): self
    {
        $this->type = 'SELECT';
        $this->selectColumns = is_array($columns) ? $columns : func_get_args();

        return $this;
    }

    public function addSelect(string|array $columns): self
    {
        $this->selectColumns = array_merge(
            $this->selectColumns,
            is_array($columns) ? $columns : func_get_args()
        );

        return $this;
    }

    public function from(string $table, ?string $alias = null): self
    {
        $this->fromTable = $table;
        $this->fromAlias = $alias;

        return $this;
    }

    public function join(string $table, string $condition, ?string $alias = null, string $type = 'INNER'): self
    {
        $this->joins[] = [
            'type' => strtoupper($type),
            'table' => $table,
            'alias' => $alias,
            'condition' => $condition
        ];

        return $this;
    }

    public function leftJoin(string $table, string $condition, ?string $alias = null): self
    {
        return $this->join($table, $condition, $alias, 'LEFT');
    }

    public function rightJoin(string $table, string $condition, ?string $alias = null): self
    {
        return $this->join($table, $condition, $alias, 'RIGHT');
    }

    public function innerJoin(string $table, string $condition, ?string $alias = null): self
    {
        return $this->join($table, $condition, $alias, 'INNER');
    }

    public function crossJoin(string $table, ?string $alias = null): self
    {
        return $this->join($table, '1=1', $alias, 'CROSS');
    }

    public function where(string|array $condition, mixed $value = null, string $operator = '='): self
    {
        if (is_array($condition)) {
            foreach ($condition as $key => $value) {
                if (is_int($key)) {
                    $this->wheres[] = ['type' => 'AND', 'condition' => $value, 'value' => null];
                } else {
                    $this->where($key, $value, $operator);
                }
            }
            return $this;
        }

        $this->wheres[] = [
            'type' => 'AND',
            'condition' => $condition,
            'value' => $value,
            'operator' => $operator
        ];

        if ($value !== null) {
            $paramName = $this->generateParamName($condition);
            $this->params[$paramName] = $value;
        }

        return $this;
    }

    public function orWhere(string|array $condition, mixed $value = null, string $operator = '='): self
    {
        if (is_array($condition)) {
            foreach ($condition as $key => $value) {
                if (is_int($key)) {
                    $this->wheres[] = ['type' => 'OR', 'condition' => $value, 'value' => null];
                } else {
                    $this->orWhere($key, $value, $operator);
                }
            }
            return $this;
        }

        $this->wheres[] = [
            'type' => 'OR',
            'condition' => $condition,
            'value' => $value,
            'operator' => $operator
        ];

        if ($value !== null) {
            $paramName = $this->generateParamName($condition);
            $this->params[$paramName] = $value;
        }

        return $this;
    }

    public function whereIn(string $column, array $values): self
    {
        if (empty($values)) {
            $this->wheres[] = ['type' => 'AND', 'condition' => '1=0', 'value' => null];
            return $this;
        }

        $placeholders = [];
        foreach ($values as $i => $value) {
            $paramName = "{$column}_in_{$i}";
            $placeholders[] = ":{$paramName}";
            $this->params[$paramName] = $value;
        }

        $this->wheres[] = [
            'type' => 'AND',
            'condition' => "{$column} IN (" . implode(', ', $placeholders) . ")",
            'value' => null
        ];

        return $this;
    }

    public function whereNotIn(string $column, array $values): self
    {
        if (empty($values)) {
            return $this;
        }

        $placeholders = [];
        foreach ($values as $i => $value) {
            $paramName = "{$column}_not_in_{$i}";
            $placeholders[] = ":{$paramName}";
            $this->params[$paramName] = $value;
        }

        $this->wheres[] = [
            'type' => 'AND',
            'condition' => "{$column} NOT IN (" . implode(', ', $placeholders) . ")",
            'value' => null
        ];

        return $this;
    }

    public function whereNull(string $column): self
    {
        $this->wheres[] = ['type' => 'AND', 'condition' => "{$column} IS NULL", 'value' => null];
        return $this;
    }

    public function whereNotNull(string $column): self
    {
        $this->wheres[] = ['type' => 'AND', 'condition' => "{$column} IS NOT NULL", 'value' => null];
        return $this;
    }

    public function whereBetween(string $column, mixed $value1, mixed $value2): self
    {
        $param1 = $this->generateParamName("{$column}_1");
        $param2 = $this->generateParamName("{$column}_2");

        $this->wheres[] = [
            'type' => 'AND',
            'condition' => "{$column} BETWEEN :{$param1} AND :{$param2}",
            'value' => null
        ];
        $this->params[$param1] = $value1;
        $this->params[$param2] = $value2;

        return $this;
    }

    public function whereLike(string $column, string $value, string $position = 'both'): self
    {
        $pattern = match ($position) {
            'start' => "{$value}%",
            'end' => "%{$value}",
            'both' => "%{$value}%",
            'exact' => $value,
            default => "%{$value}%"
        };

        $paramName = $this->generateParamName("{$column}_like");
        $this->wheres[] = [
            'type' => 'AND',
            'condition' => "{$column} LIKE :{$paramName}",
            'value' => $pattern
        ];
        $this->params[$paramName] = $pattern;

        return $this;
    }

    public function whereRaw(string $condition, array $params = []): self
    {
        $this->wheres[] = ['type' => 'AND', 'condition' => $condition, 'value' => null];
        $this->params = array_merge($this->params, $params);

        return $this;
    }

    public function groupBy(string|array $columns): self
    {
        $this->groupBy = array_merge($this->groupBy, is_array($columns) ? $columns : func_get_args());
        return $this;
    }

    public function having(string|array $condition, mixed $value = null, string $operator = '='): self
    {
        if (is_array($condition)) {
            foreach ($condition as $key => $value) {
                if (is_int($key)) {
                    $this->havings[] = ['type' => 'AND', 'condition' => $value, 'value' => null];
                } else {
                    $this->having($key, $value, $operator);
                }
            }
            return $this;
        }

        $this->havings[] = [
            'type' => 'AND',
            'condition' => $condition,
            'value' => $value,
            'operator' => $operator
        ];

        if ($value !== null) {
            $paramName = $this->generateParamName($condition);
            $this->params[$paramName] = $value;
        }

        return $this;
    }

    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $direction = strtoupper($direction);
        if (!in_array($direction, ['ASC', 'DESC'])) {
            $direction = 'ASC';
        }

        $this->orderBy[] = ['column' => $column, 'direction' => $direction];

        return $this;
    }

    public function addOrderBy(string $column, string $direction = 'ASC'): self
    {
        return $this->orderBy($column, $direction);
    }

    public function limit(?int $limit): self
    {
        $this->limit = $limit;
        return $this;
    }

    public function offset(?int $offset): self
    {
        $this->offset = $offset;
        return $this;
    }

    public function page(int $page, int $perPage = 20): self
    {
        $page = max(1, $page);
        $this->offset = ($page - 1) * $perPage;
        $this->limit = $perPage;

        return $this;
    }

    public function forUpdate(): self
    {
        $this->forUpdate = 'FOR UPDATE';
        return $this;
    }

    public function lockInShareMode(): self
    {
        $this->lockMode = 'LOCK IN SHARE MODE';
        return $this;
    }

    public function setParameter(string $key, mixed $value): self
    {
        $this->params[$key] = $value;
        return $this;
    }

    public function setParameters(array $params): self
    {
        $this->params = array_merge($this->params, $params);
        return $this;
    }

    public function getParameters(): array
    {
        return $this->params;
    }

    public function getSQL(): string
    {
        $parts = [];

        $parts[] = $this->type;
        $parts[] = implode(', ', $this->selectColumns);

        if ($this->fromTable) {
            $parts[] = "FROM {$this->fromTable}";
            if ($this->fromAlias) {
                $parts[count($parts) - 1] .= " AS {$this->fromAlias}";
            }
        }

        foreach ($this->joins as $join) {
            $table = $join['table'];
            if ($join['alias']) {
                $table .= " AS {$join['alias']}";
            }
            $parts[] = "{$join['type']} JOIN {$table} ON {$join['condition']}";
        }

        if (!empty($this->wheres)) {
            $whereParts = [];
            foreach ($this->wheres as $where) {
                $whereParts[] = [
                    'type' => $where['type'],
                    'condition' => $where['condition']
                ];
            }

            $whereSql = '';
            foreach ($whereParts as $i => $part) {
                if ($i === 0) {
                    $whereSql = $part['condition'];
                } else {
                    $whereSql .= " {$part['type']} {$part['condition']}";
                }
            }
            $parts[] = "WHERE {$whereSql}";
        }

        if (!empty($this->groupBy)) {
            $parts[] = "GROUP BY " . implode(', ', $this->groupBy);
        }

        if (!empty($this->havings)) {
            $havingParts = [];
            foreach ($this->havings as $having) {
                $havingParts[] = [
                    'type' => $having['type'],
                    'condition' => $having['condition']
                ];
            }

            $havingSql = '';
            foreach ($havingParts as $i => $part) {
                if ($i === 0) {
                    $havingSql = $part['condition'];
                } else {
                    $havingSql .= " {$part['type']} {$part['condition']}";
                }
            }
            $parts[] = "HAVING {$havingSql}";
        }

        if (!empty($this->orderBy)) {
            $orderParts = [];
            foreach ($this->orderBy as $order) {
                $orderParts[] = "{$order['column']} {$order['direction']}";
            }
            $parts[] = "ORDER BY " . implode(', ', $orderParts);
        }

        if ($this->limit !== null) {
            $parts[] = "LIMIT {$this->limit}";
        }

        if ($this->offset !== null) {
            $parts[] = "OFFSET {$this->offset}";
        }

        if ($this->forUpdate) {
            $parts[] = $this->forUpdate;
        }

        if ($this->lockMode) {
            $parts[] = $this->lockMode;
        }

        return implode(' ', array_filter($parts));
    }

    public function getQuery(): string
    {
        return $this->getSQL();
    }

    public function execute(): PDOStatement
    {
        return $this->connection->executeQuery($this->getSQL(), $this->params);
    }

    public function getResult(): array
    {
        $stmt = $this->execute();
        return $stmt->fetchAll() ?: [];
    }

    public function getOne(): ?array
    {
        $this->limit(1);
        $stmt = $this->execute();
        $result = $stmt->fetch();

        return $result === false ? null : $result;
    }

    public function getColumn(): mixed
    {
        $this->limit(1);
        $stmt = $this->execute();
        $result = $stmt->fetchColumn();

        return $result === false ? null : $result;
    }

    public function getValue(): mixed
    {
        return $this->getColumn();
    }

    public function count(): int
    {
        $originalSelect = $this->selectColumns;
        $originalOrder = $this->orderBy;

        $this->selectColumns = ['COUNT(*) as cnt'];
        $this->orderBy = [];

        if ($this->limit !== null) {
            $this->limit = null;
            $this->offset = null;
        }

        $result = $this->getOne();

        $this->selectColumns = $originalSelect;
        $this->orderBy = $originalOrder;

        return (int) ($result['cnt'] ?? 0);
    }

    public function exists(): bool
    {
        $originalSelect = $this->selectColumns;

        $this->selectColumns = ['1'];
        $this->limit(1);

        $result = $this->getOne();

        $this->selectColumns = $originalSelect;

        return $result !== null;
    }

    public function insert(array $data): string
    {
        $this->reset();
        return $this->connection->insert($this->fromTable, $data);
    }

    public function update(array $data, array $conditions = []): int
    {
        foreach ($conditions as $key => $value) {
            $this->where($key, $value);
        }

        return $this->connection->update($this->fromTable, $data, []);
    }

    public function delete(): int
    {
        $this->type = 'DELETE';
        return $this->connection->execute($this->getSQL(), $this->params);
    }

    public function reset(): self
    {
        $this->type = 'SELECT';
        $this->selectColumns = ['*'];
        $this->fromTable = null;
        $this->fromAlias = null;
        $this->joins = [];
        $this->wheres = [];
        $this->groupBy = [];
        $this->havings = [];
        $this->orderBy = [];
        $this->limit = null;
        $this->offset = null;
        $this->params = [];
        $this->forUpdate = null;
        $this->lockMode = null;

        return $this;
    }

    private function generateParamName(string $base): string
    {
        $name = preg_replace('/[^a-zA-Z0-9_]/', '_', $base);
        $i = 0;
        $paramName = $name;

        while (isset($this->params[$paramName])) {
            $paramName = "{$name}_{$i}";
            $i++;
        }

        return $paramName;
    }

    public function __toString(): string
    {
        return $this->getSQL();
    }
}
