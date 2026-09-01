<?php
declare(strict_types=1);

namespace SignalMasterAi;

/**
 * Expiring key/value cache backed by the `cache` table.
 *
 * Everything volatile lives here instead of in `settings`: tickers, funding
 * rates, open interest, the Fear & Greed index, API feed payloads and
 * rate-limit counters. Values are JSON-encoded, so callers get their arrays
 * and scalars back unchanged.
 *
 * A per-request memo layer means repeated reads of the same key inside one
 * analysis pass (the engine reads the same funding rate several times) cost
 * exactly one query.
 */
class Cache
{
    private static array $memo = [];

    /** Read a cached value, or $default when missing/expired. */
    public static function get(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, self::$memo)) {
            return self::$memo[$key];
        }
        try {
            $stmt = Database::pdo()->prepare('SELECT cvalue, expires_at FROM cache WHERE ckey = ?');
            $stmt->execute([$key]);
            $row = $stmt->fetch();
            $stmt->closeCursor();       // see Database::pdo() - a held read blocks the next write
        } catch (\Throwable $e) {
            return $default;
        }
        if (!$row || (int)$row['expires_at'] <= time()) {
            return $default;
        }
        $value = json_decode((string)$row['cvalue'], true);
        self::$memo[$key] = $value;
        return $value;
    }

    /** Store a value for $ttl seconds. */
    public static function set(string $key, mixed $value, int $ttl): void
    {
        $ttl = max(1, $ttl);
        $payload = json_encode($value);
        if ($payload === false) {
            return;
        }
        $pdo = Database::pdo();
        $sql = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'sqlite'
            ? 'INSERT INTO cache (ckey, cvalue, expires_at) VALUES (?,?,?)
               ON CONFLICT(ckey) DO UPDATE SET cvalue = excluded.cvalue, expires_at = excluded.expires_at'
            : 'INSERT INTO cache (ckey, cvalue, expires_at) VALUES (?,?,?)
               ON DUPLICATE KEY UPDATE cvalue = VALUES(cvalue), expires_at = VALUES(expires_at)';
        try {
            $pdo->prepare($sql)->execute([$key, $payload, time() + $ttl]);
            self::$memo[$key] = $value;
            // Collect expired rows occasionally on the way past. gc() used to
            // run only from cron, so an install whose cron was never wired up
            // - which is most of them, for a while - kept every expired row
            // forever: 419 dead rows out of 495 on this one. One write in
            // roughly two hundred pays for it, and gc() is a single DELETE.
            if (random_int(1, 200) === 1) {
                self::gc();
            }
        } catch (\Throwable $e) {
            // A cache write must never break the request that triggered it.
        }
    }

    /**
     * Read-through helper: return the cached value, or compute, store and
     * return it. A null result from $producer is cached too (as a "miss
     * marker") so an unreachable upstream is not retried on every page view -
     * that behaviour is what kept the Fear & Greed endpoint from being hit
     * once per visitor while it was down.
     */
    public static function remember(string $key, int $ttl, callable $producer, int $nullTtl = 0): mixed
    {
        $hit = self::get($key, '__miss__');
        if ($hit !== '__miss__') {
            return $hit;
        }
        $value = $producer();
        self::set($key, $value, $value === null && $nullTtl > 0 ? $nullTtl : $ttl);
        return $value;
    }

    public static function forget(string $key): void
    {
        unset(self::$memo[$key]);
        try {
            Database::pdo()->prepare('DELETE FROM cache WHERE ckey = ?')->execute([$key]);
        } catch (\Throwable $e) {
        }
    }

    /** Drop every key matching a LIKE pattern, e.g. "tick:%". */
    public static function forgetPrefix(string $prefix): void
    {
        self::$memo = [];
        try {
            Database::pdo()->prepare('DELETE FROM cache WHERE ckey LIKE ?')->execute([$prefix . '%']);
        } catch (\Throwable $e) {
        }
    }

    /**
     * Atomic counter used by the rate limiter. Increments the value inside a
     * fixed window and returns the running count; the first call in a window
     * creates the row with the given TTL.
     */
    public static function increment(string $key, int $ttl): int
    {
        $pdo = Database::pdo();
        $now = time();
        try {
            $stmt = $pdo->prepare('UPDATE cache SET cvalue = CAST(CAST(cvalue AS INTEGER) + 1 AS CHAR)
                                   WHERE ckey = ? AND expires_at > ?');
            $stmt->execute([$key, $now]);
            if ($stmt->rowCount() > 0) {
                $read = $pdo->prepare('SELECT cvalue FROM cache WHERE ckey = ?');
                $read->execute([$key]);
                unset(self::$memo[$key]);
                return (int)$read->fetchColumn();
            }
            // No live row to increment above - either this key has never
            // been seen, or its window just expired. Either way, another
            // request can reach this exact same branch at the exact same
            // instant (both just missed the UPDATE above), so this insert
            // can race another insert for the same key. The old conflict
            // handler here always overwrote cvalue to '1' no matter what it
            // found - so N concurrent requests opening a brand-new window
            // (a burst against a login form or an API endpoint, exactly what
            // a rate limiter/login-lockout exists to catch) each "win" the
            // conflict and each see count 1, and none of them ever gets
            // blocked. The CASE/IF below only resets to 1 when the row it
            // finds is ITSELF expired; if the row is still live (another
            // request's insert just won the race a moment earlier), it
            // increments that row instead of clobbering it.
            $isSqlite = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'sqlite';
            $sql = $isSqlite
                ? 'INSERT INTO cache (ckey, cvalue, expires_at) VALUES (?,?,?)
                   ON CONFLICT(ckey) DO UPDATE SET
                     cvalue = CASE WHEN expires_at > ? THEN CAST(CAST(cvalue AS INTEGER) + 1 AS TEXT) ELSE ? END,
                     expires_at = CASE WHEN expires_at > ? THEN expires_at ELSE ? END'
                : 'INSERT INTO cache (ckey, cvalue, expires_at) VALUES (?,?,?)
                   ON DUPLICATE KEY UPDATE
                     cvalue = IF(expires_at > ?, CAST(CAST(cvalue AS UNSIGNED) + 1 AS CHAR), ?),
                     expires_at = IF(expires_at > ?, expires_at, ?)';
            $pdo->prepare($sql)->execute([$key, '1', $now + $ttl, $now, '1', $now, $now + $ttl]);
            // The upsert above can no longer tell us its own result (it may
            // have incremented an existing row instead of the '1' it
            // inserted), so - same as the UPDATE branch above - read back
            // what actually landed rather than assuming.
            $read = $pdo->prepare('SELECT cvalue FROM cache WHERE ckey = ?');
            $read->execute([$key]);
            unset(self::$memo[$key]);
            return (int)$read->fetchColumn();
        } catch (\Throwable $e) {
            return 0;   // never let the limiter itself break a request
        }
    }

    /** Delete expired rows. Called from cron and occasionally from traffic. */
    public static function gc(): int
    {
        try {
            $stmt = Database::pdo()->prepare('DELETE FROM cache WHERE expires_at <= ?');
            $stmt->execute([time()]);
            return $stmt->rowCount();
        } catch (\Throwable $e) {
            return 0;
        }
    }
}
