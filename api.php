<?php
declare(strict_types=1);

/**
 * Public JSON API.
 *   api.php?action=candles&symbol=BTCUSDT&tf=1h   -> cached OHLCV series
 *   api.php?action=signal&symbol=BTCUSDT&tf=1h    -> fresh signal + analysis
 *   api.php?action=symbols                        -> enabled watchlist
 */

$config = require __DIR__ . '/src/bootstrap.php';

use SignalMasterAi\Database;
use SignalMasterAi\MarketData;
use SignalMasterAi\MemberAuth;
use SignalMasterAi\NewsFetcher;
use SignalMasterAi\SignalEngine;
use SignalMasterAi\WebPush;

MemberAuth::start();
// THE POLL MUST NOT HOLD THE SESSION.
//
// This endpoint is what the dashboard calls every few seconds, and some of its
// actions run the engine and fetch candles - so it is the request most likely
// to be slow, and it was holding the member's session lock the whole time.
// While it did, every other page that member opened waited at session_start(),
// including checkout. Identity has been read by now and nothing here writes to
// $_SESSION, so the lock goes back immediately.
MemberAuth::releaseSession();
header('Content-Type: application/json; charset=utf-8');

function respond(array $data, int $code = 200): never
{
    http_response_code($code);
    echo json_encode($data);
    exit;
}

/**
 * CSRF guard for the JSON endpoints: cross-origin no-cors requests cannot
 * carry an application/json content type, so requiring it blocks forged
 * cross-site writes while same-origin fetch() calls pass untouched.
 */
function requireJsonRequest(): void
{
    $ct = strtolower((string)($_SERVER['CONTENT_TYPE'] ?? ''));
    if (!str_starts_with($ct, 'application/json')) {
        respond(['ok' => false, 'error' => 'JSON content type required'], 415);
    }
}

/**
 * Is a real background worker running? `cron_last_run` is stamped by cron.php;
 * anything fresher than 15 minutes means expensive maintenance belongs there,
 * not inside a visitor's page load.
 */
function cronIsHealthy(): bool
{
    $last = (int)Database::setting('cron_last_run', '0');
    return $last > 0 && time() - $last < 900;
}

/**
 * How many watched pairs one member may hold.
 *
 * PAIRS, not coins - and that distinction was doing real damage. "Watch all my
 * coins" multiplies the coin list by every ticked timeframe, so at a cap of 60
 * an operator with three frames ticked got twenty coins and a button that
 * stopped silently. The cap is now whatever the operator sets, 0 meaning no
 * limit, and the button says what it skipped instead of just stopping.
 */
function alertPairCap(): int
{
    // Per tier, and defined once - see Watchlists::cap(). This used to return
    // a literal 12 for anyone below the bulk-watch tier while the watchlist
    // itself truncated at bulk_watch_max, so a free member's list was cut in
    // one place at 12 and in another at whatever the operator had set, and
    // neither number appeared in the panel.
    return \SignalMasterAi\Watchlists::cap();
}

/**
 * Cron-less fallback: piggyback alert dispatch (push + email) on normal site
 * traffic, throttled to the auto-update interval. A real cron job is still
 * recommended for quiet sites - this keeps alerts flowing without one.
 */
function maybeDispatchAlerts(): void
{
    // The alerts master, and the signals master above it: a call that should
    // not have been produced must not be delivered either.
    if (!\SignalMasterAi\Master::on('alerts') || !\SignalMasterAi\Master::on('signals', 'alerts')) {
        return;
    }
    $interval = max(60, (int)Database::setting('signal_auto_seconds', '60'));
    // Atomic claim: exactly one concurrent request wins the slot. The old
    // read-compare-write let two simultaneous visitors both run the full
    // push + email + Telegram sweep (and both send duplicate alerts).
    if (!Database::claimJob('last_dispatch', $interval)) {
        return;
    }
    // WHAT FAILS HERE HAS TO BE WRITTEN DOWN.
    //
    // Five jobs run in this block on an install with no cron job, and all five
    // swallowed their exceptions into an empty catch. That is the right shape -
    // a broken settler must not take a visitor's page load down with it - and
    // the wrong ending: the site has an error log built for exactly this, and
    // none of these five ever reached it. An install whose alerts stop
    // dispatching looked, from the panel, exactly like an install with nothing
    // to send. Reported and still swallowed, which is both halves of the job.
    try {
        \SignalMasterAi\Outcomes::evaluate(100);
    } catch (Throwable $e) {
        \SignalMasterAi\ErrorLog::record(\SignalMasterAi\ErrorLog::SIGNAL,
            'Settling signals failed on the web fallback: ' . $e->getMessage(),
            'This runs on visitor traffic when no cron job is set up. Outcomes will '
            . 'not be recorded until it succeeds.');
    }
    // Self-learning piggybacks on traffic ONLY when no healthy cron job is
    // running. Auto-tuning and backtesting take seconds to minutes; making an
    // unlucky visitor wait for them inside their HTTP request is never right
    // when a background worker is already handling it.
    try {
        if (Database::setting('autotune_enabled', '1') === '1' && !cronIsHealthy()
            && Database::claimJob('autotune_last', 86400)) {
            \SignalMasterAi\Outcomes::autoTuneWeights(max(5, (int)Database::setting('autotune_min_samples', '10')));
        }
    } catch (Throwable $e) {
        \SignalMasterAi\ErrorLog::record(\SignalMasterAi\ErrorLog::SIGNAL,
            'Auto-tuning failed on the web fallback: ' . $e->getMessage(),
            'Rule weights will keep their current values until it succeeds.');
    }
    try {
        if (\SignalMasterAi\Master::on('alerts', 'push')) {
            \SignalMasterAi\WebPush::dispatchFlips();
        }
    } catch (Throwable $e) {
        \SignalMasterAi\ErrorLog::record(\SignalMasterAi\ErrorLog::ALERTS,
            'Browser push dispatch failed on the web fallback: ' . $e->getMessage(),
            'Push alerts are not going out. This runs on visitor traffic when no cron job is set up.');
    }
    // Trade receipts ride the same fallback as the alerts, for the same
    // reason: on an install with no cron job this is the only thing that runs.
    try {
        \SignalMasterAi\TradeMail::sweep(10);
    } catch (Throwable $e) {
        \SignalMasterAi\ErrorLog::record(\SignalMasterAi\ErrorLog::ALERTS,
            'Trade receipts failed on the web fallback: ' . $e->getMessage(),
            'Members are not being told when their paper trades open or close.');
    }
    try {
        if (\SignalMasterAi\Master::on('alerts', 'email')) {
            \SignalMasterAi\EmailAlerts::dispatchFlips();
        }
    } catch (Throwable $e) {
        \SignalMasterAi\ErrorLog::record(\SignalMasterAi\ErrorLog::ALERTS,
            'Email alert dispatch failed on the web fallback: ' . $e->getMessage(),
            'Email alerts are not going out. This runs on visitor traffic when no cron job is set up.');
    }
    try {
        if (\SignalMasterAi\Master::on('alerts', 'telegram') && \SignalMasterAi\Telegram::enabled()) {
            \SignalMasterAi\Telegram::poll();
            \SignalMasterAi\Telegram::dispatchFlips();
        }
    } catch (Throwable $e) {
        \SignalMasterAi\ErrorLog::record(\SignalMasterAi\ErrorLog::ALERTS,
            'Telegram dispatch failed on the web fallback: ' . $e->getMessage(),
            'Telegram alerts are not going out. This runs on visitor traffic when no cron job is set up.');
    }
}

$action = $_GET['action'] ?? '';

// Per-client rate limit before any work is done. The site's own polling stays
// well inside the budget; scrapers hammering the analysis endpoint do not.
[$rlOk, $rlRetry] = \SignalMasterAi\RateLimit::check(is_string($action) ? $action : 'default');
if (!$rlOk) {
    header('Retry-After: ' . $rlRetry);
    respond(['ok' => false, 'error' => 'Rate limit exceeded - slow down and retry shortly.',
             'retry_after' => $rlRetry], 429);
}

