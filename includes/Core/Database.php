<?php
declare(strict_types=1);

namespace Techbiss\Core;

use PDO;
use PDOException;
use PDOStatement;
use RuntimeException;

/**
 * Thin PDO wrapper. Every query in the application goes through here, and every
 * value is bound as a parameter — no string interpolation of user input, ever.
 */
final class Database
{
    private static ?Database $instance = null;

    private PDO $pdo;
    private int $queryCount = 0;

    private function __construct(array $cfg)
    {
        if ($cfg['socket'] !== '' && $cfg['socket'] !== null) {
            $dsn = sprintf('mysql:unix_socket=%s;dbname=%s;charset=%s', $cfg['socket'], $cfg['name'], $cfg['charset']);
        } else {
            $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $cfg['host'], (int) $cfg['port'], $cfg['name'], $cfg['charset']);
        }

        try {
            $this->pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_STRINGIFY_FETCHES  => false,
            ]);
        } catch (PDOException $e) {
            throw new RuntimeException('Database connection failed: ' . $e->getMessage(), (int) $e->getCode(), $e);
        }
    }

    public static function boot(array $cfg): void
    {
        self::$instance = new self($cfg);
    }

    public static function instance(): Database
    {
        if (self::$instance === null) {
            throw new RuntimeException('Database has not been booted.');
        }
        return self::$instance;
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    public function queryCount(): int
    {
        return $this->queryCount;
    }

    /** Run a prepared statement and return it. */
    public function run(string $sql, array $params = []): PDOStatement
    {
        $this->queryCount++;
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $name = is_int($key) ? $key + 1 : $key;
            $type = match (true) {
                is_int($value)  => PDO::PARAM_INT,
                is_bool($value) => PDO::PARAM_BOOL,
                $value === null => PDO::PARAM_NULL,
                default         => PDO::PARAM_STR,
            };
            $stmt->bindValue($name, $value, $type);
        }
        $stmt->execute();
        return $stmt;
    }

    /** @return array<int,array<string,mixed>> */
    public function all(string $sql, array $params = []): array
    {
        return $this->run($sql, $params)->fetchAll();
    }

    /** @return array<string,mixed>|null */
    public function first(string $sql, array $params = []): ?array
    {
        $row = $this->run($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    public function value(string $sql, array $params = [], mixed $default = null): mixed
    {
        $row = $this->run($sql, $params)->fetch(PDO::FETCH_NUM);
        return $row === false ? $default : $row[0];
    }

    public function int(string $sql, array $params = []): int
    {
        return (int) $this->value($sql, $params, 0);
    }

    /** @return array<int,mixed> */
    public function column(string $sql, array $params = []): array
    {
        return $this->run($sql, $params)->fetchAll(PDO::FETCH_COLUMN);
    }

    /** Insert an associative array into a table and return the new id. */
    public function insert(string $table, array $data): int
    {
        $cols = array_keys($data);
        $sql  = sprintf(
            'INSERT INTO `%s` (%s) VALUES (%s)',
            $this->guard($table),
            implode(', ', array_map(fn ($c) => '`' . $this->guard($c) . '`', $cols)),
            implode(', ', array_map(fn ($c) => ':' . $c, $cols))
        );
        $this->run($sql, $data);
        return (int) $this->pdo->lastInsertId();
    }

    /** Update rows matching a single primary key. */
    public function update(string $table, array $data, string $keyColumn, mixed $keyValue): int
    {
        if ($data === []) {
            return 0;
        }
        $sets = implode(', ', array_map(fn ($c) => '`' . $this->guard($c) . '` = :' . $c, array_keys($data)));
        $sql  = sprintf('UPDATE `%s` SET %s WHERE `%s` = :__key', $this->guard($table), $sets, $this->guard($keyColumn));
        $data['__key'] = $keyValue;
        return $this->run($sql, $data)->rowCount();
    }

    public function delete(string $table, string $keyColumn, mixed $keyValue): int
    {
        $sql = sprintf('DELETE FROM `%s` WHERE `%s` = ?', $this->guard($table), $this->guard($keyColumn));
        return $this->run($sql, [$keyValue])->rowCount();
    }

    public function transaction(callable $fn): mixed
    {
        $this->pdo->beginTransaction();
        try {
            $result = $fn($this);
            $this->pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }


    /** Identifiers never come from user input, but be strict regardless. */
    private function guard(string $identifier): string
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/', $identifier)) {
            throw new RuntimeException('Illegal SQL identifier: ' . $identifier);
        }
        return $identifier;
    }
}
