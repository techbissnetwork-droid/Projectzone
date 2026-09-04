<?php
/**
 * Minimal migration runner used by the installer (install/index.php) and
 * available for future updates: drop a new numbered file into
 * install/migrations/ and it will show up as "pending" next time anyone
 * (post-install) opens /install/.
 */

function ensure_migrations_table(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS schema_migrations (
        id VARCHAR(100) PRIMARY KEY,
        applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function applied_migrations(PDO $pdo): array
{
    ensure_migrations_table($pdo);
    return $pdo->query('SELECT id FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN);
}

/** @return string[] absolute file paths, in order */
function available_migrations(): array
{
    $files = glob(__DIR__ . '/../install/migrations/*.php');
    sort($files);
    return $files;
}

/** @return string[] absolute file paths not yet applied, in order */
function pending_migrations(PDO $pdo): array
{
    $applied = applied_migrations($pdo);
    $pending = [];
    foreach (available_migrations() as $file) {
        if (!in_array(basename($file, '.php'), $applied, true)) {
            $pending[] = $file;
        }
    }
    return $pending;
}

/**
 * Runs one migration file. Each file must `return` a callable of shape
 * function(PDO $pdo, array $context): void:
 */
function run_migration(PDO $pdo, string $file, array $context = []): void
{
    $id = basename($file, '.php');
    $migrate = require $file;
    $migrate($pdo, $context);
    $stmt = $pdo->prepare('INSERT INTO schema_migrations (id) VALUES (?)');
    $stmt->execute([$id]);
}

function has_ever_migrated(PDO $pdo): bool
{
    ensure_migrations_table($pdo);
    return (int)$pdo->query('SELECT COUNT(*) FROM schema_migrations')->fetchColumn() > 0;
}
