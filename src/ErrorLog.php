<?php
declare(strict_types=1);

namespace SignalMasterAi;

/**
 * Things that went wrong, where the operator can see them.
 *
 * The app is built to fail soft: a price fetch that times out, a coin the
 * exchange has delisted, an SMTP box that rejects a login, a rule that throws
 * on a malformed candle - none of them should take a page down, so every one
 * of them is caught and swallowed. That is right for the visitor and wrong for
 * the operator, who is left with a site that works and results that quietly
 * do not. The cron report showed some of it, once, to whoever happened to read
 * that minute's email.
 *
 * Errors are deduplicated on purpose. A dead symbol failing every ten minutes
 * is ONE problem, not four hundred rows: the same message in the same area
 * increments a counter and moves the "last seen" stamp. What an operator needs
 * is a list of distinct problems ordered by how much they are happening, not a
 * transcript.
 */
class ErrorLog
{
    /** Areas, so the list can be read by subsystem rather than by message. */
    public const MARKET = 'market';      // price and candle fetches
    public const SIGNAL = 'signal';      // analysis and settlement
    public const ALERTS = 'alerts';      // push, email, Telegram
    public const PAYMENT = 'payment';    // gateways and webhooks
    public const SYSTEM = 'system';      // uncaught PHP errors, database, cron

    /** Set while recording, so a failure inside the logger cannot recurse. */
    private static bool $inside = false;

    /**
     * Record one failure. Never throws and never echoes: an error logger that
     * can itself break a page, or print into someone's chart, is worse than no
     * logger at all.
     */
    /**
     * The request that was being served, for a context line.
     *
     * Trimmed hard: this is appended to a 300-character column that already
     * holds the file and line, and a long query would push those out.
     */
    private static function whereFrom(): string
    {
        if (PHP_SAPI === 'cli') {
            return ' (cli)';
        }
        $uri = (string)($_SERVER['REQUEST_URI'] ?? '');
        if ($uri === '') {
            return '';
        }
        return ' (' . (string)($_SERVER['REQUEST_METHOD'] ?? 'GET') . ' '
             . mb_substr($uri, 0, 120) . ')';
    }

    public static function record(string $area, string $message, string $context = ''): void
    {
        if (self::$inside) {
            return;
        }
        self::$inside = true;
        try {
            $message = self::tidy($message);
            if ($message === '') {
                return;
            }
            $pdo = Database::pdo();
            $now = time();
            // Same problem, same place: bump the count instead of writing a
            // four-hundredth copy of it.
            $upd = $pdo->prepare(
                'UPDATE error_log SET seen = seen + 1, last_at = ?, context = ?, resolved = 0
                 WHERE area = ? AND message = ?'
            );
            $upd->execute([$now, mb_substr($context, 0, 300), $area, $message]);
            if ($upd->rowCount() === 0) {
                $pdo->prepare(
                    'INSERT INTO error_log (area, message, context, seen, first_at, last_at, resolved)
                     VALUES (?,?,?,1,?,?,0)'
                )->execute([$area, $message, mb_substr($context, 0, 300), $now, $now]);
            }
        } catch (\Throwable $e) {
            // Nothing to do about it; silence is the only safe answer here.
        } finally {
            self::$inside = false;
        }
    }

    /**
     * Messages carry the specific and the incidental in one string, and only
     * the specific should define a distinct problem. Timestamps, ids and
     * changing numbers are folded out so "HTTP 400 for XYZUSDT at 12:04" and
     * the same thing a minute later are one row, not two.
     */
    private static function tidy(string $m): string
    {
        $m = trim(preg_replace('/\s+/', ' ', $m) ?? '');
        $m = preg_replace('/\b\d{9,}\b/', 'N', $m) ?? $m;              // epochs, ids
        $m = preg_replace('/\b\d{4}-\d{2}-\d{2}[T ][\d:]+\b/', 'DATE', $m) ?? $m;
        return mb_substr($m, 0, 300);
    }