$symbol = strtoupper(trim((string)($_GET['symbol'] ?? '')));
$tf     = trim((string)($_GET['tf'] ?? ''));
if ($tf === '' || !in_array($tf, $config['market']['intervals'], true)) {
    $tf = Database::setting('default_interval', $config['market']['default_interval']);
}

/**
 * Tier gate: the symbol must exist, be enabled, be visible on the chart
 * surface, and be within the viewer's tier (guest < free < paid).
 *
 * This variant throws instead of ending the
 * response - the batch ticker checks many symbols and one failure should skip
 * that pair, not abort every other price in the request.
 */
function requireSymbolAccessQuiet(string $symbol): void
{
    $stmt = Database::pdo()->prepare('SELECT * FROM symbols WHERE symbol = ? AND enabled = 1'
        . \SignalMasterAi\Visibility::sql('chart', 'symbols'));
    $stmt->execute([$symbol]);
    $row = $stmt->fetch();
    $stmt->closeCursor();
    if (!$row || !MemberAuth::canAccess($row['tier'])) {
        throw new RuntimeException('no access');
    }
}

function requireSymbolAccess(string $symbol): array
{
    $stmt = Database::pdo()->prepare('SELECT * FROM symbols WHERE symbol = ? AND enabled = 1'
        . \SignalMasterAi\Visibility::sql('chart', 'symbols'));
    $stmt->execute([$symbol]);
    $row = $stmt->fetch();
    $stmt->closeCursor();
    if (!$row) {
        respond(['ok' => false, 'error' => 'Unknown or disabled symbol'], 404);
    }
    if (!MemberAuth::canAccess($row['tier'])) {
        respond([
            'ok' => false,
            'error' => $row['tier'] === 'paid'
                ? 'This coin is for premium members. Upgrade your account to unlock it.'
                : 'This coin is for registered members. Create a free account to unlock it.',
            'locked' => $row['tier'],
        ], 403);
    }
    return $row;
}

/**
 * The coin AND the timeframe. A coin can be read on a subset of the site's
 * timeframes, and an endpoint that checks only the coin would happily serve a
 * 5m analysis for a pair the operator has restricted to the daily - past the
 * chart, past the scanner, past everything that respects the setting.
 */
function requireSymbolTf(string $symbol, string $tf): array
{
    global $config;
    $row = requireSymbolAccess($symbol);
    $site = \SignalMasterAi\Visibility::siteTfs($config['market']['intervals']);
    if (!\SignalMasterAi\Visibility::allowsTf($symbol, $tf, $site)) {
        respond(['ok' => false, 'error' => 'This coin is not read on that timeframe',
                 'timeframes' => \SignalMasterAi\Visibility::tfsFor($symbol, $site)], 404);
    }
    return $row;
}

