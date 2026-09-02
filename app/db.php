<?php
/**
 * Database access. One PDO connection, prepared statements everywhere.
 * MySQL on real hosting; SQLite is supported so the site can be run locally
 * without a database server.
 */

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $cfg = config();

    if (($cfg['driver'] ?? 'mysql') === 'sqlite') {
        $path = $cfg['sqlite_path'];
        $dir  = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $pdo = new PDO('sqlite:' . $path, null, null, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('PRAGMA foreign_keys = ON');
    } else {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            $cfg['host'],
            $cfg['port'] ?: '3306',
            $cfg['database']
        );
        $pdo = new PDO($dsn, $cfg['username'], $cfg['password'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }

    return $pdo;
}

function db_driver(): string
{
    return config()['driver'] ?? 'mysql';
}

/** Run a query and return the statement. */
function db_run(string $sql, array $params = []): PDOStatement
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

/** All matching rows. */
function db_all(string $sql, array $params = []): array
{
    return db_run($sql, $params)->fetchAll();
}

/** First matching row, or null. */
function db_one(string $sql, array $params = []): ?array
{
    $row = db_run($sql, $params)->fetch();
    return $row === false ? null : $row;
}

/** First column of the first row. */
function db_value(string $sql, array $params = [])
{
    $row = db_run($sql, $params)->fetch(PDO::FETCH_NUM);
    return $row === false ? null : $row[0];
}

function db_count(string $sql, array $params = []): int
{
    return (int) db_value($sql, $params);
}

/** Insert an associative array; returns the new id. */
function db_insert(string $table, array $data): int
{
    $cols = array_keys($data);
    $sql  = sprintf(
        'INSERT INTO %s (%s) VALUES (%s)',
        $table,
        implode(', ', $cols),
        implode(', ', array_map(fn($c) => ':' . $c, $cols))
    );
    db_run($sql, $data);
    return (int) db()->lastInsertId();
}

/** Update by id. */
function db_update(string $table, int $id, array $data): void
{
    if (!$data) {
        return;
    }
    $set = implode(', ', array_map(fn($c) => $c . ' = :' . $c, array_keys($data)));
    $data['__id'] = $id;
    db_run("UPDATE {$table} SET {$set} WHERE id = :__id", $data);
}

function db_delete(string $table, int $id): void
{
    db_run("DELETE FROM {$table} WHERE id = ?", [$id]);
}

/** True when the schema has been created. */
function db_installed(): bool
{
    try {
        db_value('SELECT COUNT(*) FROM settings');
        return true;
    } catch (Throwable $e) {
        return false;
    }
}
