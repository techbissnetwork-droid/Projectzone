<?php
declare(strict_types=1);

/**
 * Optional background refresher for full automation on busy sites.
 * The app already refreshes news and market data lazily on page views, so a
 * cron job is never required - but pointing one here keeps caches warm:
 *
 *   CLI:  php cron.php
 *   HTTP: curl "https://your-site/cron.php?token=<cron token from Admin > Settings>"
 */

$config = require __DIR__ . '/src/bootstrap.php';

use SignalMasterAi\Database;
use SignalMasterAi\MarketData;
use SignalMasterAi\NewsFetcher;

/**
 * Is this an actual web request, or a shell running the file?
 *
 * Testing PHP_SAPI === 'cli' alone is not enough. Plenty of cPanel accounts
 * run cron through php-cgi, where the SAPI is 'cgi-fcgi' and there is no
 * request behind it: the token check then fires against a $_GET that cannot
 * exist and the job answers "403 Forbidden" to itself, every minute, forever.
 * A genuine request always carries a method and a peer address; a shell never
 * does. So that is what is tested.
 */
$isWebRequest = PHP_SAPI !== 'cli'
    && (!empty($_SERVER['REQUEST_METHOD']) || !empty($_SERVER['REMOTE_ADDR']));

if ($isWebRequest) {
    // Web cron: the token in the query string. Compared with hash_equals so a
    // wrong token cannot be found one character at a time.
    $token = (string)($_GET['token'] ?? '');
    if ($token === '' || !hash_equals(Database::setting('cron_token'), $token)) {
        http_response_code(403);
        exit('Forbidden');
    }
} elseif (PHP_SAPI !== 'cli') {
    // Shell, but through a CGI binary. No token is required - reaching the
    // shell already means server access - but the token is still accepted as
    // the first argument for operators who prefer to pass one.
    $arg = (string)($_SERVER['argv'][1] ?? '');
    $want = Database::setting('cron_token');
    if ($arg !== '' && $want !== '' && !hash_equals($want, $arg)) {
        exit("Bad token\n");
    }
}

// Headers only belong to a real response. Printed into a shell they are noise,
// and under php-cgi they end up in the cron email above the JSON.
if ($isWebRequest) {
    header('Content-Type: application/json; charset=utf-8');
}

