<?php
declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;

/**
 * Thin PDO wrapper supporting SQLite (zero-config default) and MySQL/MariaDB.
 * The connection is established lazily so marketing pages served from cache
 * never pay for it.
 */
final class Database
{
    private ?PDO $pdo = null;

    public function __construct(private array $config)
    {
    }

    public function pdo(): PDO
    {
        return $this->pdo ??= self::connect($this->config);
    }

    public function driver(): string
    {
        return strtolower((string) ($this->config['driver'] ?? 'sqlite'));
    }

    public static function connect(array $config): PDO
    {
        $driver = strtolower((string) ($config['driver'] ?? 'sqlite'));
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        if ($driver === 'sqlite') {
            $path = (string) ($config['database'] ?? '');
            if ($path === '') {
                throw new PDOException('SQLite database path is not configured.');
            }
            if ($path !== ':memory:') {
                $dir = dirname($path);
                if (!is_dir($dir)) {
                    @mkdir($dir, 0775, true);
                }
            }
            $pdo = new PDO('sqlite:' . $path, null, null, $options);
            $pdo->exec('PRAGMA foreign_keys = ON');
            $pdo->exec('PRAGMA journal_mode = WAL');
            $pdo->exec('PRAGMA synchronous = NORMAL');
            $pdo->exec('PRAGMA busy_timeout = 5000');
            return $pdo;
        }

        if ($driver === 'mysql' || $driver === 'mariadb') {
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                $config['host'] ?? '127.0.0.1',
                (int) ($config['port'] ?? 3306),
                $config['database'] ?? '',
                $config['charset'] ?? 'utf8mb4'
            );
            return new PDO($dsn, (string) ($config['username'] ?? ''), (string) ($config['password'] ?? ''), $options);
        }

        throw new PDOException("Unsupported database driver [{$driver}].");
    }

    public function select(string $sql, array $bindings = []): array
    {
        $statement = $this->pdo()->prepare($sql);
        $statement->execute($bindings);
        return $statement->fetchAll();
    }

    public function first(string $sql, array $bindings = []): ?array
    {
        $statement = $this->pdo()->prepare($sql);
        $statement->execute($bindings);
        $row = $statement->fetch();
        return $row === false ? null : $row;
    }

    public function value(string $sql, array $bindings = [], mixed $default = null): mixed
    {
        $row = $this->first($sql, $bindings);
        return $row === null ? $default : reset($row);
    }

    public function statement(string $sql, array $bindings = []): int
    {
        $statement = $this->pdo()->prepare($sql);
        $statement->execute($bindings);
        return $statement->rowCount();
    }

    public function insert(string $table, array $data): int
    {
        $columns = array_keys($data);
        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $table,
            implode(', ', $columns),
            implode(', ', array_map(static fn ($c) => ':' . $c, $columns))
        );
        $this->statement($sql, $data);
        return (int) $this->pdo()->lastInsertId();
    }

    public function update(string $table, array $data, string $where, array $bindings = []): int
    {
        $assignments = implode(', ', array_map(static fn ($c) => "{$c} = :{$c}", array_keys($data)));
        return $this->statement("UPDATE {$table} SET {$assignments} WHERE {$where}", $data + $bindings);
    }

    public function transaction(callable $callback): mixed
    {
        $pdo = $this->pdo();
        $pdo->beginTransaction();
        try {
            $result = $callback($this);
            $pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public function tableExists(string $table): bool
    {
        try {
            if ($this->driver() === 'sqlite') {
                return (bool) $this->value(
                    "SELECT 1 FROM sqlite_master WHERE type='table' AND name = ?",
                    [$table]
                );
            }
            return (bool) $this->value(
                'SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?',
                [$table]
            );
        } catch (PDOException) {
            return false;
        }
    }

    public function tables(): array
    {
        try {
            if ($this->driver() === 'sqlite') {
                return array_column(
                    $this->select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name"),
                    'name'
                );
            }
            return array_column(
                $this->select('SELECT table_name AS name FROM information_schema.tables WHERE table_schema = DATABASE() ORDER BY table_name'),
                'name'
            );
        } catch (PDOException) {
            return [];
        }
    }
}
