<?php
declare(strict_types=1);

final class Database
{
    private static ?PDO $pdo = null;

    public static function connect(array $cfg): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }
        self::$pdo = new PDO(self::dsn($cfg), $cfg['username'] ?? null, $cfg['password'] ?? null, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        if (($cfg['driver'] ?? 'mysql') === 'sqlite') {
            self::$pdo->exec('PRAGMA foreign_keys = ON');
        }
        return self::$pdo;
    }

    public static function dsn(array $cfg): string
    {
        $driver = $cfg['driver'] ?? 'mysql';
        if ($driver === 'sqlite') {
            return 'sqlite:' . $cfg['database'];
        }
        return sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $cfg['host'] ?? 'localhost',
            (int)($cfg['port'] ?? 3306),
            $cfg['database'] ?? '',
            $cfg['charset'] ?? 'utf8mb4'
        );
    }

    public static function pdo(): PDO
    {
        if (!self::$pdo instanceof PDO) {
            throw new RuntimeException('Database is not connected.');
        }
        return self::$pdo;
    }

    /** @return array<int,array<string,mixed>> */
    public static function all(string $sql, array $params = []): array
    {
        $st = self::pdo()->prepare($sql);
        $st->execute($params);
        return $st->fetchAll();
    }

    public static function one(string $sql, array $params = []): ?array
    {
        $st = self::pdo()->prepare($sql);
        $st->execute($params);
        $row = $st->fetch();
        return $row === false ? null : $row;
    }

    public static function value(string $sql, array $params = [], mixed $default = null): mixed
    {
        $st = self::pdo()->prepare($sql);
        $st->execute($params);
        $v = $st->fetchColumn();
        return $v === false ? $default : $v;
    }

    public static function run(string $sql, array $params = []): PDOStatement
    {
        $st = self::pdo()->prepare($sql);
        $st->execute($params);
        return $st;
    }

    /** Insert an associative row and return the new id. */
    public static function insert(string $table, array $data): int
    {
        $cols = array_keys($data);
        $sql  = 'INSERT INTO ' . $table . ' (' . implode(', ', $cols) . ') VALUES ('
              . implode(', ', array_map(static fn($c) => ':' . $c, $cols)) . ')';
        self::run($sql, $data);
        return (int)self::pdo()->lastInsertId();
    }

    /** Update by primary key and return affected rows. */
    public static function update(string $table, array $data, int $id, string $pk = 'id'): int
    {
        $sets = [];
        foreach (array_keys($data) as $c) {
            $sets[] = $c . ' = :' . $c;
        }
        $data['__pk'] = $id;
        $sql = 'UPDATE ' . $table . ' SET ' . implode(', ', $sets) . ' WHERE ' . $pk . ' = :__pk';
        return self::run($sql, $data)->rowCount();
    }

    public static function delete(string $table, int $id, string $pk = 'id'): int
    {
        return self::run('DELETE FROM ' . $table . ' WHERE ' . $pk . ' = :id', ['id' => $id])->rowCount();
    }
}
