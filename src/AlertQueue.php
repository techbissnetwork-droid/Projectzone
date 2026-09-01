<?php
declare(strict_types=1);

namespace SignalMasterAi;

/**
 * Alerts held back so they can go out together.
 *
 * Batching inside one cron run already collapses simultaneous flips into one
 * message. It does nothing for the ordinary case, though: coins do not flip
 * politely at the same minute, so a ten-minute cron turns a morning of five
 * separate turns into five separate emails. A window holds each new signal
 * until the oldest one waiting is old enough, and then everything goes in one
 * message.
 *
 * A row per held signal rather than a blob on the member: the payload is a
 * full signal snapshot including its indicator set, and a dozen of those would
 * not reliably fit in a TEXT column on MySQL. It also makes pruning a DELETE
 * instead of a read-modify-write, which matters when the sweep is running
 * against every member at once.
 *
 * The snapshot is deliberate. Re-reading the verdict at flush time would mean
 * sending a plan the engine has since moved on from - the member would get a
 * message about a setup that no longer exists, with prices that never applied
 * together. What is delivered is what was true when the call fired.
 */
class AlertQueue
{
    /** Hold one signal for later delivery. Never throws. */
    public static function push(int $memberId, string $channel, string $symbol, string $tf, array $payload): void
    {
        if ($memberId <= 0) {
            return;
        }
        try {
            Database::pdo()->prepare(
                'INSERT INTO alert_queue (member_id, channel, symbol, tf, payload, at)
                 VALUES (?,?,?,?,?,?)'
            )->execute([
                $memberId, $channel, mb_substr($symbol, 0, 40), mb_substr($tf, 0, 10),
                (string)json_encode($payload), time(),
            ]);
        } catch (\Throwable $e) {
            ErrorLog::record(ErrorLog::ALERTS, 'Could not queue an alert - ' . $e->getMessage(),
                $symbol . ':' . $tf);
        }
    }

    /**
     * Everything waiting for this member, but only once the oldest has waited
     * out the window. Returns an empty array while the window is still open,
     * so the caller sends nothing and tries again next run.
     *
     * Oldest-first, so a bundled message reads in the order the calls actually
     * happened rather than in whatever order the database felt like.
     */
    public static function due(int $memberId, string $channel, int $windowSec): array
    {
        try {
            $pdo = Database::pdo();
            $stmt = $pdo->prepare('SELECT MIN(at) FROM alert_queue WHERE member_id = ? AND channel = ?');
            $stmt->execute([$memberId, $channel]);
            $oldest = (int)($stmt->fetchColumn() ?: 0);
            if ($oldest === 0 || time() - $oldest < $windowSec) {
                return [];
            }
            $rows = $pdo->prepare(
                'SELECT payload FROM alert_queue WHERE member_id = ? AND channel = ? ORDER BY at ASC, id ASC');
            $rows->execute([$memberId, $channel]);
            $out = [];
            foreach ($rows->fetchAll(\PDO::FETCH_COLUMN) as $json) {
                $h = json_decode((string)$json, true);
                if (is_array($h) && isset($h['sym'], $h['tf'], $h['sig'])) {
                    $out[] = $h;
                }
            }
            return $out;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Drop what was just delivered.
     *
     * Bounded by the same window the send was: a flip that arrived while the
     * message was being built has not been delivered, and deleting the whole
     * queue would lose it without trace.
     */
    public static function clear(int $memberId, string $channel, int $windowSec): void
    {
        try {
            $pdo = Database::pdo();
            $stmt = $pdo->prepare('SELECT MIN(at) FROM alert_queue WHERE member_id = ? AND channel = ?');
            $stmt->execute([$memberId, $channel]);
            $oldest = (int)($stmt->fetchColumn() ?: 0);
            if ($oldest === 0) {
                return;
            }
            $pdo->prepare('DELETE FROM alert_queue WHERE member_id = ? AND channel = ? AND at <= ?')
                ->execute([$memberId, $channel, $oldest + $windowSec]);
        } catch (\Throwable $e) {
        }
    }

    /** How many alerts are waiting right now, for the admin panel. */
    public static function waiting(): int
    {
        try {
            return (int)Database::pdo()->query('SELECT COUNT(*) FROM alert_queue')->fetchColumn();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * Throw away anything that has waited far too long.
     *
     * A member whose delivery has been failing for a day should not get a
     * hundred stale setups the moment it starts working: the plans they carry
     * expired hours ago, and sending them would be worse than sending nothing.
     * Called from cron.
     */
    public static function prune(): int
    {
        try {
            $stmt = Database::pdo()->prepare('DELETE FROM alert_queue WHERE at < ?');
            $stmt->execute([time() - 86400]);
            return $stmt->rowCount();
        } catch (\Throwable $e) {
            return 0;
        }
    }
}
