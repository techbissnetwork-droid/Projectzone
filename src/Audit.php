<?php
declare(strict_types=1);

namespace SignalMasterAi;

/**
 * Who changed what, and when.
 *
 * This engine edits itself. Rule weights are retuned from settled outcomes,
 * rules are quarantined and released, level distances are refitted, and the
 * champion/challenger loop applies setting changes on its own. All of that is
 * desirable and none of it was recorded - so when the numbers moved, there was
 * no way to tell whether the market had changed or the software had.
 *
 * That is the difference between an experiment and a guess. A record of every
 * change, with the value before and after and who made it, turns "it got worse
 * last week" into "it got worse the day the momentum weights were retuned".
 *
 * Entries are written for admin actions and for the engine's own, tagged
 * differently, so the two can be told apart at a glance.
 */
class Audit
{
    /** Automatic changes are attributed to this instead of a person. */
    public const ACTOR_ENGINE = 'engine';

    /**
     * Record one change. Never throws: an audit trail that can break a save is
     * worse than one with a gap in it.
     *
     * @param string $action short machine-ish verb, e.g. 'setting.change'
     * @param string $target what was changed, e.g. a setting key or symbol
     * @param mixed  $before value before, '' when not applicable
     * @param mixed  $after  value after
     * @param string $actor  admin username, or ACTOR_ENGINE
     *
     * Values are deliberately untyped. Callers pass whatever the thing they
     * changed happens to be - a checkbox array, an int, a float from a form -
     * and a logger that throws on the wrong type would take the save down with
     * it. flatten() makes it readable; nothing here rejects anything.
     */
    public static function log(
        string $action,
        string $target = '',
        $before = '',
        $after = '',
        ?string $actor = null
    ): void {
        try {
            if ($actor === null) {
                $actor = self::currentAdmin();
            }
            Database::pdo()->prepare(
                'INSERT INTO admin_log (at, actor, action, target, before_val, after_val, ip)
                 VALUES (?,?,?,?,?,?,?)'
            )->execute([
                time(),
                mb_substr($actor, 0, 60),
                mb_substr($action, 0, 40),
                mb_substr($target, 0, 120),
                mb_substr(self::flatten($before), 0, 400),
                mb_substr(self::flatten($after), 0, 400),
                mb_substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45),
            ]);
        } catch (\Throwable $e) {
            // Deliberately silent.
        }
    }

    /**
     * Record a batch of setting changes, skipping the ones that did not
     * actually change. A save that rewrites 142 fields and alters two should
     * leave two lines, not 142 - a log nobody can read is not a log.
     *
     * Secrets are recorded as having changed without recording what to.
     *
     * @param array<string,string> $before keyed by setting name
     * @param array<string,string> $after  keyed by setting name
     */
    public static function settings(array $before, array $after): int
    {
        $n = 0;
        foreach ($after as $k => $v) {
            $old = (string)($before[$k] ?? '');
            if ($old === (string)$v) {
                continue;
            }
            // Secrets::isSecret is the single answer, shared with the settings
            // form and the storage layer. The hand-kept list and pair of
            // str_contains() that used to be here was a second opinion, and it
            // already disagreed with the form: a key the form printed in full
            // was hidden here, so the audit trail claimed a discretion the
            // page did not have. cron_token stays hidden in the log even
            // though the panel shows it - a log is read by more people, over a
            // longer time, than the page it came from.
            $isSecret = Secrets::isSecret($k) || $k === 'cron_token' || str_contains($k, 'secret');
            self::log('setting.change', $k,
                $isSecret ? '(hidden)' : $old,
                $isSecret ? '(changed)' : (string)$v);
            $n++;
        }
        return $n;
    }

    /** Recent entries, newest first. */
    public static function recent(int $limit = 200, string $actor = '', string $action = ''): array
    {
        $where = [];
        $args = [];
        if ($actor !== '') {
            $where[] = 'actor = ?';
            $args[] = $actor;
        }
        if ($action !== '') {
            $where[] = 'action = ?';
            $args[] = $action;
        }
        $sql = 'SELECT * FROM admin_log'
             . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
             . ' ORDER BY at DESC LIMIT ' . max(1, min(1000, $limit));
        try {
            $stmt = Database::pdo()->prepare($sql);
            $stmt->execute($args);
            return $stmt->fetchAll();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** Distinct actors seen, for the filter. */
    public static function actors(): array
    {
        try {
            return Database::pdo()->query('SELECT DISTINCT actor FROM admin_log ORDER BY actor')
                ->fetchAll(\PDO::FETCH_COLUMN);
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** Drop entries past the retention window. Called from cron. */
    public static function prune(): int
    {
        $days = max(0, (int)Database::setting('audit_retention_days', '180'));
        if ($days <= 0) {
            return 0;
        }
        try {
            $stmt = Database::pdo()->prepare('DELETE FROM admin_log WHERE at < ?');
            $stmt->execute([time() - $days * 86400]);
            return $stmt->rowCount();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /** The logged-in admin, or a marker when there is no session behind this. */
    private static function currentAdmin(): string
    {
        try {
            if (PHP_SAPI === 'cli') {
                return self::ACTOR_ENGINE;
            }
            // The admin session names its own user; without one this is the
            // engine acting through a page view (self-tuning piggybacks on
            // traffic when no cron is running), not a person.
            return !empty($_SESSION['admin_user'])
                ? (string)$_SESSION['admin_user']
                : self::ACTOR_ENGINE;
        } catch (\Throwable $e) {
            return 'unknown';
        }
    }

    /** Arrays and objects flatten to something readable rather than "Array". */
    private static function flatten($v): string
    {
        if (is_array($v)) {
            return implode(', ', array_map(static fn($x) => is_scalar($x) ? (string)$x : '?', $v));
        }
        return is_scalar($v) ? (string)$v : '';
    }
}
