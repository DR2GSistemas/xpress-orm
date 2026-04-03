<?php

declare(strict_types=1);

namespace Xpress\Orm\Connection;

use PDO;
use PDOException;
use PDOStatement;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final class XConnection
{
    private ?PDO $pdo = null;
    private LoggerInterface $logger;
    private array $config;
    private bool $connected = false;

    public function __construct(
        array $config,
        ?LoggerInterface $logger = null
    ) {
        $this->config = $this->validateConfig($config);
        $this->logger = $logger ?? new NullLogger();
    }

    private function validateConfig(array $config): array
    {
        $required = ['driver', 'host', 'database', 'username'];

        foreach ($required as $key) {
            if (empty($config[$key])) {
                throw new \InvalidArgumentException("Config key '{$key}' is required");
            }
        }

        $config['port'] = $config['port'] ?? 3306;
        $config['charset'] = $config['charset'] ?? 'utf8mb4';
        $config['port'] = (int) $config['port'];

        return $config;
    }

    public function connect(): void
    {
        if ($this->connected && $this->pdo !== null) {
            return;
        }

        $dsn = $this->buildDsn();

        try {
            $this->pdo = new PDO(
                $dsn,
                $this->config['username'],
                $this->config['password'] ?? '',
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::ATTR_STRINGIFY_FETCHES => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES '{$this->config['charset']}' COLLATE '{$this->config['charset']}_unicode_ci'",
                ]
            );

            $this->connected = true;
            $this->logger->info('Database connected successfully', [
                'host' => $this->config['host'],
                'database' => $this->config['database']
            ]);
        } catch (PDOException $e) {
            $this->logger->error('Database connection failed', [
                'error' => $e->getMessage(),
                'host' => $this->config['host']
            ]);
            throw new \RuntimeException("Failed to connect to database: {$e->getMessage()}", 0, $e);
        }
    }

    private function buildDsn(): string
    {
        $driver = $this->config['driver'];

        return match ($driver) {
            'pdo_mysql', 'mysql' => sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                $this->config['host'],
                $this->config['port'],
                $this->config['database'],
                $this->config['charset']
            ),
            'pdo_pgsql', 'pgsql' => sprintf(
                'pgsql:host=%s;port=%d;dbname=%s',
                $this->config['host'],
                $this->config['port'],
                $this->config['database']
            ),
            'pdo_sqlite', 'sqlite' => sprintf(
                'sqlite:%s',
                $this->config['database']
            ),
            default => throw new \InvalidArgumentException("Unsupported driver: {$driver}")
        };
    }

    public function disconnect(): void
    {
        $this->pdo = null;
        $this->connected = false;
        $this->logger->info('Database disconnected');
    }

    public function getPdo(): PDO
    {
        if (!$this->connected) {
            $this->connect();
        }
        return $this->pdo;
    }

    public function isConnected(): bool
    {
        return $this->connected;
    }

    public function beginTransaction(): void
    {
        $this->getPdo()->beginTransaction();
        $this->logger->debug('Transaction started');
    }

    public function commit(): void
    {
        $this->getPdo()->commit();
        $this->logger->debug('Transaction committed');
    }

    public function rollback(): void
    {
        $this->getPdo()->rollBack();
        $this->logger->debug('Transaction rolled back');
    }

    public function inTransaction(): bool
    {
        return $this->getPdo()->inTransaction();
    }

    public function executeQuery(string $sql, array $params = []): PDOStatement
    {
        $this->logger->debug('Executing query', [
            'sql' => $this->sanitizeSql($sql),
            'params_count' => count($params)
        ]);

        $stmt = $this->getPdo()->prepare($sql);

        foreach ($params as $key => $value) {
            $stmt->bindValue(
                is_int($key) ? $key + 1 : $key,
                $value,
                $this->getPdoType($value)
            );
        }

        $stmt->execute();

        return $stmt;
    }

    public function execute(string $sql, array $params = []): int
    {
        $stmt = $this->executeQuery($sql, $params);
        return $stmt->rowCount();
    }

    public function lastInsertId(string $sequence = null): string
    {
        return (string) $this->getPdo()->lastInsertId($sequence);
    }

    public function selectOne(string $sql, array $params = []): ?array
    {
        $stmt = $this->executeQuery($sql, $params);
        $result = $stmt->fetch();

        if ($result === false) {
            return null;
        }

        return $result;
    }

    public function selectAll(string $sql, array $params = []): array
    {
        $stmt = $this->executeQuery($sql, $params);
        return $stmt->fetchAll() ?: [];
    }

    public function selectColumn(string $sql, array $params = []): mixed
    {
        $stmt = $this->executeQuery($sql, $params);
        $result = $stmt->fetch(PDO::FETCH_COLUMN);

        return $result === false ? null : $result;
    }

    public function selectValue(string $sql, array $params = []): mixed
    {
        return $this->selectColumn($sql, $params);
    }

    public function exists(string $table, array $conditions): bool
    {
        $where = $this->buildWhereClause($conditions, 'AND', true);
        $sql = "SELECT 1 FROM {$table} WHERE {$where['sql']} LIMIT 1";

        return $this->selectOne($sql, $where['params']) !== null;
    }

    public function count(string $table, array $conditions = []): int
    {
        $sql = "SELECT COUNT(*) FROM {$table}";

        if (!empty($conditions)) {
            $where = $this->buildWhereClause($conditions, 'AND', true);
            $sql .= " WHERE {$where['sql']}";
            return (int) $this->selectColumn($sql, $where['params']);
        }

        return (int) $this->selectColumn($sql);
    }

    public function insert(string $table, array $data): string
    {
        $columns = array_keys($data);
        $placeholders = array_map(fn($col) => ":{$col}", $columns);

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $table,
            implode(', ', $columns),
            implode(', ', $placeholders)
        );

        $this->execute($sql, $data);

        return $this->lastInsertId();
    }

    public function update(string $table, array $data, array $conditions): int
    {
        $set = array_map(fn($col) => "{$col} = :{$col}", array_keys($data));
        $where = $this->buildWhereClause($conditions, 'AND', true);

        $sql = sprintf(
            'UPDATE %s SET %s WHERE %s',
            $table,
            implode(', ', $set),
            $where['sql']
        );

        return $this->execute($sql, array_merge($data, $where['params']));
    }

    public function delete(string $table, array $conditions): int
    {
        $where = $this->buildWhereClause($conditions, 'AND', true);

        $sql = sprintf(
            'DELETE FROM %s WHERE %s',
            $table,
            $where['sql']
        );

        return $this->execute($sql, $where['params']);
    }

    public function upsert(string $table, array $data, array $uniqueColumns): int
    {
        $columns = array_keys($data);
        $placeholders = array_map(fn($col) => ":{$col}", $columns);
        $updateSet = array_map(fn($col) => "{$col} = VALUES({$col})", $columns);

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s) ON DUPLICATE KEY UPDATE %s',
            $table,
            implode(', ', $columns),
            implode(', ', $placeholders),
            implode(', ', $updateSet)
        );

        $this->execute($sql, $data);

        return $this->getPdo()->lastInsertId() ?: 0;
    }

    public function buildWhereClause(array $conditions, string $operator = 'AND', bool $prepared = true): array
    {
        $parts = [];
        $params = [];

        foreach ($conditions as $key => $value) {
            if (is_int($key)) {
                $parts[] = (string) $value;
                continue;
            }

            if ($value === null) {
                $parts[] = "{$key} IS NULL";
            } elseif (is_array($value)) {
                $placeholders = array_map(fn($i) => ":{$key}_{$i}", array_keys($value));
                $parts[] = "{$key} IN (" . implode(', ', $placeholders) . ")";
                foreach ($value as $i => $v) {
                    $params["{$key}_{$i}"] = $v;
                }
            } else {
                if ($prepared) {
                    $parts[] = "{$key} = :{$key}";
                    $params[$key] = $value;
                } else {
                    $safeValue = addslashes((string) $value);
                    $parts[] = "{$key} = '{$safeValue}'";
                }
            }
        }

        $operator = strtoupper($operator);
        $sql = implode(" {$operator} ", $parts);

        return ['sql' => $sql, 'params' => $params];
    }

    public function createQueryBuilder(): XQueryBuilder
    {
        return new XQueryBuilder($this);
    }

    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    private function getPdoType(mixed $value): int
    {
        return match (true) {
            is_int($value) => PDO::PARAM_INT,
            is_bool($value) => PDO::PARAM_BOOL,
            is_null($value) => PDO::PARAM_NULL,
            default => PDO::PARAM_STR
        };
    }

    private function sanitizeSql(string $sql): string
    {
        $len = strlen($sql);
        if ($len > 200) {
            return substr($sql, 0, 200) . '...';
        }
        return $sql;
    }

    public function getConfig(): array
    {
        return $this->config;
    }

    public function quote(string $value): string
    {
        return $this->getPdo()->quote($value);
    }

    public function tableExists(string $table): bool
    {
        $sql = "SELECT COUNT(*) FROM information_schema.tables 
                WHERE table_schema = ? AND table_name = ?";

        return (int) $this->selectColumn($sql, [
            $this->config['database'],
            $table
        ]) > 0;
    }

    public function getTableColumns(string $table): array
    {
        $sql = "SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE, COLUMN_KEY, COLUMN_DEFAULT, EXTRA
                FROM information_schema.columns
                WHERE table_schema = ? AND table_name = ?
                ORDER BY ORDINAL_POSITION";

        return $this->selectAll($sql, [
            $this->config['database'],
            $table
        ]);
    }

    public function getTableIndexes(string $table): array
    {
        $sql = "SELECT INDEX_NAME, COLUMN_NAME, NON_UNIQUE
                FROM information_schema.statistics
                WHERE table_schema = ? AND table_name = ?
                ORDER BY INDEX_NAME, SEQ_IN_INDEX";

        return $this->selectAll($sql, [
            $this->config['database'],
            $table
        ]);
    }
}
