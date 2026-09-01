<?php
declare(strict_types=1);

namespace SignalMasterAi;

/**
 * Background housekeeping: bounded storage and a health snapshot.
 *
 * The candle table had no retention policy at all. With the exchange import
 * enabled (~470 USDT pairs) and six timeframes at 1000 bars each, an install
 * could accumulate millions of rows that nothing ever reads - the engine only
 * looks at the most recent `candle_limit` bars. Retention keeps enough history
 * for the backtester and drops the rest.
 */
class Maintenance
{
    /**
     * Trim each symbol/timeframe series to `candle_retention_bars` rows.
     * Returns the number of deleted candles.
     */
    /**
     * What is actually filling the database, biggest first.
     *
     * Asked whether signals should be capped at 90 days to stop the database
     * growing. Measured on a real install first, because the intuition is
     * backwards: candles were 9.2 MB of a 15.5 MB file - 58% - across 130,506
     * rows at 72 bytes each, while every signal ever stored was 207 rows and
     * 1.3 MB. Cutting signals to save space trades away the whole verified
     * track record and everything the tuner learns from, to reclaim a tenth of
     * what the candle cache holds. And candles are a CACHE: deleted, they are
     * re-fetched from the exchange on demand. A deleted signal is gone.
     *
     * So the panel shows the sizes rather than describing them, and an
     * operator reaching for a retention setting can see which table their
     * problem is actually in.
     *
     * dbstat is a compile-time option in SQLite and absent on MySQL, so bytes
     * come back null when it cannot be read - the row counts still work, and a
     * missing number is shown as unknown rather than as zero.
     *
     * @return array<int,array{table:string,rows:int,bytes:?int}>
     */
    public static function storage(int $limit = 12): array
    {
        $pdo = Database::pdo();
        $out = [];
        try {
            $names = [];
            $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
            if ($driver === 'sqlite') {
                foreach ($pdo->query(
                    "SELECT name FROM sqlite_master WHERE type = 'table'
                       AND name NOT LIKE 'sqlite_%' ORDER BY name") as $r) {
                    $names[] = (string)$r['name'];
                }
            } else {
                foreach ($pdo->query('SHOW TABLES') as $r) {
                    $names[] = (string)array_values($r)[0];
                }
            }
            foreach ($names as $t) {
                if (!preg_match('/^[A-Za-z0-9_]+$/', $t)) {
                    continue;
                }
                $rows = 0;
                try {
                    $rows = (int)$pdo->query('SELECT COUNT(*) FROM "' . $t . '"')->fetchColumn();
                } catch (\Throwable $e) {
                    continue;
                }
                $bytes = null;
                try {
                    $b = $pdo->query('SELECT SUM(pgsize) FROM dbstat WHERE name = ' .
                        $pdo->quote($t))->fetchColumn();
                    $bytes = $b === false || $b === null ? null : (int)$b;
                } catch (\Throwable $e) {
                    $bytes = null;
                }
                $out[] = ['table' => $t, 'rows' => $rows, 'bytes' => $bytes];
            }
        } catch (\Throwable $e) {
            return [];
        }
        // Biggest first by bytes where known, else by rows, so the table an
        // operator needs to see is the one at the top.
        usort($out, static fn (array $a, array $b): int =>
            [$b['bytes'] ?? -1, $b['rows']] <=> [$a['bytes'] ?? -1, $a['rows']]);
        return array_slice($out, 0, max(1, $limit));
    }

    public static function pruneCandles(int $maxSeries = 400): int
    {
        $keep = (int)Database::setting('candle_retention_bars', '1500');
        if ($keep <= 0) {
            return 0;   // retention disabled: keep everything
        }
        $keep = max(300, $keep);   // never trim below what the engine needs
        // Two depths, because two kinds of coin.
        //
        // A single retention number said every coin on the site deserves the
        // same history. It does not: the deep window exists so the backtester
        // has something to replay, and a coin outside the scan rotation is not
        // being backtested by anything. Charging a 479-coin watchlist 1500
        // bars per timeframe for that is hundreds of thousands of rows kept
        // for a replay nobody runs. Coins in the rotation keep the full
        // window; the rest keep what the engine itself needs to read a chart,
        // which is all a page view ever asks for.
        $idle = max(300, min($keep, (int)Database::setting('candle_retention_idle_bars', '320')));
        $pdo = Database::pdo();
        $rotating = array_flip(array_column($pdo->query(
            'SELECT symbol FROM symbols WHERE enabled = 1 AND scan = 1')->fetchAll(), 'symbol'));

        $series = $pdo->query(
            'SELECT symbol, tf, COUNT(*) n FROM candles GROUP BY symbol, tf HAVING COUNT(*) > ' . $idle
            . ' ORDER BY n DESC LIMIT ' . (int)$maxSeries
        )->fetchAll();

        $del = $pdo->prepare('DELETE FROM candles WHERE symbol = ? AND tf = ? AND open_time < ?');
        $removed = 0;
        foreach ($series as $s) {
            $k = isset($rotating[$s['symbol']]) ? $keep : $idle;
            if ((int)$s['n'] <= $k) {
                continue;
            }
            // OFFSET cannot be bound as a parameter on every driver, so the
            // depth is an integer built here rather than passed in.
            $cut = $pdo->prepare(
                'SELECT open_time FROM candles WHERE symbol = ? AND tf = ?
                 ORDER BY open_time DESC LIMIT 1 OFFSET ' . ($k - 1)
            );
            $cut->execute([$s['symbol'], $s['tf']]);
            $oldest = $cut->fetchColumn();
            if ($oldest === false) {
                continue;
            }
            $del->execute([$s['symbol'], $s['tf'], (int)$oldest]);
            $removed += $del->rowCount();
        }
        return $removed;
    }

    /**
     * Candles nobody can ever read again.
     *
     * Retention trims each series to its newest N bars, which is the right
     * rule for a series the site still uses - and no rule at all for one it
     * does not. A coin switched off, or a timeframe removed from the enabled
     * list, keeps its full N bars for ever: on a 479-coin watchlist that is
     * hundreds of thousands of rows of a chart no page can open, sitting in a
     * shared-hosting database that charges for every one of them.
     *
     * Only genuinely unreachable series are removed. A coin that is enabled
     * but out of the scan rotation keeps its candles: it is still on the site,
     * still searchable, still chartable, and re-fetching it on the next view
     * would be slower for the reader than the disk is expensive.
     */
    public static function pruneOrphanCandles(): int
    {
        $pdo = Database::pdo();
        $tfs = array_values(array_filter(array_map(
            'trim', explode(',', Database::setting('enabled_intervals', '')))));
        $removed = 0;

        $stmt = $pdo->query('DELETE FROM candles WHERE symbol NOT IN
                             (SELECT symbol FROM symbols WHERE enabled = 1)');
        $removed += $stmt ? $stmt->rowCount() : 0;

        if ($tfs) {
            $in = implode(',', array_fill(0, count($tfs), '?'));
            $del = $pdo->prepare("DELETE FROM candles WHERE tf NOT IN ($in)");
            $del->execute($tfs);
            $removed += $del->rowCount();
        }
        return $removed;
    }

    /**
     * Refresh the query planner's statistics.
     *
     * Both engines pick a plan from what they know about the data, and this
     * site had never told either of them anything - so every index it added
     * was a suggestion the planner was free to ignore, and on the tables that
     * grow it did. Cheap on SQLite, cheap on MySQL for tables of this size,
     * and run once a day right after pruning has changed their shape.
     *
     * Never fatal: a host that refuses ANALYZE gets a slower plan, not a
     * broken cron run.
     */
    public static function analyze(): bool
    {
        $pdo = Database::pdo();
        try {
            // Asked of the connection rather than the config, because this is
            // a fact about the driver in hand.
            if ($pdo->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'sqlite') {
                $pdo->exec('ANALYZE');
                return true;
            }
            // ANALYZE TABLE RETURNS ROWS, AND THEY MUST BE READ.
            //
            // MySQL answers it with a result set - one row per table saying
            // what it did. exec() does not read those rows, so they sit on the
            // connection and every query after this one dies with "Cannot
            // execute queries while other unbuffered queries are active". The
            // catch below hid the ANALYZE failure and left the connection
            // poisoned for the rest of the run, which on a real install meant
            // the daily cron falling over at this line and taking the pruning,
            // the digest and the health stamp down with it. Found by running
            // cron against MySQL; on SQLite nothing was ever wrong.
            foreach (['signals', 'members', 'payments', 'candles', 'signal_state',
                      'paper_trades', 'news_items'] as $t) {
                $st = $pdo->query("ANALYZE TABLE `$t`");
                if ($st !== false) {
                    $st->fetchAll();
                    $st->closeCursor();
                }
            }
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Refused setups age out faster than published ones: they are evidence for
     * the tuner, not a record anybody can look up, and the tuner only ever
     * reads the recent window.
     */
    public static function pruneShadows(): int
    {
        $days = max(7, min(3650, (int)Database::setting('shadow_retention_days', '120')));
        try {
            $st = Database::pdo()->prepare('DELETE FROM shadow_signals WHERE created_at < ?');
            $st->execute([time() - $days * 86400]);
            return $st->rowCount();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * Drop resolved signals older than the retention window. The public track
     * record only ever shows the last 100, and rule statistics look at the
     * last 500, so unbounded growth buys nothing.
     */
    public static function pruneSignals(): int
    {
        $days = (int)Database::setting('signal_retention_days', '365');
        if ($days <= 0) {
            return 0;
        }
        $stmt = Database::pdo()->prepare(
            "DELETE FROM signals WHERE outcome NOT IN ('') AND closed_at > 0 AND closed_at < ?"
        );
        $stmt->execute([time() - $days * 86400]);
        self::syncSignalState();
        return $stmt->rowCount();
    }

    /**
     * Keep the current-verdict table honest after rows are deleted elsewhere.
     *
     * A state row points at the ledger row that governs the pair. Retention,
     * an admin prune and a symbol purge can all remove that row underneath it,
     * which would leave the board pointing at a call that no longer exists.
     * Pointing at nothing is the correct answer there: the board then shows
     * the verdict from the state row itself, and the pair is free to publish a
     * fresh call instead of being deduplicated against a ghost.
     */
    public static function syncSignalState(): int
    {
        $pdo = Database::pdo();
        $stmt = $pdo->query(
            'UPDATE signal_state SET signal_id = 0
             WHERE signal_id > 0 AND signal_id NOT IN (SELECT id FROM signals)'
        );
        $fixed = $stmt ? $stmt->rowCount() : 0;
        $pdo->exec('DELETE FROM signal_state WHERE symbol NOT IN (SELECT symbol FROM symbols)');

        // AND ROWS ON A TIMEFRAME THE SITE NO LONGER RUNS.
        //
        // A verdict is stored per symbol and timeframe. Turn a frame off
        // site-wide and its rows stay for ever: nothing deleted them, because
        // the only cleanup here was for deleted COINS. Measured on this
        // install, 35 of 128 rows - 27% - were on 12h, 2h, 30m, 3m, 6h, 8h,
        // 1mo and 3d, none of which the site runs.
        //
        // Nothing wrong was ever SHOWN by them: every reader filters on
        // Visibility::allowsTf and the scanner counts them out under "tf_off".
        // They are dead weight that every board query walks past, and they
        // grow by a coin list every time an operator tries a frame and turns
        // it off again.
        //
        // Filtered by the same siteTfs() the readers use, so this can never
        // delete a row a page would still have shown. Skipped entirely when
        // that list comes back empty - a misread config must not be allowed to
        // empty the table.
        try {
            $config = require SMA_ROOT . '/config.php';
            $live = Visibility::siteTfs($config['market']['intervals'] ?? []);
            if ($live) {
                $in = implode(',', array_fill(0, count($live), '?'));
                $del = $pdo->prepare("DELETE FROM signal_state WHERE tf NOT IN ($in)");
                $del->execute($live);
            }
        } catch (\Throwable $e) {
            // A cleanup is never worth breaking the run it is part of.
        }
        return $fixed;
    }

    /** Everything the status page and admin health panel need, in one pass. */
    /**
     * Does this server actually rewrite /charts to /charts.php?
     *
     * Asked by fetching the site's own extensionless /status. Cached, because
     * the answer only changes when someone edits .htaccess or moves host, and
     * a self-HTTP call is not something to do on a visitor's page load - cron
     * refreshes it, and the settings page reads what cron left.
     *
     * null means "could not tell": no site URL yet, or outbound HTTP blocked.
     * That is deliberately different from false, since only a definite no is
     * allowed to switch clean URLs off.
     */
    public static function rewriteWorks(bool $force = false): ?bool
    {
        if (!$force) {
            $cached = Cache::get('rewrite_ok');
            if ($cached !== null) {
                return (bool)$cached;
            }
        }
        $base = rtrim(Database::setting('site_url'), '/');
        if ($base === '' || !function_exists('curl_init')) {
            return null;
        }
        $get = static function (string $url): array {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 8,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_USERAGENT      => 'SignalMasterAi/1.0 (rewrite self-test)',
            ]);
            $body = (string)curl_exec($ch);
            $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            curl_close($ch);
            return [$code, $body];
        };

        // The status code alone is not an answer. Plenty of setups return 200
        // for a path they did not actually route - PHP's own development
        // server serves index.php for anything it cannot find, and a host with
        // a custom error page can do the same. So the test is whether the page
        // that came back IS the status page.
        [$code, $body] = $get($base . '/status');
        if ($code === 0) {
            return null;                       // could not reach ourselves
        }
        $marker = 'System status';
        $ok = $code === 200 && str_contains($body, $marker);

        // And a control: a path that cannot exist must NOT come back as that
        // same page. If it does, this server answers everything with one
        // document and the test above proves nothing.
        if ($ok) {
            [, $ctrl] = $get($base . '/sma-rewrite-probe-' . bin2hex(random_bytes(4)));
            if (str_contains($ctrl, $marker)) {
                $ok = false;
            }
        }
        Cache::set('rewrite_ok', $ok, 3600);
        return $ok;
    }

    /**
     * Is the data directory actually unreachable over HTTP?
     *
     * THE PROTECTION IS A .htaccess FILE, AND HALF THE WORLD DOES NOT READ ONE.
     *
     * data/ holds the SQLite database - every member's email address and
     * password hash - the uploaded payment proofs, and config.local.php with
     * the database credentials and any API keys. It is inside the web root
     * because this application ships as a zip an operator uploads whole, and
     * it is protected by data/.htaccess plus a rule in the root one.
     *
     * Apache and LiteSpeed honour those. NGINX READS NEITHER, and nginx is
     * common on exactly the cheap hosting this is built for. On such a host
     * every file above is a plain download and nothing on the site says so:
     * the .htaccess is present, correct, and completely inert. The operator
     * has no way to know, because the thing that would tell them is the one
     * check nobody runs against their own site.
     *
     * So it is asked the only way it can be answered - by fetching our own
     * URLs and seeing what comes back. Guessing from SERVER_SOFTWARE would be
     * wrong twice over: a proxy can rewrite it, and what matters is the
     * result, not the badge.
     *
     * Cached like the rewrite probe: the answer changes when someone moves
     * host, and a self-HTTP call does not belong on a visitor's page load.
     *
     * @return array{known:bool, exposed:array<int,string>}
     */
    public static function dataExposed(bool $force = false): array
    {
        // A key of its own. This shared 'data_exposed' with
        // DataGuard::databaseExposed, which stores a string there - see the
        // note on that method for what the collision cost both of them.
        if (!$force) {
            $cached = Cache::get('data_exposed_probe');
            if (is_array($cached)) {
                return $cached;
            }
        }
        $base = rtrim(Database::setting('site_url'), '/');
        if ($base === '' || !function_exists('curl_init')) {
            return ['known' => false, 'exposed' => []];
        }
        // Written fresh each time and deleted after. Probing the real database
        // would mean a request that downloads it if the answer is bad, which
        // is a strange thing for a security check to do; this is a file whose
        // whole content is a marker string.
        $marker = 'sma-exposure-probe-' . bin2hex(random_bytes(6));
        $probe = SMA_DATA_DIR . '/exposure-probe.txt';
        if (!is_dir(SMA_DATA_DIR) || @file_put_contents($probe, $marker) === false) {
            return ['known' => false, 'exposed' => []];
        }
        $targets = [
            'data/exposure-probe.txt' => 'the data directory',
            'data/uploads/'           => 'uploaded payment proofs',
        ];
        $known = false;
        $exposed = [];
        foreach ($targets as $path => $label) {
            $ch = curl_init($base . '/' . $path);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 8,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_USERAGENT      => 'SignalMasterAi/1.0 (exposure self-test)',
            ]);
            $body = (string)curl_exec($ch);
            $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            curl_close($ch);
            if ($code === 0) {
                continue;                      // could not reach ourselves
            }
            $known = true;
            // A 200 is not enough on its own - a host with a catch-all error
            // page returns 200 for everything. The probe file is proved by its
            // own marker; the listing by the probe file's name appearing in an
            // index of a directory that is not supposed to have one.
            if ($code === 200 && ($path === 'data/uploads/'
                    ? preg_match('/<title>Index of|\[DIR\]/i', $body) === 1
                    : str_contains($body, $marker))) {
                $exposed[] = $label;
            }
        }
        @unlink($probe);
        $out = ['known' => $known, 'exposed' => $exposed];
        Cache::set('data_exposed_probe', $out, 21600);
        return $out;
    }

    public static function health(): array
    {
        $pdo = Database::pdo();
        $cronLast = (int)Database::setting('cron_last_run', '0');
        $dispatch = (int)Database::setting('last_dispatch', '0');
        $freshest = (int)($pdo->query('SELECT MAX(fetched_at) FROM fetch_log')->fetchColumn() ?: 0);
        $newsLast  = (int)($pdo->query('SELECT MAX(fetched_at) FROM news_items')->fetchColumn() ?: 0);

        // How many members have asked for email. Decides whether an
        // unconfigured mailer is a preference or a broken promise.
        $mailWaiting = 0;
        try {
            $mailWaiting = (int)Database::pdo()
                ->query('SELECT COUNT(*) FROM member_alerts WHERE enabled = 1')->fetchColumn();
        } catch (\Throwable $e) {
            // table not there yet on a very fresh install
        }

        $checks = [
            'cron' => [
                'label'  => 'Background worker',
                'ok'     => $cronLast > 0 && time() - $cronLast < 900,
                'detail' => $cronLast > 0 ? self::ago(time() - $cronLast) : 'never run',
                'hint'   => 'Add the cron job shown on the admin dashboard.',
                // What it means to a READER, for the public status page. The
                // hint above is the operator's fix and stays off that page.
                'public' => 'Signals may be older than usual until this catches up.',
            ],
            'market' => [
                'label'  => 'Market data feed',
                'ok'     => $freshest > 0 && time() - $freshest < 3600,
                'detail' => $freshest > 0 ? self::ago(time() - $freshest) : 'no candles cached',
                'hint'   => 'Check Settings > Market data APIs and run the endpoint test.',
                'public' => 'Prices and candles may be behind the market.',
            ],
            'alerts' => [
                'label'  => 'Alert dispatch',
                'ok'     => Database::setting('alerts_enabled', '1') !== '1'
                            || ($dispatch > 0 && time() - $dispatch < 1800),
                'detail' => $dispatch > 0 ? self::ago(time() - $dispatch) : 'never dispatched',
                'hint'   => 'Alerts run from cron, or from site traffic as a fallback.',
                'public' => 'Alerts may arrive late.',
            ],
            'news' => [
                'label'  => 'News & events',
                'ok'     => Database::setting('news_enabled', '1') !== '1'
                            || ($newsLast > 0 && time() - $newsLast < 21600),
                'detail' => $newsLast > 0 ? self::ago(time() - $newsLast) : 'no headlines stored',
                'hint'   => 'Verify the RSS sources under Admin > News.',
                'public' => 'Headlines and the events calendar may be stale.',
            ],
            // SOFT ONLY WHILE NOBODY IS RELYING ON IT.
            //
            // Unconfigured mail on a site that sends none is a choice, and
            // nagging about it is noise - which is why this was soft. But the
            // moment a member ticks "email me when this flips", the same state
            // means their alerts silently never arrive: nothing bounces,
            // nothing errors, the dispatcher simply has no mailer and the
            // member waits. The panel said "Email delivery: not configured" in
            // grey and the status line still read OPERATIONAL.
            //
            // So the severity follows the consequence, the way the scan and
            // checkout checks already do: soft with no subscribers, a real
            // failure with even one.
            'email' => [
                'label'  => 'Email delivery',
                'ok'     => Mailer::enabled() || $mailWaiting === 0,
                'detail' => Mailer::enabled()
                    ? 'configured'
                    : ($mailWaiting > 0
                        ? 'not configured — ' . $mailWaiting . ' member'
                          . ($mailWaiting === 1 ? '' : 's') . ' waiting on email alerts'
                        : 'not configured (nobody is asking for email)'),
                'hint'   => $mailWaiting > 0
                    ? 'Members have email alerts switched on and this site cannot send mail, so none '
                      . 'of them will ever arrive. Set SMTP under Settings > Email, or turn member '
                      . 'email alerts off so nobody is offered something that cannot happen.'
                    : 'Optional: set SMTP in Settings > Email to enable email alerts.',
                'public' => $mailWaiting > 0 ? 'Email alerts are not going out at the moment.' : '',
                'public_detail' => $mailWaiting > 0 ? 'unavailable' : 'not in use',
                'soft'   => $mailWaiting === 0,
            ],
        ];

        // Whether the files that must never be public actually are not.
        //
        // Not soft when it fails. Everything else in this list is a service
        // running late; this one is the member table being downloadable, and
        // it fails silently on any host that does not read .htaccess. See
        // dataExposed() - the answer comes from fetching our own URLs, because
        // the protection being PRESENT and the protection WORKING are two
        // different facts and only the second one matters.
        $exposure = self::dataExposed();
        $checks['exposure'] = [
            'label'  => 'Private files',
            'ok'     => $exposure['exposed'] === [],
            'detail' => !$exposure['known']
                ? 'could not check from here'
                : ($exposure['exposed'] === []
                    ? 'not reachable over the web'
                    : 'READABLE BY ANYONE: ' . implode(' and ', $exposure['exposed'])),
            'hint'   => 'The data folder holds the database - every member email and password hash - '
                . 'the payment proofs and your database credentials. It is protected by '
                . '.htaccess, which Apache and LiteSpeed honour and NGINX IGNORES ENTIRELY. On '
                . 'nginx add: location ^~ /data/ { deny all; } to the server block, then re-check. '
                . 'Moving the data folder above the web root works everywhere.',
            'public' => '',
            'soft'   => !$exposure['known'],
            // NEVER ON THE PUBLIC STATUS PAGE.
            //
            // Every other check describes a service running late, which a
            // reader is entitled to know about. This one describes a way in.
            // Printing "Private files: READABLE BY ANYONE" on a page with no
            // login is handing the address to whoever reads it first, and
            // flipping the public status word to "degraded" invites them to
            // look. It belongs to the operator alone.
            'admin_only' => true,
        ];

        // Is anything going to be scanned at all?
        //
        // The worker can report healthy runs, the feed can be fresh and the
        // dispatcher can be ticking while the engine analyses nothing, because
        // those three checks all measure machinery rather than work. With the
        // rotation off, the scan's entire list is watched pairs plus recently
        // viewed ones - so on a site nobody has used yet it is empty, every
        // run does nothing, and every indicator above stays green. Measured on
        // a test install: eight runs in that state produced zero verdicts.
        //
        // This is the check that would have said so. It counts what the scan
        // can reach, not whether it ran.
        $scanOn = Database::setting('fullscan_enabled', '1') === '1'
                  && Master::on('signals', 'scan');
        $rotation = (int)$pdo->query('SELECT COUNT(*) FROM symbols WHERE enabled = 1 AND scan = 1')
                             ->fetchColumn();
        $watched = count(WebPush::watchedPairs()) + count(EmailAlerts::watchedPairs());
        $reach = $scanOn ? $rotation : 0;
        $checks['scan'] = [
            'label'  => 'Scan coverage',
            'ok'     => $reach > 0 || $watched > 0,
            'detail' => $scanOn
                ? $rotation . ' coin' . ($rotation === 1 ? '' : 's') . ' in the rotation'
                  . ($watched > 0 ? ', ' . $watched . ' watched pair' . ($watched === 1 ? '' : 's') : '')
                : ($watched > 0
                    ? 'rotation off — only ' . $watched . ' watched pair' . ($watched === 1 ? '' : 's')
                    : 'nothing to scan'),
            'hint'   => 'Settings > Background worker > What the background scan covers. With the '
                      . 'rotation off, a coin nobody watches is never analysed, so it can never '
                      . 'reach the scanner, the track record or an alert.',
            'public' => 'Fewer coins are being analysed than usual.',
            // The public page gets the answer, not the inventory. How many
            // coins an operator scans and how many pairs their members watch
            // are facts about the business, and a status page is not where a
            // competitor should be able to read them off.
            'public_detail' => ($reach > 0 || $watched > 0) ? 'running' : 'not running',
        ];

        // CAN A MEMBER ACTUALLY PAY?
        //
        // The one failure on this list that costs money directly, and the one
        // nothing was watching. An automatic payment method is hidden at
        // checkout unless its gateway has credentials, so an operator could
        // save a Cryptomus method, see it listed as ENABLED, and have every
        // member reaching the upgrade page told there were no payment methods
        // at all. Nothing joined those two facts up.
        //
        // Only asked of a site that has something for sale: an install with no
        // enabled plan is not trying to take money and should not be nagged
        // about how it would.
        $plansOn = (int)$pdo->query('SELECT COUNT(*) FROM plans WHERE enabled = 1')->fetchColumn();
        if ($plansOn > 0) {
            $manualOn = (int)$pdo->query(
                "SELECT COUNT(*) FROM payment_methods WHERE enabled = 1 AND kind = 'manual'")->fetchColumn();
            $autoOn = (int)$pdo->query(
                "SELECT COUNT(*) FROM payment_methods WHERE enabled = 1 AND kind = 'cryptomus'")->fetchColumn();
            $autoLive = $autoOn > 0 && Gateways::blocked('cryptomus') === null;
            $extraLive = count(Gateways::availableExtra());
            $usable = $manualOn + ($autoLive ? $autoOn : 0) + $extraLive;
            // Named so the operator does not have to guess which half is
            // missing - saved-but-dead is a different job from nothing-saved.
            $stranded = $autoOn > 0 && !$autoLive;
            $checks['checkout'] = [
                'label'  => 'Checkout',
                'ok'     => $usable > 0,
                'detail' => $usable > 0
                    ? $usable . ' payment method' . ($usable === 1 ? '' : 's') . ' a member can use'
                      . ($stranded ? ', ' . $autoOn . ' automatic one(s) stranded: '
                                     . Gateways::blocked('cryptomus') : '')
                    : ($stranded
                        ? 'nothing a member can use — ' . Gateways::blocked('cryptomus')
                        : 'nothing a member can use — no enabled payment method'),
                'hint'   => 'Billing > Payment methods. An automatic method only appears at checkout '
                          . 'once its gateway has credentials; until then the upgrade page tells '
                          . 'members there are no payment methods at all.',
                'public' => 'Upgrading is unavailable at the moment.',
                'public_detail' => $usable > 0 ? 'available' : 'unavailable',
            ];
        }

        // The public status word counts only what the public can see. An
        // admin-only check must not move it, or the page says "degraded" and
        // then lists nothing that is wrong - which reads as the site hiding
        // something, and in this one case would be a hint worth following.
        $degraded = 0;
        foreach ($checks as $c) {
            if (!$c['ok'] && empty($c['soft']) && empty($c['admin_only'])) {
                $degraded++;
            }
        }
        return [
            'checks'  => $checks,
            'status'  => $degraded === 0 ? 'operational' : ($degraded > 1 ? 'degraded' : 'partial'),
            'at'      => time(),
        ];
    }

    public static function ago(int $seconds): string
    {
        if ($seconds < 60) {
            return $seconds . 's ago';
        }
        if ($seconds < 3600) {
            return intdiv($seconds, 60) . 'm ago';
        }
        if ($seconds < 86400) {
            return intdiv($seconds, 3600) . 'h ago';
        }
        return intdiv($seconds, 86400) . 'd ago';
    }
}
