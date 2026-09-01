<?php
declare(strict_types=1);

$config = require __DIR__ . '/_admin.php';

use SignalMasterAi\Auth;
use SignalMasterAi\Database;

Auth::requireLogin();
$pdo = Database::pdo();

$stats = [
    'symbols'  => (int)$pdo->query('SELECT COUNT(*) FROM symbols WHERE enabled = 1')->fetchColumn(),
    'candles'  => (int)$pdo->query('SELECT COUNT(*) FROM candles')->fetchColumn(),
    'signals'  => (int)$pdo->query('SELECT COUNT(*) FROM signals')->fetchColumn(),
    'rules'    => (int)$pdo->query('SELECT COUNT(*) FROM ta_knowledge WHERE enabled = 1')->fetchColumn(),
    'news'     => (int)$pdo->query('SELECT COUNT(*) FROM news_items')->fetchColumn(),
    'members'  => (int)$pdo->query('SELECT COUNT(*) FROM members')->fetchColumn(),
    'push'     => (int)$pdo->query('SELECT COUNT(*) FROM push_subs')->fetchColumn(),
];

$recent = $pdo->query('SELECT * FROM signals ORDER BY created_at DESC LIMIT 10')->fetchAll();
$caches = $pdo->query('SELECT * FROM fetch_log ORDER BY fetched_at DESC LIMIT 10')->fetchAll();
$health = \SignalMasterAi\Maintenance::health();

