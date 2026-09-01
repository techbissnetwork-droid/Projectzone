<?php
declare(strict_types=1);

namespace SignalMasterAi;

/**
 * Every email this site tried to send, and what happened to it.
 *
 * A failed send reached error_log() and nowhere else. On shared hosting that
 * is nowhere at all, so "my alerts stopped" and "my alerts were never sent"
 * looked identical from the admin panel - and so did "the member watched a
 * pair that never flipped". One row per attempt closes all three: it says who
 * it went to, what it was about, whether it left, and if not, why not.
 *
 * Unlike the error log this is NOT deduplicated. The error log answers "what
 * is broken"; this answers "did that specific message go", and collapsing two
 * sends to the same member into one row would destroy exactly the detail
 * anyone comes here for.
 */
class EmailLog
{
    /** What the message was for. Kinds are filters, not behaviour. */
    public const ALERT  = 'alert';      // a BUY/SELL flip on a watched pair
    public const DIGEST = 'digest';     // the daily summary
    public const ACCOUNT = 'account';   // password reset, welcome
    public const TRADE  = 'trade';      // a paper position opened or closed
    public const TEST   = 'test';       // the admin's own test send
    public const OTHER  = 'other';

    /** Record one attempt. Never throws: logging must not break a send. */
    public static function record(
        string $to,
        string $subject,
        bool $ok,
        string $reason = '',
        string $kind = self::OTHER,
        int $memberId = 0,
        string $context = ''
    ): void {
        try {
            Database::pdo()->prepare(
                'INSERT INTO email_log (at, member_id, recipient, kind, subject, ok, reason, context)
                 VALUES (?,?,?,?,?,?,?,?)'
            )->execute([
                time(), $memberId, mb_substr($to, 0, 190), $kind, mb_substr($subject, 0, 190),
                $ok ? 1 : 0, mb_substr($reason, 0, 300), mb_substr($context, 0, 400),
            ]);
        } catch (\Throwable $e) {
        }
    }

    /** Recent attempts, newest first. */
    public static function recent(int $limit = 200, string $kind = '', bool $failedOnly = false): array
    {
        try {
            $where = [];
            $args = [];
            if ($kind !== '') {
                $where[] = 'kind = ?';
                $args[] = $kind;
            }
            if ($failedOnly) {
                $where[] = 'ok = 0';
            }
            $sql = 'SELECT * FROM email_log'
                 . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
                 . ' ORDER BY at DESC, id DESC LIMIT ' . max(1, min(1000, $limit));
            $stmt = Database::pdo()->prepare($sql);
            $stmt->execute($args);
            return $stmt->fetchAll();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** Sent and failed in a window, for the admin summary cards. */
    public static function summary(int $sinceHours = 24): array
    {
        try {
            $stmt = Database::pdo()->prepare(
                'SELECT SUM(CASE WHEN ok = 1 THEN 1 ELSE 0 END) sent,
                        SUM(CASE WHEN ok = 0 THEN 1 ELSE 0 END) failed
                 FROM email_log WHERE at >= ?'
            );
            $stmt->execute([time() - $sinceHours * 3600]);
            $r = $stmt->fetch();
            $stmt->closeCursor();
            return ['sent' => (int)($r['sent'] ?? 0), 'failed' => (int)($r['failed'] ?? 0)];
        } catch (\Throwable $e) {
            return ['sent' => 0, 'failed' => 0];
        }
    }

    /** The kinds actually present, for the filter. */
    public static function kinds(): array
    {
        try {
            return Database::pdo()->query('SELECT DISTINCT kind FROM email_log ORDER BY kind')
                ->fetchAll(\PDO::FETCH_COLUMN);
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * What this member has been sent lately.
     *
     * The question behind "I ticked watch-all and got nothing" is almost never
     * about the whole site - it is about one account. Shown on the member's
     * row in the admin so it can be answered without reading a global list.
     */
    public static function forMember(int $memberId, int $limit = 20): array
    {
        try {
            $stmt = Database::pdo()->prepare(
                'SELECT * FROM email_log WHERE member_id = ? ORDER BY at DESC, id DESC LIMIT '
                . max(1, min(200, $limit))
            );
            $stmt->execute([$memberId]);
            return $stmt->fetchAll();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** Drop old rows. Called from cron. */
    public static function prune(): int
    {
        $days = max(0, (int)Database::setting('email_log_days', '30'));
        if ($days <= 0) {
            return 0;
        }
        try {
            $stmt = Database::pdo()->prepare('DELETE FROM email_log WHERE at < ?');
            $stmt->execute([time() - $days * 86400]);
            return $stmt->rowCount();
        } catch (\Throwable $e) {
            return 0;
        }
    }
}