try {
    switch ($action) {
        case 'promo_dismiss': {
            // Remembered separately from the upgrade prompt - see the note on
            // the promo_dismissed column.
            //
            // Guarded like every other write here. Both dismiss endpoints
            // shipped without it and took a plain GET, so an <img src> on any
            // page silenced the offer for every logged-in visitor who loaded
            // it. The damage is small - a prompt nobody sees - but "small
            // damage" is not the test; a cross-site request must not reach a
            // write at all, and this file already has the guard.
            requireJsonRequest();
            $m = MemberAuth::current();
            if ($m) {
                Database::pdo()->prepare('UPDATE members SET promo_dismissed = ? WHERE id = ?')
                    ->execute([time(), (int)$m['id']]);
            }
            // A signed-out visitor has no row to write to, so the answer is a
            // cookie. Without it the popup returned on every single page view
            // with no way to stop it - an unclosable interruption on a public
            // site, which is worse than never showing the offer at all.
            setcookie('sma_promo_off', (string)time(), [
                'expires'  => time() + 400 * 86400,
                'path'     => '/',
                'httponly' => false,
                'samesite' => 'Lax',
            ]);
            respond(['ok' => true]);
        }

        case 'upsell_dismiss': {
            requireJsonRequest();
            // Remember that this member closed the upgrade prompt. Server-side
            // rather than in localStorage: a prompt that reappears the moment
            // somebody opens the site on their phone is a prompt that gets
            // reported as a bug, and rightly.
            $m = MemberAuth::current();
            if (!$m) {
                respond(['ok' => true]);       // nothing to remember, not an error
            }
            Database::pdo()->prepare('UPDATE members SET upsell_dismissed = ? WHERE id = ?')
                ->execute([time(), (int)$m['id']]);
            respond(['ok' => true]);
        }

        case 'top_coins': {
            // The coins with the best verified record, for "watch the winners".
            //
            // Gated server-side as well as in the page that draws the button.
            // A tier check that lives only in the markup is not a tier check -
            // the endpoint is a URL, and the whole point of the gate is that
            // this is something premium buys.
            if (Database::setting('top_watch_enabled', '1') !== '1') {
                respond(['ok' => false, 'error' => 'Not enabled on this site'], 403);
            }
            if (!MemberAuth::meetsTier(Database::setting('top_watch_tier', 'paid'))) {
                respond(['ok' => false, 'error' => 'Premium members only', 'upgrade' => true], 403);
            }
            $by = ($_GET['by'] ?? 'winrate') === 'return' ? 'return' : 'winrate';
            $tier = MemberAuth::tier();
            $rows = \SignalMasterAi\TrackRecord::topCoins(
                $by,
                // Asked for more than the operator offers is still bounded by
                // the operator's number - the client cannot widen its own gate.
                min((int)($_GET['limit'] ?? 0) ?: (int)Database::setting('top_watch_count', '10'),
                    (int)Database::setting('top_watch_count', '10')),
                (int)Database::setting('top_watch_min', '10')
            );
            // Coins sold above this member's tier are dropped rather than
            // returned locked: this list exists to be watched, and offering a
            // coin the save would then refuse is worse than a shorter list.
            $tierStmt = Database::pdo()->prepare(
                'SELECT tier FROM symbols WHERE symbol = ? AND enabled = 1'
                . \SignalMasterAi\Visibility::sql('alerts', 'symbols'));
            $out = [];
            foreach ($rows as $r) {
                $tierStmt->execute([$r['symbol']]);
                $st = $tierStmt->fetchColumn();
                if ($st !== false && MemberAuth::canAccess((string)$st, $tier)) {
                    $out[] = $r;
                }
            }
            respond(['ok' => true, 'by' => $by, 'coins' => $out,
                     'min_settled' => (int)Database::setting('top_watch_min', '10')]);
        }

        case 'symbols': {
            $tier = MemberAuth::tier();
            $rows = Database::pdo()
                ->query("SELECT symbol, label, tier FROM symbols WHERE enabled = 1"
                       . \SignalMasterAi\Visibility::sql('chart', 'symbols') . " ORDER BY symbol")
                ->fetchAll();
            foreach ($rows as &$r) {
                $r['locked'] = !MemberAuth::canAccess($r['tier'], $tier);
            }
            respond(['ok' => true, 'viewer_tier' => $tier, 'symbols' => $rows]);
        }

        case 'candles': {
            requireSymbolTf($symbol, $tf);
            $md = new MarketData($config);
            $candles = $md->candles($symbol, $tf);
            // Tick size travels with the series so the client can format prices
            // at the precision the exchange actually quotes, instead of
            // guessing from magnitude and rounding a $2.4567 level to "2.46".
            respond([
                'ok' => true, 'symbol' => $symbol, 'tf' => $tf, 'candles' => $candles,
                'tick_size' => $md->tickSize($symbol),
            ]);
        }

        case 'signal': {
            // The signals master. Off means the engine answers nothing here,
            // whatever the coin's own switches say.
            if (!\SignalMasterAi\Master::on('signals', 'chart')) {
                respond(['ok' => false, 'error' => 'Signals are switched off on this site',
                         'master_off' => true], 503);
            }
            requireSymbolTf($symbol, $tf);
            maybeDispatchAlerts();
            $md = new MarketData($config);
            $candles = $md->candles($symbol, $tf);

            // ANALYSED, NEVER STORED.
            //
            // Opening a coin runs the full engine, so a visitor always sees a
            // current reading and can trade whatever setup it finds. What it
            // does NOT do is write: no ledger row, no state row, and therefore
            // no alert - because the alert dispatchers read the state row, and
            // that is only ever written by cron.
            //
            // The reason is not cost. A stored call is a PUBLISHED call: it
            // enters the history, the scanner board, the public track record
            // and other people's inboxes. Whether that happens should depend on
            // the market, not on whether somebody happened to open a chart -
            // otherwise the record fills up on the coins that get looked at,
            // the win rate measures browsing habits, and a stranger's page load
            // sends mail to every member watching that pair. The background
            // scan decides what the site says; this decides what one reader
            // sees right now.
            $engine = (new SignalEngine())->withMarketData($md)->suppressStore();

            // A member running a non-default strategy profile gets their own
            // thresholds applied to that reading. Same rule, for the same
            // reason plus one more: the shared history must reflect the site's
            // engine rather than one member's settings.
            $memberId = MemberAuth::currentId();
            $profile = $memberId > 0 ? \SignalMasterAi\MemberPrefs::engineProfile($memberId) : [];
            if ($profile) {
                $engine->withProfile($profile);
            }
            $result = $engine->analyse($symbol, $tf, $candles);
            $result['personalised'] = $profile !== [];

            // The publish floor. The analysis is stored either way - the
            // engine has to keep learning from setups the site does not show,
            // or the track record measures only the setups that were easy.
            // What changes is what the reader is told: a call below the floor
            // is presented as "no published call", not as a weaker BUY, and
            // the reason is stated rather than the verdict silently going
            // quiet.
            if (!\SignalMasterAi\Publish::allows((string)($result['grade'] ?? ''))
                && ($result['signal'] ?? 'NEUTRAL') !== 'NEUTRAL') {
                // The verdict changes; the reason does not travel with it.
                //
                // This shipped the grade, the floor and a sentence naming both
                // to every caller. Nothing on the site rendered it, so its only
                // reader was somebody with the network tab open - who learned
                // the exact setting deciding what they are not shown. A call
                // below the floor reads as no call, which is the whole point;
                // the operator can see what the floor costs on the dashboard.
                $result['withheld'] = true;
                $result['signal'] = 'NEUTRAL';
                $result['levels'] = null;
            }

            // Recent signal changes for this pair (the audit trail is
            // store-on-change, so each row is a real flip).
            $hist = Database::pdo()->prepare(
                'SELECT `signal`, score, created_at, outcome, outcome_note FROM signals
                 WHERE symbol = ? AND tf = ? ORDER BY created_at DESC LIMIT 12'
            );
            $hist->execute([$symbol, $tf]);
            // Collapse consecutive same-verdict rows (pre-existing installs
            // stored score wiggles too) so the chips read as real flips.
            $flips = [];
            foreach ($hist->fetchAll() as $h) {
                if ($flips && end($flips)['signal'] === $h['signal']) {
                    continue;
                }
                $flips[] = [
                    'signal'  => $h['signal'],
                    'score'   => (float)$h['score'],
                    'at'      => (int)$h['created_at'],
                    'outcome' => (string)$h['outcome'],
                    'note'    => (string)$h['outcome_note'],
                ];
                if (count($flips) === 3) {
                    break;
                }
            }
            $result['history'] = $flips;
            $result['stats'] = \SignalMasterAi\Outcomes::stats($symbol, $tf);

            // THE PUBLISHED CALL'S REFERENCE.
            //
            // The reading above is live and deliberately unstored, so it has
            // no reference of its own - references belong to calls the site
            // actually published. What this can honestly say is which
            // published call the reading agrees with: the pair's current
            // stored verdict, and only while it is still the same side. A
            // reference printed beside a verdict that has since flipped would
            // point at a different call from the one on the screen, which is
            // worse than printing nothing.
            $result['ref'] = null;
            try {
                $rq = Database::pdo()->prepare(
                    'SELECT s.ref, s.`signal` FROM signal_state st
                       JOIN signals s ON s.id = st.signal_id
                      WHERE st.symbol = ? AND st.tf = ? LIMIT 1'
                );
                $rq->execute([$symbol, $tf]);
                $pub = $rq->fetch();
                $live = (string)($result['signal'] ?? 'NEUTRAL');
                if ($pub && (string)($pub['ref'] ?? '') !== ''
                    && (string)$pub['signal'] === $live
                    && $live !== 'NEUTRAL' && empty($result['withheld'])) {
                    $result['ref'] = (string)$pub['ref'];
                }
            } catch (\Throwable $e) {
                $result['ref'] = null;   // a reference is a nicety, never a failure
            }

            // Optional plain-English summary. Cached per verdict, so a page
            // refreshing every 60s does not bill per refresh, and entirely
            // absent when the operator has not configured a provider.
            try {
                $result['explanation'] = \SignalMasterAi\Explain::forSignal($result);
            } catch (Throwable $e) {
                $result['explanation'] = null;
            }
            respond(['ok' => true] + $result);
        }

        case 'alerts': {
            // Latest signal per watched pair, for browser notifications.
            // pairs=BTCUSDT:1h,ETHUSDT:4h (viewer-dependent cap). Pairs outside the viewer's
            // tier are skipped.
            //
            // READ ONLY, and from the state row.
            //
            // Two faults, one fix. It re-analysed up to three stale pairs per
            // call, which was how a pair with no recent verdict got one - and
            // once the visitor side stopped storing, that analysis cleared
            // nothing, so every poll re-analysed the same three pairs for ever.
            // At a sixty-second poll that is three full passes of the rule set
            // a minute, per open browser tab, producing a value that is thrown
            // away. Cron reads watched pairs first and most often; there is
            // nothing here for a visitor's browser to add.
            //
            // And it read the `signals` ledger, which holds CHANGES - and holds
            // no NEUTRAL at all under the default setting. So a pair that
            // flipped to NEUTRAL hours ago kept being reported as a live call
            // to the one feed whose job is to raise browser notifications. The
            // current verdict lives in signal_state, which is where every other
            // reader of "what is this pair right now" looks.
            $pairs = array_slice(array_filter(array_map('trim', explode(',', (string)($_GET['pairs'] ?? '')))), 0, alertPairCap());
            $tier = MemberAuth::tier();
            $pdo = Database::pdo();
            $symStmt = $pdo->prepare('SELECT * FROM symbols WHERE symbol = ? AND enabled = 1'
                . \SignalMasterAi\Visibility::sql('scanner', 'symbols'));
            $sigStmt = $pdo->prepare(
                'SELECT `signal`, score, confidence, price, changed_at AS created_at
                 FROM signal_state WHERE symbol = ? AND tf = ?'
            );
            maybeDispatchAlerts();
            $out = [];
            foreach ($pairs as $pair) {
                [$ps, $ptf] = array_pad(explode(':', $pair, 2), 2, '');
                $ps = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $ps) ?? '');
                if ($ps === '' || !in_array($ptf, $config['market']['intervals'], true)) {
                    continue;
                }
                $symStmt->execute([$ps]);
                $srow = $symStmt->fetch();
                $symStmt->closeCursor();
                if (!$srow || !MemberAuth::canAccess($srow['tier'], $tier)) {
                    continue;
                }
                $sigStmt->execute([$ps, $ptf]);
                $sig = $sigStmt->fetch();
                $sigStmt->closeCursor();
                // No row means the scan has not reached this pair yet. Left out
                // rather than invented: this feed drives browser notifications,
                // and a made-up quiet reading is a notification that never
                // fires for a setup nobody checked.
                if ($sig) {
                    $out[] = [
                        'symbol' => $ps, 'tf' => $ptf,
                        'signal' => $sig['signal'], 'score' => (float)$sig['score'],
                        'confidence' => (float)$sig['confidence'], 'price' => (float)$sig['price'],
                        'at' => (int)$sig['created_at'],
                    ];
                }
            }
            respond(['ok' => true, 'alerts' => $out, 'now' => time()]);
        }

        case 'ticker': {
            // Batch form: the portfolio marks several open positions to market
            // at once, and one request per position would be a burst of calls
            // every refresh. Capped, and each symbol still passes the same
            // access check it would on its own.
            $many = trim((string)($_GET['symbols'] ?? ''));
            if ($many !== '') {
                $wanted = array_slice(array_unique(array_filter(
                    array_map(fn($s) => strtoupper(trim($s)), explode(',', $many))
                )), 0, 25);
                $prices = [];
                $md = new MarketData($config);
                foreach ($wanted as $sym) {
                    // Access checked BEFORE the cache is consulted, same
                    // order as the single-symbol path below - Cache is
                    // table-backed and shared across every request/user, so
                    // checking it first meant any authorized read of a
                    // premium coin (a paying member's own portfolio, or the
                    // single-symbol endpoint two lines down) left a 5-second
                    // window where this batch endpoint handed that price to
                    // anyone, gate skipped entirely, on a cache hit.
                    try {
                        requireSymbolAccessQuiet($sym);
                    } catch (Throwable $e) {
                        continue;   // one unauthorized/unavailable pair must not blank the whole set
                    }
                    $cached = \SignalMasterAi\Cache::get('tick:' . $sym);
                    if (is_numeric($cached)) {
                        $prices[$sym] = (float)$cached;
                        continue;
                    }
                    try {
                        $price = $md->lastPrice($sym);
                        \SignalMasterAi\Cache::set('tick:' . $sym, $price, 5);
                        $prices[$sym] = $price;
                    } catch (Throwable $e) {
                        // One unavailable pair must not blank the whole set.
                    }
                }
                respond(['ok' => true, 'prices' => $prices]);
            }
            // Live last price with a short server-side micro-cache (5 s), so
            // the chart can tick in near-real-time without hammering the API.
            requireSymbolAccess($symbol);
            $cached = \SignalMasterAi\Cache::get('tick:' . $symbol);
            if (is_numeric($cached)) {
                respond(['ok' => true, 'symbol' => $symbol, 'price' => (float)$cached, 'cached' => true]);
            }
            try {
                $md = new MarketData($config);
                $price = $md->lastPrice($symbol);   // provider-aware, auto-failover
                \SignalMasterAi\Cache::set('tick:' . $symbol, $price, 5);
                respond(['ok' => true, 'symbol' => $symbol, 'price' => $price, 'cached' => false]);
            } catch (Throwable $e) {
                respond(['ok' => false, 'error' => 'Ticker unavailable'], 502);
            }
        }

        case 'mtf': {
            // One verdict per timeframe for this pair, so a reader can see at a
            // glance whether the call they are looking at is the odd one out.
            // A 5m BUY under a falling daily is a different trade from a 5m BUY
            // with everything above it agreeing, and the signal card alone
            // cannot show that.
            //
            // Served from what has already been analysed rather than by running
            // the engine ten more times: a fresh pass per timeframe on every
            // chart view is exactly the sort of thing that makes a site
            // unusable on shared hosting. Timeframes with nothing stored are
            // reported as unknown rather than as neutral.
            requireSymbolAccess($symbol);
            // Only the frames this coin is actually read on - an agreement
            // panel listing timeframes the engine never looks at would report
            // "unknown" forever and read as a gap in the data.
            $wanted = \SignalMasterAi\Visibility::tfsFor($symbol, array_values(array_intersect(
                $config['market']['intervals'],
                array_map('trim', explode(',', sma_setting('enabled_intervals',
                    implode(',', $config['market']['intervals']))))
            )) ?: $config['market']['intervals']);
            $stmt = Database::pdo()->prepare(
                "SELECT `signal`, score, confidence, created_at FROM signals
                 WHERE symbol = ? AND tf = ? ORDER BY created_at DESC LIMIT 1"
            );
            $rows = [];
            foreach ($wanted as $iv) {
                $stmt->execute([$symbol, $iv]);
                $r = $stmt->fetch();
                $stmt->closeCursor();
                $rows[] = [
                    'tf'      => $iv,
                    'signal'  => $r ? (string)$r['signal'] : null,
                    'score'   => $r ? round((float)$r['score'], 2) : null,
                    'age_min' => $r ? (int)floor((time() - (int)$r['created_at']) / 60) : null,
                ];
            }
            respond(['ok' => true, 'symbol' => $symbol, 'frames' => $rows]);
        }

        case 'context': {
            // Market context: the reads that describe the market this pair is
            // trading in, rather than the pair itself. Every one of them is
            // already an engine input - this endpoint just shows the reader
            // the same numbers the engine scored, so a verdict is not a number
            // out of nowhere.
            //
            // Every field is independently null-graceful: futures endpoints
            // are geo-blocked in some regions and spot-only coins have no
            // derivatives at all, and a missing figure must read as "no data"
            // rather than blanking the panel or, worse, as a zero.
            requireSymbolTf($symbol, $tf);
            $out = ['ok' => true, 'symbol' => $symbol, 'tf' => $tf];
            $md = new MarketData($config);
            $try = static function (callable $fn) {
                try {
                    return $fn();
                } catch (Throwable $e) {
                    return null;
                }
            };
            $out['fng']       = $try(fn() => SignalEngine::fearGreed());
            $out['fng_trend'] = $try(fn() => SignalEngine::fearGreedTrend());
            $out['funding']   = sma_setting('funding_enabled', '1') === '1'
                ? $try(fn() => $md->fundingRate($symbol)) : null;
            $out['long_short'] = $try(fn() => $md->longShortRatio($symbol));
            $out['oi_change']  = $try(fn() => $md->openInterestChange($symbol, $tf));
            $out['taker']      = sma_setting('taker_enabled', '1') === '1'
                ? $try(fn() => $md->takerRatio($symbol, $tf)) : null;
            $out['breadth']    = sma_setting('breadth_enabled', '1') === '1'
                ? $try(fn() => $md->breadth($tf)) : null;
            respond($out);
        }

        case 'alert_email': {
            // Per-member email alert preference + watched pairs (server-side).
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                respond(['ok' => false, 'error' => 'POST required'], 405);
            }
            requireJsonRequest();
            $member = MemberAuth::current();
            if (!$member) {
                respond(['ok' => false, 'error' => 'Log in to enable email alerts', 'login' => true], 401);
            }
            $body = json_decode(file_get_contents('php://input') ?: '', true);
            if (!is_array($body)) {
                respond(['ok' => false, 'error' => 'Invalid JSON'], 400);
            }
            $tier = MemberAuth::tier();
            $clean = [];
            $symStmt = Database::pdo()->prepare('SELECT tier FROM symbols WHERE symbol = ? AND enabled = 1'
                . \SignalMasterAi\Visibility::sql('alerts', 'symbols'));
            // Normalise and de-duplicate BEFORE the cap and before touching
            // the database.
            //
            // The cap counted entries, not distinct pairs, and nothing
            // collapsed repeats - so twenty thousand copies of one real pair
            // all passed validation and were stored, 220 KB in a single
            // column, after twenty thousand identical lookups of the same
            // symbol. Worse than the waste: the dispatcher reads only the
            // first pairs of this list, so duplicates filling those slots
            // mean the coins a member actually watches never alert. Watched
            // lists already de-duplicate; these two did not.
            $seen = [];
            foreach ((array)($body['pairs'] ?? []) as $pair) {
                if (!is_string($pair)) {
                    continue;
                }
                [$ps, $ptf] = array_pad(explode(':', $pair, 2), 2, '');
                $ps = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $ps) ?? '');
                if ($ps === '' || !in_array($ptf, $config['market']['intervals'], true)) {
                    continue;
                }
                $seen["$ps:$ptf"] = $ps;
            }
            foreach (array_slice($seen, 0, alertPairCap(), true) as $key => $ps) {
                $symStmt->execute([$ps]);
                $st = $symStmt->fetchColumn();
                if ($st !== false && MemberAuth::canAccess((string)$st, $tier)) {
                    $clean[] = $key;
                }
            }
            $enabled = !empty($body['enabled']) ? 1 : 0;
            $pdo = Database::pdo();
            $stmt = $pdo->prepare('UPDATE member_alerts SET enabled = ?, pairs = ? WHERE member_id = ?');
            $stmt->execute([$enabled, implode(',', $clean), $member['id']]);
            if ($stmt->rowCount() === 0) {
                $pdo->prepare('INSERT INTO member_alerts (member_id, enabled, pairs, last_sent) VALUES (?,?,?,?)')
                    ->execute([$member['id'], $enabled, implode(',', $clean), '{}']);
            }
            respond(['ok' => true, 'enabled' => (bool)$enabled, 'pairs' => $clean,
                     'email_configured' => \SignalMasterAi\Mailer::enabled()]);
        }

        case 'push_subscribe':
        case 'push_sync':
        case 'push_unsubscribe': {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                respond(['ok' => false, 'error' => 'POST required'], 405);
            }
            requireJsonRequest();
            $body = json_decode(file_get_contents('php://input') ?: '', true);
            if (!is_array($body)) {
                respond(['ok' => false, 'error' => 'Invalid JSON'], 400);
            }

            if ($action === 'push_unsubscribe') {
                WebPush::removeSubscription((string)($body['endpoint'] ?? ''));
                respond(['ok' => true]);
            }

            // Validate the watched pairs against the viewer's tier.
            $tier = MemberAuth::tier();
            $clean = [];
            $symStmt = Database::pdo()->prepare('SELECT tier FROM symbols WHERE symbol = ? AND enabled = 1'
                . \SignalMasterAi\Visibility::sql('alerts', 'symbols'));
            // Normalise and de-duplicate BEFORE the cap and before touching
            // the database.
            //
            // The cap counted entries, not distinct pairs, and nothing
            // collapsed repeats - so twenty thousand copies of one real pair
            // all passed validation and were stored, 220 KB in a single
            // column, after twenty thousand identical lookups of the same
            // symbol. Worse than the waste: the dispatcher reads only the
            // first pairs of this list, so duplicates filling those slots
            // mean the coins a member actually watches never alert. Watched
            // lists already de-duplicate; these two did not.
            $seen = [];
            foreach ((array)($body['pairs'] ?? []) as $pair) {
                if (!is_string($pair)) {
                    continue;
                }
                [$ps, $ptf] = array_pad(explode(':', $pair, 2), 2, '');
                $ps = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $ps) ?? '');
                if ($ps === '' || !in_array($ptf, $config['market']['intervals'], true)) {
                    continue;
                }
                $seen["$ps:$ptf"] = $ps;
            }
            foreach (array_slice($seen, 0, alertPairCap(), true) as $key => $ps) {
                $symStmt->execute([$ps]);
                $st = $symStmt->fetchColumn();
                if ($st !== false && MemberAuth::canAccess((string)$st, $tier)) {
                    $clean[] = $key;
                }
            }

            $member = MemberAuth::current();
            $memberId = $member ? (int)$member['id'] : 0;

            if ($action === 'push_subscribe') {
                $ok = WebPush::saveSubscription((array)($body['subscription'] ?? []), $clean, $memberId);
                respond(['ok' => $ok, 'pairs' => $clean]);
            }
            // push_sync
            WebPush::syncPairs((string)($body['endpoint'] ?? ''), $clean);
            respond(['ok' => true, 'pairs' => $clean]);
        }

        case 'board': {
            // THE SCANNER, FOR THE CHART'S OWN POPUP.
            //
            // The chart offers the board without leaving the coin: tap
            // Scanner, read the live setups, tap one and it loads into the
            // chart underneath, ready to trade. There used to be a standalone
            // scanner page rendering these same rows; it is gone now and this
            // sheet is the only door, so this is the only place they render,
            // over the session rather than a token - action=feed next to this
            // is authenticated for bots and is the wrong door for a signed-in
            // reader.
            //
            // Gated on the 'scanner' feature. A popup is a different way in,
            // not a different entitlement, and a member who cannot open the
            // sheet must not read its contents here either.
            $bMember = MemberAuth::current();
            $bTier = $bMember ? (string)($bMember['tier'] ?? 'free') : 'free';
            // Same 'perf_coins' gate as the win-rate column, sort and "By
            // coin" tab this sheet draws from it (see Gate::FEATURES).
            $bWinRateOpen = \SignalMasterAi\Gate::allows('perf_coins', $bTier);
            if (!\SignalMasterAi\Gate::allows('scanner', $bTier)) {
                // HOW MANY, BUT NOT WHICH.
                //
                // A locked panel that says only "this is premium" asks somebody
                // to buy a number they cannot see. The count is the one fact
                // that makes the offer concrete and gives nothing away: there
                // are eleven setups on the board right now, and Premium is
                // what tells you which coins they are on. It is also honest in
                // the direction that costs us - on a quiet day it says two,
                // and it should.
                $bCount = 0;
                try {
                    $bCount = (int)Database::pdo()->query(
                        "SELECT COUNT(*) FROM (
                           SELECT s.symbol FROM signals s
                           INNER JOIN (SELECT symbol, tf, MAX(created_at) mc FROM signals
                                        GROUP BY symbol, tf) x
                             ON x.symbol = s.symbol AND x.tf = s.tf AND x.mc = s.created_at
                           INNER JOIN symbols sy ON sy.symbol = s.symbol AND sy.enabled = 1
                           WHERE s.`signal` IN ('BUY','SELL')
                             AND (s.outcome IS NULL OR s.outcome = '')
                             AND s.created_at >= " . (time() - 12 * 3600) . "
                         ) t"
                    )->fetchColumn();
                } catch (\Throwable $e) {
                    $bCount = 0;
                }
                respond(['ok' => false, 'error' => 'locked', 'live' => $bCount,
                         'message' => 'The scanner is part of Premium.'], 403);
            }
            $bLimit = max(5, min(200, (int)($_GET['limit'] ?? 25)));

            // CLOSED IS A DIFFERENT QUESTION AND A DIFFERENT TABLE OF FACTS.
            //
            // EVERY TRACKED COIN, RANKED BY ITS OWN VERIFIED RECORD.
            //
            // A different question from the two boards below: not "where is
            // there a setup right now" but "which coins actually win" - the
            // "By coin" table that used to sit on the track record page (see
            // TrackRecord::topCoins() and Gate::FEATURES['perf_coins']).
            // Gated the same as the rest of this endpoint's win-rate data - a
            // lock that only hides the panel in the sheet is not a lock, the
            // URL still answers for whoever asks it directly.
            //
            // One request covers both the sheet's teaser chips and its
            // "See all" expansion - the chips are the first few of this same
            // list, and Load more paginates through what was already fetched
            // rather than asking again. 200 to actually mean "every tracked
            // coin", the same ceiling the live board itself uses.
            if (($_GET['view'] ?? '') === 'bycoin') {
                if (!$bWinRateOpen) {
                    respond(['ok' => false, 'error' => 'locked',
                             'message' => 'Per-coin win rate is part of Premium.'], 403);
                }
                $bCoinLimit = max(1, min(200, (int)($_GET['limit'] ?? 20)));
                $bCoins = [];
                try {
                    $bCoins = \SignalMasterAi\TrackRecord::topCoins('winrate', $bCoinLimit, 5);
                } catch (\Throwable $e) {
                    $bCoins = [];
                }
                respond(['ok' => true, 'view' => 'bycoin', 'coins' => $bCoins,
                         'winRateOpen' => true, 'at' => time()]);
            }
            // This endpoint used to also answer "what happened to the last
            // lot" (view=closed, backed by Scanner::closedBoard) alongside
            // the live board, for the "Live setups" sheet's now-removed
            // Closed tab. The sheet is live-only now and nothing else ever
            // requested that view (grep the JS), so the branch is gone; the
            // settled record lives on performance.php instead.
            // Scanner::closedBoard() itself is untouched - only this dead
            // API surface for it was removed.
            //
            // BUILT FROM Scanner::run() NOW, NOT A SEPARATE QUERY.
            //
            // This used to be its own hand-rolled SQL query plus a bars-old
            // cutoff bolted on beside it - a second, thinner copy of exactly
            // what Scanner::run() (the same board-builder admin's dashboard
            // already calls) does properly: reads signal_state rather than
            // the ledger's last row, drops anything the verifier has since
            // settled, drops anything the coin's own time stop has passed,
            // AND - the one this endpoint's own bars cutoff could never catch
            // - drops a call whose published stop or target has already been
            // touched by the candles since, even though it is still
            // technically "open". That last one is exactly "a row read BUY
            // here, the chart reads no setup" - the board showing a setup
            // that has already run its course. One board-builder rather than
            // two disagreeing ones.
            $bBoard = \SignalMasterAi\Scanner::board(
                ['exclude_neutral' => true, 'sort' => 'score'],
                $bLimit
            );
            // ALREADY RUNNING, NOT LEFT BEHIND.
            //
            // Scanner::run() drops a call from $bBoard the moment price has
            // touched a target - it is no longer enterable, which is the
            // question $bBoard answers. It is still open, though, and a
            // reader who took it wants to see it keep going: Scanner::board()
            // just populated Scanner::$progress with exactly those rows, each
            // carrying which target (tp1/tp2/tp3) it reached. Sent as its own
            // list rather than merged into $bOut, so the sheet can give it a
            // section of its own instead of mixing a setup still open to
            // enter with one already three-fifths of the way to its target -
            // the two answer different questions and look identical once
            // merged.
            $bProgress = \SignalMasterAi\Scanner::$progress;
            $bBars = ['1m' => 60, '3m' => 180, '5m' => 300, '15m' => 900,
                      '30m' => 1800, '1h' => 3600, '4h' => 14400, '1d' => 86400];
            $bNow = time();
            // Shared with $bProgress below: same fields either way, plus
            // 'stage' when the row carries one. One mapping, so the two lists
            // can never drift into describing a row differently.
            $bMap = static function (array $r) use ($bBars, $bNow): array {
                $bSec = $bBars[(string)$r['tf']] ?? 3600;
                $out = [
                    'symbol' => (string)$r['symbol'],
                    'tf' => (string)$r['tf'],
                    'signal' => (string)$r['signal'],
                    // The call's public reference, so the popup and the chart
                    // it opens are naming the same signal rather than leaving
                    // the reader to match a coin and a time by eye.
                    'ref' => (string)($r['ref'] ?? ''),
                    'score' => round((float)$r['score'], 2),
                    'confidence' => round((float)$r['confidence'], 1),
                    'grade' => (string)$r['grade'],
                    // Which of the three types this is, so the popup can say
                    // it and filter on it without a second request.
                    'rr_type' => $r['rr_type'],
                    'rr_tier' => (int)$r['rr_tier'],
                    // THE DEADLINE, WITHOUT THE PLAN.
                    //
                    // The board deliberately withholds entry, stop and targets
                    // on a premium coin - the plan is the product. A deadline
                    // is not a plan: it says how much of this setup's life is
                    // left, which is what stops a 15m call an hour old looking
                    // the same as a 4h call an hour old on a mixed board.
                    'expires_at' => (int)$r['expires_at'],
                    'price' => (float)$r['price'],
                    'at' => (int)$r['at'],
                    // How many bars ago, so the row can say its own age. A
                    // reader deciding whether to take a call is entitled to
                    // know it was read three candles back rather than now.
                    'bars_ago' => (int)floor(($bNow - (int)$r['at']) / max(1, $bSec)),
                ];
                if (isset($r['stage'])) {
                    $out['stage'] = (string)$r['stage'];
                }
                return $out;
            };
            $bOut = array_map($bMap, $bBoard);
            $bProgOut = array_map($bMap, $bProgress);
            // VERIFIED WIN RATE, ATTACHED TO THE ROWS ALREADY ON THE BOARD.
            //
            // A lookup keyed on symbol, run once against every coin across
            // both lists together - one query rather than two, and a coin
            // appearing in both gets the same figure either way. A free
            // viewer's rows are left exactly as built above - no winRate key,
            // so the sheet's column and sort both stay off rather than
            // showing a blank or a zero that reads as "this coin never wins".
            if ($bWinRateOpen && ($bOut || $bProgOut)) {
                $bSymbols = array_values(array_unique(array_merge(
                    array_column($bOut, 'symbol'),
                    array_column($bProgOut, 'symbol')
                )));
                $bWinRates = \SignalMasterAi\TrackRecord::winRatesFor($bSymbols);
                $bAttachWinRate = static function (array $rows) use ($bWinRates): array {
                    foreach ($rows as &$bRow) {
                        if (isset($bWinRates[$bRow['symbol']])) {
                            $bRow['winRate'] = $bWinRates[$bRow['symbol']]['winRate'];
                            $bRow['winSettled'] = $bWinRates[$bRow['symbol']]['settled'];
                        }
                    }
                    unset($bRow);
                    return $rows;
                };
                $bOut = $bAttachWinRate($bOut);
                $bProgOut = $bAttachWinRate($bProgOut);
            }
            respond(['ok' => true, 'signals' => $bOut, 'progress' => $bProgOut, 'at' => time(),
                     'winRateOpen' => $bWinRateOpen]);
        }

        case 'feed': {
            // Premium signals API: token-authenticated JSON feed of the latest
            // stored signal per pair. Docs: api.php?action=feed&token=YOUR_TOKEN
            if (Database::setting('api_feed_enabled', '1') !== '1') {
                respond(['ok' => false, 'error' => 'API feed disabled'], 403);
            }
            if (!\SignalMasterAi\Master::on('signals', 'api')) {
                respond(['ok' => false, 'error' => 'The signal feed is switched off on this site'], 503);
            }
            // The tier gate for the feed. The token identifies the member, so
            // it is checked below against the row the token belongs to rather
            // than against a session - a feed is called by a bot, and a bot
            // has no session to read a tier from.
            // Header first, query string second.
            //
            // A secret in a URL is a secret in the web server's access log, in
            // the browser history, and in the Referer header of anything that
            // page links to. Every one of those outlives the request. The
            // query form still works - integrations already written against
            // it must not break - but the header is what the docs show now,
            // and it is the only form that leaves no copy behind.
            $token = (string)($_SERVER['HTTP_X_API_TOKEN'] ?? '');
            if ($token === '') {
                $auth = (string)($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
                if (stripos($auth, 'bearer ') === 0) {
                    $token = trim(substr($auth, 7));
                }
            }
            if ($token === '') {
                $token = (string)($_GET['token'] ?? '');
            }
            if (!preg_match('/^[a-f0-9]{48}$/', $token)) {
                respond(['ok' => false, 'error' => 'Invalid token'], 401);
            }
            $mStmt = Database::pdo()->prepare('SELECT * FROM members WHERE api_token = ?');
            $mStmt->execute([hash('sha256', $token)]);
            $m = $mStmt->fetch();
            $mStmt->closeCursor();
            // The viewer tier this token stands for, expiry included: a plan
            // that has run out is a free account, whatever the column says.
            $feedTier = 'guest';
            if ($m) {
                $feedTier = ($m['tier'] === 'paid'
                    && ((int)$m['paid_until'] === 0 || (int)$m['paid_until'] > time()))
                    ? 'paid' : 'free';
            }
            // Was hard-coded premium-only. The gate table now owns it, so an
            // operator who wants to offer the feed to every registered member
            // has somewhere to say so - and the upgrade page stops selling it
            // the moment they do.
            if (!$m || !\SignalMasterAi\Gate::allows('api_feed', $feedTier)) {
                respond(['ok' => false, 'error' => \SignalMasterAi\Gate::tier('api_feed') === 'paid'
                    ? 'Token requires an active premium plan'
                    : 'Token requires a registered account'], 403);
            }
            // 10s micro-cache: bots poll aggressively.
            $cachedFeed = \SignalMasterAi\Cache::get('feed');
            if (is_array($cachedFeed)) {
                respond(['ok' => true, 'cached' => true, 'signals' => $cachedFeed]);
            }
            $rows = Database::pdo()->query(
                "SELECT s.symbol, s.tf, s.`signal`, s.score, s.confidence, s.price, s.indicators,
                        s.created_at, s.ref, s.outcome
                 FROM signals s
                 INNER JOIN (SELECT symbol, tf, MAX(created_at) mc FROM signals GROUP BY symbol, tf) x
                   ON x.symbol = s.symbol AND x.tf = s.tf AND x.mc = s.created_at
                 INNER JOIN symbols sy ON sy.symbol = s.symbol AND sy.enabled = 1"
                 . \SignalMasterAi\Visibility::sql('scanner')
                 . \SignalMasterAi\Publish::sql('s') . "
                 ORDER BY s.created_at DESC LIMIT 200"
            )->fetchAll();
            $out = [];
            foreach ($rows as $r) {
                $inds = json_decode((string)$r['indicators'], true);
                $lv = is_array($inds) ? ($inds['levels'] ?? null) : null;
                $out[] = [
                    // THE FIELD THAT MAKES POLLING SAFE.
                    //
                    // A bot polling this every ten seconds sees the same row
                    // over and over, and had nothing stable to recognise it
                    // by: it had to key on symbol + timeframe + timestamp and
                    // hope. The reference is one string per published call, so
                    // "have I already acted on this?" becomes a set lookup.
                    // Same value the webhook sends as 'id', so a member who
                    // switches from polling to push does not re-fire the calls
                    // they already took.
                    'id' => (string)($r['ref'] ?? ''),
                    'ref' => (string)($r['ref'] ?? ''),
                    'symbol' => $r['symbol'],
                    'tf' => $r['tf'],
                    'signal' => $r['signal'],
                    'score' => (float)$r['score'],
                    'confidence' => (float)$r['confidence'],
                    'grade' => is_array($inds) ? ($inds['grade'] ?? null) : null,
                    // Which of the three types, lifted out of levels so a
                    // filter does not have to reach into a nested object.
                    'rr_type' => is_array($lv) ? ($lv['rr_type'] ?? null) : null,
                    'rr_tier' => is_array($lv) ? (int)($lv['rr_tier'] ?? 0) : 0,
                    // Still running, or already settled. A feed that does not
                    // say invites a bot to open a trade the site closed hours
                    // ago - the latest row for a pair is not the same thing as
                    // a live one.
                    'open' => ($r['outcome'] ?? '') === '' || $r['outcome'] === null,
                    'price' => (float)$r['price'],
                    'levels' => $lv,
                    'at' => (int)$r['created_at'],
                ];
            }
            \SignalMasterAi\Cache::set('feed', $out, 10);
            respond(['ok' => true, 'cached' => false, 'signals' => $out,
                     'disclaimer' => \SignalMasterAi\Database::setting('site_notice')]);
        }

        case 'prefs': {
            // Read or update the viewer's strategy profile, risk settings and
            // alert filters.
            $member = MemberAuth::current();
            if (!$member) {
                respond(['ok' => false, 'error' => 'Log in to manage your settings', 'login' => true], 401);
            }
            $mid = (int)$member['id'];
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                requireJsonRequest();
                $body = json_decode(file_get_contents('php://input') ?: '', true);
                if (!is_array($body)) {
                    respond(['ok' => false, 'error' => 'Invalid JSON'], 400);
                }
                \SignalMasterAi\MemberPrefs::save($mid, $body);
            }
            $prefs = \SignalMasterAi\MemberPrefs::get($mid);
            respond([
                'ok' => true,
                'prefs' => $prefs,
                'profiles' => \SignalMasterAi\MemberPrefs::PROFILES,
                'webhook_secret' => $prefs['webhook_url'] !== ''
                    ? \SignalMasterAi\Webhooks::secret($mid) : null,
                'webhook_failures' => \SignalMasterAi\Webhooks::recentFailures($mid),
            ]);
        }


        case 'watchlists': {
            // Server-side watchlists. These used to live only in the browser,
            // so a member's list vanished on their second device even though
            // the server was already storing the pairs for alert delivery.
            $member = MemberAuth::current();
            if (!$member) {
                respond(['ok' => false, 'error' => 'Log in to sync watchlists', 'login' => true], 401);
            }
            $mid = (int)$member['id'];
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                requireJsonRequest();
                $body = json_decode(file_get_contents('php://input') ?: '', true);
                if (!is_array($body)) {
                    respond(['ok' => false, 'error' => 'Invalid JSON'], 400);
                }
                $listId = (int)($body['id'] ?? 0);
                switch ((string)($body['op'] ?? '')) {
                    case 'create':
                        \SignalMasterAi\Watchlists::create($mid, (string)($body['name'] ?? ''), (array)($body['pairs'] ?? []));
                        break;
                    case 'rename':
                        \SignalMasterAi\Watchlists::rename($mid, $listId, (string)($body['name'] ?? ''));
                        break;
                    case 'pairs':
                        \SignalMasterAi\Watchlists::setPairs($mid, $listId, (array)($body['pairs'] ?? []));
                        break;
                    case 'activate':
                        \SignalMasterAi\Watchlists::setActive($mid, $listId);
                        break;
                    case 'delete':
                        \SignalMasterAi\Watchlists::delete($mid, $listId);
                        break;
                    case 'import':
                        // First login on a device that still has the old
                        // localStorage list: adopt it rather than lose it.
                        \SignalMasterAi\Watchlists::importLocal($mid, (array)($body['pairs'] ?? []));
                        break;
                    default:
                        respond(['ok' => false, 'error' => 'Unknown operation'], 400);
                }
            }
            respond(['ok' => true, 'lists' => \SignalMasterAi\Watchlists::all($mid)]);
        }

        case 'paper': {
            $member = MemberAuth::current();
            if (!$member) {
                respond(['ok' => false, 'error' => 'Log in to use the paper portfolio', 'login' => true], 401);
            }
            $mid = (int)$member['id'];
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                requireJsonRequest();
                $body = json_decode(file_get_contents('php://input') ?: '', true);
                if (!is_array($body)) {
                    respond(['ok' => false, 'error' => 'Invalid JSON'], 400);
                }
                $op = (string)($body['op'] ?? '');
                if ($op === 'open') {
                    $sym = strtoupper((string)preg_replace('/[^A-Z0-9]/i', '', (string)($body['symbol'] ?? '')));
                    $ptf = (string)($body['tf'] ?? '');
                    if ($sym === '' || !in_array($ptf, $config['market']['intervals'], true)) {
                        respond(['ok' => false, 'error' => 'Unknown pair'], 400);
                    }
                    requireSymbolAccess($sym);
                    // Re-derive the signal server-side: a client must not be
                    // able to invent its own entry and stop.
                    $md2 = new MarketData($config);
                    $res = (new SignalEngine())->withMarketData($md2)->suppressStore()
                        ->analyse($sym, $ptf, $md2->candles($sym, $ptf));
                    // "market" means the price right now, fetched here rather
                    // than taken from the request: a client that could name
                    // its own entry could book a fill that never existed.
                    $entryPrice = null;
                    if ((string)($body['entry_mode'] ?? 'signal') === 'market') {
                        try {
                            $entryPrice = $md2->lastPrice($sym);
                        } catch (Throwable $e) {
                            $entryPrice = null;   // fall back to the signal's entry
                        }
                    }
                    $why = null;
                    $id = \SignalMasterAi\Paper::open($mid, $res, \SignalMasterAi\MemberPrefs::get($mid),
                        (string)($body['source'] ?? 'paper'), $why,
                        (float)($body['margin'] ?? 0), (float)($body['leverage'] ?? 1), $entryPrice,
                        \SignalMasterAi\Paper::exitChoice(
                            $body['exit_target'] ?? \SignalMasterAi\Paper::EXIT_DEFAULT));
                    if ($id === null) {
                        $funds = \SignalMasterAi\Paper::funds($mid);
                        $errors = [
                            'position_limit' => 'You have '
                                . \SignalMasterAi\Limits::of('positions')
                                . ' positions open, which is the most your plan allows. '
                                . 'Close one, or upgrade for more.',
                            'duplicate' => 'You are already following ' . $sym . ' on ' . $ptf
                                . '. Close it in your portfolio before opening another.',
                            'no_amount' => 'Enter how much of your balance to commit to this trade.',
                            'past_stop' => 'The market has already passed the stop for this setup, so there is nothing left to enter.',
                            // No longer "go to your portfolio": the ticket can
                            // take a deposit itself now, so the message says
                            // what is short rather than where to go.
                            'insufficient_funds' => 'That is more than your available funds ($'
                                . number_format($funds['available'], 2) . '). Add funds or commit less.',
                        ];
                        respond([
                            'ok' => false,
                            'reason' => $why,
                            'funds' => $funds,
                            'error' => $errors[$why]
                                ?? 'There is no setup to follow here - the signal is NEUTRAL, so it has no entry or stop.',
                        ], 400);
                    }
                } elseif ($op === 'close') {
                    \SignalMasterAi\Paper::close($mid, (int)($body['id'] ?? 0),
                        (float)($body['price'] ?? 0), (string)($body['note'] ?? ''));
                } elseif ($op === 'deposit') {
                    // Add simulated funds without leaving the page.
                    //
                    // Running out of balance mid-order used to end the attempt:
                    // the ticket closed, and the message said to add funds in
                    // the portfolio - which means leave the chart, deposit,
                    // come back, find the coin again, reopen the ticket, and
                    // hope the setup is still valid. It usually is not; that is
                    // the whole point of a setup. The wallet is simulated, so
                    // there was never a reason for it to be a separate journey.
                    //
                    // Same rules as the portfolio's own form: a positive
                    // amount, capped, rounded to cents, added to what is there.
                    $amt = round(max(0.0, min(1e9, (float)($body['amount'] ?? 0))), 2);
                    if ($amt <= 0) {
                        respond(['ok' => false, 'error' => 'Enter an amount above zero.'], 400);
                    }
                    $prefsNow = \SignalMasterAi\MemberPrefs::get($mid);
                    \SignalMasterAi\MemberPrefs::save($mid, [
                        'account_size' => max(0.0, (float)$prefsNow['account_size']) + $amt,
                    ]);
                } else {
                    respond(['ok' => false, 'error' => 'Unknown operation'], 400);
                }
            }
            respond([
                'ok' => true,
                // Each open position carries its own deadline, so the panel
                // can count it down instead of only saying when it opened.
                // Stamped here rather than computed in the browser: the bars
                // and the timeframe are server facts and the arithmetic
                // belongs beside them - see Paper::deadline.
                'open' => array_map(static function (array $t): array {
                    $t['expires_at'] = \SignalMasterAi\Paper::deadline($t);
                    return $t;
                }, \SignalMasterAi\Paper::positions($mid, 'open')),
                'closed' => \SignalMasterAi\Paper::positions($mid, 'closed', 50),
                'summary' => \SignalMasterAi\Paper::summary($mid),
                'funds' => \SignalMasterAi\Paper::funds($mid),
            ]);
        }

        case 'ask': {
            // Follow-up questions about the current setup. The stored analysis
            // is the only context the model receives, so it cannot answer
            // about a different chart or invent numbers.
            if (!\SignalMasterAi\Explain::enabled()) {
                respond(['ok' => false, 'error' => 'Explanations are not enabled on this site'], 404);
            }
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                respond(['ok' => false, 'error' => 'POST required'], 405);
            }
            requireJsonRequest();
            $need = Database::setting('ai_ask_tier', 'paid');
            $tier = MemberAuth::tier();
            // The daily allowance, per tier - see Limits. The rate limiter
            // above stops a burst; this is how much of an upstream-billed
            // feature the plan includes, which is a different question and the
            // only one a member notices.
            $askMax = \SignalMasterAi\Limits::of('ai_asks');
            if ($askMax > 0) {
                $askKey = 'askday:' . (MemberAuth::currentId() ?: 'ip'
                          . substr(sha1((string)($_SERVER['REMOTE_ADDR'] ?? '')), 0, 12))
                          . ':' . gmdate('Y-m-d');
                if ((int)(\SignalMasterAi\Cache::get($askKey) ?? 0) >= $askMax) {
                    respond(['ok' => false, 'upgrade' => $tier !== 'paid',
                             'error' => 'You have used all ' . $askMax . ' questions for today.'
                                      . ($tier !== 'paid' ? ' Premium members get more.'
                                                          : ' The allowance resets at 00:00 UTC.')], 429);
                }
                \SignalMasterAi\Cache::increment($askKey, 172800);
            }
            $allowed = MemberAuth::meetsTier($need, $tier);
            if (!$allowed) {
                respond(['ok' => false, 'error' => $need === 'paid'
                    ? 'Ask-the-analysis is a premium feature.'
                    : 'Create a free account to ask about a setup.'], 403);
            }
            $body = json_decode(file_get_contents('php://input') ?: '', true);
            if (!is_array($body)) {
                respond(['ok' => false, 'error' => 'Invalid JSON'], 400);
            }
            $sym = strtoupper((string)preg_replace('/[^A-Z0-9]/i', '', (string)($body['symbol'] ?? '')));
            requireSymbolAccess($sym);
            $qtf = in_array((string)($body['tf'] ?? ''), $config['market']['intervals'], true)
                ? (string)$body['tf'] : $tf;
            $md3 = new MarketData($config);
            $res = (new SignalEngine())->withMarketData($md3)->suppressStore()
                ->analyse($sym, $qtf, $md3->candles($sym, $qtf));
            $answer = \SignalMasterAi\Explain::ask($res, (string)($body['question'] ?? ''));
            respond($answer === null
                ? ['ok' => false, 'error' => 'Could not produce an answer right now.']
                : ['ok' => true, 'answer' => $answer,
                   'disclaimer' => 'Educational explanation of automated analysis - not advice.']);
        }

        case 'news': {
            if (!\SignalMasterAi\Master::on('news', 'chart')) {
                respond(['ok' => false, 'error' => 'News is disabled'], 404);
            }
            $nf = new NewsFetcher();
            $nf->refreshIfStale();   // fully automated: lazy refresh on demand
            respond([
                'ok'     => true,
                'news'   => $nf->latest((int)Database::setting('news_max_items', '30')),
                'events' => $nf->upcomingEvents(20),
                'now'    => time(),
            ]);
        }

        default:
            respond(['ok' => false, 'error' => 'Unknown action'], 400);
    }
} catch (Throwable $e) {
    respond(['ok' => false, 'error' => $e->getMessage()], 500);
}