// What the dashboard is actually for.
//
// It used to open with seven counts - symbols, candles, signals, rules,
// headlines, members, subscribers - which answer "how much stuff is in here"
// and not one of them answers "is this working". An operator checking in
// wants to know whether the engine is making money, whether the background
// worker is keeping up, and whether anything took itself offline overnight.
// Those go first now; the inventory is still below, where it belongs.
// THE SAME POPULATION THE TRACK RECORD PAGE COUNTS.
//
// This counted every settled signal the engine ever produced. The public page
// counts only the ones it published - coins visible on the track record, on an
// enabled timeframe, at or above the publish grade. So the two pages reported
// different totals and different win rates for the same question, with nothing
// on either saying why: 146 settled at 59.6% here against 106 at 54.7% there.
// An operator comparing them concludes one of them is broken, and cannot tell
// which.
//
// The published figure is the one that belongs in the headline, because it is
// the record the product actually has - what a member was shown and could have
// traded. The engine's private hit rate on calls nobody ever saw is a
// different and much less important number, so it moves to the line
// underneath, where it answers the question it is actually good for: is the
// publish floor earning its keep?
$trackStats = \SignalMasterAi\TrackRecord::stats();
$perf = ['t' => $trackStats['total'], 'w' => $trackStats['wins'], 'r' => $trackStats['sumR']];
// Everything, published or not, for the comparison line below.
$perfAll = \SignalMasterAi\TrackRecord::rawStats();
$pAllT = $perfAll['total'];
$pAllWin = $perfAll['winRate'];
$pT = (int)($perf['t'] ?? 0);
$pAvg = $pT > 0 ? (float)$perf['r'] / $pT : null;
$pWin = $pT > 0 ? round((int)$perf['w'] / $pT * 100, 1) : null;
// Is that average worth acting on yet, or is it still inside its own noise?
//
// Over the PUBLISHED set, because that is the set $pAvg was measured on. This
// read the unfiltered table, so the significance verdict was computed from one
// population and printed as a claim about another - and the two differ by
// exactly the calls the operator withheld. On an install where the publish
// floor is doing real work that is a large difference, and it can only ever
// mislead in one direction: an average drawn from a hundred published trades
// declared "proven" on the strength of two hundred unpublished ones.
$pProven = false;
if ($pT > 1) {
    $vals = $pdo->query("SELECT outcome_r FROM signals WHERE outcome IN ('confirmed','invalid')"
                         . \SignalMasterAi\TrackRecord::sql() . "
                         ORDER BY closed_at DESC LIMIT 1000")->fetchAll(PDO::FETCH_COLUMN);
    $m = array_sum($vals) / count($vals);
    $var = 0.0;
    foreach ($vals as $v) {
        $var += ((float)$v - $m) ** 2;
    }
    $se = sqrt($var / max(1, count($vals) - 1)) / sqrt(count($vals));
    $pProven = $se > 0 && abs($m) > 1.96 * $se;
}

// The scan rotation, in the unit that matters: how long until a coin is
// looked at again, against the fastest timeframe being scanned.
$scanCoins = (int)$pdo->query('SELECT COUNT(*) FROM symbols WHERE enabled = 1 AND scan = 1')->fetchColumn();
// The site's timeframe list. There is no separate scan list any more - a
// frame the site runs is a frame the scan rotates through.
$scanTfs = \SignalMasterAi\Visibility::siteTfs($config['market']['intervals'] ?? []);
$scanOn = Database::setting('fullscan_enabled', '1') === '1';
$jobs = $scanOn ? $scanCoins * max(1, count($scanTfs)) : 0;
$batch = max(1, (int)Database::setting('fullscan_batch', '10'));
$seenSec = (int)Database::setting('cron_interval_seen', '0');
$overrideMin = (int)Database::setting('cron_interval_min', '0');
$everyMin = $overrideMin > 0 ? $overrideMin : ($seenSec > 0 ? max(1, (int)round($seenSec / 60)) : 10);

/**
 * Coins per run - what actually happens, not what was asked for.
 *
 * This divided the job list by the configured batch, which assumes every run
 * finishes its batch. Runs stop on a time budget instead (see cron.php), so a
 * host managing forty coins in fifty seconds was told its cycle was the one a
 * batch of two hundred would give - a number eight times better than the
 * truth, in the one direction an operator cannot afford to be wrong about,
 * because the whole point of the figure is "can the rotation keep up".
 *
 * Cron now records what it got through. Until it has, the configured batch is
 * the only estimate available, and the card says which of the two it used
 * rather than presenting a guess and a measurement identically.
 */
$rateFor = static function (string $key) use ($batch): array {
    $rate = (float)Database::setting('fullscan_rate_' . $key, '0');
    return $rate > 0
        ? [max(1, (int)round($rate)), true, Database::setting('fullscan_stopped_' . $key, '0') === '1']
        : [$batch, false, false];
};
$tfMinutes = static fn(string $t): int => \SignalMasterAi\SignalEngine::tfMinutes($t);
[$perRun, $perRunMeasured, $budgetBound] = $rateFor('all');
$cycleMin = $jobs > 0 ? (int)ceil($jobs / $perRun) * $everyMin : 0;

/**
 * PER FRAME, because that is the only comparison that means anything.
 *
 * cron.php has recorded a throughput per timeframe for a while now, and said
 * in its own comment why: a combined cycle counts the daily list inside a
 * figure then judged against the fifteen-minute bar, and the daily list has no
 * business being judged against fifteen minutes. Nothing ever read those
 * numbers. The tile went on dividing every coin on every frame by one blended
 * rate and colouring itself red when the result exceeded the fastest frame -
 * which it almost always does, on an install that is in fact keeping up.
 *
 * Each frame is now measured against its own bar. A frame with no recorded
 * rate is not guessed at: mostly that means nothing of it has come due yet,
 * and inventing a cycle for it is how the blended number went wrong.
 */
$tfCycles = [];
foreach ($scanTfs as $t) {
    if (!$scanOn || $scanCoins === 0) {
        continue;
    }
    $tfCycles[$t] = [
        'bar'    => $tfMinutes($t),
        'rate'   => (float)Database::setting('fullscan_rate_all_' . $t, '0'),
        // Coins that came due on this frame in the last run and were not
        // reached. Ground truth, no division: see the note in cron.php about
        // why a per-run rate cannot answer this for a slow frame.
        'behind' => (int)Database::setting('fullscan_behind_all_' . $t, '0'),
    ];
}
$tfBehind = array_keys(array_filter($tfCycles, fn($c) => $c['behind'] > 0));
// One timeframe-to-minutes table for the whole app - SignalEngine::tfMinutes().
// This page and cron.php each carried their own copy of it.
$tfMin = $tfMinutes;
$fastest = null;
foreach ($scanTfs as $t) {
    if ($fastest === null || $tfMin($t) < $fastest) {
        $fastest = $tfMin($t);
    }
}
$cycleTooSlow = $fastest !== null && $cycleMin > $fastest;

// Split cron jobs, each measured on its own.
//
// One job scanning every timeframe has one cycle time. Two - a fast one for
// the frames under an hour and a slow one for the rest - have two, over
// different lists, at different cadences, and the site-wide figure above
// describes neither of them. Each worker that has run in the last two hours is
// reported against its own fastest frame, which is the only comparison that
// answers "can this keep up".
$workers = [];
foreach ($pdo->query("SELECT skey, svalue FROM settings WHERE skey LIKE 'cron_seen_%'") as $wRow) {
    $key = substr((string)$wRow['skey'], strlen('cron_seen_'));
    $seenAt = (int)$wRow['svalue'];
    if ($key === 'all' || $seenAt < time() - 7200) {
        continue;               // the undivided job, or one that stopped
    }
    $wTfs = $scanTfs;
    if (str_starts_with($key, 'fast')) {
        $wTfs = array_values(array_filter($scanTfs, fn($t) => $tfMin($t) < 60));
    } elseif (str_starts_with($key, 'slow')) {
        $wTfs = array_values(array_filter($scanTfs, fn($t) => $tfMin($t) >= 60));
    } elseif (str_starts_with($key, 'tf_')) {
        // A job named for its own frames - one cron per timeframe. The key
        // carries the list, so the row can be judged against exactly the
        // frames that job is responsible for rather than against all of them.
        $named = explode('_', substr($key, 3));
        $wTfs = array_values(array_intersect($scanTfs, $named));
    }
    if (!$wTfs) {
        continue;               // configured for frames this site does not scan
    }
    $wEvery = (int)Database::setting('cron_interval_' . $key, '0');
    $wEveryMin = $wEvery > 0 ? max(1, (int)round($wEvery / 60)) : $everyMin;
    $wJobs = $scanCoins * count($wTfs);
    [$wPerRun, $wMeasured, $wBudgetBound] = $rateFor($key);
    $wCycle = $scanOn ? (int)ceil($wJobs / $wPerRun) * $wEveryMin : 0;
    $wFastest = null;
    foreach ($wTfs as $t) {
        if ($wFastest === null || $tfMin($t) < $wFastest) {
            $wFastest = $tfMin($t);
        }
    }
    $workers[$key] = [
        'tfs' => $wTfs, 'every' => $wEveryMin, 'jobs' => $wJobs, 'cycle' => $wCycle,
        'fastest' => $wFastest, 'measured' => $wEvery > 0,
        'per_run' => $wPerRun, 'per_run_measured' => $wMeasured, 'budget_bound' => $wBudgetBound,
        'slow' => $wFastest !== null && $wCycle > $wFastest,
    ];
}
ksort($workers);
// With the job split, the site-wide cycle above is the arithmetic of a single
// worker that does not exist - it counts every timeframe against one cadence.
// The per-worker rows supersede it, so the whole-site verdict stands down
// rather than contradicting them on the same screen.
if ($workers) {
    $cycleTooSlow = false;
}

// Anything that took itself offline while nobody was looking.
$dropped = (int)$pdo->query("SELECT COUNT(*) FROM symbols
                             WHERE enabled = 1 AND scan = 0 AND scan_note != ''")->fetchColumn();
$quarantined = count((array)json_decode(Database::setting('quarantined_rules', '[]'), true));
// Published only, like every other figure on this page - this counted every
// open call the engine held, so the dashboard said 120 while the track record
// said 72 and neither explained the difference.
$openSignals = \SignalMasterAi\TrackRecord::openCount();
$engineEdits = count(\SignalMasterAi\Audit::recent(50, \SignalMasterAi\Audit::ACTOR_ENGINE));
// Everything above answers "is it running well". This answers "is anything
// broken" - the failures the app catches so a page cannot go down, which is
// exactly why nobody ever saw them.
$errs = \SignalMasterAi\ErrorLog::summary(24);

admin_header('Dashboard', 'dashboard',
    'Anything waiting on you, then how the site is running right now. If the top of this page is '
    . 'quiet, nothing needs a decision.');
show_flash();
?>
<?php // The tiles are captured, not printed here.
      //
      // They used to be the first thing on the page, so a healthy site and a
      // site with three payments waiting and a stalled worker opened
      // identically. What needs doing goes first now; the numbers follow it. ?>
<?php ob_start(); ?>
<h2>Is it working?</h2>
<div class="cards">
  <div class="card">
    <div class="num" style="color:<?= $pAvg === null ? 'var(--muted)' : ($pAvg >= 0 ? 'var(--up)' : 'var(--down)') ?>">
      <?= $pAvg === null ? '—' : ($pAvg > 0 ? '+' : '') . round($pAvg, 3) ?></div>
    <div class="lbl">Expectancy (R)<?= $pT > 0 && !$pProven ? ' — not proven' : '' ?></div>
  </div>
  <div class="card"><div class="num"><?= $pWin === null ? '—' : $pWin . '%' ?></div>
    <div class="lbl">Win rate over <?= number_format($pT) ?> published</div></div>
  <div class="card">
    <div class="num" style="color:<?= $health['checks']['cron']['ok'] ? 'var(--up)' : 'var(--down)' ?>">
      <?= $seenSec > 0 ? max(1, (int)round($seenSec / 60)) . 'm' : '—' ?></div>
    <div class="lbl">Cron interval (measured)</div></div>
  <?php // The scan, judged one frame at a time.
        //
        // The headline is the frame that is furthest behind its own bar, and
        // if none is behind it says so instead of printing a large blended
        // number in red. The per-frame line under it is the working detail:
        // which frames are measured, how long each takes, and which have not
        // had anything come due yet.
        $worstTf = null;
        foreach ($tfBehind as $t) {
            if ($worstTf === null || $tfCycles[$t]['behind'] > $tfCycles[$worstTf]['behind']) {
                $worstTf = $t;
            }
        }
        $behindTotal = array_sum(array_column($tfCycles, 'behind')); ?>
  <div class="card">
    <div class="num" style="color:<?= $tfBehind ? 'var(--down)' : ($jobs > 0 ? 'var(--up)' : 'var(--text)') ?>">
      <?= $jobs <= 0 ? 'off' : ($tfBehind ? number_format($behindTotal) : 'keeping up') ?></div>
    <div class="lbl"><?= $jobs <= 0
        ? 'Full scan'
        : ($tfBehind
            ? 'Coins left unscanned last run &mdash; worst on <strong>' . sma_e((string)$worstTf) . '</strong>'
            : 'Scan rotation, nothing left waiting') ?>
      <?php if ($jobs > 0): ?>
        <span style="display:block;font-size:11px;color:var(--muted)">
          <?php
          $bits = [];
          foreach ($tfCycles as $t => $c) {
              $bits[] = sma_e($t) . ' ' . ($c['behind'] > 0 ? $c['behind'] . ' waiting' : 'clear');
          }
          echo implode(' &middot; ', $bits);
          echo $budgetBound ? '<br>runs are hitting their time limit' : '';
          ?></span>
      <?php endif; ?></div></div>
  <div class="card"><div class="num" style="color:<?= $dropped > 0 ? 'var(--warn)' : 'var(--text)' ?>">
    <?= $dropped ?></div><div class="lbl">Coins the scan dropped</div></div>
  <div class="card"><div class="num" style="color:<?= $quarantined > 0 ? 'var(--warn)' : 'var(--text)' ?>">
    <?= $quarantined ?></div><div class="lbl">Rules benched by the engine</div></div>
  <div class="card"><div class="num"><?= number_format($openSignals) ?></div>
    <div class="lbl">Calls still open</div></div>
  <div class="card"><div class="num"><?= $engineEdits ?></div>
    <div class="lbl"><a href="audit.php" style="color:inherit">Recent engine edits</a></div></div>
  <div class="card"><div class="num" style="color:<?= $errs['problems'] > 0 ? 'var(--down)' : 'var(--up)' ?>">
    <?= $errs['problems'] ?></div>
    <div class="lbl"><a href="errors.php" style="color:inherit">Problems in 24h</a></div></div>
</div>
<?php // How a good win rate and a bad expectancy sit next to each other.
      //
      // These two tiles are the first thing on the page and they contradict
      // each other on sight: 64.6% of calls won, and the average call still
      // lost money. Both are correct and come from the same query, so there is
      // nothing to fix in the numbers - what was missing was the one fact that
      // reconciles them, which is the size of a winner against the size of a
      // loser. Without it the honest reading of the dashboard is "this is
      // broken", and the operator goes looking for a bug that is not there.
      //
      // Printed whenever both sides exist, not only when the expectancy is
      // negative: the same sentence is what tells a profitable site WHY it is
      // profitable, and a rule that only speaks up in the bad case teaches the
      // reader to skim it. ?>
<?php if ($trackStats['avgWin'] !== null && $trackStats['avgLoss'] !== null): ?>
  <p class="hint" style="margin:-4px 0 14px">
    <?= $pWin ?>% of those calls won, and the average winner made
    <strong>+<?= $trackStats['avgWin'] ?>R</strong> against an average loser of
    <strong>-<?= $trackStats['avgLoss'] ?>R</strong>
    <?php // The break-even win rate: the hit rate this payoff would need to
          // come out flat. It turns "the winners are too small" from a feeling
          // into a target - 0.58 against 1.09 needs 65.3%, so a site winning
          // 64.6% is not far off, and the fix is a wider first target rather
          // than a better entry.
          $be = $trackStats['avgWin'] + $trackStats['avgLoss'] > 0
              ? round($trackStats['avgLoss'] / ($trackStats['avgWin'] + $trackStats['avgLoss']) * 100, 1)
              : null; ?>
    <?php if ($be !== null): ?>
      &mdash; at that payoff it takes a <strong><?= $be ?>%</strong> win rate to break even, so
      <?= $pWin !== null && $pWin >= $be
          ? 'the hit rate is carrying it'
          : 'winning more often than not is not enough here. Either the winners have to run further or the losers have to be cut sooner' ?>.
    <?php endif; ?>
  </p>
<?php endif; ?>
<?php // Why this does not match the public page, when it does not match.
      //
      // Printed only when the two populations actually differ, so a site that
      // publishes everything is not handed a sentence about a distinction it
      // does not have. When they do differ this is the useful number: it says
      // whether the publish floor is picking the better calls or just fewer
      // of them. A floor that filters forty signals and leaves the win rate
      // where it was is a floor throwing away business for nothing. ?>
<?php if ($pAllT > $pT): ?>
  <p class="hint" style="margin:-4px 0 14px">
    The figures above count the <strong><?= number_format($pT) ?></strong> settled calls this site
    actually published &mdash; the same ones on the
    <a href="../performance.php" target="_blank">public track record</a>, so the two agree.
    <?= number_format($pAllT - $pT) ?> more settled below the publish floor, on a hidden coin or on a
    timeframe you do not run, and were never shown to anyone.
    <?php if ($pAllWin !== null): ?>
      Counting those as well, the engine's raw win rate is
      <strong><?= $pAllWin ?>%</strong> over <?= number_format($pAllT) ?>
      &mdash; <?= $pWin === null ? '' : ($pAllWin > $pWin
          ? 'higher than what you publish, so the floor is filtering out calls that were working'
          : ($pAllWin < $pWin
              ? 'lower than what you publish, so the floor is doing its job'
              : 'the same as what you publish, so the floor is costing you calls without improving them')) ?>.
    <?php endif; ?>
  </p>
<?php endif; ?>
<?php $smaTiles = ob_get_clean(); ?>
<?php
// Cached: this walks every settled published signal, and the dashboard is
// reloaded far more often than a trade settles.
$triage = \SignalMasterAi\Triage::cached($config['market']['intervals'] ?? []);
$triageActs = array_values(array_filter(
    $triage['slices'] ?? [], static fn(array $x): bool => !empty($x['actionable'])));
$triageExits = \SignalMasterAi\Cache::remember(
    'triage_exits_v1', 300, static fn(): array => \SignalMasterAi\Triage::exits());
$triageCurve = \SignalMasterAi\Cache::remember(
    'triage_curve_v1', 300, static fn(): array => \SignalMasterAi\Triage::targetCurve());
?>
<?php
// One plain sentence per problem, only when there is one. A dashboard that
// says "all good" in five different colours teaches nobody to read it.
$alerts = [];
if ($pT > 0 && !$pProven) {
    $alerts[] = ['warn', 'The expectancy above is inside its own margin of error on ' . number_format($pT)
        . ' trades — it cannot yet tell an edge from luck. The <a href="../performance.php">track record</a>'
        . ' shows the range.'];
}
if ($pAvg !== null && $pAvg < 0 && $pProven) {
    // "One timeframe is usually carrying the loss" was a guess dressed as a
    // finding, and on the install that prompted this panel it was the wrong
    // guess - the two worst frames together only moved the record from
    // -0.104R to -0.061R, still under water. The alert now names what the
    // record actually says, or admits the loss is spread across everything,
    // which is a different problem with different fixes.
    $worstSlice = null;
    foreach (($triage['slices'] ?? []) as $ts) {
        if (!empty($ts['actionable'])) { $worstSlice = $ts; break; }
    }
    $alerts[] = ['bad', 'The engine is losing money on a sample large enough to believe. '
        . ($worstSlice !== null
            ? 'The worst measured slice is <strong>' . sma_e((string)$worstSlice['label']) . '</strong> ('
              . strtolower((string)$worstSlice['dim_label']) . ') at '
              . sprintf('%+.3fR', (float)$worstSlice['avg_r']) . ' over '
              . number_format((int)$worstSlice['n']) . ' &mdash; see <em>Where the loss is coming'
              . ' from</em> below.'
            : 'No single timeframe, session, direction or grade is measurably losing on its own, so'
              . ' the loss is spread across all of them &mdash; that points at the targets, the stop'
              . ' distance or the cost per trade rather than at anything you can switch off.')];
}
// The one finding on this page that is not about trading.
//
// The data directory is denied by .htaccess, and nginx does not read
// .htaccess. On those hosts the database - every member email, every password
// hash, every API token, the payment records - is one GET away from anyone who
// guesses the path, and no amount of correct PHP changes that. The app cannot
// fix the server config, so it checks and refuses to let the operator not know.
if (Database::setting('admin_first_login', '0') === '1') {
    // Name the actual file. It carries a random suffix so it cannot be fetched
    // by guessing the URL, which means the operator genuinely cannot find it
    // without being told.
    $noteFile = \SignalMasterAi\DataGuard::firstLoginFile();
    $where = $noteFile !== null
        ? '<code>data/' . htmlspecialchars(basename($noteFile), ENT_QUOTES) . '</code>'
        : 'a file in <code>data/</code>';
    $alerts[] = ['bad', '<strong>This admin account still has its generated password.</strong> '
        . 'It was written to ' . $where . ' because no administrator existed. '
        . 'Change it under <a href="settings.php#security">Settings &rsaquo; Security</a> &mdash; '
        . 'that deletes the file. Until then a copy of a working credential is sitting on disk.'];
}

// Not a live hole - nothing can plant a .php file in the uploads directory
// today - but it is the difference between the next upload bug being a bug and
// being a shell, so it is reported before it matters rather than after.
if (\SignalMasterAi\DataGuard::uploadsExecute() === true) {
    $alerts[] = ['warn', '<strong>PHP runs inside your uploads directory.</strong> Nothing can put a '
        . 'script there today &mdash; uploads take their extension from the image data, never from '
        . 'the filename &mdash; but any future upload bug would become code execution on this server. '
        . 'Deny script handling under <code>data/</code> in the web server config.'];
}

// (A missing src/ file is caught in _admin.php, before any page body runs -
// this dashboard cannot report it, because the panel never gets this far.)

$dataExposed = \SignalMasterAi\DataGuard::databaseExposed();
if ($dataExposed === true) {
    $alerts[] = ['bad', '<strong>Your database is downloadable from the web.</strong> '
        . '<code>' . sma_e((string)\SignalMasterAi\DataGuard::publicPath()) . '</code> returns the file '
        . 'to anyone who asks &mdash; member emails, password hashes, API tokens and payment records. '
        . 'The web server is ignoring the <code>.htaccess</code> that should block it, which nginx '
        . 'always does. Deny that directory in the server config, or move it above the document root, '
        . 'then reload this page to re-check.'];
}

// Only when a frame is genuinely behind ITS OWN bar. This fired on
// $cycleTooSlow - every coin on every frame divided by one blended rate and
// compared to the fastest frame - which is red on installs that are keeping up
// perfectly well, and a warning that is usually wrong is a warning nobody
// reads by the second week.
if ($tfBehind) {
    // Name the actual limiter, and quantify the fix rather than listing ideas.
    //
    // This used to end with "split the job into a fast and a slow cron". That
    // is no longer the answer and never was a good one: the job now divides
    // itself between the frames, and a second cron whose absence nobody
    // notices takes every timeframe it owned dark. What is left are the three
    // things that genuinely change the arithmetic, biggest first.
    //
    // Running more often is the biggest and it was not even mentioned. The
    // cycle is (jobs / coins per run) x minutes between runs, so the last term
    // is a straight multiplier: a job that fires every minute instead of every
    // five does five times the work with no other change. That is one edit to
    // one crontab line, and on the install this was written for it turns 190
    // minutes into 38.
    // The worst frame is what the numbers below describe, because that is the
    // one that has to fit and every other frame fits behind it.
    $wt = (string)$worstTf;
    $wBar = $tfCycles[$wt]['bar'];
    $fixes = [];
    if ($everyMin > 1) {
        $fixes[] = '<strong>run cron every minute instead of every ' . $everyMin
            . '</strong> — that is ' . $everyMin . ' times the work with nothing else changed,'
            . ' and it is one edit to one crontab line';
    }
    $fixes[] = 'scan fewer coins or drop a timeframe — you have ' . number_format($scanCoins)
        . ' coin' . ($scanCoins === 1 ? '' : 's') . ' on ' . count($scanTfs) . ' frame'
        . (count($scanTfs) === 1 ? '' : 's') . ' (untick coins under <a href="symbols.php">Coins</a>,'
        . ' or drop a frame from the timeframe list)';
    if ($budgetBound) {
        $fixes[] = 'raise <code>fullscan_max_seconds</code> if your host allows a longer run —'
            . ' each run is stopping on the clock after ' . number_format($perRun)
            . ' coin' . ($perRun === 1 ? '' : 's') . ', so raising the batch will not help';
    } else {
        $fixes[] = 'raise the scan batch in <a href="settings.php#market">Settings</a>';
    }
    $behindList = [];
    foreach ($tfBehind as $t) {
        $behindList[] = '<strong>' . sma_e($t) . '</strong> left ' . $tfCycles[$t]['behind']
            . ' coin' . ($tfCycles[$t]['behind'] === 1 ? '' : 's') . ' unscanned';
    }
    $alerts[] = ['bad', 'The last run could not finish the work that had come due: '
        . implode(', ', $behindList) . '. A bar closes, the scan does not reach the coin, and the'
        . ' setup is over before it is looked at. In order of effect: '
        . implode('; ', $fixes) . '.'];
}
// Watched pairs that the run could not get to.
//
// Different from the rotation and more urgent: a rotation that is behind
// publishes stale verdicts, but a watched pair that is not read cannot flip,
// so no alert fires for it and the member hears nothing at all. This had no
// card and no alert - the only trace was a line in the error log.
$watchDue = (int)Database::setting('watch_due_all', '0');
$watchDone = (int)Database::setting('watch_done_all', '0');
if (Database::setting('watch_stopped_all', '0') === '1' && $watchDue > $watchDone) {
    $alerts[] = ['bad', 'The last cron run read ' . number_format($watchDone) . ' of '
        . number_format($watchDue) . ' watched pairs that were due, then ran out of time. The '
        . 'oldest go first so nothing is skipped forever, but alerts on the rest are late by '
        . 'however long it takes to get round them. Run cron more often, raise '
        . '<code>fullscan_max_seconds</code>, or ask members to watch fewer pairs.'];
}
if ($dropped > 0) {
    $alerts[] = ['warn', $dropped . ' coin(s) took themselves out of the scan after repeated failures.'
        . ' See <a href="symbols.php">Coins</a> for why, and to put them back.'];
}
if ($errs['problems'] > 0) {
    $alerts[] = ['bad', $errs['problems'] . ' thing(s) failed in the last 24 hours, '
        . number_format($errs['events']) . ' time(s) in total. They were caught so no page went'
        . ' down, which is exactly why you would not have noticed. The '
        . '<a href="errors.php">error log</a> lists each one with the setting that fixes it.'];
}
foreach ($workers as $wKey => $w) {
    if ($w['slow']) {
        $alerts[] = ['bad', 'The <strong>' . sma_e($wKey) . '</strong> cron worker takes '
            . (int)$w['cycle'] . ' minutes to get round its ' . number_format((int)$w['jobs'])
            . ' jobs, but its fastest timeframe is ' . (int)$w['fastest'] . ' minutes — a setup is'
            . ' over before it looks again.'
            . ($w['per_run_measured']
               ? ' It gets through ' . number_format((int)$w['per_run']) . ' coin'
                 . ((int)$w['per_run'] === 1 ? '' : 's') . ' a run'
                 . ($w['budget_bound']
                    ? ', and is stopping at its time limit every run — raising the batch will not'
                      . ' change that. Run it more often, raise <code>fullscan_max_seconds</code>, or'
                    : '. Run it more often, raise the batch in'
                      . ' <a href="settings.php#market">Settings</a>, or')
               : ' Run that job more often, raise the batch in'
                 . ' <a href="settings.php#market">Settings</a>, or')
            . ' move those coins out of the scan under <a href="symbols.php">Coins</a>.'];
    }
}
if (!$health['checks']['cron']['ok']) {
    // How long it has been stalled, not just that it is.
    //
    // This is the page's own cron warning, and the banner in _admin.php - the
    // one that used to appear above it and say the same thing twice - is now
    // suppressed here. That banner carried the elapsed time and this did not,
    // so it moves across rather than being lost: "not run recently" and "last
    // ran four hours ago" are different problems, and only the second one
    // tells the operator whether this started just now or has been true all
    // week. The cron command itself is further down this page, which is why
    // the hint's "shown on the admin dashboard" is dropped when we ARE the
    // admin dashboard.
    $cronDetail = (string)($health['checks']['cron']['detail'] ?? '');
    $alerts[] = ['bad', '<strong>The background worker has not run recently</strong>, so signals '
        . 'and alerts are stalling'
        . ($cronDetail !== '' ? ' &mdash; last run ' . sma_e($cronDetail) : '')
        . '. Until it runs again, both fall back to whatever visitor traffic the site happens to '
        . 'get. The cron command to paste is in the <a href="#cron">worker panel below</a>.'];
}

// Work waiting on a person, as opposed to conditions worth knowing about.
//
// These were not on this page at all. A member who has paid and uploaded
// their proof sits in a queue the dashboard never mentioned - the operator
// had to think to go and look, which is precisely what a dashboard is for.
$todo = [];
try {
    $nPay = (int)Database::pdo()->query(
        "SELECT COUNT(*) FROM payments WHERE status = 'awaiting_review'")->fetchColumn();
    if ($nPay > 0) {
        $todo[] = ['pay', $nPay, $nPay === 1 ? 'payment waiting for review' : 'payments waiting for review',
                   'Someone has paid and is waiting to be let in.', 'payments.php', 'Review'];
    }
} catch (\Throwable $e) {
}
try {
    $nErr = (int)Database::pdo()->query('SELECT COUNT(*) FROM error_log WHERE resolved = 0')->fetchColumn();
    if ($nErr > 0) {
        $todo[] = ['err', $nErr, $nErr === 1 ? 'unresolved problem' : 'unresolved problems',
                   'Things the site caught and kept running through.', 'errors.php', 'Open'];
    }
} catch (\Throwable $e) {
}
try {
    $nUnv = (int)Database::pdo()->query('SELECT COUNT(*) FROM members WHERE verified = 0')->fetchColumn();
    if ($nUnv > 2) {
        $todo[] = ['unv', $nUnv, 'accounts never verified their email',
                   'Normal in small numbers. A lot of them at once usually means the mail is not arriving.',
                   'members.php', 'See'];
    }
} catch (\Throwable $e) {
}
// Two tiers, because they are two different things.
//
// "Three payments waiting" is work. "The expectancy is inside its own margin
// of error" is worth knowing and cannot be acted on today. Putting them under
// one heading with one colour is how a panel teaches people to stop reading
// it - the fix is not a better colour, it is not calling the second one an
// alarm in the first place.
$acting = array_values(array_filter($alerts, fn($a) => $a[0] === 'bad'));
$noting = array_values(array_filter($alerts, fn($a) => $a[0] !== 'bad'));
$needs = count($todo) + count($acting);

// What is waiting, in a few words, for the closed row. Naming the kinds is
// the difference between a badge you learn to ignore and one you open: "3"
// says nothing, "1 payment, 8 problems" says whether it can wait.
$smaGist = [];
foreach ($todo as [, $n, $label, , , ]) {
    $smaGist[] = (int)$n . ' ' . sma_e($label);
}
if ($acting) {
    $smaGist[] = count($acting) === 1 ? 'a warning' : count($acting) . ' warnings';
}
// Escaped part by part above, so the separator can be an entity. Printed raw.
$smaGist = implode(' &middot; ', array_slice($smaGist, 0, 3));
?>

<?php // Does anything need me? That is the whole question this page answers
      // first. Everything below is reference. ?>
<?php if ($needs === 0): ?>
  <div class="panel ok-panel">
    <h2 style="margin:0">&#10003; Nothing needs you</h2>
    <p class="hint" style="margin:6px 0 0">The worker is running, the scan keeps up with the
      timeframes it scans, no payment is waiting, and nothing has failed since yesterday.</p>
  </div>
<?php else: ?>
  <?php // Closed until you ask for it.
        //
        // Expanded, this was the whole first screen of the dashboard on a
        // phone - two action rows and a paragraph of prose above anything
        // else, so "how is the site doing" meant scrolling past the answer to
        // a different question. The count is the alarm; the detail is what you
        // open when the count is not zero.
        //
        // Note this does trade loudness for calm: an operator who never taps
        // it does not read what is inside. That is why the count, the colour
        // and the word for what is waiting all stay on the closed row - the
        // summary has to say enough to make not opening it a decision rather
        // than an oversight. ?>
  <details class="panel needs-panel">
    <summary>
      <span class="needs-title">Needs you <span class="needs-count"><?= (int)$needs ?></span></span>
      <span class="needs-gist"><?= $smaGist ?></span>
    </summary>
    <?php foreach ($todo as [$k, $n, $label, $why, $href, $cta]): ?>
      <div class="needs-row">
        <span class="needs-n"><?= (int)$n ?></span>
        <span class="needs-text"><strong><?= sma_e($label) ?></strong>
          <span class="hint" style="display:block"><?= sma_e($why) ?></span></span>
        <a class="btn small" href="<?= $href ?>"><?= sma_e($cta) ?></a>
      </div>
    <?php endforeach; ?>
    <?php foreach ($acting as [$level, $msg]): ?>
      <div class="needs-row needs-bad">
        <span class="needs-dot" aria-hidden="true"></span>
        <span class="needs-text"><?= $msg ?></span>
      </div>
    <?php endforeach; ?>
  </details>
<?php endif; ?>

<?php // Context, not an alarm. Quiet on purpose. ?>
<?php if ($noting): ?>
  <details class="panel noting-panel">
    <summary>Worth knowing <span class="hint">(<?= count($noting) ?>)</span></summary>
    <?php foreach ($noting as [$level, $msg]): ?>
      <p class="noting-row"><?= $msg ?></p>
    <?php endforeach; ?>
  </details>
<?php endif; ?>

<?= $smaTiles ?>

<?php // WHERE THE LOSS IS COMING FROM.
      //
      // The tiles above reconcile a good win rate with a bad expectancy - the
      // average winner against the average loser, and the hit rate that payoff
      // would need. That is WHY the arithmetic comes out negative. This is
      // WHICH CALLS are doing it, which on a record that is mostly one
      // timeframe is a different question with a different answer.
      //
      // Shown whether the record is winning or losing: the weakest slice of a
      // profitable engine is the next thing to fix, and a panel that only
      // appears in the bad case is one nobody learns to read.
      if (($triage['total'] ?? 0) >= \SignalMasterAi\Triage::MIN_N * 2): ?>
<div class="panel">
  <h2>Where the loss is coming from</h2>
  <p class="hint">The published record split four ways, each with its 95% interval. A slice is
    named only when that whole interval sits below zero on
    <strong><?= (int)\SignalMasterAi\Triage::MIN_N ?>+</strong> settled calls
    <em>and</em> removing it would lift the record by at least
    <strong><?= number_format(\SignalMasterAi\Triage::MIN_GAIN, 2) ?>R</strong> a trade.
    Below zero on its own is not the test: when the record is losing, most large slices of it dip
    below zero, and the biggest one usually reads worst simply for being biggest. The
    <strong>Gain</strong> column is the question &mdash; what the rest of the record is worth
    without it.</p>

  <?php // WHAT A LOSS COSTS, AND WHAT A WIN PAYS.
        //
        // Printed above the slice table, and whether or not the slice table
        // has anything in it, because on a record that picks direction well
        // and still loses this is the whole answer and the slices are a
        // distraction from it. Two calls in three ending in profit is not the
        // problem; the most common winning exit banking a fifth of what the
        // most common losing exit gives up is.
        if (($triageExits['n'] ?? 0) >= \SignalMasterAi\Triage::MIN_N
            && $triageExits['need_avg'] !== null): ?>
    <div class="tbl-scroll">
    <table class="grid">
      <tr><th>How it ended</th><th>Count</th><th>Share</th><th>Avg R</th></tr>
      <?php foreach ($triageExits['rows'] as $label => $er): ?>
        <tr><td><?= sma_e((string)$label) ?></td>
            <td><?= number_format((int)$er['n']) ?></td>
            <td><?= sma_e((string)$er['share']) ?>%</td>
            <td style="font-weight:700;color:<?= $er['avg_r'] >= 0 ? 'var(--up)' : 'var(--down)' ?>">
              <?= sprintf('%+.3f', (float)$er['avg_r']) ?></td></tr>
      <?php endforeach; ?>
    </table>
    </div>
    <p class="hint">
      <?php $cost = (float)$triageExits['cost_r']; ?>
      A loss settles at <strong><?= sprintf('%+.3fR', (float)$triageExits['stop_avg']) ?></strong>
      on average.
      <?php if ($cost > 0.005): ?>
        A stop sized to 1R should settle at &minus;1.000R, so
        <strong><?= sprintf('%.3fR', $cost) ?></strong> of every loss is fees, spread and the gap
        between the stop price and the fill &mdash; before the market has done anything. That is
        either the <a href="settings.php#signals">round-trip cost</a> being set optimistically or
        the frames being too fast for a fee to disappear into the move.
      <?php else: ?>
        That is a clean 1R, so costs are not eating the losses.
      <?php endif; ?>
    </p>
    <p class="hint">
      At that loss size and that hit rate, the average winner has to make
      <strong><?= sprintf('%+.3fR', (float)$triageExits['need_avg']) ?></strong> for the record to
      come out flat. It makes
      <strong style="color:<?= $triageExits['short'] > 0 ? 'var(--down)' : 'var(--up)' ?>">
        <?= sprintf('%+.3fR', (float)$triageExits['win_avg']) ?></strong><?php
      if ($triageExits['short'] > 0): ?> &mdash; short by
        <strong><?= sprintf('%.3fR', (float)$triageExits['short']) ?></strong> a trade.
        The lever is the <strong>stop's own distance</strong> &mdash; every published target is a
        multiple of it, so widening or tightening the stop reshapes the whole ladder at once &mdash;
        and how much of the position comes off at the first target. The
        <a href="engine.php#levels">stop &amp; target optimiser</a> searches exactly that on this
        install's own history.<?php else: ?>, which clears it.<?php endif; ?>
    </p>
  <?php endif; ?>

  <?php // CAN THE WIN RATE GO TO 80%?
        //
        // Asked, and it has an arithmetic answer that is not the useful one.
        // Moving the first target close enough to entry does raise the hit
        // rate - and lowers the payoff faster, so the break-even bar rises
        // faster than the number does.
        //
        // The useful answer is the ceiling, and it comes out of data every
        // settled signal already carries. A trade whose worst excursion
        // reached the stop is a loser at EVERY target distance, so the best
        // win rate any target policy can reach is the share that never touched
        // the stop. Printed first, because it is the one line that decides
        // whether the question has an answer at all.
        if (($triageCurve['n'] ?? 0) >= \SignalMasterAi\Triage::MIN_N
            && $triageCurve['ceiling'] !== null): ?>
    <h3 style="margin:18px 0 6px">What the win rate can be</h3>
    <p class="hint">
      <strong><?= sma_e((string)$triageCurve['ceiling']) ?>%</strong> is the ceiling. Over
      <?= number_format((int)$triageCurve['n']) ?> settled calls,
      <strong><?= number_format((int)$triageCurve['stopped']) ?></strong> touched their stop &mdash;
      and a call that was stopped out cannot reach a target however near you put it. So no target
      distance produces a win rate above that figure, and moving the target only chooses a point on
      the curve below it.
      <?php if ($triageCurve['ceiling'] < 80): ?>
        <strong>80% is not reachable by moving targets on this record.</strong> Getting there needs
        fewer stop-outs &mdash; a wider stop, which makes every loss cost more, or entries that go
        the right way more often.
      <?php endif; ?>
    </p>
    <div class="tbl-scroll">
    <table class="grid">
      <tr><th>First target</th><th>Win rate it would have given</th><th>Expectancy</th></tr>
      <?php foreach ($triageCurve['rows'] as $cr):
            $isBest = $triageCurve['best'] && abs($cr['tp1'] - $triageCurve['best']['tp1']) < 0.001; ?>
        <tr<?= $isBest ? ' style="background:color-mix(in srgb, var(--accent) 8%, transparent)"' : '' ?>>
          <td><?= number_format((float)$cr['tp1'], 2) ?>R<?= $isBest ? ' <span class="hint">best here</span>' : '' ?></td>
          <td><?= sma_e((string)$cr['win_rate']) ?>%</td>
          <td style="font-weight:700;color:<?= $cr['exp_r'] >= 0 ? 'var(--up)' : 'var(--down)' ?>">
            <?= sprintf('%+.3f', (float)$cr['exp_r']) ?></td></tr>
      <?php endforeach; ?>
    </table>
    </div>
    <p class="hint"><strong>Measured, not simulated.</strong> Every settled signal records how far
      price went in its favour and how far against, in units of its own risk. This reads those - so
      it is what the calls you published actually did, not a replay. A call that reached both its
      target and its stop is counted as the stop, because the excursions do not say which came
      first; that makes every win rate here a floor rather than a guess.
      The expectancy column treats the target as closing the whole position, so it is optimistic
      about near targets and pessimistic about far ones relative to your partial ladder &mdash; use
      it to compare distances, not as a forecast. To search entries as well as levels, the
      <a href="engine.php#levels">stop &amp; target optimiser</a> replays the engine itself.</p>
  <?php endif; ?>

  <?php if (!$triageActs): ?>
    <p class="hint"><strong>Nothing here is measurably losing on its own.</strong> No timeframe,
      session, direction or grade has an interval entirely below zero on a readable sample, so
      whatever the record is doing it is doing everywhere. That points at the exit plan rather than
      at a switch: the
      <a href="engine.php#levels">stop &amp; target optimiser</a> searches the stop distance and
      the exit split, and <a href="settings.php#signals">Settings &rsaquo; Signals</a> holds the
      round-trip cost and the cost ceiling that decide how much of each R the fees take.</p>
  <?php else: ?>
    <div class="tbl-scroll">
    <table class="grid">
      <tr><th>Slice</th><th>Settled</th><th>Avg R</th><th>95% interval</th>
          <th>Record without it</th><th>Gain</th></tr>
      <?php foreach ($triageActs as $ts): ?>
        <tr>
          <td><strong><?= sma_e((string)$ts['label']) ?></strong>
            <br><span class="hint"><?= sma_e((string)$ts['dim_label']) ?> &middot;
              <?= sma_e((string)$ts['share']) ?>% of the record</span></td>
          <td><?= number_format((int)$ts['n']) ?></td>
          <td style="font-weight:700;color:var(--down)"><?= sprintf('%+.3f', (float)$ts['avg_r']) ?></td>
          <td class="hint"><?= $ts['ci'] ? sprintf('%+.3f to %+.3f', $ts['ci'][0], $ts['ci'][1]) : '&mdash;' ?></td>
          <td><?php $w = $ts['without']; ?>
            <strong style="color:<?= $w['avg_r'] >= 0 ? 'var(--up)' : 'var(--down)' ?>">
              <?= sprintf('%+.3f', (float)$w['avg_r']) ?>R</strong>
            <span class="hint">over <?= number_format((int)$w['n']) ?></span></td>
          <td style="font-weight:700;color:var(--up)"><?= sprintf('%+.3f', (float)$ts['gain']) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php // THE DECISION IS RARELY ONE SLICE.
            //
            // Two frames each measurably losing cannot have their two
            // counterfactuals read together - each was computed with the other
            // still in the record. Disjoint slices subtract exactly, so this
            // row is a real figure and not the sum of two estimates. On the
            // install that prompted the panel it is also the row that changes
            // the decision: dropping both losing frames still left the record
            // under water, which the two rows above, read together, would have
            // suggested otherwise. ?>
      <?php foreach (($triage['combined'] ?? []) as $cd): ?>
        <tr style="background:color-mix(in srgb, var(--accent) 8%, transparent)">
          <td><strong>Both <?= sma_e(strtolower((string)$cd['dim_label'])) ?>s together</strong>
            <br><span class="hint"><?= sma_e(implode(' + ', (array)$cd['keys'])) ?> &middot;
              <?= number_format((int)$cd['cut_n']) ?> calls dropped</span></td>
          <td colspan="3" class="hint">every flagged slice in this dimension removed at once</td>
          <td><strong style="color:<?= $cd['avg_r'] >= 0 ? 'var(--up)' : 'var(--down)' ?>">
              <?= sprintf('%+.3f', (float)$cd['avg_r']) ?>R</strong>
            <span class="hint">over <?= number_format((int)$cd['n']) ?></span></td>
          <td style="font-weight:700;color:var(--up)"><?= sprintf('%+.3f', (float)$cd['gain']) ?></td>
        </tr>
      <?php endforeach; ?>
    </table>
    </div>
    <p class="hint">Where each switch lives:
      <?php $seenDims = [];
            foreach ($triageActs as $ts) {
                if (isset($seenDims[$ts['dim']])) { continue; }
                $seenDims[$ts['dim']] = true;
                $d = \SignalMasterAi\Triage::DIMS[$ts['dim']] ?? null;
                if (!$d) { continue; }
                echo '<a href="' . sma_e($d[1]) . '">' . $d[2] . '</a> &middot; ';
            } ?>
      <span>nothing is changed for you.</span></p>
    <p class="hint"><strong>Read the last column as a ceiling, not a forecast.</strong> Each slice
      was chosen by looking at this sample, so removing it flatters this sample by construction &mdash;
      the same cut on next month's calls will do less. And a slice that loses is not always a slice
      to delete: a timeframe with a real edge and a stop that is too tight for its noise reads
      exactly like this, and the fix for that one is the
      <a href="engine.php#levels">level optimiser</a>, not the off switch.</p>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php // "Everything in here" used to sit at this point: the same seven
      // subjects the header already opens, redrawn as boxes.
      //
      // Removed. It was a second copy of the navigation, immediately below
      // the first, and it cost five screens of scrolling on a phone before
      // the dashboard's own content. A menu is worth repeating only when the
      // repeat says something the original does not; this one listed the
      // identical links from the identical Panel::CATEGORIES.
      //
      // The header dropdowns still carry every one of those links, with the
      // one-line description of each, so nothing became unreachable. ?>

<?php if ($workers): ?>
<div class="panel">
  <h2>Cron workers</h2>
  <p class="hint">This site is running more than one cron job, each taking a share of the scan.
    Every worker is judged against its <em>own</em> fastest timeframe, because that is the only
    comparison that answers whether it keeps up &mdash; a 30-minute cycle is fine for the daily and
    useless for 15m.</p>
  <div class="tbl-scroll">
  <table class="grid">
    <tr class="head"><th>Worker</th><th>Timeframes</th><th>Runs every</th><th>Jobs</th>
      <th>Coins/run</th><th>Full cycle</th><th>Keeps up?</th></tr>
    <?php foreach ($workers as $wKey => $w): ?>
    <tr>
      <td><strong><?= sma_e($wKey) ?></strong></td>
      <td class="muted"><?= sma_e(implode(', ', $w['tfs'])) ?></td>
      <td><?= (int)$w['every'] ?>m<?= $w['measured'] ? '' : ' <span class="muted small">(assumed)</span>' ?></td>
      <td><?= number_format((int)$w['jobs']) ?></td>
      <td><?= number_format((int)$w['per_run']) ?><?= $w['per_run_measured']
        ? ($w['budget_bound'] ? ' <span class="muted small">(time-capped)</span>' : '')
        : ' <span class="muted small">(batch, not yet measured)</span>' ?></td>
      <td style="color:<?= $w['slow'] ? 'var(--down)' : 'var(--up)' ?>"><?= (int)$w['cycle'] ?>m</td>
      <td><?= $w['slow']
        ? '<span style="color:var(--down)">no &mdash; slower than its own ' . (int)$w['fastest'] . 'm frame</span>'
        : '<span style="color:var(--up)">yes</span>' ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
  </div>
</div>
<?php endif; ?>

<?php // The alert list used to be printed here as well, in full.
      //
      // $alerts is already split above - the 'bad' ones into "Needs you" and
      // the rest into "Worth knowing" - and this block, which predates that
      // split and was never removed with it, then printed every one of them a
      // second time further down the same page. So each warning appeared
      // twice, in two different styles, and an operator reading "the
      // background worker has not run recently" in two places had no way to
      // tell whether that was one problem or two.
      //
      // Nothing is lost by deleting it: every entry in $alerts is in exactly
      // one of the two panels above. The "nothing needs attention" line that
      // used to sit in its else branch is gone for the same reason - the
      // "Nothing needs you" panel above already says it, and said it first. ?>

<h2>System health</h2>
<div class="form-card">
  <p class="hint" style="margin-bottom:10px">
    When the feed stalls or the worker dies the site keeps serving older and older signals as though
    they were current. These checks are also public on
    <a href="../status.php" target="_blank" rel="noopener" style="color:var(--accent)">status.php</a>.
  </p>
  <div class="tbl-scroll">
  <table class="grid">
    <?php foreach ($health['checks'] as $c): ?>
      <tr>
        <td style="width:34px;font-weight:800;color:<?= $c['ok'] ? 'var(--up)' : (!empty($c['soft']) ? 'var(--muted)' : 'var(--down)') ?>">
          <?= $c['ok'] ? '✓' : (!empty($c['soft']) ? '–' : '✕') ?>
        </td>
        <td><strong><?= sma_e($c['label']) ?></strong>
          <div class="hint"><?= sma_e($c['detail']) ?><?= !$c['ok'] ? ' — ' . sma_e($c['hint']) : '' ?></div>
        </td>
      </tr>
    <?php endforeach; ?>
  </table>
  </div>
</div>
<?php
// These count what is IN THE DATABASE. Every public page counts what was
// PUBLISHED, and the two differ by the grade floor, the hidden coins and the
// timeframes the site does not run.
//
// Unlabelled, that is the win-rate problem again in a different place: an
// operator reads "582 signals generated" here, opens the scanner, sees one
// row, and has no way to tell whether the site is broken or working exactly
// as they configured it. Both numbers were always right; neither said which
// question it was answering.
$pubCount  = \SignalMasterAi\TrackRecord::publishedCount();
$scanCount = (int)$pdo->query('SELECT COUNT(*) FROM symbols WHERE enabled = 1 AND scan = 1')->fetchColumn();
$gradeFloor = \SignalMasterAi\Publish::floor();
?>
<h2>What is stored</h2>
<p class="hint" style="margin:-6px 0 10px">Everything in the database, including what the site does
  not publish. The public pages count only published calls &mdash; the two lines below say by how
  much they differ, and why. To remove any of it,
  <a href="data.php">System &rsaquo; Delete data</a> counts each kind for one coin or all of them
  and deletes only what you tick.</p>
<div class="cards">
  <div class="card"><div class="num"><?= $stats['symbols'] ?></div><div class="lbl">Active symbols</div>
    <?php if ($scanCount !== $stats['symbols']): ?>
      <div class="lbl" style="color:var(--warn)"><?= $scanCount ?> in the scan rotation</div>
    <?php endif; ?></div>
  <div class="card"><div class="num"><?= number_format($stats['candles']) ?></div><div class="lbl">Candles stored on server</div></div>
  <?php
  // "3 signals generated" beside "17 on the scanner" reads like a
  // contradiction, and it is not: they count different things. This tile is
  // the LEDGER - one row per call ever published, written when a pair takes on
  // a trade idea it does not already have open. The scanner shows the CURRENT
  // VERDICT of every pair, which persists between calls and is re-stated on
  // every scan without writing anything. A coin that has been bullish all week
  // is one ledger row and seven days of live verdict.
  //
  // Both numbers were already on this page with nothing to tell an operator
  // that, so the obvious reading was that signals were going missing.
  $liveCalls = (int)$pdo->query(
      "SELECT COUNT(*) FROM signal_state WHERE `signal` != 'NEUTRAL'")->fetchColumn();
  ?>
  <div class="card"><div class="num"><?= number_format($stats['signals']) ?></div>
    <div class="lbl">Calls published (all time)</div>
    <?php if ($pubCount < $stats['signals']): ?>
      <div class="lbl"><strong><?= number_format($pubCount) ?></strong> above the floor
        <?php if ($gradeFloor !== ''): ?>(grade <?= sma_e($gradeFloor) ?>+)<?php endif; ?></div>
    <?php endif; ?>
    <div class="lbl" style="color:var(--muted)"><strong><?= number_format($liveCalls) ?></strong>
      live on the board now</div></div>
  <div class="card"><div class="num"><?= $stats['rules'] ?></div><div class="lbl">Active knowledge rules</div></div>
  <div class="card"><div class="num"><?= number_format($stats['news']) ?></div><div class="lbl">News headlines stored</div></div>
  <div class="card"><div class="num"><?= number_format($stats['members']) ?></div><div class="lbl">Registered members</div></div>
  <div class="card"><div class="num"><?= number_format($stats['push']) ?></div><div class="lbl">Push alert subscribers</div></div>
</div>

<h2>Latest signals</h2>
<?php
// Raw engine output, and it has to be said. This is SELECT * ... LIMIT 10
// with no filter at all, so it shows grades below the publish floor and
// timeframes the site has switched off - rows that appear nowhere a visitor
// can reach. That is the right list for an operator checking the engine is
// alive, and the wrong list to compare against the scanner, which is exactly
// what its position on this page invites.
$siteTfsNow = \SignalMasterAi\Visibility::siteTfs($config['market']['intervals'] ?? []);
?>
<p class="hint" style="margin:-6px 0 10px">Straight from the engine, unfiltered &mdash; including
  grades below your publish floor<?= $gradeFloor !== '' ? ' (' . sma_e($gradeFloor) . '+)' : '' ?>
  and timeframes you have switched off. Rows marked <span style="color:var(--warn)">not published</span>
  do not appear on the scanner, the track record or in any alert.</p>
<?php if (!$recent): ?>
  <p class="hint">No signals yet. Open the public site and press "Analyse chart", or wait for visitors.</p>
<?php else: ?>
<div class="tbl-scroll">
<table class="grid">
  <tr><th>When (UTC)</th><th>Symbol</th><th>TF</th><th>Signal</th><th>Score</th><th>Confidence</th><th>Price</th><th>Published?</th></tr>
  <?php foreach ($recent as $s): ?>
  <?php
  // Why this row is or is not public, worked out per row rather than left to
  // the reader. Three things can hide it and they are not interchangeable -
  // "grade B, floor is A" is a decision the operator made and can undo,
  // "timeframe switched off" is usually a leftover from before they switched
  // it off, and NEUTRAL was never a call at all.
  $rInds = json_decode((string)$s['indicators'], true);
  $rGrade = is_array($rInds) ? (string)($rInds['grade'] ?? '') : '';
  $why = [];
  if ($s['signal'] === 'NEUTRAL') {
      $why[] = 'no call';
  }
  if (!in_array((string)$s['tf'], $siteTfsNow, true)) {
      $why[] = $s['tf'] . ' is switched off';
  }
  if ($gradeFloor !== '' && $rGrade !== ''
      && !\SignalMasterAi\WebPush::gradePasses($gradeFloor, (string)$s['indicators'])) {
      $why[] = 'grade ' . $rGrade . ' is below ' . $gradeFloor;
  }
  ?>
  <tr>
    <td><?= gmdate('Y-m-d H:i', (int)$s['created_at']) ?></td>
    <td><?= sma_e($s['symbol']) ?></td>
    <td><?= sma_e($s['tf']) ?></td>
    <td><span class="badge <?= strtolower($s['signal']) ?>"><?= sma_e($s['signal']) ?></span></td>
    <td><?= $s['score'] > 0 ? '+' : '' ?><?= sma_e((string)$s['score']) ?></td>
    <td><?= sma_e((string)$s['confidence']) ?>%</td>
    <td><?= sma_e((string)$s['price']) ?></td>
    <td><?php if (!$why): ?><span style="color:var(--up)">yes</span><?php else: ?>
      <span style="color:var(--warn)" title="<?= sma_e(implode('; ', $why)) ?>">no &mdash;
        <?= sma_e($why[0]) ?></span><?php endif; ?></td>
  </tr>
  <?php endforeach; ?>
</table>
</div>
<?php endif; ?>

<?php
// "The engine is generating signals - why is the scanner showing one row?"
//
// Both numbers on this page are right and they count different things, and
// until now the only way to find out which filter was eating the difference
// was to guess. So run the scanner's own board with the scanner's own
// defaults, and report what it dropped and why. This is not a description of
// the filters - it is the filters, executed, on this install's data.
//
// The board is a single query over signal_state (one row per coin and
// timeframe), so the cost is the same as loading the scanner once.
$board = \SignalMasterAi\Scanner::boardWithDrops(['exclude_neutral' => true, 'sort' => 'grade'], 0);
$boardRows = $board['rows'];
$drops = $board['drops'];
$dropSeen = (int)($drops['seen'] ?? 0);
?>
<h2>Why the scanner shows what it shows</h2>
<div class="form-card">
  <p class="hint" style="margin-top:0">The scanner reads stored verdicts, one per coin and
    timeframe, and drops rows for the reasons below &mdash; in this order. This ran the real board
    just now, with the filters a visitor gets by default.</p>
  <?php if ($dropSeen === 0): ?>
    <?php
    // Nothing stored is a different problem from everything filtered, and it
    // has four distinct causes that need four different actions. Naming the
    // one that applies is the whole value of this branch: "no signals yet" on
    // a dashboard whose worker, feed and dispatcher all read green sends an
    // operator looking in the three places where nothing is wrong.
    $noWhy = [];
    if (!\SignalMasterAi\Master::master('signals')) {
        $noWhy[] = ['The signals master switch is off.',
                    'settings.php#masters', 'Turn it on under Master switches'];
    } elseif (!\SignalMasterAi\Master::on('signals', 'scan')) {
        $noWhy[] = ['The background scan is switched off, so nothing is analysed at all.',
                    'settings.php#cron', 'Background worker'];
    } elseif (Database::setting('fullscan_enabled', '1') !== '1'
              && count(\SignalMasterAi\WebPush::watchedPairs())
                 + count(\SignalMasterAi\EmailAlerts::watchedPairs()) === 0) {
        $noWhy[] = ['The scan is set to cover only pairs someone watches, and nobody is watching '
                    . 'anything yet — so its work list is empty and every run does nothing.',
                    'settings.php#cron', 'Set it to cover every coin in the rotation'];
    } elseif ($scanCoins === 0) {
        $noWhy[] = ['No coin is in the scan rotation.', 'symbols.php', 'Add coins'];
    } elseif ((int)Database::setting('cron_last_run', '0') === 0) {
        $noWhy[] = ['The background worker has never run.', '#cron', 'Install the cron job'];
    }
    ?>
    <?php if ($noWhy): ?>
      <p class="hint" style="color:var(--warn-text)"><strong>Nothing is being
        scanned.</strong> <?= sma_e($noWhy[0][0]) ?>
        <a href="<?= sma_e($noWhy[0][1]) ?>"><?= sma_e($noWhy[0][2]) ?></a>.</p>
    <?php else: ?>
      <p class="hint"><strong>No stored verdicts yet.</strong> The scan is configured and the worker
        has run &mdash; give it a full rotation. The cycle estimate is under
        <a href="#cron">Automation</a> below.</p>
    <?php endif; ?>
  <?php else: ?>
  <div class="tbl-scroll">
  <table class="grid">
    <tr class="head"><th>Stage</th><th>Pairs</th><th>Why</th></tr>
    <tr>
      <td><strong>Stored verdicts</strong></td>
      <td><strong><?= number_format($dropSeen) ?></strong></td>
      <td class="muted">One per enabled coin per timeframe, from the last background scan</td>
    </tr>
    <?php foreach (\SignalMasterAi\Scanner::DROP_REASONS as $key => $why): ?>
      <?php $n = (int)($drops[$key] ?? 0); if ($n === 0) { continue; } ?>
      <tr>
        <td class="muted">&minus; dropped</td>
        <td style="color:var(--warn)"><?= number_format($n) ?></td>
        <td class="muted"><?= sma_e($why) ?><?php
          if ($key === 'grade' && $gradeFloor !== ''): ?> &mdash; currently
            <strong><?= sma_e($gradeFloor) ?></strong>, under
            <a href="settings.php#publish">What the site publishes</a><?php endif; ?><?php
          if ($key === 'tf_off'): ?> &mdash; the site runs
            <strong><?= sma_e(implode(', ', $siteTfsNow)) ?></strong>, under
            <a href="settings.php#timeframes">Timeframes</a><?php endif; ?><?php
          if ($key === 'stale'): ?> &mdash; a longer scan cycle than the setups live for; see the
            cycle estimate under <a href="settings.php#cron">Background worker</a><?php endif; ?></td>
      </tr>
    <?php endforeach; ?>
    <tr>
      <td><strong>On the scanner</strong></td>
      <td><strong style="color:var(--up)"><?= number_format(count($boardRows)) ?></strong></td>
      <td class="muted">What a visitor sees right now</td>
    </tr>
  </table>
  </div>
  <?php
  // The single biggest cause, named. A table of eight numbers still asks the
  // reader to find the big one, and the answer to "why" is almost always one
  // row of it.
  $topDrop = ''; $topN = 0;
  foreach (\SignalMasterAi\Scanner::DROP_REASONS as $k => $w) {
      if ((int)($drops[$k] ?? 0) > $topN) { $topN = (int)$drops[$k]; $topDrop = $w; }
  }
  ?>
  <?php if ($topN > 0): ?>
    <p class="hint"><strong>Biggest cause:</strong> <?= sma_e($topDrop) ?>
      &mdash; <?= number_format($topN) ?> of <?= number_format($dropSeen) ?> pairs.</p>
  <?php endif; ?>
  <p class="hint">None of these lose a signal. Everything the engine produces is stored, verified
    and learned from whether or not it is published &mdash; that is why this page's
    <strong>Signals generated</strong> is larger, and why it should be.</p>
  <?php endif; ?>
</div>

<h2>Automation</h2>
<?php
$lastDispatch = (int)Database::setting('last_dispatch', '0');
$cronToken = Database::setting('cron_token');
$siteUrl = Database::setting('site_url', 'https://your-site');
$age = $lastDispatch > 0 ? time() - $lastDispatch : -1;
?>
<?php // Named, because the stalled-worker alert at the top of this page links
      // straight here rather than telling the operator to go and find it. ?>
<div class="form-card" id="cron">
  <p style="font-size:13px;margin-bottom:8px">
    Last alert dispatch:
    <strong style="color:<?= $age >= 0 && $age < 900 ? 'var(--up)' : 'var(--warn)' ?>">
      <?= $age < 0 ? 'never yet' : ($age < 60 ? $age . 's ago' : ($age < 3600 ? intdiv($age, 60) . 'm ago' : intdiv($age, 3600) . 'h ago')) ?>
    </strong>
  </p>
  <p class="hint">Alerts also piggyback on site traffic automatically, but a real cron job keeps
    push/email alerts and background signals flowing even with zero visitors. Set up either:</p>
  <?php
  // The bare word "php" is why most cPanel cron jobs silently do nothing: cron
  // runs with a minimal PATH, and where "php" resolves at all it is often an
  // ancient system build with none of the extensions this app needs. The
  // absolute path to the interpreter that is running RIGHT NOW is the one that
  // is known to work, so print that rather than a guess - and fall back to the
  // usual cPanel location when PHP_BINARY is unavailable.
  $phpBin = defined('PHP_BINARY') && PHP_BINARY !== '' && is_string(PHP_BINARY)
      ? PHP_BINARY : '/usr/local/bin/php';
  // PHP_BINARY under mod_php/FPM points at the web SAPI, which cannot run a
  // script from cron. Map the common cPanel layouts onto their CLI twin.
  $phpCli = str_replace(['/bin/php-cgi', '/bin/php-fpm', '/sbin/php-fpm'], '/bin/php', $phpBin);
  ?>
  <p class="hint" style="margin-top:8px">
    <strong>Server cron</strong> (cPanel &rarr; Cron Jobs &rarr; every minute):<br>
    <code>* * * * * <?= sma_e($phpCli) ?> <?= sma_e(SMA_ROOT) ?>/cron.php &gt;/dev/null 2&gt;&amp;1</code><br>
    <span style="display:block;margin-top:6px">Use the full path to PHP, not just <code>php</code> — cron has almost no
      PATH and the bare word usually resolves to nothing, or to an old build without the extensions
      this app needs. The path above is the interpreter serving this page. If cPanel still reports
      nothing, drop <code>&gt;/dev/null 2&gt;&amp;1</code> so it emails you the error.</span>
  </p>
  <p class="hint" style="margin-top:8px">
    <strong>Web cron</strong> (works everywhere, no shell needed):<br>
    <code><?= sma_e($siteUrl) ?>/cron.php?token=<?= sma_e($cronToken) ?></code><br>
    <span style="display:block;margin-top:6px">Same job over HTTP. In cPanel:
      <code>* * * * * curl -s "<?= sma_e($siteUrl) ?>/cron.php?token=<?= sma_e($cronToken) ?>" &gt;/dev/null 2&gt;&amp;1</code>
      — if the URL opens in your browser, this line works.</span>
  </p>
</div>

<h2>Market data cache</h2>
<?php if (!$caches): ?>
  <p class="hint">Nothing cached yet - the first chart view will populate this.</p>
<?php else: ?>
<div class="tbl-scroll">
<table class="grid">
  <tr><th>Symbol</th><th>Timeframe</th><th>Candles</th><th>Last refreshed (UTC)</th><th>Age</th></tr>
  <?php foreach ($caches as $c): $age = time() - (int)$c['fetched_at']; ?>
  <tr>
    <td><?= sma_e($c['symbol']) ?></td>
    <td><?= sma_e($c['tf']) ?></td>
    <td><?= (int)$c['candles'] ?></td>
    <td><?= gmdate('Y-m-d H:i:s', (int)$c['fetched_at']) ?></td>
    <td><?= $age < 60 ? $age . 's' : ($age < 3600 ? intdiv($age, 60) . 'm' : intdiv($age, 3600) . 'h') ?> ago</td>
  </tr>
  <?php endforeach; ?>
</table>
</div>
<?php endif; ?>
<?php admin_footer(); ?>