// ONE RUN AT A TIME.
//
// Nothing stopped two cron runs overlapping, and at a five-minute schedule
// against a fifty-second budget nothing ever did. At one minute - which is
// what makes the rotation keep up with a 15m timeframe - a run that overruns
// for any reason meets the next one head on: both scan, both write, both
// advance the rotation cursor, and the coins between the two positions are
// skipped for that lap. On SQLite they also contend for the write lock, which
// is the failure this codebase has already been bitten by once.
//
// flock, not a database flag or a timestamp. A lock held by the process is
// released by the kernel when the process ends, however it ends - a fatal, an
// OOM kill, the host's own timeout. A "cron_running" row set at the start and
// cleared at the end is a lock that a killed run leaves on forever, and the
// site then quietly stops scanning until somebody notices and clears it by
// hand. There is no stale-lock case here because there is no stale lock.
//
// The handle is deliberately kept in a variable that lives for the whole
// script: closing it, or letting it fall out of scope, drops the lock.
//
// Fails OPEN. A filesystem that cannot flock (some NFS mounts) must not mean
// the site never scans again, so an unavailable lock lets the run proceed -
// back to exactly the behaviour that shipped before this existed.
$cronLockPath = (defined('SMA_DATA_DIR') ? SMA_DATA_DIR : __DIR__ . '/data') . '/cron.lock';
$cronLockFp = @fopen($cronLockPath, 'c');
if ($cronLockFp !== false && !@flock($cronLockFp, LOCK_EX | LOCK_NB)) {
    // Someone else has it. Say so and stop - this is the normal, healthy
    // outcome of a schedule that fires faster than a run finishes, not an
    // error, so it is not written to the error log.
    $skipMsg = ['ok' => true, 'skipped' => 'another cron run is still in progress',
                'at' => date('c')];
    if ($isWebRequest) {
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode($skipMsg), "\n";
    exit;
}

/** How this instance was invoked - see the timeframe split below. */
$cronArgs = [];
if ($isWebRequest) {
    foreach (['slot', 'slots', 'group', 'tfs'] as $k) {
        if (isset($_GET[$k])) {
            $cronArgs[$k] = (string)$_GET[$k];
        }
    }
} else {
    foreach ((array)($_SERVER['argv'] ?? []) as $a) {
        if (preg_match('/^--?(slot|slots|group|tfs)=(.+)$/', (string)$a, $m)) {
            $cronArgs[$m[1]] = $m[2];
        }
    }
}
$cronSlots = max(1, min(12, (int)($cronArgs['slots'] ?? 1)));
$cronSlot  = max(1, min($cronSlots, (int)($cronArgs['slot'] ?? 1)));

/**
 * Which timeframes this instance is responsible for.
 *
 * ONE CRON JOB IS THE ANSWER. Install this and nothing else:
 *
 *   * * * * *  php /path/to/cron.php
 *
 * A daily candle does not change between two five-minute runs, and a 15m one
 * is stale after ten, so the frames genuinely do deserve different rates. The
 * old answer to that was to make the OPERATOR provide them - a fast cron for
 * the frames under an hour, a slow one for the rest - and it was the wrong
 * place to solve it twice over. Plenty of shared hosts allow one cron entry.
 * And the failure mode was silent and total: install only the fast job, or
 * install both and let the slow one stop, and every watched pair on 1h, 4h and
 * 1d is never analysed by anything, so no flip is ever detected and no alert
 * ever fires for it. A live install was carrying 225 pairs in exactly that
 * state, with nothing to show for it but a line in the error log.
 *
 * The rates are now decided inside the job, where the information is. Each
 * frame keeps its own list and its own place in it, and every run's work is
 * divided between them by how often each frame can produce something new - a
 * 15m list earns four times the share of an hourly one and ninety-six times
 * the share of a daily one, with the fractions banked between runs so the slow
 * frames advance steadily instead of never. Watched pairs are paced the same
 * way, by when each was last read, so they cannot be blacked out by which job
 * happens to be running.
 *
 * The old flags still work and still mean what they meant, for anyone who has
 * already built a schedule out of them:
 *
 *   php cron.php --group=fast     (only the frames under 1h)
 *   php cron.php --group=slow     (only 1h and above)
 *   php cron.php --tfs=15m,1h     (exactly these)
 *
 * or over HTTP with &group=fast / &tfs=15m,1h on the token URL. They now
 * narrow a job that would otherwise pace itself, rather than being the only
 * way to get sensible rates. A very long list can still split across machines
 * with --slot=1 --slots=2 on two copies: each walks its own interleaved share
 * of the rotation with its own resume position.
 *
 * Everything that is not the scan - settlement, alerts, pruning, the health
 * stamp - stays single. Those must happen once per cycle whoever happens to
 * run them, so they sit behind an atomic claim rather than behind a named job,
 * which would stop the site the day that job was removed.
 */
$cronGroup = strtolower(trim((string)($cronArgs['group'] ?? '')));
$cronTfs = null;                      // null = every timeframe, as before
if (isset($cronArgs['tfs']) && trim((string)$cronArgs['tfs']) !== '') {
    $cronTfs = array_values(array_intersect($config['market']['intervals'],
        array_map('trim', explode(',', (string)$cronArgs['tfs']))));
    // Keyed by the frames themselves, not by the word "custom".
    //
    // Every --tfs= job used to answer to the same key, so a site running one
    // cron per timeframe had four workers sharing ONE rotation cursor: the 15m
    // job advanced it past coins the 1h job had not scanned, the 1h job pushed
    // it past the 15m job's, and each of them silently skipped whatever the
    // others had consumed. Their health counters were merged too, so the
    // dashboard showed a single "custom" worker and no way to see which frame
    // was falling behind. Distinct frames are distinct jobs.
    $cronGroup = 'tf_' . implode('_', $cronTfs);
} elseif ($cronGroup === 'fast' || $cronGroup === 'slow') {
    $cronTfs = [];
    foreach ($config['market']['intervals'] as $iv) {
        $mins = \SignalMasterAi\SignalEngine::tfMinutes($iv);
        if ($cronGroup === 'fast' ? $mins < 60 : $mins >= 60) {
            $cronTfs[] = $iv;
        }
    }
} else {
    $cronGroup = '';
}
// A group that matches nothing would silently scan the whole market instead of
// nothing, which is the opposite of what was asked for. Keep it empty and say
// so in the report.
$cronKey = $cronGroup !== '' ? $cronGroup : 'all';
if ($cronSlots > 1) {
    $cronKey .= '_' . $cronSlot . '_' . $cronSlots;
}
$tfAllowed = static fn(string $tf): bool => $cronTfs === null || in_array($tf, $cronTfs, true);

// What is actually running, so the admin panel can say "you configured two
// groups and one is firing" instead of leaving it to be discovered - and each
// worker's own cadence, measured rather than declared. The dashboard
// needs it per worker now: a fast job every 5 minutes and a slow one every 30
// have different cycle times over different lists, and one site-wide number
// would describe neither. Same median-of-recent-gaps method as the site-wide
// figure, and for the same reason - one missed run must not be mistaken for
// the schedule.
$seenKey = 'cron_seen_' . $cronKey;
$prevSeen = (int)Database::setting($seenKey, '0');
$nowSeen = time();
if ($prevSeen > 0) {
    $gap = $nowSeen - $prevSeen;
    if ($gap >= 20 && $gap <= 6 * 3600) {
        $g = array_values(array_filter(array_map('intval',
            explode(',', Database::setting('cron_gaps_' . $cronKey, '')))));
        $g[] = $gap;
        $g = array_slice($g, -5);
        Database::setSetting('cron_gaps_' . $cronKey, implode(',', $g));
        sort($g);
        Database::setSetting('cron_interval_' . $cronKey, (string)$g[intdiv(count($g), 2)]);
    }
}
Database::setSetting($seenKey, (string)$nowSeen);

$report = ['news' => [], 'market' => []];
if ($cronGroup !== '' || $cronSlots > 1) {
    $report['worker'] = array_filter([
        'group' => $cronGroup ?: null,
        'timeframes' => $cronTfs,
        'slot' => $cronSlots > 1 ? $cronSlot . ' of ' . $cronSlots : null,
    ], static fn($v) => $v !== null);
}

if (\SignalMasterAi\Master::on('news', 'fetch')) {
    $report['news'] = (new NewsFetcher())->refreshIfStale();
}

// Auto-analysis: refresh candles and regenerate the signal for the default
// chart plus every symbol/timeframe that has been viewed recently, so signals
// stay current on the server even with no visitors on the page. The engine
// only stores a history row when the verdict actually changes.
$engine = (new \SignalMasterAi\SignalEngine())->withMarketData(new MarketData($config));
$md = new MarketData($config);
$targets = [[
    Database::setting('default_symbol', $config['market']['default_symbol']),
    Database::setting('default_interval', $config['market']['default_interval']),
]];
$stmt = Database::pdo()->prepare(
    'SELECT symbol, tf FROM fetch_log WHERE fetched_at >= ? ORDER BY fetched_at DESC LIMIT 25'
);
$stmt->execute([time() - 86400]);
foreach ($stmt->fetchAll() as $r) {
    $targets[] = [$r['symbol'], $r['tf']];
}
// Pairs watched by Web Push subscribers or email-alert members must stay
// analysed even when nobody has the site open - that is what makes
// browser-closed and email alerts work.
foreach (array_merge(\SignalMasterAi\WebPush::watchedPairs(), \SignalMasterAi\EmailAlerts::watchedPairs()) as $pair) {
    $parts = explode(':', $pair, 2);
    if (count($parts) === 2) {
        $targets[] = $parts;
    }
}
// Full-market background scan (admin toggle): rotate through every enabled
// coin on the chosen timeframes, a batch per cron run, so signals and the
// public track record cover the whole watchlist even with zero watchers.
// Kept in its own queue so the time budget below can cut it short without
// ever dropping a watched pair.
$fsQueue = [];
$pinQueue = [];      // priority coins - every run, ahead of the rotation
$fsTotal = 0;
$fsStart = 0;
// Per timeframe, because the rotation is per timeframe now: where each frame's
// list resumes, how long it is, and how many of it this run got through.
$fsPosKeys = [];
$fsPassedTf = [];   // analysed + skipped-as-fresh, per frame: what the cursor advances by
$fsQueuedTf = [];
$fsStartPos = [];
$fsTotalTf = [];
$fsDoneTf = [];
// Two switches, two questions, and they are no longer the same switch.
//
// When each pair was last analysed, for everything that needs it.
//
// AT THE TOP LEVEL, BECAUSE ITS READERS ARE.
//
// This was loaded inside the rotation block below, which only runs when the
// scan master is on AND fullscan_enabled is 1. The watched-pair pacing gate
// further down runs either way and captures both of these - so on an install
// set to "watched pairs only" they were never defined, and that gate silently
// treated every watched pair as never analysed. Every pair came back due on
// every run, a daily candle re-read every few minutes, and the oldest-first
// sort that stops the tail of a long watchlist starving collapsed to a single
// value, which is no order at all. Exactly the failure the previous move was
// written to fix, reintroduced by moving it somewhere conditional. A value
// read unconditionally has to be set unconditionally.
$stateSeen = [];
try {
    foreach (Database::pdo()->query('SELECT symbol, tf, updated_at FROM signal_state') as $sRow) {
        $stateSeen[$sRow['symbol'] . '@' . $sRow['tf']] = (int)$sRow['updated_at'];
    }
} catch (Throwable $e) {
    // No state table yet: everything reads as never-analysed, which is right.
}
$nowScan = time();

// Master::on('signals','scan') is WHETHER the scan runs. fullscan_enabled is
// WHAT it covers: every coin in the rotation, or only the pairs somebody
// watches. fullscan_enabled used to be wired as the master's legacy key, so
// the second answer silently decided the first - "only watched pairs" turned
// the entire scan off, watched pairs included, and stored nothing.
if (\SignalMasterAi\Master::on('signals', 'scan')
    && Database::setting('fullscan_enabled', '1') === '1') {
    // The site's list, and there is no other one.
    //
    // The scan used to keep its own timeframe list, narrowing the site's. Two
    // settings, and they contradicted each other in the operator's favour
    // exactly never: switch 15m off site-wide and it left the chart, the
    // scanner and the track record while the rotation carried on scanning it,
    // because it was still ticked in its own box. Work nobody asked for, on a
    // frame the site says it does not run, producing calls the track record
    // filters out again.
    //
    // Intersecting the two fixed the contradiction and left the confusion: a
    // reader still had to hold both lists in their head to predict what would
    // be scanned. One list answers it - a frame the site runs is scanned,
    // alerted on, charted and re-calibrated, because nobody ever wanted those
    // to be four separate decisions.
    $fsTfs = \SignalMasterAi\Visibility::siteTfs($config['market']['intervals']);
    // Narrowed to this worker's frames. A fast job never queues a daily and a
    // slow job never queues a 15m, so the two lists are disjoint by
    // construction and neither can spend its budget on the other's work.
    $fsTfs = array_values(array_filter($fsTfs, $tfAllowed));
    // scan = 0 keeps a coin on the site but out of the rotation, so the cron
    // budget goes to the pairs the operator actually publishes.
    $fsSymbols = array_column(Database::pdo()->query(
        'SELECT symbol FROM symbols WHERE enabled = 1 AND scan = 1 ORDER BY symbol')->fetchAll(), 'symbol');
    // Priority coins are lifted out of the rotation entirely: they run every
    // time, and the rotation covers everything else. Leaving them in both
    // would waste a rotation slot re-reading something already read.
    $pinned = array_column(Database::pdo()->query(
        'SELECT symbol FROM symbols WHERE enabled = 1 AND scan = 1 AND priority = 1
         ORDER BY symbol')->fetchAll(), 'symbol');
    $pinSet = array_flip($pinned);
    // A coin is only queued on the timeframes it is actually read on. Before
    // this, every coin was scanned on every rotation frame whether or not the
    // reading was worth having - a thin altcoin with no meaningful 5m
    // structure cost a slot per frame on every cycle to produce calls nobody
    // should act on, and those slots came out of the coins that matter.
    $ownTfs = static fn(string $sym): array =>
        array_values(array_intersect($fsTfs, \SignalMasterAi\Visibility::tfsFor($sym, $fsTfs)));
    foreach ($pinned as $pSym) {
        foreach ($ownTfs($pSym) as $fsTf) {
            $pinQueue[] = [$pSym, $fsTf];
        }
    }
    // One list per timeframe, not one list of everything.
    //
    // THIS IS WHY A SINGLE CRON JOB IS ENOUGH. The rotation used to be one
    // flat queue walked by one cursor, so every frame got the same number of
    // slots per run: a daily candle - which closes once a day - was re-read as
    // often as a 15-minute one. On a real install that read "full scan cycle
    // 190 minutes, fastest frame 15 minutes": three quarters of every run was
    // spent re-reading candles that could not have moved, and the frame that
    // needed the slots was the one starved of them.
    //
    // The answer to that was "install a second cron job for the fast frames",
    // which is a lot to ask of a shared host, and it fails in a way nobody
    // sees: if only one of the two jobs is installed, every timeframe the
    // other one owned is never scanned at all, forever, with no error.
    //
    // So the split happens inside the job instead. Each frame keeps its own
    // list and its own place in it, and the run's work is divided between them
    // by how often each frame can actually produce something new. One job, and
    // the minute frames get the share the hour frames were wasting.
    $fsByTf = [];
    foreach ($fsSymbols as $fsSym) {
        if (isset($pinSet[$fsSym])) {
            continue;
        }
        foreach ($ownTfs($fsSym) as $fsTf) {
            $fsByTf[$fsTf][] = [$fsSym, $fsTf];
        }
    }
    // This instance's share of the rotation. Interleaved rather than sliced in
    // blocks, so every slot walks the whole alphabet at the same rate instead
    // of one job owning A-F forever - and a slot that stops running leaves
    // gaps spread through the list rather than a third of it dark.
    if ($cronSlots > 1) {
        foreach ($fsByTf as $fsTf => $fsList) {
            $mine = [];
            foreach ($fsList as $ji => $job) {
                if ($ji % $cronSlots === $cronSlot - 1) {
                    $mine[] = $job;
                }
            }
            $fsByTf[$fsTf] = $mine;
        }
    }
    $fsByTf = array_filter($fsByTf);
    if ($fsByTf) {
        // The batch is what the operator asks for, not what a hard-coded
        // ceiling allows. A watchlist of 479 coins across three timeframes is
        // 1437 jobs; capped at 60 a run that is a four-hour cycle, which makes
        // a 15m scan meaningless. What actually needs protecting is the run
        // itself, and a count cannot protect it - the same 60 coins take a
        // different time on a fast VPS and a shared box. The time budget below
        // does the protecting, so this is free to be as large as it needs.
        $batch = max(1, min(5000, (int)Database::setting('fullscan_batch', '10')));

        // What each frame is worth per run: how many times an hour it can
        // close a new candle. A 15m frame closes four times an hour and a
        // daily once a day, so the 15m list earns 96 times the share - which
        // is the ratio that was missing when they shared one queue.
        //
        // Its own resume position too, per frame. Sharing one pointer across
        // frames meant the daily list advanced whenever the 15m list did.
        // (The old flat `fullscan_pos` is left behind rather than migrated:
        // an index into a list that no longer exists cannot be translated into
        // one, and the cost of starting a frame at zero is that it re-reads
        // part of one cycle. Self-correcting, once.)
        $fsWeight = [];
        foreach ($fsByTf as $fsTf => $fsList) {
            $fsWeight[$fsTf] = 60.0 / max(1, \SignalMasterAi\SignalEngine::tfMinutes((string)$fsTf));
        }
        $fsWSum = array_sum($fsWeight) ?: 1.0;
        // Credit carried between runs, which is what stops the slow frames
        // starving outright.
        //
        // Four frames and a batch of twelve gives the daily frame 0.09 of a
        // slot per run. Rounded inside one run that is zero, every run,
        // forever - the daily rotation would simply never advance, and the
        // report would say "1d: 0 of 32" for the rest of the site's life. The
        // share is real, it is just smaller than one slot, so it is banked:
        // eleven runs of nine hundredths is a slot, and the frame takes it.
        //
        // Clamped either side so a frame cannot bank an unbounded debt or
        // credit across an outage and then either monopolise a run or sit out
        // a dozen of them.
        $creditRaw = json_decode(Database::setting('fullscan_credit_' . $cronKey, '{}'), true);
        $credit = [];
        $fsPos = [];
        $fsLeft = [];
        foreach ($fsByTf as $fsTf => $fsList) {
            $fsPosKeys[$fsTf] = 'fullscan_pos_' . $cronKey . '_' . $fsTf;
            $fsPos[$fsTf] = (int)Database::setting($fsPosKeys[$fsTf], '0') % count($fsList);
            $fsLeft[$fsTf] = count($fsList);
            $credit[$fsTf] = is_array($creditRaw)
                ? max(-2.0, min(1.0, (float)($creditRaw[$fsTf] ?? 0.0))) : 0.0;
            $fsStartPos[$fsTf] = $fsPos[$fsTf];
            $fsTotalTf[$fsTf] = count($fsList);
            $fsTotal += count($fsList);
        }
        // Smooth weighted round-robin rather than "take N from each frame in
        // turn". The order matters as much as the counts: the run stops on a
        // clock, and a queue that listed one frame's whole share before
        // starting the next would give the frames at the end of it nothing at
        // all on any host that runs out of time - which is every host this
        // pacing exists for. Interleaved, a run cut short keeps the same
        // proportions it would have had if it finished.
        // A CANDLE THAT HAS NOT CLOSED CANNOT CHANGE THE ANSWER.
        //
        // The rotation walks a ring and re-reads whatever it lands on. With
        // cron every minute and a lap of roughly an hour, a 4h pair is visited
        // about four times per candle and a daily one about twenty-four - and
        // every visit inside the same bar recomputes an identical verdict from
        // identical closed candles. Three quarters of the 4h work and
        // ninety-six per cent of the daily work was arithmetic nobody needed.
        //
        // Watched pairs have been paced this way from the start, at a third of
        // their frame so a live setup is looked at two or three times inside
        // its own candle. The rotation does not need that: nobody is waiting
        // on a background coin between closes, so once per bar is the whole
        // job. A pair with no state has never been analysed and is always due.
        //
        // Skipping is not the same as being done. The slot is handed back to
        // whichever frame can use it, so a run that would have spent itself
        // re-reading fresh dailies now spends itself on coins with new bars.
        $barGate = Database::setting('fullscan_bar_gate', '1') === '1';
        $fsFresh = 0;
        $fsFreshTf = [];
        $fsDue = static function (string $sym, string $tf) use ($stateSeen, $nowScan): bool {
            $last = (int)($stateSeen[$sym . '@' . $tf] ?? 0);
            if ($last <= 0) {
                return true;               // never analysed
            }
            $bar = \SignalMasterAi\SignalEngine::tfMinutes($tf) * 60;
            if ($bar <= 0) {
                return true;
            }
            // Same bar index = no close between the two, whatever the wall
            // clock says. Comparing elapsed seconds instead would call a pair
            // read at 13:59 stale at 14:01 on the hour frame, which is the
            // same candle.
            return intdiv($nowScan, $bar) !== intdiv($last, $bar);
        };

        $slots = 0;
        while ($slots < $batch) {
            $pick = null;
            foreach ($fsByTf as $fsTf => $fsList) {
                if ($fsLeft[$fsTf] <= 0) {
                    continue;
                }
                $credit[$fsTf] += $fsWeight[$fsTf] / $fsWSum;
                if ($pick === null || $credit[$fsTf] > $credit[$pick]) {
                    $pick = $fsTf;
                }
            }
            if ($pick === null) {
                break;              // every frame's list is exhausted
            }
            $credit[$pick] -= 1.0;
            $n = count($fsByTf[$pick]);
            $fsJob = $fsByTf[$pick][($fsPos[$pick] + ($fsTotalTf[$pick] - $fsLeft[$pick])) % $n];
            $fsLeft[$pick]--;
            if ($barGate && !$fsDue((string)$fsJob[0], (string)$fsJob[1])) {
                // Costs nothing, so it does not spend the frame's share - and
                // it still counts as passed, or the cursor would stop dead at
                // the first block of fresh coins and never reach the stale
                // ones behind them.
                $credit[$pick] += 1.0;
                $fsPassedTf[$pick] = ($fsPassedTf[$pick] ?? 0) + 1;
                $fsFresh++;
                $fsFreshTf[$pick] = ($fsFreshTf[$pick] ?? 0) + 1;
                continue;
            }
            $fsQueue[] = $fsJob;
            $fsQueuedTf[$pick] = ($fsQueuedTf[$pick] ?? 0) + 1;
            $slots++;
        }
        $fsCredit = [];
        foreach ($credit as $fsTf => $c) {
            $fsCredit[$fsTf] = round(max(-2.0, min(1.0, $c)), 4);
        }
        Database::setSetting('fullscan_credit_' . $cronKey, (string)json_encode($fsCredit));
        $fsStart = 0;
    }
}
// How long this run may spend analysing before it stops and leaves the rest
// for the next one.
//
// Left to a count, an ambitious batch on a shared host is killed mid-run by
// max_execution_time: PHP stops wherever it happens to be, the position was
// already advanced past coins that were never scanned, and every cycle
// silently skips a block of the watchlist. Stopping on the clock instead is
// safe at any batch size - the run ends cleanly, the position is saved at the
// coin it actually reached, and the next run carries on from there.
//
// 0 means work it out: most of whatever the host allows, or fifty seconds
// when it allows unlimited, leaving room for the rest of the cron to finish.
$budget = (int)Database::setting('fullscan_max_seconds', '0');
if ($budget <= 0) {
    $limit = (int)ini_get('max_execution_time');
    $budget = $limit > 0 ? (int)max(10, $limit * 0.8) : 50;
}
// A MEASUREMENT THAT NEVER GETS A TURN IS A MEASUREMENT THAT DOES NOT EXIST.
//
// The long-job step sits at the bottom of this file, after the scan, on
// whatever time is left. On a quiet install that is fine. On a busy one the
// full scan uses the whole budget every run, the step is skipped every run,
// and a twelve-minute measurement never finishes - which is the same failure
// as not having built it, only harder to notice.
//
// So when a job is waiting, its slice is reserved HERE, by shortening the
// scan's deadline. The scan gives up a quarter of its budget for as long as a
// measurement is outstanding and gets it back the moment the job is done. The
// site's real work still comes first; it just no longer takes everything.
$jobReserve = 0.0;
if (\SignalMasterAi\LongJob::pending()) {
    $jobReserve = (float)(int)Database::setting('longjob_seconds', '0');
    if ($jobReserve <= 0) {
        $jobReserve = $budget * 0.25;
    }
    $jobReserve = max(0.0, min($jobReserve, $budget * 0.5));
}
$deadline = ($_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true)) + ($budget - $jobReserve);

// A coin the exchange no longer has fails on every run, forever, and takes a
// slot in every cycle that a live coin could have had. Failures are collected
// here and judged after the loop, never during it - see below for why.
$failLimit = max(0, (int)Database::setting('scan_autodisable_fails', '3'));
$failed = [];      // symbol => first error seen this run
$worked = [];      // symbol => true, at least one timeframe read cleanly

$seen = [];
$fsDone = 0;
// A coin restricted to a subset of frames is not analysed outside them,
// whoever asked. The rotation is built from its own list already; these come
// from watched pairs, the default chart and whatever was viewed recently, and
// without this a member watching a pair on a frame the operator has turned off
// for that coin would keep it analysed - and alerting - anyway.
$siteTfsAll = \SignalMasterAi\Visibility::siteTfs($config['market']['intervals']);
// The signals master, above everything in this loop.
//
// This is the only switch that stops the scan. The rotation is gated further
// up by fullscan_enabled, which decides COVERAGE - and watched pairs are
// analysed here whether or not the rotation runs, because "cover only what
// somebody watches" has to mean that rather than "cover nothing". Master off
// means the engine does not run in cron at all: no analysis, no stored
// verdicts, and nothing for the alert dispatch below to find.
if (!\SignalMasterAi\Master::on('signals', 'scan')) {
    $targets = [];
    $pinQueue = [];
    $fsQueue = [];
    $report['signals'] = 'master off';
}
// A WATCHED PAIR IS NEVER SOMEONE ELSE'S JOB.
//
// This used to drop any watched pair outside the running worker's timeframe
// group, on the reasoning that a fast job should not re-read a daily candle
// twelve times an hour. True, and it turned into the worst failure this
// application had: install only the fast cron - or install both and let the
// slow one stop - and every watched pair on 1h, 4h and 1d is never analysed by
// anything, forever. It has no stored verdict, so no flip can be detected, so
// no alert can ever fire for it, and the only trace is a line in the error log
// saying the pair has never been analysed. A live install was carrying 225 of
// those. The members watching those pairs were simply never going to hear
// anything, and nothing in the panel said so.
//
// A group is a hint about which frames a job should PREFER. It was never
// supposed to be a promise that some other job exists. So the frame is paced
// instead of filtered: a pair is re-read when its own candle could plausibly
// have moved, by whichever job happens to run. A daily watched pair is read
// about every eight hours whether one job is installed or four - the
// over-reading the filter was there to prevent, prevented properly.
$targets = array_values(array_filter($targets, static function ($t) use ($siteTfsAll, $stateSeen, $nowScan) {
    $sym = (string)$t[0];
    $tf  = (string)$t[1];
    if (!\SignalMasterAi\Visibility::allowsTf($sym, $tf, $siteTfsAll)) {
        return false;
    }
    $last = $stateSeen[$sym . '@' . $tf] ?? 0;
    if ($last <= 0) {
        return true;      // never analysed - always due, whatever the frame
    }
    // A third of the frame, so a setup is looked at two or three times inside
    // its own candle rather than once at the end of it. Never more often than
    // once a minute, which is faster than any cron schedule anyway.
    $mins = \SignalMasterAi\SignalEngine::tfMinutes($tf);
    return $nowScan - $last >= max(60, (int)round($mins * 60 / 3));
}));
// Oldest first. The pass below stops on a clock, so on a long watchlist the
// order decides which pairs get read at all - and "the one nothing has looked
// at in longest" is the only order under which nothing can starve. Sorted
// rather than left in watchlist order, which would read the same alphabetical
// prefix every run and never reach the end of the list.
usort($targets, static function ($a, $b) use ($stateSeen) {
    return ($stateSeen[$a[0] . '@' . $a[1]] ?? 0) <=> ($stateSeen[$b[0] . '@' . $b[1]] ?? 0);
});

$fsStopped = false;
// How much of the budget the watched pass may spend before it leaves the rest
// to the rotation. Seven tenths when the rotation has nothing of its own
// queued this run - nothing to protect. Tightened to a bit over half when it
// does, so a site with a lot of watched pairs can't push full-market coverage
// down to the single-coin floor the loop below still guarantees as a last
// resort: watched pairs still get the majority of the run, just not all of it.
$watchShare = ($fsQueue || $pinQueue) ? 0.55 : 0.7;
$watchDeadline = ($_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true)) + $budget * $watchShare;
$watchStopped = false;
$watchDone = 0;
$pinCount = count($pinQueue);
$targetCount = count($targets);
$scanList = array_merge($targets, $pinQueue, $fsQueue);

// Open every stale pair's request at once, before analysing any of them.
//
// The loop below is serial and has to be - it writes signals, settles state
// and sends alerts in order. What does not have to be serial is the waiting.
// Each iteration used to open one HTTP request, block until it came back,
// then move on, so a hundred pairs meant a hundred round trips end to end
// even though the machine was idle for almost all of it.
//
// This warms the cache for the whole batch over concurrent connections first,
// so the loop then reads from the database and the analysis is the only thing
// left in it. Nothing about the loop changed; it simply stops being the thing
// that waits.
//
// Bounded by fetch_prefetch_max rather than the deadline: a pair fetched but
// not reached this run is not wasted work, because the candles are cached and
// the next run gets it free. What has to be bounded is the wall clock, and a
// cap on how many requests are issued is the honest way to do that - the
// alternative is a prefetch that abandons half its own responses.
try {
    $prefetchMax = max(0, min(1000, (int)Database::setting('fetch_prefetch_max', '120')));
    if ($prefetchMax > 0 && $scanList) {
        $report['prefetch'] = $md->prefetch(array_slice($scanList, 0, $prefetchMax));
    }
} catch (Throwable $e) {
    // An optimisation that fails leaves every pair exactly as it was: stale,
    // and refreshed by the serial path in the loop, which is what happened
    // before this existed.
    $report['prefetch'] = ['error' => $e->getMessage()];
}

foreach ($scanList as $i => [$sym, $tf]) {
    $isFullScan = $i >= $targetCount;
    // Only the rotation advances the resume position. A priority coin is read
    // every run by definition, so counting it as progress through the list
    // would march the pointer past coins nobody had looked at.
    $isRotation = $i >= $targetCount + $pinCount;
    // Watched pairs and the default chart come first: they are what makes
    // alerts work. Only the background rotation yields to the clock.
    //
    // But it always gets at least one coin. On a slow host with a long list
    // of recently-viewed pairs the budget can be gone before the rotation
    // starts, and a rotation that scans nothing never advances - the same
    // block of the watchlist would be first in the queue forever and the rest
    // would never be reached at all. One coin a run is slow; zero is stuck.
    if ($isRotation && $fsDone > 0 && microtime(true) >= $deadline) {
        $fsStopped = true;
        break;
    }
    // And the watched pass yields too, at its own earlier line.
    //
    // It had no limit at all, on the principle that a watched pair must never
    // be dropped. That principle is right and the implementation of it was the
    // opposite: two hundred watched pairs at a few seconds each is longer than
    // any shared host allows, PHP is killed partway through, and everything
    // after this loop - settlement, the alert dispatch itself, the health
    // stamp - never runs. "Never dropped" turned into "nothing is delivered".
    //
    // They are sorted oldest-first, so stopping here drops the pairs that were
    // read most recently and the next run starts with the ones this one could
    // not reach. Nothing starves; it just takes more than one run to get round
    // a list the host cannot do in one. The share left over is what keeps the
    // rotation from being squeezed to its one-coin floor on every run.
    if (!$isFullScan && $watchDone > 0 && microtime(true) >= $watchDeadline) {
        $watchStopped = true;
        continue;                  // skip the rest of the watched pairs, keep the rotation
    }
    $key = "$sym@$tf";
    if (isset($seen[$key])) {
        if ($isRotation) {
            $fsDone++;      // already analysed above; the rotation still passed it
            $fsDoneTf[$tf] = ($fsDoneTf[$tf] ?? 0) + 1;
            $fsPassedTf[$tf] = ($fsPassedTf[$tf] ?? 0) + 1;
        }
        continue;
    }
    $seen[$key] = true;
    try {
        $result = $engine->analyse($sym, $tf, $md->candles($sym, $tf));
        $report['market'][$key] = $result['signal'] . ' (' . $result['score'] . ')';
        $worked[$sym] = true;
    } catch (Throwable $e) {
        $why = $e->getMessage();
        $report['market'][$key] = 'error: ' . $why;
        $failed[$sym] = $failed[$sym] ?? $why;
        // The report only reaches whoever reads that minute's cron mail. The
        // error log is where it is still readable tomorrow.
        \SignalMasterAi\ErrorLog::record(\SignalMasterAi\ErrorLog::SIGNAL,
            'Analysis failed - ' . $why, $key);
    }
    if ($isRotation) {
        $fsDone++;
        // Per frame as well as in total: each frame keeps its own place in its
        // own list, so each needs its own count of how far this run moved it.
        $fsDoneTf[$tf] = ($fsDoneTf[$tf] ?? 0) + 1;
        $fsPassedTf[$tf] = ($fsPassedTf[$tf] ?? 0) + 1;
    } elseif (!$isFullScan) {
        $watchDone++;
    }
}

// Judge the failures now that the whole run is in view.
//
// Counting them as they happened would have made an exchange outage look like
// a watchlist of dead coins: every pair fails, every pair is struck three
// times in one run because it is scanned on three timeframes, and the next
// morning the rotation is empty. So a symbol counts once per run however many
// timeframes it failed on, one clean read anywhere clears it, and if most of
// the run failed nothing is counted at all - that is the exchange, not the
// coins, and it will be back.
if ($failLimit > 0 && $seen) {
    $attempted = count($worked) + count(array_diff_key($failed, $worked));
    $badShare = $attempted > 0 ? count(array_diff_key($failed, $worked)) / $attempted : 0.0;
    if ($badShare > 0.5) {
        $report['scan_health'] = ['skipped_strikes' => true, 'failed_share' => round($badShare, 2),
                                  'why' => 'most of this run failed - treating it as an outage, not as dead coins'];
    } else {
        $pdo2 = Database::pdo();
        $clear = $pdo2->prepare("UPDATE symbols SET scan_fails = 0, scan_note = ''
                                 WHERE symbol = ? AND scan_fails > 0");
        $strike = $pdo2->prepare('UPDATE symbols SET scan_fails = scan_fails + 1, scan_note = ?
                                  WHERE symbol = ?');
        // Says what happened without counting it against the coin.
        $noteOnly = $pdo2->prepare('UPDATE symbols SET scan_note = ? WHERE symbol = ?');
        $lookup = $pdo2->prepare('SELECT scan_fails FROM symbols WHERE symbol = ?');
        $disable = $pdo2->prepare('UPDATE symbols SET scan = 0 WHERE symbol = ?');
        $dropped = [];
        $temporary = [];
        foreach (array_keys($worked) as $sym) {
            $clear->execute([$sym]);
        }
        foreach ($failed as $sym => $why) {
            if (isset($worked[$sym])) {
                continue;              // read fine on another timeframe
            }
            // A STRIKE MEANS "THIS COIN IS NOT THERE", NOT "THIS FETCH FAILED".
            //
            // Every failure counted the same, so three rate-limited runs, three
            // timeouts, or three minutes of an exchange being unreachable from
            // this host dropped a perfectly live coin out of the rotation - and
            // nothing ever puts it back, because a coin with scan = 0 is never
            // read again to discover that it recovered. On a long watchlist
            // against a rate-limited endpoint that is not an edge case, it is
            // Tuesday: the list quietly erodes and the operator finds out from
            // the gaps in their own track record.
            //
            // The >50% guard above already catches a total outage. It does
            // nothing for the ordinary case - a handful of coins failing every
            // run while the rest succeed - which is exactly how a rate limit
            // and a flaky endpoint both look.
            //
            // So only a failure that says the symbol does not exist counts, and
            // only when EVERY configured endpoint said it. Anything else is
            // recorded against the coin, so the operator can see it, and left
            // in the rotation to be tried again.
            if (!str_contains($why, \SignalMasterAi\MarketData::PERMANENT_TAG)) {
                // The note, NOT the counter. Incrementing here would leave a
                // coin sitting at two strikes from a bad afternoon, so the
                // first genuine "no such symbol" would drop it immediately -
                // the same coin lost to the same cause, one release later.
                $noteOnly->execute(['temporary: ' . mb_substr($why, 0, 178), $sym]);
                $temporary[] = $sym;
                continue;
            }
            $strike->execute([mb_substr($why, 0, 190), $sym]);
            $lookup->execute([$sym]);
            if ((int)$lookup->fetchColumn() >= $failLimit) {
                $disable->execute([$sym]);
                $dropped[] = $sym;
            }
        }
        if ($dropped) {
            $report['scan_disabled'] = $dropped;
            \SignalMasterAi\ErrorLog::record(\SignalMasterAi\ErrorLog::MARKET,
                'Coins dropped from the scan - every endpoint reports no such symbol',
                implode(', ', $dropped) . '. Re-enable under Coins if this is wrong.');
        }
        if ($temporary) {
            // Reported, not silent. These coins failed and were KEPT, and an
            // operator watching the same twenty names fail every run needs to
            // know it is their endpoint and not their watchlist.
            $report['scan_failed_temporarily'] = ['kept' => count($temporary),
                                                  'symbols' => array_slice($temporary, 0, 25)];
        }
    }
}
// Resume from the coin actually reached, not from the one the batch size
// hoped for.
// The watched pass, reported rather than assumed.
//
// This was invisible: watched pairs were analysed, or silently were not, and
// the only way to find out was a member saying they got nothing. If the list
// is longer than the host can read in one run, that is a fact the operator
// needs on the dashboard, not a mystery in an inbox.
if ($targetCount > 0) {
    $report['watched'] = [
        'due_this_run' => $targetCount,
        'analysed' => $watchDone,
        'stopped_on_time' => $watchStopped,
    ];
    Database::setSetting('watch_due_' . $cronKey, (string)$targetCount);
    Database::setSetting('watch_done_' . $cronKey, (string)$watchDone);
    Database::setSetting('watch_stopped_' . $cronKey, $watchStopped ? '1' : '0');
    if ($watchStopped) {
        \SignalMasterAi\ErrorLog::record(\SignalMasterAi\ErrorLog::SIGNAL,
            'The cron run ran out of time before reading every watched pair that was due',
            $watchDone . ' of ' . $targetCount . ' read. The oldest go first, so the rest are '
            . 'first in line next run - but alerts on them are late by however long that is. '
            . 'Run cron more often, or raise fullscan_max_seconds under Settings > Market.');
    }
}

// Priority coins run on every instance by definition - they are the pairs the
// operator publishes, and reading them once per cycle is the whole point. With
// several slots that means each one reads them, which is the intended cost of
// having them read every run.
if ($pinCount > 0 && $fsTotal === 0) {
    // Everything is pinned, so there is no rotation to report on - but the
    // priority pass still happened and should not vanish from the report.
    $report['fullscan'] = ['priority_every_run' => $pinCount, 'of' => 0,
                           'stopped_on_time' => $fsStopped, 'budget_sec' => $budget,
                           'skipped_same_bar' => $fsFresh ?? 0];
}
if ($fsTotal > 0) {
    // Each frame resumes where it got to, not where the combined queue got to.
    foreach ($fsPosKeys as $fsTf => $fsPosKey) {
        $n = $fsTotalTf[$fsTf] ?? 0;
        if ($n > 0) {
            // PASSED, not analysed. A job skipped because its candle has not
            // closed was still dealt with this lap, and if the cursor ignored
            // it the rotation would stop dead at the first run of fresh coins
            // and never reach the stale ones behind them - the gate would turn
            // an optimisation into a rotation that cannot advance.
            Database::setSetting($fsPosKey,
                (string)((($fsStartPos[$fsTf] ?? 0) + ($fsPassedTf[$fsTf] ?? 0)) % $n));
        }
    }

    // How many coins this run ACTUALLY got through, kept for the dashboard.
    //
    // The batch setting is a ceiling, not a promise: the run stops on the
    // time budget above wherever it happens to be, so a host that manages
    // forty coins in fifty seconds has an eight-times-longer cycle than a
    // batch of two hundred implies. The dashboard used to do that arithmetic
    // against the configured batch and quote a cycle time the host could not
    // deliver - reassuring, and wrong in the one direction that matters.
    //
    // Smoothed rather than last-run, because a single run is noisy: a slow
    // exchange response, a run that landed while the priority pass was long,
    // or the first run after a settings change would each move the headline
    // number for reasons that are not the throughput. The weight is low
    // enough to ignore one bad run and high enough to follow a real change
    // over a handful of them.
    $rateKey = 'fullscan_rate_' . $cronKey;
    $prev = (float)Database::setting($rateKey, '0');
    $rate = $prev > 0 ? $prev * 0.7 + $fsDone * 0.3 : (float)$fsDone;
    Database::setSetting($rateKey, (string)round($rate, 2));
    Database::setSetting('fullscan_stopped_' . $cronKey, $fsStopped ? '1' : '0');

    // The same measurement per frame, which is the only one that answers the
    // question the dashboard asks. "Full scan cycle 190 minutes against a
    // fastest frame of 15 minutes" compared a combined figure to one frame:
    // the daily list is IN that 190 minutes and has no business being judged
    // against 15. Each frame now reports its own throughput, so the alert can
    // say "your 15m rotation takes 40 minutes" - a true statement about a
    // thing an operator can act on.
    $perTf = [];
    foreach ($fsTotalTf as $fsTf => $n) {
        $didTf = (int)($fsDoneTf[$fsTf] ?? 0);
        $tfRateKey = 'fullscan_rate_' . $cronKey . '_' . $fsTf;
        $prevTf = (float)Database::setting($tfRateKey, '0');
        $rateTf = $prevTf > 0 ? $prevTf * 0.7 + $didTf * 0.3 : (float)$didTf;
        Database::setSetting($tfRateKey, (string)round($rateTf, 2));
        // WHAT WAS DUE AND DID NOT GET DONE.
        //
        // The rate above is per RUN, and most runs have nothing due for a slow
        // frame - so dividing the coin list by it produces numbers like "the
        // 4h rotation takes 16000 minutes", which is not a throughput, it is a
        // measure of how rarely 4h comes due. Every cycle figure built on that
        // rate inherits the same nonsense; the dashboard printed exactly that
        // before this was recorded.
        //
        // The backlog needs no arithmetic and cannot be misread: work came
        // due on this frame, this many coins of it were not reached. Zero
        // means the frame is keeping up, whatever its rate looks like.
        $queuedTf = (int)($fsQueuedTf[$fsTf] ?? 0);
        $behindTf = max(0, $queuedTf - $didTf);
        Database::setSetting('fullscan_behind_' . $cronKey . '_' . $fsTf, (string)$behindTf);
        $perTf[$fsTf] = [
            'jobs' => $n,
            'queued' => $queuedTf,
            'scanned' => $didTf,
            'behind' => $behindTf,
            'per_run_measured' => round($rateTf, 2),
            'full_cycle_runs' => $rateTf > 0 ? (int)ceil($n / $rateTf) : null,
        ];
    }

    $report['fullscan'] = [
        'priority_every_run' => $pinCount,
        'scanned' => $fsDone,
        'asked_for' => count($fsQueue),
        'of' => $fsTotal,
        'per_run_measured' => round($rate, 1),
        'full_cycle_runs' => $fsDone > 0 ? (int)ceil($fsTotal / $fsDone) : null,
        'stopped_on_time' => $fsStopped,
        'budget_sec' => $budget,
        // Jobs the rotation walked past because their candle had not closed
        // since the last look. Reported so the saving is visible rather than
        // merely claimed - on a healthy install this should be large next to
        // 'scanned', and if it is zero the gate is doing nothing for you.
        'skipped_same_bar' => $fsFresh,
        'skipped_by_timeframe' => $fsFreshTf,
        'by_timeframe' => $perTf,
    ];
}

// Flash-move circuit breaker: if BTC just moved violently, arm a caution
// window. While it is armed every pair needs a stronger score and cannot be
// graded A - the rule set is reading a chart that is still reacting to
// something it cannot see. Uses candles the analysis pass above already
// cached, so it costs no extra request, and fails soft: a guard that breaks
// the cron is worse than no guard.
if (Database::setting('panic_enabled', '1') === '1') {
    try {
        $pStmt = Database::pdo()->prepare(
            'SELECT close FROM candles WHERE symbol = ? AND tf = ? ORDER BY open_time DESC LIMIT 2');
        $pStmt->execute(['BTCUSDT', '1h']);
        $pCloses = array_map('floatval', $pStmt->fetchAll(PDO::FETCH_COLUMN));
        if (count($pCloses) === 2 && $pCloses[1] > 0) {
            $movePct = abs($pCloses[0] - $pCloses[1]) / $pCloses[1] * 100;
            $trigger = max(1.0, (float)Database::setting('panic_move_pct', '4'));
            if ($movePct >= $trigger) {
                $hold = max(10, (int)Database::setting('panic_hold_min', '90')) * 60;
                Database::setSetting('panic_until', (string)(time() + $hold));
                $report['circuit_breaker'] = ['armed' => true, 'btc_1h_move_pct' => round($movePct, 2)];
            }
        }
    } catch (Throwable $e) {
        // fail soft - never let the guard break the run
    }
}

// Everything below this line happens once per cycle, not once per cron job.
//
// Settlement, alerts, pruning and the health stamp are all "do this once"
// work. Run by three concurrent instances they would settle the same trades
// three times, mail every member three times, and - worst of the three -
// measure the gap between cron runs as a few seconds, which is the number the
// dashboard uses to tell an operator whether their scan can keep up.
//
// The claim is atomic and anyone can win it, rather than being pinned to one
// named job: a site whose alerts stop the day someone removes the fast cron is
// not an improvement on a site with one cron job. Twenty seconds is shorter
// than any sane cron period, so a single instance always wins it and nothing
// about the ordinary case changes; two instances firing on the same minute
// means one does this work and the other stops here.
$isPrimary = Database::claimJob('cron_tail', 20);
$report['primary'] = $isPrimary;
if (!$isPrimary) {
    // Another instance is doing the once-per-cycle work. This one is finished.
    echo json_encode(['ok' => true, 'refreshed' => $report, 'at' => gmdate('c')]);
    exit;
}

// Verify open signals against subsequent price action (confirmed / invalid).
// Guarded because settlement is what the whole track record is made of: if it
// stops, the site keeps publishing signals and quietly stops scoring them,
// which looks like nothing at all until someone notices the open count only
// ever goes up.
try {
    [$won, $lost] = \SignalMasterAi\Outcomes::evaluate();
    // The setups it declined, settled the same way. Nobody was shown these, so
    // nothing here can reach a member - they exist only so the tuner can find
    // out whether refusing them was right.
    [$shWon, $shLost] = \SignalMasterAi\Outcomes::evaluateShadows();
    $report['shadows_settled'] = ['confirmed' => $shWon, 'invalid' => $shLost];
    $report['outcomes'] = ['confirmed' => $won, 'invalid' => $lost];
} catch (Throwable $e) {
    $report['outcomes'] = ['error' => $e->getMessage()];
    \SignalMasterAi\ErrorLog::record(\SignalMasterAi\ErrorLog::SIGNAL,
        'Settlement failed - ' . $e->getMessage());
}

// Plans that have run out.
//
// Nothing anywhere looked for one, so an expired account kept members.tier =
// 'paid' for ever - only the READERS compensated - and kept a watchlist full
// of premium coins it could no longer open, over a cap that had just dropped
// from unlimited to whatever free gets. Every run now flips the ones that are
// due and prunes what they may no longer watch. Cheap: the query is an
// indexed range over a column almost nothing matches.
try {
    $expReport = \SignalMasterAi\Membership::expireDue();
    if ($expReport['expired'] > 0) {
        $report['memberships'] = $expReport;
    }
} catch (Throwable $e) {
    $report['memberships'] = ['error' => $e->getMessage()];
    \SignalMasterAi\ErrorLog::record(\SignalMasterAi\ErrorLog::SIGNAL,
        'Membership expiry sweep failed - ' . $e->getMessage());
}

// Self-learning: once a day, nudge rule weights toward their verified hit
// rates (Admin > Settings > Signal engine to disable; manual button stays in
// the Knowledge base). Only rules with enough clean samples are touched.
if (Database::setting('autotune_enabled', '1') === '1' && Database::claimJob('autotune_last', 86400)) {
    $minS = max(5, (int)Database::setting('autotune_min_samples', '10'));
    $report['autotune'] = ['rules_adjusted' => \SignalMasterAi\Outcomes::autoTuneWeights($minS)];
}

// Performance watchdog: if the recent record as a whole has turned negative,
// bring the recalibration forward rather than waiting for the weekly timer.
// Judged on expectancy - a win-rate trigger would have reported health while
// this engine lost money at a 67% hit rate. Once a day at most.
if (Database::setting('watchdog_enabled', '1') === '1' && Database::claimJob('watchdog_job', 86400)) {
    try {
        $report['watchdog'] = \SignalMasterAi\Outcomes::watchdog();
    } catch (Throwable $e) {
        $report['watchdog'] = ['checked' => false, 'reason' => 'error'];
    }
}

// Announce qualifying flips to the public channels. A channel that posts every
// marginal signal trains its audience to ignore it, so only setups meeting the
// broadcast grade go out, once per pair/timeframe/verdict.
//
// Guarded for the same reason settlement above is, and after this step proved
// it: a broadcast that could not load threw here, cron stopped at this line,
// and every step below - followed-trade settlement, cache expiry, history
// pruning, retention, the digest, the health stamp - silently never ran. One
// optional announcement is not worth the rest of the run.
try {
    if (\SignalMasterAi\Broadcast::enabled()) {
        $report['broadcast'] = ['announced' => \SignalMasterAi\Broadcast::dispatch()];
    }
} catch (Throwable $e) {
    $report['broadcast'] = ['error' => $e->getMessage()];
    \SignalMasterAi\ErrorLog::record(\SignalMasterAi\ErrorLog::SIGNAL,
        'Broadcast failed - ' . $e->getMessage());
}

// Settle open paper positions against the price action that followed, using
// the same conservative walker as the public track record.
if (Database::setting('paper_enabled', '1') === '1') {
    try {
        // Settle first, then mail: a position that closes on this pass should
        // have its close reported on this pass too, not on the next one.
        $report['paper'] = ['closed' => \SignalMasterAi\Paper::settle()];
        try {
            $report['paper']['mailed'] = \SignalMasterAi\TradeMail::sweep();
        } catch (\Throwable $e) {
            \SignalMasterAi\ErrorLog::record(\SignalMasterAi\ErrorLog::ALERTS,
                'Trade receipts failed: ' . $e->getMessage(),
                'Paper trades still settle; the emails about them are not going out.');
        }
    } catch (Throwable $e) {
        $report['paper'] = ['error' => $e->getMessage()];
        \SignalMasterAi\ErrorLog::record(\SignalMasterAi\ErrorLog::SIGNAL,
            'Followed-trade settlement failed - ' . $e->getMessage());
    }
}

// A long measurement, a few steps at a time.
//
// The preset accuracy test is roughly four seconds per coin per timeframe per
// preset, which is twelve minutes for a real comparison - far past what a web
// request survives on shared hosting, and the reason the tools that answer
// "is this configuration any good" were unrunnable. It advances here instead,
// on the cron that runs anyway.
//
// LAST, and on a small slice of its own. Everything above this line is the
// site's actual job: scanning pairs, settling outcomes, sending alerts. A
// measurement is never allowed to eat the budget those need, so it takes what
// is left and stops on a whole step.
if (\SignalMasterAi\LongJob::pending()) {
    try {
        // A GUARANTEED SLICE, NOT THE LEFTOVERS.
        //
        // First attempt gave the job whatever remained of the scan's budget.
        // Measured on this install: a cron run takes 18 seconds of a 20 second
        // budget, so "whatever remains" was 2 - under the cost of a single
        // replay - and the step was skipped on every single run. The
        // measurement would have sat at 0 of 27 forever while reporting
        // itself as merely waiting.
        //
        // So the slice is a floor, not a remainder. It only applies while a
        // job is outstanding, it is bounded, and it is the operator's dial. A
        // cron that runs a few seconds longer during a measurement the
        // operator asked for is the correct trade; a measurement that never
        // advances is not.
        $jobStarted = $_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true);
        $jobSlice = (int)Database::setting('longjob_seconds', '0');
        if ($jobSlice <= 0) {
            $jobSlice = (int)max(6, min(30, $budget * 0.25));
        }
        // Never run past the host's own execution limit - being killed
        // mid-write is the one outcome worse than being slow.
        $hardLimit = (int)ini_get('max_execution_time');
        if ($hardLimit > 0) {
            $usedSoFar = microtime(true) - $jobStarted;
            $jobSlice = (int)min($jobSlice, max(0, $hardLimit * 0.9 - $usedSoFar));
        }
        $report['job'] = $jobSlice >= 4
            ? \SignalMasterAi\LongJob::stepLocked((float)$jobSlice) + ['kind' => (\SignalMasterAi\LongJob::state()['kind'] ?? '')]
            : ['skipped' => 'not enough of the execution limit left in this run'];
    } catch (Throwable $e) {
        $report['job'] = ['error' => $e->getMessage()];
        \SignalMasterAi\ErrorLog::record(\SignalMasterAi\ErrorLog::SYSTEM,
            'Long job step failed - ' . $e->getMessage());
    }
}

// Housekeeping: expire cache rows every run, trim candle/signal history daily.
// Housekeeping that throws must not take the health stamp down with it - a
// cron that stops stamping is read everywhere in the panel as "cron is dead",
// which is a much larger alarm than "the cache sweep failed".
try {
    $report['cache_gc'] = \SignalMasterAi\Cache::gc();
} catch (Throwable $e) {
    $report['cache_gc'] = ['error' => $e->getMessage()];
    \SignalMasterAi\ErrorLog::record(\SignalMasterAi\ErrorLog::SYSTEM,
        'Cache sweep failed - ' . $e->getMessage());
}
if (Database::claimJob('prune_last', 86400)) {
    $report['pruned'] = [
        'candles' => \SignalMasterAi\Maintenance::pruneCandles(),
        'orphan_candles' => \SignalMasterAi\Maintenance::pruneOrphanCandles(),
        'signals' => \SignalMasterAi\Maintenance::pruneSignals(),
        'audit_log' => \SignalMasterAi\Audit::prune(),
        'error_log' => \SignalMasterAi\ErrorLog::prune(),
        // Held alerts nobody could be sent. A day-old setup is a plan that has
        // already expired; delivering a hundred of them the moment mail starts
        // working again is worse than delivering none.
        'alert_queue' => \SignalMasterAi\AlertQueue::prune(),
        'email_log' => \SignalMasterAi\EmailLog::prune(),
        'shadows' => \SignalMasterAi\Maintenance::pruneShadows(),
    ];
    // Re-check whether the server still rewrites. Once a day, and it is the one
    // thing that decides whether the site writes /charts or /charts.php - an
    // operator who changes host should not have to notice that themselves.
    $report['rewrite_ok'] = \SignalMasterAi\Maintenance::rewriteWorks(true);
    // INDEXES THE PLANNER WILL NOT USE ARE INDEXES YOU DO NOT HAVE.
    //
    // Both engines choose a plan from statistics, and this site never gathered
    // any - so the planner worked from guesses about tables it had never
    // measured. Demonstrated on the newest-signals query: with no statistics
    // it sorted the whole ledger through the wrong index; after one ANALYZE it
    // walked the right one and did no sort at all. Once a day, straight after
    // the pruning that changes the shape of every table it measures.
    $report['analyzed'] = \SignalMasterAi\Maintenance::analyze();
}

// Deliver Web Push + email notifications for any BUY/SELL flips detected.
Database::setSetting('last_dispatch', (string)time());
// One channel per attempt, each in its own guard. A rejected SMTP login used
// to take the whole run down with it - and everything below this point with
// it - so a mail server problem quietly became a settlement problem. Now the
// channel that failed is the only thing that fails, and it says so.
if (Database::setting('alerts_enabled', '1') === '1') {
    try {
        [$sent, $removed] = \SignalMasterAi\WebPush::dispatchFlips();
        $report['push'] = ['sent' => $sent, 'dead_subscriptions_removed' => $removed];
    } catch (Throwable $e) {
        $report['push'] = ['error' => $e->getMessage()];
        \SignalMasterAi\ErrorLog::record(\SignalMasterAi\ErrorLog::ALERTS,
            'Browser push delivery failed - ' . $e->getMessage());
    }
    try {
        $report['email'] = ['sent' => \SignalMasterAi\EmailAlerts::dispatchFlips()];
    } catch (Throwable $e) {
        $report['email'] = ['error' => $e->getMessage()];
        \SignalMasterAi\ErrorLog::record(\SignalMasterAi\ErrorLog::ALERTS,
            'Email alert delivery failed - ' . $e->getMessage());
    }
    if (\SignalMasterAi\Telegram::enabled()) {
        try {
            $report['telegram'] = [
                'updates_processed' => \SignalMasterAi\Telegram::poll(),
                'sent' => \SignalMasterAi\Telegram::dispatchFlips(),
            ];
        } catch (Throwable $e) {
            $report['telegram'] = ['error' => $e->getMessage()];
            \SignalMasterAi\ErrorLog::record(\SignalMasterAi\ErrorLog::ALERTS,
                'Telegram delivery failed - ' . $e->getMessage());
        }
    }
}

// Weekly walk-forward calibration: re-run the full historical backtest on the
// admin-chosen timeframes and re-apply weights + per-TF multipliers, so the
// engine keeps re-fitting itself to the market forever with zero clicks.
if (Database::setting('auto_backtest_enabled', '1') === '1'
    && Database::claimJob('auto_backtest_last', 7 * 86400)) {
    $symbols = array_column(
        Database::pdo()->query('SELECT symbol FROM symbols WHERE enabled = 1 ORDER BY symbol')->fetchAll(), 'symbol');
    $minS = max(5, (int)Database::setting('autotune_min_samples', '10'));
    // The site's own list. There is no second list to keep in step with it:
    // a frame the site runs is scanned, alerted on, charted and re-calibrated,
    // because those were never decisions anybody wanted to make separately.
    $tfs = \SignalMasterAi\Visibility::siteTfs($config['market']['intervals']) ?: ['1h'];

    // Bounded and resumed, not run to completion. Backtest::runMany grants
    // itself up to ten minutes for a full market, and this used to call it
    // once per timeframe over EVERY enabled symbol, synchronously, still
    // holding cron's own lock - so once a week, whichever run drew this job
    // could stay busy for minutes on end, and every scheduled tick behind it
    // saw the lock held and stood down: no scanning, no alerts, no
    // settlement, until it finished. A deadline alone would just mean the
    // same first symbols get re-measured every week forever and the rest of
    // the market is never recalibrated - so a resume position is kept too,
    // the same way the coin rotation above remembers its own place. Slower to
    // cover everything on a large watchlist, never stuck on a subset.
    $abWork = [];
    foreach ($tfs as $abTf) {
        foreach ($symbols as $abSym) {
            $abWork[] = [$abTf, $abSym];
        }
    }
    $bt = [];
    if ($abWork) {
        $abTotal = count($abWork);
        $abPos = (int)Database::setting('auto_backtest_pos', '0') % $abTotal;
        $abDeadline = microtime(true) + 40.0;
        $abBatch = [];      // timeframe => [symbols...], in case a slice spans more than one
        $abMoved = 0;
        while ($abMoved < $abTotal && microtime(true) < $abDeadline) {
            [$abTf, $abSym] = $abWork[($abPos + $abMoved) % $abTotal];
            $abBatch[$abTf][] = $abSym;
            $abMoved++;
        }
        Database::setSetting('auto_backtest_pos', (string)(($abPos + $abMoved) % $abTotal));
        foreach ($abBatch as $abTf => $abSymbols) {
            $rep = \SignalMasterAi\Backtest::runMany($abSymbols, $abTf, true, $minS);
            $bt[$abTf] = ['signals' => $rep['signals'], 'winrate' => $rep['winrate'],
                          'weights' => $rep['weights_changed'], 'multipliers' => $rep['tf_multipliers_set'],
                          'symbols_this_run' => count($abSymbols)];
        }
        // What this run actually covered of the full rotation, so a slow
        // cycle is visible rather than assumed - the same reporting idiom the
        // coin rotation above uses for the same reason.
        $bt['coverage'] = ['moved' => $abMoved, 'of' => $abTotal,
                           'full_cycle_runs' => $abMoved > 0 ? (int)ceil($abTotal / $abMoved) : null];
    }
    $report['auto_backtest'] = $bt;
}

// The self-tuning loop. Two steps, in this order and for a reason:
//
//   drift check first - if the change currently in force has failed to
//   deliver, put it back BEFORE proposing anything new. Stacking a second
//   change on top of a broken one makes both unattributable.
//
//   then propose. Reads what the measured record says is weak, turns it into
//   concrete candidates, replays history under each, and adopts at most one
//   clear winner. Weekly, because a change needs time to accumulate the live
//   evidence the next drift check will judge it on.
if (Database::setting('adapt_enabled', '1') === '1') {
    if (Database::claimJob('adapt_drift_last', 86400)) {
        $report['adapt_drift'] = \SignalMasterAi\Adapt::driftCheck(
            max(20, (int)Database::setting('adapt_min_live', '40')));
    }
    if (Database::claimJob('adapt_last', 7 * 86400)) {
        $symbols = array_column(Database::pdo()
            ->query('SELECT symbol FROM symbols WHERE enabled = 1 ORDER BY symbol LIMIT 10')
            ->fetchAll(), 'symbol');
        // Already bounded to 10 symbols and a handful of proposals, so this
        // rarely needs the deadline - it is a ceiling for the rare install
        // where it doesn't, not the normal case. See auto_backtest above for
        // why a synchronous, unbounded weekly job inside this same lock is a
        // problem worth guarding against even when it usually finishes fast.
        $rep = \SignalMasterAi\Adapt::run($symbols, Database::setting('adapt_tf', '1h'), microtime(true) + 20.0);
        $report['adapt'] = ['proposals' => $rep['proposals'],
                            'applied' => $rep['applied']['key'] ?? null,
                            'stopped_early' => $rep['stopped_early'] ?? false];
    }
}

// Daily email digest: yesterday's flips on each member's watched pairs plus
// the site-wide verified stats. Sent once per day after the admin-set hour.
if (Database::setting('digest_enabled') === '1' && \SignalMasterAi\Mailer::enabled()
    && (int)gmdate('G') >= (int)Database::setting('digest_hour', '8')
    && Database::setting('digest_last') !== gmdate('Y-m-d')) {
    Database::setSetting('digest_last', gmdate('Y-m-d'));
    try {
        $report['digest'] = ['sent' => \SignalMasterAi\EmailAlerts::sendDigests()];
    } catch (Throwable $e) {
        $report['digest'] = ['error' => $e->getMessage()];
        \SignalMasterAi\ErrorLog::record(\SignalMasterAi\ErrorLog::ALERTS,
            'Daily digest failed - ' . $e->getMessage());
    }
}

// Health stamp: the admin panel warns when this stops updating.
//
// The gap since the previous run is recorded too, so nobody has to be asked
// how often their host runs this. The schedule is set in a hosting panel the
// app cannot see, an answer typed into a settings field goes stale the moment
// it is changed there, and the one thing that always knows is the file
// itself. The last few gaps are kept and the middle one used, so a single
// missed run - a reboot, a busy minute - cannot pass itself off as the
// schedule. Gaps outside a plausible range are not recorded at all.
$prev = (int)Database::setting('cron_last_run', '0');
$nowRun = time();
if ($prev > 0) {
    $gap = $nowRun - $prev;
    if ($gap >= 30 && $gap <= 6 * 3600) {
        $gaps = array_values(array_filter(array_map('intval',
            explode(',', Database::setting('cron_gaps', '')))));
        $gaps[] = $gap;
        $gaps = array_slice($gaps, -5);
        Database::setSetting('cron_gaps', implode(',', $gaps));
        $sorted = $gaps;
        sort($sorted);
        Database::setSetting('cron_interval_seen', (string)$sorted[intdiv(count($sorted), 2)]);
    }
}
Database::setSetting('cron_last_run', (string)$nowRun);

// A readable report.
//
// One line per analysed pair was fine for a handful of watched charts. On a
// rotation of several hundred it is thousands of words of "NEUTRAL (3.42)"
// mailed out every ten minutes, and the one line that matters - a coin that
// errored - is buried somewhere in the middle of it. Above a threshold the
// per-pair list collapses to counts, and the errors are listed in full,
// because those are the only entries anyone acts on. Set cron_verbose = 1 to
// get every line back.
$verbose = Database::setting('cron_verbose', '0') === '1';
if (!$verbose && count($report['market']) > 40) {
    $tally = ['BUY' => 0, 'SELL' => 0, 'NEUTRAL' => 0];
    $errors = [];
    foreach ($report['market'] as $pair => $line) {
        if (str_starts_with($line, 'error: ')) {
            $errors[$pair] = substr($line, 7);
            continue;
        }
        $verdict = strtok($line, ' ');
        $tally[$verdict] = ($tally[$verdict] ?? 0) + 1;
    }
    $report['market'] = [
        'analysed' => array_sum($tally) + count($errors),
        'buy' => $tally['BUY'], 'sell' => $tally['SELL'], 'neutral' => $tally['NEUTRAL'],
        'errors' => count($errors),
        'error_detail' => array_slice($errors, 0, 25, true),
    ];
    if (count($errors) > 25) {
        $report['market']['error_detail']['...'] = (count($errors) - 25) . ' more';
    }
}

echo json_encode(['ok' => true, 'refreshed' => $report, 'at' => gmdate('c')]),
     $isWebRequest ? '' : PHP_EOL;