    /** Open problems, most recently seen first. */
    public static function recent(int $limit = 100, string $area = '', bool $includeResolved = false): array
    {
        try {
            $where = $includeResolved ? [] : ['resolved = 0'];
            $args = [];
            if ($area !== '') {
                $where[] = 'area = ?';
                $args[] = $area;
            }
            $sql = 'SELECT * FROM error_log'
                 . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
                 . ' ORDER BY resolved, last_at DESC LIMIT ' . max(1, min(500, $limit));
            $stmt = Database::pdo()->prepare($sql);
            $stmt->execute($args);
            return $stmt->fetchAll();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** Every area that currently has something in the log. */
    public static function areas(): array
    {
        try {
            return Database::pdo()
                ->query('SELECT DISTINCT area FROM error_log ORDER BY area')
                ->fetchAll(\PDO::FETCH_COLUMN);
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** How many distinct problems are open, and how many times they happened. */
    public static function summary(int $sinceHours = 24): array
    {
        try {
            $stmt = Database::pdo()->prepare(
                'SELECT COUNT(*) problems, COALESCE(SUM(seen), 0) events FROM error_log
                 WHERE resolved = 0 AND last_at >= ?'
            );
            $stmt->execute([time() - $sinceHours * 3600]);
            $r = $stmt->fetch();
            $stmt->closeCursor();
            return ['problems' => (int)($r['problems'] ?? 0), 'events' => (int)($r['events'] ?? 0)];
        } catch (\Throwable $e) {
            return ['problems' => 0, 'events' => 0];
        }
    }

    /**
     * Mark one problem handled. It is not deleted: if it happens again the
     * counter starts moving and it comes straight back into the list, which is
     * how an operator finds out that a fix did not hold.
     */
    public static function resolve(int $id): void
    {
        try {
            Database::pdo()->prepare('UPDATE error_log SET resolved = 1 WHERE id = ?')->execute([$id]);
        } catch (\Throwable $e) {
        }
    }

    public static function resolveAll(): int
    {
        try {
            $stmt = Database::pdo()->query('UPDATE error_log SET resolved = 1 WHERE resolved = 0');
            return $stmt ? $stmt->rowCount() : 0;
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /** Drop resolved problems nobody has seen for a while. Called from cron. */
    public static function prune(): int
    {
        $days = max(0, (int)Database::setting('error_retention_days', '30'));
        if ($days <= 0) {
            return 0;
        }
        try {
            $stmt = Database::pdo()->prepare('DELETE FROM error_log WHERE last_at < ?');
            $stmt->execute([time() - $days * 86400]);
            return $stmt->rowCount();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /** Human labels for the area column. */
    public static function areaLabel(string $area): string
    {
        return [
            self::MARKET  => 'Price data',
            self::SIGNAL  => 'Signals',
            self::ALERTS  => 'Alerts',
            self::PAYMENT => 'Payments',
            self::SYSTEM  => 'System',
        ][$area] ?? ucfirst($area);
    }

    /**
     * What to actually do about it.
     *
     * A logged error that only says "HTTP 451" tells an operator that
     * something is wrong and nothing about how to end it. Nearly every failure
     * this app can produce has a known cause and a specific setting behind it,
     * and the difference between a log and a to-do list is exactly this
     * column. Matching is on the message text because that is what the
     * subsystems produce; where nothing matches, saying nothing is better than
     * guessing at someone's server.
     */
    public static function hint(string $area, string $message): string
    {
        $m = strtolower($message);
        $pairs = [
            // A file that never reached the server. Named first because the
            // message PHP produces for it - "Class ... not found" - reads like
            // a code fault, and an operator who believes that will not go and
            // look at what they uploaded. See the loader in bootstrap.php,
            // which now names the file it could not find.
            'is missing from this server' =>
                            'A file from this release is not on the server. Re-upload the src/ '
                          . 'directory from the release zip - an upload or unzip that stops part '
                          . 'way through leaves exactly this, and everything that needs that file '
                          . 'stays broken until it is back.',
            'cannot be read' =>
                            'The file is there but this server will not open it. Set the src/ '
                          . 'directory and its files to 644 (folders 755) in your file manager.',
            'not found'  => 'A class this release needs did not load. If the message names a '
                          . 'SignalMasterAi class, the matching file under src/ is missing or '
                          . 'unreadable - re-upload src/ from the release zip.',
            'http 451'   => 'The exchange is refusing this server by location. Pick a different '
                          . 'endpoint under Settings > Market data, or run the site from a region '
                          . 'the exchange serves.',
            'http 429'   => 'The exchange is rate-limiting this server. Scan fewer coins, or run '
                          . 'cron less often - both are under Settings > Market data.',
            'http 418'   => 'The exchange has temporarily banned this server for request volume. '
                          . 'Reduce the number of scanned coins under Coins and wait it out.',
            'http 403'   => 'The endpoint rejected the request. Check the endpoint URL and any '
                          . 'API key under Settings > Market data.',
            'http 5'     => 'The exchange itself is having trouble. Nothing to fix here - it '
                          . 'clears on its own; mark it resolved and see whether it comes back.',
            'resolve host' => 'This server cannot reach the internet by name. Outbound HTTP or DNS '
                          . 'is blocked - most shared hosts allow it on request.',
            'timed out'  => 'The request did not finish in time. Usually the exchange being slow; '
                          . 'if it is constant, the host may be blocking outbound requests.',
            'invalid symbol' => 'This pair is not valid at the endpoint - usually a delisted coin. '
                          . 'Turn it off under Coins.',
            'no cached data' => 'Nothing has ever been fetched for this pair, so there is nothing '
                          . 'to fall back on. Fix the fetch above, or turn the pair off under Coins.',
            'no mail method' => 'Nothing is set to send mail. Choose PHP mail or SMTP under '
                          . 'Settings > Email and send yourself the test message; until then the '
                          . 'members who ticked "email me" get nothing and are told nothing.',
            'never been analysed' => 'Tick the coin for the scan under Coins, and make sure that '
                          . 'timeframe is in the scan list under Settings > Market data. A pair '
                          . 'nothing analyses has no verdict to change, so it cannot alert.',
            'not an enabled coin' => 'Add the coin under Coins, or re-enable it. Members can watch '
                          . 'it, and it will never fire while it is off.',
            'smtp'       => 'Mail is being refused. Re-check the SMTP host, port, username and '
                          . 'password under Settings > Email.',
            'mail'       => 'Mail could not be sent. Check the email settings under Settings > Email.',
            'telegram'   => 'Telegram refused the message. Re-check the bot token under '
                          . 'Settings > Telegram, and that the bot is still in the channel.',
            'signature'  => 'The gateway signed with a different key than this site holds. Copy the '
                          . 'API key from the gateway again under Plans & gateways.',
            'vapid'      => 'Browser push keys are missing or wrong. Regenerate them under '
                          . 'Settings > Alerts; existing subscriptions will need to re-subscribe.',
            'sqlstate'   => 'The database refused a query. If it names a missing column, re-run the '
                          . 'installer to apply pending migrations.',
            'disk'       => 'The server is out of space. Free some, or lower the candle retention '
                          . 'under Settings > Market data.',
        ];
        foreach ($pairs as $needle => $advice) {
            if (str_contains($m, $needle)) {
                return $advice;
            }
        }
        if ($area === self::SYSTEM) {
            return 'A PHP-level fault. The file and line are in the detail column - if it is not '
                 . 'obvious, send that line and this message on to support.';
        }
        return '';
    }

    /**
     * Catch what nobody caught.
     *
     * Registered from the bootstrap so an uncaught exception or a PHP warning
     * lands in the same list as the failures the app handles deliberately.
     * Display behaviour is untouched - this only records.
     */
    public static function install(): void
    {
        set_exception_handler(static function (\Throwable $e): void {
            self::record(self::SYSTEM, get_class($e) . ': ' . $e->getMessage(),
                basename($e->getFile()) . ':' . $e->getLine());
            // Everything PHP would have done on its own still happens: the
            // message reaches the server log, and the request still answers
            // 500 rather than a truncated 200 that looks like a working page.
            error_log('Uncaught ' . get_class($e) . ': ' . $e->getMessage()
                . ' in ' . $e->getFile() . ':' . $e->getLine());
            if (PHP_SAPI !== 'cli' && !headers_sent()) {
                http_response_code(500);
            }
        });
        set_error_handler(static function (int $no, string $str, string $file = '', int $line = 0): bool {
            // Suppressed expressions and notices are noise; warnings and above
            // are the ones worth a row.
            if (!(error_reporting() & $no) || $no === E_NOTICE || $no === E_DEPRECATED) {
                return false;
            }
            // WHERE IT HAPPENED, AND WHAT WAS BEING ASKED FOR.
            //
            // The context was the file and line alone. That names the code but
            // not the case: "SignalEngine.php:2380" tells an operator nothing
            // about which coin, which timeframe or which page produced it, and
            // a warning that only fires for one pair on one frame is exactly
            // the kind that never reproduces from a line number. Reported
            // against this build: "Trying to access array offset on value of
            // type null", with no way to tell what had been requested.
            //
            // The path and its query, or the cron job's name, is the missing
            // half. Query strings on this site carry symbols, timeframes and
            // view names - never credentials - and the row is admin-only.
            self::record(self::SYSTEM, $str,
                basename($file) . ':' . $line . self::whereFrom());
            return false;   // let PHP's own handling continue
        });
    }
}
