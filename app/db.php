<?php
/** PDO connection plus a few small query helpers used across the app. */

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $c = config('db');

    if (($c['driver'] ?? 'mysql') === 'sqlite') {
        $path = $c['sqlite_path'];
        $dir  = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $pdo = new PDO('sqlite:' . $path, null, null, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('PRAGMA foreign_keys = ON');
        return $pdo;
    }

    $dsn = sprintf(
        'mysql:host=%s;dbname=%s;charset=%s',
        $c['host'],
        $c['name'],
        $c['charset'] ?? 'utf8mb4'
    );

    $pdo = new PDO($dsn, $c['user'], $c['pass'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);

    return $pdo;
}

function db_driver(): string
{
    return config('db')['driver'] ?? 'mysql';
}

/** Run a prepared statement and return the statement. */
function q(string $sql, array $params = []): PDOStatement
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st;
}

/** All matching rows. */
function all(string $sql, array $params = []): array
{
    return q($sql, $params)->fetchAll();
}

/** First matching row, or null. */
function one(string $sql, array $params = []): ?array
{
    $row = q($sql, $params)->fetch();
    return $row === false ? null : $row;
}

/** Single scalar value from the first column. */
function scalar(string $sql, array $params = [])
{
    $v = q($sql, $params)->fetchColumn();
    return $v === false ? null : $v;
}

/** Every active row of a content table, in display order. */
function rows(string $table, bool $activeOnly = true): array
{
    $table = safe_table($table);
    $where = $activeOnly ? 'WHERE is_active = 1' : '';
    return all("SELECT * FROM {$table} {$where} ORDER BY sort ASC, id ASC");
}

/** Guard against a table name ever reaching SQL from user input. */
function safe_table(string $table): string
{
    if (!preg_match('/^[a-z_]+$/', $table)) {
        throw new InvalidArgumentException('Bad table name');
    }
    return $table;
}

function db_insert(string $table, array $data): int
{
    $table = safe_table($table);
    $cols  = array_keys($data);
    $place = array_map(fn($c) => ':' . $c, $cols);
    q(
        sprintf('INSERT INTO %s (%s) VALUES (%s)', $table, implode(',', $cols), implode(',', $place)),
        $data
    );
    return (int) db()->lastInsertId();
}

function db_update(string $table, int $id, array $data): void
{
    $table = safe_table($table);
    $sets  = implode(', ', array_map(fn($c) => "$c = :$c", array_keys($data)));
    $data['id'] = $id;
    q("UPDATE {$table} SET {$sets} WHERE id = :id", $data);
}

function db_delete(string $table, int $id): void
{
    q('DELETE FROM ' . safe_table($table) . ' WHERE id = :id', ['id' => $id]);
}
