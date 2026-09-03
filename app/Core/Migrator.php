<?php
declare(strict_types=1);

namespace App\Core;

use PDOException;

/**
 * Applies the platform schema and records what ran. Safe to re-run: every
 * statement is idempotent, and MySQL's lack of `CREATE INDEX IF NOT EXISTS`
 * is handled by treating a duplicate-index error as a no-op.
 */
final class Migrator
{
    /** @var list<string> */
    private array $log = [];

    public function __construct(private Database $db, private string $basePath)
    {
    }

    public function log(): array
    {
        return $this->log;
    }

    private function note(string $level, string $message): void
    {
        $this->log[] = $level . '|' . $message;
    }

    public function run(): bool
    {
        $factory = require $this->basePath . '/database/schema.php';
        $schema = $factory($this->db->driver());
        $ok = true;

        foreach ($schema['tables'] as $name => $sql) {
            try {
                $existed = $this->db->tableExists($name);
                $this->db->pdo()->exec($sql);
                $this->note('ok', $existed ? "Table {$name} already present" : "Created table {$name}");
            } catch (PDOException $e) {
                $ok = false;
                $this->note('err', "Failed creating {$name}: " . $this->clean($e->getMessage()));
            }
        }

        $indexed = 0;
        foreach ($schema['indexes'] as $sql) {
            try {
                $this->db->pdo()->exec($this->normaliseIndex($sql));
                $indexed++;
            } catch (PDOException $e) {
                // 1061 = duplicate key name, 42000 = syntax variant; both mean
                // the index is already in place on MySQL.
                if (!$this->isDuplicateIndex($e)) {
                    $this->note('warn', 'Index skipped: ' . $this->clean($e->getMessage()));
                }
            }
        }
        $this->note('ok', "Applied {$indexed} index definitions");

        if ($ok) {
            $this->record('platform_schema_v1');
        }

        return $ok;
    }

    private function normaliseIndex(string $sql): string
    {
        if ($this->db->driver() === 'sqlite') {
            return $sql;
        }
        return str_replace('INDEX IF NOT EXISTS', 'INDEX', $sql);
    }

    private function isDuplicateIndex(PDOException $e): bool
    {
        $message = strtolower($e->getMessage());
        return str_contains($message, 'duplicate key name')
            || str_contains($message, 'already exists');
    }

    private function clean(string $message): string
    {
        return trim(preg_replace('/\s+/', ' ', $message) ?? $message);
    }

    public function record(string $name): void
    {
        try {
            $exists = (int) $this->db->value('SELECT COUNT(*) FROM migrations WHERE name = ?', [$name], 0);
            if ($exists === 0) {
                $this->db->insert('migrations', [
                    'name' => $name,
                    'batch' => 1,
                    'ran_at' => gmdate('c'),
                ]);
            }
        } catch (PDOException) {
            // The migrations table itself failed to create; the error is
            // already recorded in the log above.
        }
    }

    public function hasRun(string $name): bool
    {
        if (!$this->db->tableExists('migrations')) {
            return false;
        }
        return (int) $this->db->value('SELECT COUNT(*) FROM migrations WHERE name = ?', [$name], 0) > 0;
    }

    /** True when the schema exists but carries no rows the installer would overwrite. */
    public function isEmpty(): bool
    {
        if (!$this->db->tableExists('users')) {
            return true;
        }
        return (int) $this->db->value('SELECT COUNT(*) FROM users', [], 0) === 0;
    }
}
