<?php
declare(strict_types=1);

/**
 * Public track record: every verified signal - wins AND losses - with the
 * exact outcome measured against real price action. Nothing is curated or
 * hidden; this page exists so visitors can judge the engine for themselves.
 * Admin toggle: Settings > Legal & trust.
 */

$config = require __DIR__ . '/src/bootstrap.php';

use SignalMasterAi\Database;
use SignalMasterAi\MemberAuth;

if (sma_setting('performance_page_enabled', '1') !== '1') {
    header('Location: index.php');
    exit;
}

MemberAuth::start();
$member = MemberAuth::current();
$siteName = sma_setting('site_name', $config['app_name']);
$tagline  = sma_setting('site_tagline', $config['app_tagline']);
$hex = fn(string $k, string $d) => preg_match('/^#[0-9a-f]{6}$/i', $v = sma_setting($k, $d)) ? $v : $d;
$accent = $hex('accent_color', \SignalMasterAi\View::BRAND_DEFAULTS['accent_color']);
$upCol  = $hex('up_color', \SignalMasterAi\View::BRAND_DEFAULTS['up_color']);
$downCol= $hex('down_color', \SignalMasterAi\View::BRAND_DEFAULTS['down_color']);

$pdo = Database::pdo();

// Coins the operator has switched off are not part of the record.
//
// This page never checked. A coin turned off under Coins - delisted, retired,
// removed from the product - vanished from the chart, the scanner and the
// alerts, and went on publishing its settled history here forever, so the
// headline figures described a market the site no longer covers. The master
// switch now means what it says, and the per-surface "Track record" toggle
// can drop a coin from this page alone while it keeps trading everywhere else.
// Every figure on this page is filtered the same way, so the fragment carries
// both rules: coins the operator hides from the track record, and the publish
// floor.
//
// The floor belongs here and not only on the scanner. A record that counts
// signals nobody was ever shown is not this site's record - it answers "how
// did the engine do", when the question a visitor is asking is "how did the
// calls I would have seen do". Those differ by exactly the setups the operator
// chose not to publish. Only `symbols` is joined alongside and it has no grade
// column, so the unaliased reference stays unambiguous.
if (!\SignalMasterAi\Master::on('signals', 'track')) {
    header('Location: index.php');
    exit;
}
// One definition of "published", shared with the dashboard and the landing
// page so the three cannot drift apart again. See TrackRecord for what went
// wrong when each of them carried its own copy.
$visible = \SignalMasterAi\TrackRecord::sql();

// One record, for the whole engine.
//
// The headline figures were briefly filterable per timeframe, which split the
// page into several records that each looked thinner than the real one and
// made the first question a visitor had to answer "which of these am I
// reading?". The per-timeframe comparison still exists further down, as a
// breakdown of one record rather than a switch between several - that is the
// place for it, and it is the place it was before.

// The headline figures, from the one place that defines them.
//
// This page used to compute them here - the same SELECT, over the same
// filter, written out a second time. That is not a copy that happens to
// agree; it is a copy that agrees UNTIL SOMEBODY EDITS ONE OF THEM, and the
// two pages it has to agree with are the landing page and the dashboard,
// neither of which is edited in the same sitting as this one. The whole
// reason TrackRecord exists is that four such copies drifted apart and the
// site published three different win rates at once. Leaving the largest of
// them in place and calling the problem solved would have left the next
// edit free to do it all over again.
//
// (A win is a trade that MADE MONEY, not one that reached a label:
// "confirmed" means TP1 came before the stop, and with part of the position
// banked there a trade can still end fractionally negative once costs are
// taken. That definition now lives in TrackRecord::stats() with the rest.)
// THE HEADLINE OVER A WINDOW THE READER CHOOSES.
//
// One lifetime figure answers "is this any good" and cannot answer "is it any
// good NOW", and those diverge exactly when it matters - an engine that was
// right for six months and has been wrong for three weeks reads fine on the
// only number the page showed. The window is a query string so it is
// linkable and crawlable, defaults to everything, and the counts underneath
// follow it so the table and the headline can never describe different sets.
$perDays = (int)($_GET['days'] ?? 0);
if (!in_array($perDays, [7, 30, 90, 365], true)) {
    $perDays = 0;
}
// The SAME window clause TrackRecord::stats() builds internally, so every
// breakdown further down the page counts the identical population the
// headline does. This used to be applied only to the headline: picking "Last
// 7 days" shrank the three tiles at the top to a week's sample while every
// breakdown table below - by grade, by type, by timeframe, by session, how
// signals ended - kept showing all-time figures, so the page contradicted
// itself (a "By timeframe" column summing to more than the "verified
// signals" tile above it) exactly the way the byGrade/byType visibility bug
// once did, just via a different missing filter.
$window = $perDays > 0 ? ' AND closed_at >= ' . (time() - $perDays * 86400) : '';
$sum = \SignalMasterAi\TrackRecord::stats($perDays ?: null);
$expired = \SignalMasterAi\TrackRecord::expiredCount();
$total = $sum['total'];
$wins = $sum['wins'];
$winrate = $sum['winRate'];
$avgR = $sum['avgR'];

// Only settled trades ever reach this list - an open call is never on this
// page. Which is the important half of the guarantee; the other half is that
// a premium coin's published plan is the product, so the numbers that make
// the record credible are shown to everyone while the prices themselves stay
// behind the tier the coin is sold under. Nothing is hidden that could change
// a statistic: the outcome, the R and every count are identical for a guest
// and for a paying member.
// Paged, because a hundred was a cap and not a decision.
//
// An install with a few hundred settled trades published the newest hundred
// and simply stopped - no count, no link, nothing to say the rest existed. The
// page claims to show every verified trade, so it has to either show them or
// say how many it is holding back.
//
// `show` is how many rows to render. It is a plain query parameter so the
// button works with no JavaScript at all; the script below turns it into an
// append instead of a reload, and `rows=1` returns the new slice on its own.
// 0 means every trade, and there is no ceiling above it.
//
// A cap here is a promise this page cannot keep - "every verified trade" is
// either true or it is marketing. What a cap actually protects is memory, and
// that is solved properly below by iterating the statement instead of loading
// the whole result set into an array, so the row count stops being a number
// anyone has to choose.
$perPage = (int)sma_setting('perf_page_size', '50');
$perPage = $perPage <= 0 ? 0 : max(10, $perPage);

// The detailed record: the excursion figures and the profit-factor tile.
//
// These were cut from this page for being measurement about the measurement -
// true of a stranger deciding whether to trust the number, and wrong about
// everyone else. A member who already trusts it wants to know how far a trade
// went against them before it worked, because that is the number that decides
// whether they could have held it. Profit factor is the same story: a
// restatement for a first-time reader, the headline figure for somebody
// sizing a position.
//
// So it is a switch rather than a judgement, and it defaults ON. Nothing here
// is recomputed for it - mae_r, mfe_r and bars_held have always been selected,
// and $profitFactor has always been calculated. Only the rendering was
// removed, which is why turning this on costs nothing.
//
// Read here, above the $rowsOnly exit, because the row partial needs it and
// that endpoint returns before the rest of the page's switches are read.
$perfDetail = sma_setting('perf_detail', '1') === '1';

// ONE LIST: EVERY SETTLED CALL, HOWEVER IT FINISHED.
//
// This page shows settled calls only - reached its target, stopped out, or
// ran out of time - sorted newest-first in one table rather than split across
// tabs by how they ended. What is still running belongs to the Scanner ("Live
// setups" on charts.php) instead: that is the "what can I take right now"
// surface, and a second, smaller copy of it here just gave the reader two
// places disagreeing about which calls to trust. A track record is a record
// of what happened, not of what is in progress.
$rowsOnly  = ($_GET['rows'] ?? '') === '1';
$showAll = $perPage === 0 || ($_GET['show'] ?? '') === 'all';
$show = $showAll ? 0 : max($perPage, (int)($_GET['show'] ?? $perPage));
$fromRow = $rowsOnly ? max(0, (int)($_GET['from'] ?? 0)) : 0;
$take = $rowsOnly ? max(1, $perPage) : $show;

$tradeSql = "SELECT s.symbol, s.tf, s.`signal`, s.price, s.outcome, s.outcome_note, s.outcome_r,
            s.created_at, s.closed_at, s.indicators, s.mae_r, s.mfe_r, s.bars_held,
            COALESCE(sy.tier, 'public') AS coin_tier
     FROM signals s LEFT JOIN symbols sy ON sy.symbol = s.symbol
     WHERE s.outcome IN ('confirmed','invalid','expired')"
     . \SignalMasterAi\Visibility::subquery('track', 's.symbol')
     . \SignalMasterAi\Publish::sql('s')
     . \SignalMasterAi\Visibility::tfSql($config['market']['intervals'], 's')
     . ' ORDER BY s.closed_at DESC'
     . ($take > 0 ? ' LIMIT ' . (int)$take . ' OFFSET ' . (int)$fromRow : '');
// The statement itself, not fetchAll(). PDOStatement is Traversable, so the
// row partial foreaches it exactly the same way - and a hundred thousand
// settled trades stream through one row at a time instead of arriving as one
// enormous array. This is what makes "no limit" a safe thing to offer.
$rows = $pdo->query($tradeSql);

// How many settled trades exist in total, so the button can say what is left
// rather than being a button that might do nothing.
$tradeTotal = (int)$pdo->query(
    "SELECT COUNT(*) FROM signals s WHERE s.outcome IN ('confirmed','invalid','expired')"
    . \SignalMasterAi\Visibility::subquery('track', 's.symbol')
    . \SignalMasterAi\Publish::sql('s')
    . \SignalMasterAi\Visibility::tfSql($config['market']['intervals'], 's')
)->fetchColumn();
$viewerTier = MemberAuth::tier();

// The per-coin breakdown (win rate, sample size, per coin) that used to be
// computed here - $byCoin, $coinTotal, $thinRow, and the ?coins=1 append
// endpoint below - moved out along with the table it fed: first to the
// scanner page, and now to the "By coin" tab of the "Live setups" sheet on
// charts.php (see TrackRecord::topCoins() and the api.php 'board' action's
// view=bycoin branch). See the note further down where "By coin" used to
// render.

// The append response: just the rows, nothing around them.
//
// Gated like the coin rows, and for the same reason: the endpoint is a URL,
// and a lock that only hides markup is not a lock.
if ($rowsOnly) {
    if (!\SignalMasterAi\Gate::allows('perf_trades')) {
        http_response_code(403);
        header('Content-Type: text/html; charset=utf-8');
        exit('');
    }
    header('Content-Type: text/html; charset=utf-8');
    header('X-Total: ' . $tradeTotal);
    require __DIR__ . '/_perf_rows.php';
    exit;
}

// Which parts of this page are published.
//
// The trade list is the record; everything else on the page is commentary on
// it - headline ratios, breakdowns by timeframe and coin, and a verdict on
// whether the sample proves anything yet. An operator may reasonably want the
// evidence without the essay, so each block is a switch, and the list of
// trades is the one that stays.
//
// A cumulative-R chart used to sit between the figures and the breakdowns,
// with a perf_show_curve switch of its own. Both are gone. It was a shape
// rather than a number: nobody could read a value off it, the same story is
// told exactly by average R and the confidence interval directly above it,
// and on a phone it cost a screen of height to say what the tile already
// said. The rows it was drawn from are still read - they are where profit
// factor and the confidence interval come from.
$showStats  = sma_setting('perf_show_stats', '1') === '1';
$showBreak  = sma_setting('perf_show_breakdowns', '1') === '1';
$showTrades = sma_setting('perf_show_trades', '1') === '1';

// ONE PAGE, IN ORDER.
//
// This was briefly cut into Summary / Breakdowns / Signals tabs, with an
// admin switch for the layout and a second one for which tab opened first.
// Three controls where the reader wanted none: a track record is evidence,
// and evidence that hides two thirds of itself behind buttons reads as
// selective. One page prints in one go, hands to somebody as a single
// document, and answers the browser's own find from anywhere in it.
//
// What each section shows is still entirely the operator's call - the
// perf_show_* switches below decide whether a section is on the page at all,
// which is a different question from whether it is behind a tab.

// Expectancy and profit factor. Win rate alone is the statistic most likely
// to mislead: 40% at 2R is a profitable system and 60% at 0.5R is not, so the
// page should not lead with it.
// Windowed the same as the headline, and the MOST RECENT 1000, not the
// oldest. Both were bugs: no day-window clause meant profit factor and the
// R confidence interval stayed all-time even while "Last 7 days" was
// selected, and ORDER BY ... ASC took the oldest settled trades once a site
// passed 1000 of them - a stale historical slice sitting right next to
// figures ($avgR, $winrate) computed from the current, unlimited population.
$settledRows = $pdo->query(
    "SELECT outcome_r, closed_at FROM signals
     WHERE outcome IN ('confirmed','invalid')" . $visible . $window . " ORDER BY closed_at DESC LIMIT 1000"
)->fetchAll();
$grossWin = 0.0; $grossLoss = 0.0;
$nWin = 0; $nLoss = 0;
foreach ($settledRows as $cr) {
    $r = (float)$cr['outcome_r'];
    if ($r > 0) { $grossWin += $r; $nWin++; } else { $grossLoss += abs($r); $nLoss++; }
}
$profitFactor = $grossLoss > 0 ? round($grossWin / $grossLoss, 2) : null;
// $avgWin, $avgLoss, $payoff and $breakEven were computed here for a stats
// row that has been removed - see the note further down. $peak/$maxDd went
// the same way with the max drawdown tile, and the running $equity/$curve
// with the chart. $grossWin, $grossLoss, $nWin and $nLoss stay because
// $profitFactor above still needs them; the derived figures do not, and a
// value nothing reads is a value that quietly rots.

// How much of this is signal and how much is sample size.
//
// A record of sixty trades was presented with exactly the confidence of one
// with six thousand, and the difference is the entire question. Expectancy is
// an average of numbers that scatter by about a full R, so its own error bar
// is wide until the trade count is well into the hundreds - and a reader
// deciding whether to follow this deserves to be told that by the page rather
// than find it out with money.
//
// The interval is the ordinary standard error of the mean at 95%. It is not a
// forecast: it says where the TRUE long-run average plausibly sits given what
// has been seen, which is the honest limit of what any track record can claim.
$rVals = array_map(static fn($c) => (float)$c['outcome_r'], $settledRows);
$rN = count($rVals);
$rSd = null;
$rCi = null;
if ($rN > 1) {
    $mean = array_sum($rVals) / $rN;
    $var = 0.0;
    foreach ($rVals as $v) {
        $var += ($v - $mean) ** 2;
    }
    $rSd = sqrt($var / ($rN - 1));
    $se = $rSd / sqrt($rN);
    $rCi = [round($mean - 1.96 * $se, 3), round($mean + 1.96 * $se, 3)];
}
// Wilson interval on the win rate - the same method the engine already uses
// to keep its own confidence honest, so the two agree.
$wrCi = null;
if ($total > 0) {
    $wrCi = [
        round(\SignalMasterAi\Outcomes::wilsonLower($wins, $total) * 100, 1),
        round((1 - \SignalMasterAi\Outcomes::wilsonLower($total - $wins, $total)) * 100, 1),
    ];
}
// Does the record establish anything yet? Only if the whole interval sits on
// one side of zero.
$proven = $rCi !== null && ($rCi[0] > 0 || $rCi[1] < 0);

// Two numbers a sceptic asks for, and neither was here.
//
// How many calls are still open: without it, nothing on this page rules out
// the oldest trick in the business - letting losers run unresolved so they
// never reach the record. A published open count makes that visible the
// moment it starts happening.
//
// And when the last one settled: a page with no timestamp could be live or
// three weeks abandoned, and it reads identically either way.
$openNow = \SignalMasterAi\TrackRecord::openCount();
$lastSettled = (int)($pdo->query("SELECT MAX(closed_at) FROM signals
                                  WHERE outcome IN ('confirmed','invalid')" . $visible)
                          ->fetchColumn() ?: 0);

// The open calls themselves - what is running now and how far each one has
// got - used to be listed here too. Moved out: a settled record answers "how
// did it do", and the running list answers "what is it doing", which is a
// different question with its own home now - the Scanner's "Live setups"
// sheet on charts.php, the "what can I take right now" surface, with the
// by-coin win-rate view next to its close button. $openNow above still counts
// them for the one honest sentence this page keeps - that they exist and are
// not in the figures - without duplicating the list itself.
?>
<?php
// Through the shared head, like every other public page.
//
// This one hand-wrote its own: no canonical, no Open Graph, no Twitter card,
// no manifest, no apple-touch icon. It is also the single most linkable page
// on the site - a public, verified track record is the thing somebody shares -
// so it was the page least able to afford being the one with no share preview
// and no canonical address.
$perfCss = <<<'CSS'
.perf-wrap { max-width: 900px; margin: 26px auto; padding: 0 16px; }
/* The trade table, at window width. Everything else on the page is prose and
   stays at a readable measure; a fourteen-column table squeezed into the same
   column is unreadable in a different way. */
.perf-bleed { width: calc(100vw - 24px); margin-left: calc(50% - 50vw + 12px); }
@media (max-width: 940px) { .perf-bleed { width: auto; margin-left: 0; } }
.perf-head { text-align: center; margin-bottom: 20px; }
.perf-head h1 { font-size: clamp(24px, 4vw, 34px); letter-spacing: -0.5px; }
.perf-head p { color: var(--muted); font-size: 13.5px; max-width: 640px; margin: 10px auto 0; line-height: 1.7; }
/* Centred prose is fine for one line and punishing for seven. On a phone this
   intro wrapped to seven ragged lines whose left edge moved on every one of
   them, so the eye had to hunt for the start of each - the reader gives up
   before the first number. Centre the heading, set the paragraph flush left. */
.perf-center { text-align: center; }
.perf-scope { margin: 10px auto 0; max-width: 640px; }
.perf-scope summary { color: var(--muted); font-size: 12.5px; cursor: pointer; padding: 4px 0; }
.perf-scope p { margin-top: 6px; }
@media (max-width: 700px) {
  .perf-head p, .perf-center, .perf-scope { text-align: left; }
}
/* A grid, not a wrapping flex row.
   Five tiles in a centred flex row wrap to 2 + 2 + 1, and the widths come
   from the content, so the rows were ragged and the fifth tile sat alone in
   the middle of its own line looking like a rendering fault. Equal columns
   fix the raggedness; spanning the odd one out fixes the orphan. */
.perf-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
              gap: 10px; margin: 22px 0; }
.pstat { background: var(--surface); border: 1px solid var(--border); border-radius: 12px;
         padding: 15px 12px; min-width: 0; text-align: center; }
.pstat b { display: block; font-size: 25px; line-height: 1.2; }
/* The named size, not a literal: this is an uppercase eyebrow under a number,
   and the scale steps it up to 11px on a phone. Hardcoded it stayed at ten and
   a half on the four figures this page exists to show. */
.pstat span { display: block; color: var(--muted); font-size: var(--fs-micro); text-transform: uppercase;
              letter-spacing: 0.5px; margin-top: 5px; }
@media (max-width: 640px) {
  /* Two columns rather than auto-fit: at 375px auto-fit gives one tall column
     of five, and these numbers are the page's headline - they should be
     readable at a glance, not scrolled through. */
  .perf-stats { grid-template-columns: 1fr 1fr; gap: 8px; }
  .pstat:last-child:nth-child(odd) { grid-column: 1 / -1; }
  .pstat b { font-size: 23px; }
}
.perf-table { width: 100%; border-collapse: collapse; background: var(--surface); border: 1px solid var(--border); border-radius: 12px; overflow: hidden; font-size: 13px; }
.perf-table th { text-align: left; padding: 10px 12px; background: var(--surface2); color: var(--muted); font-size: 11.5px; text-transform: uppercase; letter-spacing: 0.4px; }
.perf-table td { padding: 9px 12px; border-top: 1px solid var(--border); }
/* The outcome text is a sentence - "Time stop - setup went nowhere" - and the
   table is width:100%, so every column added past it steals width from the
   only cell that cannot shrink gracefully. Turning the detailed record on took
   that row to five wrapped lines. A floor on this one column costs the table a
   horizontal scroll it already knows how to do (.tbl-scroll) and buys back a
   readable row; the three excursion columns hold two or three characters each
   and must never be the thing that wraps. */
.perf-table td.oc { min-width: 128px; }
/* Three pips for the three targets, filled up to the one reached. Decoration
   for scanning the column quickly - the words beside them carry the meaning,
   which is why the pips are aria-hidden. */
.tp-pips { display: inline-flex; gap: 3px; vertical-align: 1px; margin-right: 6px; }
.tp-pips i {
  width: 7px; height: 7px; border-radius: 50%;
  border: 1px solid var(--border); background: var(--surface2);
}
.tp-pips i.on { background: var(--up); border-color: var(--up); }
.perf-table th.num, .perf-table td.num { white-space: nowrap; text-align: right; }
.sig-b { font-weight: 500; }
.sig-b.buy { color: var(--up); }
.sig-b.sell { color: var(--down); }
.oc { font-weight: 500; font-size: 12px; }
.oc.confirmed { color: var(--up); }
.oc.invalid { color: var(--down); }
.oc.expired { color: var(--muted); }
.r-pos { color: var(--up); font-weight: 500; }
.r-neg { color: var(--down); font-weight: 500; }
.perf-note { color: var(--muted); font-size: 12px; line-height: 1.7; margin-top: 16px; }
.tbl-scroll { overflow-x: auto; }
@media (max-width: 720px) { .perf-table { font-size: 12px; } .perf-table th, .perf-table td { padding: 7px 8px; } }
CSS;
\SignalMasterAi\View::head(
    'Verified track record',
    'Every verified signal from ' . $siteName . ' - wins and losses, measured against real price action, '
    . 'with the entry, stop and targets each one was published with.',
    ['style' => $perfCss]
);
?>
<?php \SignalMasterAi\View::topbar('performance'); ?>

<div class="perf-wrap">
  <div class="perf-head">
    <h1>📊 Verified track record</h1>
    <?php // The scope line goes AFTER the sentence about what this page is,
          // not between the heading and it. Above, in bold, with a warning
          // glyph, it was the first thing a stranger read - a statement about
          // what is not here, before anything had said what is. ?>
    <?php // One sentence, then the numbers.
          //
          // This was a seven-line paragraph, and every line of it was true and
          // worth saying - which is exactly the trap. A visitor arrives to
          // check one thing: is the record real. Seven lines of méthode before
          // the first figure reads as throat-clearing, and the qualifications
          // that establish the honesty get skipped along with everything else.
          // So the claim leads and the qualifications sit one tap away, where
          // the reader who wants them is the reader who opens them. Nothing is
          // removed - <details> is still in the page, in the markup, and
          // findable by search. ?>
    <p><strong>Wins and losses are both shown.</strong> Every BUY/SELL signal is checked
      automatically against the price action that followed it.</p>
    <?php // Windows, as links rather than a form: the whole page is this one
          // number seen four ways, so each way deserves its own address -
          // shareable, bookmarkable, and something a search engine can read.
          //
          // Counted on the day a call was DECIDED, not the day it was made, so
          // the recent windows are not perpetually missing their slowest
          // trades - which are disproportionately the losers. ?>
    <nav class="perf-windows" aria-label="Period">
      <?php // A year is the longest window worth offering, because it is the
            // longest window the site can be holding: settled signals are kept
            // for signal_retention_days, 365 by default, so "all time" on a
            // stock install IS the last year. Both are still listed - the
            // operator can raise or lower that setting, and the two labels
            // stop meaning the same thing the moment they do. ?>
      <?php foreach ([0 => 'All time', 7 => 'Last 7 days', 30 => 'Last 30 days',
                      90 => 'Last 90 days', 365 => 'Last 12 months'] as $pd => $lbl): ?>
        <a href="performance.php<?= $pd ? '?days=' . $pd : '' ?>"
           class="<?= $perDays === $pd ? 'on' : '' ?>"
           <?= $perDays === $pd ? 'aria-current="page"' : '' ?>><?= sma_e($lbl) ?></a>
      <?php endforeach; ?>
    </nav>
    <?php // HOW FAR BACK "ALL TIME" CAN POSSIBLY REACH.
          //
          // Settled calls are deleted past signal_retention_days, so on a
          // stock install "all time" is the last year and nothing older
          // exists to be shown. A page that says "all time" while silently
          // meaning "as much as we kept" is overclaiming, and the reader has
          // no way to know which. Said once, here, under the windows.
          $perKeep = (int)sma_setting('signal_retention_days', '365'); ?>
    <?php if ($perKeep > 0): ?>
      <p class="perf-note" style="margin-top:-8px">Settled calls are kept for
        <strong><?= (int)$perKeep ?> days</strong>, so &ldquo;all time&rdquo; reaches back that far
        and no further<?= $perKeep === 365 ? ' &mdash; which makes it the same set as the last 12 months' : '' ?>.</p>
    <?php endif; ?>
    <?php if ($perDays > 0 && $total === 0): ?>
      <p class="perf-note"><strong>Nothing settled in the last <?= (int)$perDays ?> days.</strong>
        That is not a bad run &mdash; it is an empty one. A call has to reach its stop, a target or
        its time stop to be counted here, and on the slower timeframes that takes longer than this
        window. <a href="performance.php">See all time</a>.</p>
    <?php endif; ?>
    <details class="perf-scope">
      <summary>How this is measured</summary>
      <p>Each signal resolves one of three ways - target reached, stop hit, or the setup
        expired without either. Nothing on this page is curated by hand, and nothing is
        added or removed after the fact.<?php
        if (($pubNote = \SignalMasterAi\Publish::note(true)) !== ''): ?>
        <?= sma_e($pubNote) ?><?php endif; ?></p>
    </details>
  </div>

  <?php // One page, top to bottom: headline figures, then the breakdowns,
        // then every signal. No tab bar - see the note beside the
        // perf_show_* switches at the top of this file. ?>

  <?php if ($total === 0): ?>
    <div class="perf-stats"><div class="pstat"><b>—</b><span>collecting data</span></div></div>
    <p class="perf-note perf-center">The engine is new here - verified outcomes
      appear automatically as signals resolve. Check back soon.</p>
  <?php else: ?>
  <?php if ($showStats): ?>
  <div class="perf-stats reveal">
    <?php // Three numbers by default, four with the detailed record on.
          //
          // Profit factor is expectancy expressed as a ratio, so it is not
          // load-bearing for a stranger deciding whether to trust this - which
          // is why it is not here unconditionally. It is exactly what a member
          // sizing a position needs, which is why it is here at all.
          // Settings > Legal & trust > Detailed track record.
          //
          // Max drawdown sat beside it and is gone, along with the
          // cumulative-R chart that used to sit below: both were the equity
          // curve's shape rather than a number a reader could act on, and the
          // figures here say the same thing in a form that can be compared.
          // Nothing else read $maxDd, so the arithmetic went with it. ?>
    <div class="pstat"><b class="<?= ($avgR ?? 0) >= 0 ? 'r-pos' : 'r-neg' ?>"><?= $avgR !== null ? ($avgR > 0 ? '+' : '') . $avgR : '—' ?></b><span>average R per signal</span></div>
    <div class="pstat"><b><?= $winrate !== null ? $winrate . '%' : '—' ?></b><span>win rate</span></div>
    <div class="pstat"><b class="cnt" data-n="<?= (int)$total ?>">0</b><span>verified signals</span></div>
    <?php if ($perfDetail): ?>
      <?php // Null when nothing has lost yet: with no gross loss the ratio is a
            // division by zero, and "infinite" is not a track record, it is a
            // sample that has not met its first loss. Say so rather than
            // printing a number that will collapse the first time one lands. ?>
      <div class="pstat" title="Gross profit divided by gross loss. Above 1.00 means the wins outweigh the losses.">
        <b class="<?= $profitFactor !== null && $profitFactor >= 1 ? 'r-pos' : ($profitFactor !== null ? 'r-neg' : '') ?>"><?= $profitFactor !== null ? number_format($profitFactor, 2) : '—' ?></b>
        <span>profit factor</span></div>
    <?php endif; ?>
  </div>
  <?php // Average win, average loss, payoff ratio and break-even win rate used
        // to sit here, with a paragraph explaining how they relate. Removed:
        // four numbers and an essay, all of them restatements of the
        // expectancy already shown above. Expectancy is the one that answers
        // "does this make money", and it is a single number rather than a
        // ratio the reader has to do arithmetic on. ?>

  <?php if ($rCi !== null): ?>
  <?php // What the sample supports, said the way an analyst would say it.
        //
        // This used to headline "This record shows a real loss". Every word of
        // that is true when the interval sits below zero, and it is still the
        // wrong sentence: it is the site delivering a verdict on itself, in
        // the largest text in the box, to somebody deciding whether to sign
        // up. A reader does not need to be told what a negative expectancy
        // means - the figure is directly above, in red.
        //
        // NOTHING IS SOFTENED. The interval, the sign and the sample size are
        // all still stated, and the losing case still renders in the red
        // treatment. What changed is that the box reports a measurement
        // instead of editorialising about it, which is both more professional
        // and more useful: "the 95% interval is entirely below zero" tells a
        // trader precisely what it tells a statistician, and "shows a real
        // loss" tells them nothing they could not already see.
        //
        // If the honest number is not one you want on a public page, the page
        // has a switch (Settings > Legal & trust). Publishing a losing record
        // and publishing nothing are both defensible; publishing a losing
        // record dressed up as a winning one is not, so that is the one thing
        // this box will not do. ?>
  <div class="perf-confidence reveal <?= $proven ? ($rCi[0] > 0 ? 'ok' : 'bad') : 'unproven' ?>">
    <strong><?= $proven
      ? ($rCi[0] > 0
          ? 'Statistically significant result over ' . $total . ' verified signals'
          : 'Measured negative expectancy over ' . $total . ' verified signals')
      : 'Sample too small to be conclusive' ?></strong>
    <p><?php if ($proven): ?>
      The 95% confidence interval for average R is
      <strong><?= $rCi[0] > 0 ? '+' : '' ?><?= $rCi[0] ?></strong> to
      <strong><?= $rCi[1] > 0 ? '+' : '' ?><?= $rCi[1] ?></strong>R per signal &mdash; entirely
      <?= $rCi[0] > 0 ? 'above' : 'below' ?> zero, so this result is unlikely to be chance.
      <?= $rCi[0] > 0
          ? 'On this sample the engine is profitable after costs.'
          : 'On this sample the engine is losing after costs. Every signal behind it is listed'
            . ' below.' ?>
    <?php else: ?>
      The 95% interval for average R spans zero
      (<?= $rCi[0] > 0 ? '+' : '' ?><?= $rCi[0] ?> to <?= $rCi[1] > 0 ? '+' : '' ?><?= $rCi[1] ?>R),
      so <?= $total ?> verified signal<?= $total === 1 ? '' : 's' ?> is not yet enough to separate
      skill from chance in either direction.
    <?php endif; ?></p>
  </div>
  <?php endif; ?>
  <p class="perf-note perf-center">
    <strong><?= number_format($openNow) ?></strong> call<?= $openNow === 1 ? ' is' : 's are' ?>
    still open and not counted above &mdash; they join the record when they resolve, win or lose.
    <?php if ($openNow > 0): ?>
      See what is running now in the <a href="charts.php">Scanner</a>.
    <?php endif; ?>
    <?php if ($lastSettled > 0): ?>
      Last signal settled <strong><?= sma_e(\SignalMasterAi\View::ago(time() - $lastSettled)) ?></strong>.
    <?php endif; ?>
    <?php if ($expired > 0): ?>
      <?= number_format($expired) ?> expired without reaching a stop or a target and
      <?= $expired === 1 ? 'counts' : 'count' ?> as no-trades.
    <?php endif; ?>
  </p>


  <?php // The paragraph that used to explain what expectancy is has gone: the
        // tile is labelled "average R per trade" now, which is the definition,
        // so the explanation was the label written out longhand. What R means
        // is in the small print at the foot of the page. ?>
  <?php endif; ?>

  <?php if ($showBreak): ?>
  <?php // One reveal for the whole breakdowns block, not one per table: the
        // headings inside it (.perf-slide-h) are walked sibling-by-sibling by
        // the carousel builder in ui.js, which groups each heading with the
        // elements up to the next h2 - splitting this into several reveal
        // wrappers would put each heading in its own subtree and break that
        // walk (or silently swallow every slide but the first once the
        // carousel reparents them). One wrapper preserves the exact sibling
        // order the carousel depends on and still gets the section a single
        // scroll-triggered fade-in. ?>
  <div class="reveal">
  <?php
  $byTf = $pdo->query(
      "SELECT tf, COUNT(*) t,
              SUM(CASE WHEN outcome_r > 0 THEN 1 ELSE 0 END) w,
              SUM(outcome_r) r
       FROM signals WHERE outcome IN ('confirmed','invalid')" . $visible . $window . "
       GROUP BY tf ORDER BY t DESC"
  )->fetchAll();
  ?>
  <?php // One threshold for both breakdown tables below. Twenty is where a win
        // rate stops being mostly noise; a table that greys one and not the
        // other would be saying two different things with the same styling. ?>
  <?php $minMeaningful = 20; ?>

  <?php if (count($byTf) > 1): ?>
  <?php
  // BY GRADE, BECAUSE THE GRADE IS THE PROMISE.
  //
  // Every call on this site is published with a letter, the alert filter is
  // built on it, and until now a reader had no way to check what the letters
  // were worth. It sits above "by timeframe" on purpose: it is the first
  // thing somebody who has been reading the grades wants to test, and the
  // rows are few enough to read at a glance.
  //
  // Thin rows are shown rather than hidden - a grade with nine settled calls
  // is part of the record - but they carry the count and are dimmed, because
  // the honest answer to "how good is A+" on nine trades is "nobody knows
  // yet" and a table that quietly drops them implies a completeness it does
  // not have.
  $gradeRows = [];
  try { $gradeRows = \SignalMasterAi\Outcomes::byGrade($perDays ?: null); } catch (Throwable $e) {}
  ?>
  <?php if ($gradeRows): ?>
  <h2 class="sec perf-slide-h" data-slide="grade">By grade</h2>
  <div class="tbl-scroll" style="margin-bottom:14px">
  <table class="perf-table">
    <tr><th>Grade</th><th>Verified</th><th>Win rate</th><th>Avg R</th></tr>
    <?php foreach ($gradeRows as $g): ?>
      <tr<?= $g['thin'] ? ' class="thin-row"' : '' ?>>
        <th scope="row"><?= sma_e((string)$g['grade']) ?></th>
        <td><?= (int)$g['n'] ?><?= $g['thin'] ? ' <span class="thin-tag" title="Too few settled calls to read as a rate">too few</span>' : '' ?></td>
        <td><?= $g['win_rate'] === null ? '&mdash;' : sma_e((string)$g['win_rate']) . '%' ?></td>
        <td class="<?= (float)$g['expectancy'] >= 0 ? 'r-pos' : 'r-neg' ?>">
          <?= (float)$g['expectancy'] >= 0 ? '+' : '' ?><?= sma_e((string)$g['expectancy']) ?>R</td>
      </tr>
    <?php endforeach; ?>
  </table>
  </div>
  <p class="perf-note" style="margin-top:0">The letter is decided when the signal is made, from the
    rules that fired &mdash; never afterwards. <strong>Avg R</strong> is what one unit of risk
    returned on average: it is the column that decides whether a grade made money, and it can
    disagree with the win rate, because a run of small wins does not pay for one full-sized loss.</p>
  <?php endif; ?>

  <?php
  // BY TYPE, BESIDE BY GRADE, BECAUSE THEY ANSWER DIFFERENT QUESTIONS.
  //
  // The grade says how good the read was. The type says what the plan paid for
  // the risk it took, and a member who filters the scanner to 1:3 is entitled
  // to see whether 1:3 is the type that actually makes money here or only the
  // one that sounds best. Calls stored before the types existed carry no type
  // and are left out rather than filed under a label they never had, so this
  // table fills in from here rather than claiming a history it does not have.
  $typeRows = [];
  try { $typeRows = \SignalMasterAi\Outcomes::byType($perDays ?: null); } catch (Throwable $e) {}
  ?>
  <?php if ($typeRows): ?>
  <h2 class="sec perf-slide-h" data-slide="type">By signal type</h2>
  <div class="tbl-scroll" style="margin-bottom:14px">
  <table class="perf-table">
    <tr><th>Type</th><th>Verified</th><th>Win rate</th><th>Avg R</th></tr>
    <?php foreach ($typeRows as $g): ?>
      <tr<?= $g['thin'] ? ' class="thin-row"' : '' ?>>
        <th scope="row"><span class="rr-type rr<?= (int)substr((string)$g['grade'], -1) ?>"><?= sma_e((string)$g['grade']) ?></span></th>
        <td><?= (int)$g['n'] ?><?= $g['thin'] ? ' <span class="thin-tag" title="Too few settled calls to read as a rate">too few</span>' : '' ?></td>
        <td><?= $g['win_rate'] === null ? '&mdash;' : sma_e((string)$g['win_rate']) . '%' ?></td>
        <td class="<?= (float)$g['expectancy'] >= 0 ? 'r-pos' : 'r-neg' ?>">
          <?= (float)$g['expectancy'] >= 0 ? '+' : '' ?><?= sma_e((string)$g['expectancy']) ?>R</td>
      </tr>
    <?php endforeach; ?>
  </table>
  </div>
  <p class="perf-note" style="margin-top:0">Every plan carries targets at one, two and three times
    its risk. The type is which of them the setup was expected to reach, decided when the signal
    was made from how often that coin has covered that ground inside the deadline. A 1:1 is not a
    worse signal &mdash; it is a shorter trade with a nearer target, and it wins more often for
    exactly that reason. <strong>Avg R</strong> is the column that says which type paid.</p>
  <?php endif; ?>

  <h2 class="sec perf-slide-h" data-slide="timeframe">By timeframe</h2>
  <div class="tbl-scroll" style="margin-bottom:14px">
  <table class="perf-table">
    <tr><th>Timeframe</th><th>Verified</th><th>Win rate</th><th>Avg R</th></tr>
    <?php
    // Expectancy carries the colour, not win rate. They disagree more often
    // than anyone expects: this engine once ran a 67% win rate on its fastest
    // timeframes while losing money on every one of them, because the wins
    // were small and the losses were not. A table that paints win rate green
    // would have called that healthy.
    //
    // Thin samples get no colour at all rather than a confident one - a
    // handful of trades cannot tell an edge from a run of luck.
    foreach ($byTf as $b): $bt = (int)$b['t']; $bw = (int)$b['w'];
          $br = $bt > 0 ? round((float)$b['r'] / $bt, 2) : 0;
          $thin = $bt < $minMeaningful; ?>
    <tr<?= $thin ? ' style="opacity:.6"' : '' ?>>
      <td><strong><?= sma_e($b['tf']) ?></strong></td>
      <td><?= $bt ?> signals<?= $thin ? ' <span class="hint">too few to read</span>' : '' ?></td>
      <td><?= $bt ? round($bw / $bt * 100, 1) : 0 ?>%</td>
      <td style="font-weight: 500<?= $thin ? '' : ';color:' . ($br > 0.05 ? 'var(--up)' : ($br < -0.05 ? 'var(--down)' : 'var(--warn-text)')) ?>">
        <?= $br > 0 ? '+' : '' ?><?= $br ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
  </div>
  <p class="hint" style="margin:-6px 0 16px">
    Read the <strong>Avg R</strong> column, not the win rate — a timeframe can win two
    thirds of its signals and still lose money. Under <?= (int)$minMeaningful ?> verified signals
    is greyed: too few to read.
  </p>
  <?php endif; ?>

  <?php
  // WHICH HOURS THIS ENGINE ACTUALLY MAKES MONEY IN.
  //
  // Expectancy per hour of the UTC day has been measured here for a long time
  // and shown in the admin, where it is twenty-four rows of clock face. Nobody
  // asks the question in that shape. They ask whether it does better in the
  // Asian session, and five named blocks is the answer to the question that
  // was actually asked. Sessions, and why they do not overlap, are defined
  // once in Sessions - see that class for the arithmetic.
  //
  // Streamed rather than fetched: this is every settled signal on the install
  // and the page already learned once not to load a whole result set into an
  // array to count it. Two columns, one pass, no growth in memory with the
  // record.
  //
  // Opened, not closed. A 4h setup opened in the London session may well
  // resolve overnight, and the question is which session it was TAKEN in -
  // that is the one a reader can choose to be at their desk for.
  // Windowed on closed_at, same as the headline and every other breakdown -
  // "which session was it opened in" (the grouping column, untouched below)
  // is a different question from "which population are we counting", and the
  // period picker at the top of the page controls the second one.
  $sessStmt = $pdo->query(
      "SELECT created_at, outcome_r FROM signals
       WHERE outcome IN ('confirmed','invalid')" . $visible . $window
  );
  $bySession = \SignalMasterAi\Sessions::bucket($sessStmt);
  $sessTotal = array_sum(array_column($bySession, 'n'));
  // The member already told us where they live, on the alerts tab. Showing the
  // block in their own clock is the difference between a fact about the engine
  // and something they can put in a calendar.
  $sessTz = $member ? (string)(\SignalMasterAi\MemberPrefs::get((int)$member['id'])['timezone'] ?? '') : '';
  ?>
  <?php if ($sessTotal > 0): ?>
  <h2 class="sec perf-slide-h" data-slide="session">By trading session</h2>
  <div class="tbl-scroll" style="margin-bottom:14px">
  <table class="perf-table">
    <?php // Four columns, not five: the hours belong to the session's name
          // rather than beside it, and a fifth column on a 375px phone turned
          // every row into four lines of wrapped text. Same shape as the
          // timeframe table above, which is the table a reader compares it to.
          //
          // No "too few to read" label in the count either - the row is greyed
          // and the sentence under the table above explains what greying
          // means, for both tables, from one threshold. ?>
    <tr><th>Session</th><th>Verified</th><th>Win rate</th><th class="num">Avg R</th></tr>
    <?php foreach ($bySession as $sk => $sv):
          $sn = (int)$sv['n'];
          $sr = $sv['avg_r'] === null ? 0.0 : (float)$sv['avg_r'];
          $thin = $sn < $minMeaningful;
          $local = $sessTz !== '' ? \SignalMasterAi\Sessions::localRange($sk, $sessTz) : ''; ?>
    <tr<?= $thin ? ' style="opacity:.6"' : '' ?>>
      <td><strong><?= sma_e(\SignalMasterAi\Sessions::label($sk)) ?></strong>
        <br><span class="hint"><?= sma_e(\SignalMasterAi\Sessions::range($sk)) ?><?php
          if ($local !== ''): ?> &middot; you <?= sma_e($local) ?><?php endif; ?></span></td>
      <td><?= $sn ?></td>
      <td><?= $sn ? sma_e((string)$sv['winrate']) . '%' : '&mdash;' ?></td>
      <td class="num" style="font-weight: 500<?= $thin ? '' : ';color:' . ($sr > 0.05 ? 'var(--up)' : ($sr < -0.05 ? 'var(--down)' : 'var(--warn-text)')) ?>">
        <?= $sn ? ($sr > 0 ? '+' : '') . number_format($sr, 2) : '&mdash;' ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
  </div>
  <p class="hint" style="margin:-6px 0 16px">
    The session a signal was <strong>opened</strong> in, in UTC. The blocks do not overlap, so the
    five counts add up to the <?= number_format($sessTotal) ?> verified signals above.
    <?php if ($sessTz === ''): ?>Set your timezone in
      <a href="account.php?tab=alerts">your account</a> to see these in your own clock.<?php endif; ?>
  </p>
  <?php endif; ?>

  <?php
  // How trades actually ended, which coins carried the record, and how much
  // heat the winners took. Three questions a reader has after "what is the win
  // rate", and none of them were answerable from this page before.
  // Windowed too - $labelWins/$reachedTarget below are derived entirely from
  // this query, and were being compared against the windowed $wins/$total
  // from the headline. All-time reached-target vs. a 7-day total could print
  // a "reached one" share over 100%; now both sides describe the same
  // population.
  $byExit = $pdo->query(
      "SELECT outcome, outcome_note, COUNT(*) n, ROUND(AVG(outcome_r), 3) r
       FROM signals WHERE outcome IN ('confirmed','invalid','expired')" . $visible . $window . "
       GROUP BY outcome, outcome_note ORDER BY n DESC"
  )->fetchAll();
  // By coin: every coin, with the thin ones marked rather than removed.
  //
  // The threshold, the page size and what counts as thin are all settings now
  // - see the block up beside the trade-row endpoint for why the floor is 1.
  // What is left here is the ordering, and it needs a word.
  //
  // Ranked by total R, which on small samples is partly a leaderboard of luck:
  // the coin at the top may be the one that got three good rolls rather than
  // the one the engine reads best. That is exactly what the thin marker is
  // for. Sorting by average R instead would be worse, not better - it puts a
  // single +3R fluke above a coin that has earned +40R over ninety signals.

  // Collapse onto the public label before displaying. Grouped by the raw
  // note, "TP2 reached", "TP2 reached, remainder stopped at TP1" and "TP2
  // reached, remainder closed at the time stop" were three separate rows and
  // none of them answered "how often does it reach TP2".
  $exits = [];
  // Counted from the OUTCOME, not from the label.
  //
  // "How many of these reached a target" has to be exact, because the note
  // under the table subtracts it from the win count. Reading it off the labels
  // means trusting that every reached-target row is called "TP-something" -
  // and a row whose note was never written renders as "Confirmed", which is a
  // reached target under a name the test would miss. The outcome column says
  // it without being parsed: confirmed IS "TP1 came before the stop".
  $reachedTarget = 0;
  foreach ($byExit as $e) {
      $k = \SignalMasterAi\View::shortOutcome($e['outcome_note'], (string)$e['outcome']);
      if (!isset($exits[$k])) {
          $exits[$k] = ['n' => 0, 'rsum' => 0.0];
      }
      $exits[$k]['n'] += (int)$e['n'];
      $exits[$k]['rsum'] += (float)$e['r'] * (int)$e['n'];   // weighted, not a mean of means
      if ((string)$e['outcome'] === 'confirmed') {
          $reachedTarget += (int)$e['n'];
      }
  }
  uasort($exits, fn($a, $b) => $b['n'] <=> $a['n']);
  ?>
  <?php if ($exits): ?>
    <h2 class="sec perf-slide-h" data-slide="exits">How signals ended</h2>
    <?php
    $allEnd = array_sum(array_column($exits, 'n'));
    // SHARES THAT ADD UP TO A HUNDRED.
    //
    // Rounded independently they did not. The live record read 36 + 33 + 15 +
    // 14 + 3 = 101%, which on the one page whose entire argument is "check the
    // arithmetic yourself" is the worst possible place to be a percentage
    // point out. Largest remainder: floor every share, then hand the leftover
    // points to the rows that were rounded down hardest. Every row is within
    // one point of its true share and the column sums to exactly 100.
    $shares = [];
    if ($allEnd > 0) {
        $rem = [];
        $used = 0;
        foreach ($exits as $label => $e) {
            $exact = $e['n'] / $allEnd * 100;
            $shares[$label] = (int)floor($exact);
            $rem[$label] = $exact - $shares[$label];
            $used += $shares[$label];
        }
        arsort($rem);
        foreach (array_keys($rem) as $label) {
            if ($used >= 100) {
                break;
            }
            $shares[$label]++;
            $used++;
        }
    }
    // How many of these endings reached a target but did not make money.
    //
    // A reader who adds the target rows up gets a win count, and it is not the
    // one in the tile above: a call that banks part at TP1 and is then
    // scratched is filed under "TP1 reached" and settles at or below zero
    // after costs. On the live record that is 252 against 246 - two win rates
    // derivable from one page, with the definition that reconciles them in a
    // collapsed block at the foot of it. Said here instead, where the
    // subtraction is being done.
    $labelWins = $reachedTarget;
    $scratched = $labelWins - $wins;
    ?>
    <div class="tbl-scroll">
    <table class="perf-table">
      <tr><th>Ending</th><th>Count</th><th>Share</th><th>Avg R</th></tr>
      <?php foreach ($exits as $label => $e):
            $avg = $e['n'] ? round($e['rsum'] / $e['n'], 2) : 0; ?>
        <tr>
          <td><?= sma_e($label) ?></td>
          <td><?= $e['n'] ?></td>
          <td><?= (int)($shares[$label] ?? 0) ?>%</td>
          <td class="<?= $avg >= 0 ? 'r-pos' : 'r-neg' ?>"><?= $avg > 0 ? '+' : '' ?><?= $avg ?></td>
        </tr>
      <?php endforeach; ?>
    </table>
    </div>
    <?php if ($scratched > 0 && $winrate !== null): ?>
      <p class="hint" style="margin:-6px 0 16px">
        Adding the target rows gives <strong><?= number_format($labelWins) ?></strong> calls that
        reached one, which is <strong><?= $total > 0 ? round($labelWins / $total * 100, 1) : 0 ?>%</strong>
        &mdash; not the <strong><?= sma_e((string)$winrate) ?>%</strong> above.
        <?= number_format($scratched) ?> of them banked part of the position at a target, were then
        scratched, and finished at or below zero after costs. A win here means the trade
        <em>made money</em>, not that it touched a line.
      </p>
    <?php endif; ?>

  <?php endif; ?>

  <?php // "How they behaved while open" - the average worst and best point of
        // winners and losers - used to sit here. Removed: maximum adverse and
        // favourable excursion are portfolio-management statistics, and on a
        // page whose job is "did these calls make money" they were two more
        // tables to scroll past before the answer. The columns are still on
        // every individual trade in the list below for anyone who wants them. ?>

  <?php // "By coin" used to render here - every coin ranked by its own verified
        // outcome, gated behind Premium. Moved out instead: the table on this
        // page could only be read, where the "By coin" tab of the "Live
        // setups" sheet (charts.php) sits next to a board a member can
        // actually act on - open the coin, see the current chart, trade it.
        // Same data, same 'perf_coins' gate (see Gate::FEATURES), one place
        // it lives now rather than two. ?>
  </div>
  <?php endif; ?>

  <?php if ($showTrades): ?>
  <h2 class="sec">Every signal, with the plan it was published with</h2>
  <?php
  // The audit of the headline figures, one tier below the per-coin table.
  //
  // Not gated to sell it - gated because coin, timeframe and R on every
  // settled trade IS the per-coin ranking with the arithmetic left to the
  // reader, and a lock on that table which anyone can undo with a calculator
  // is decorative. Free to any registered account, because a record nobody can
  // check is worth less than one they can.
  $tradesOpen = \SignalMasterAi\Gate::allows('perf_trades');
  ?>
  <?php if (!$tradesOpen): ?>
    <div class="coin-lock">
      <p><strong>The full trade log is for registered accounts.</strong></p>
      <p>Every settled call with the entry, stop and targets it was published with, and how it
        finished &mdash; <strong><?= number_format($total) ?></strong> verified so far. The figures
        above are computed from exactly these rows; this is where you check them.</p>
      <p class="coin-lock-act">
        <a class="btn" href="account.php?tab=register">Create a free account</a>
      </p>
    </div>
  <?php else: ?>
  <?php // ONE LIST. Every settled call together, newest first - reached
        // target, stopped out, or ran out of time - rather than split into
        // tabs by how it ended. The Outcome column already says which. ?>
  <p class="hint-p" style="margin:0 0 10px">Every settled signal, whichever way it finished.
    <?php // The split by LABEL is not the split the win rate uses, and a
          // reader who assumes it is will read the Outcome column as the win
          // and loss columns. It is not: a call closed at TP1 after a deep
          // drawdown and a call stopped at break-even both land where the
          // money says, not where the label does. ?>
    A win on this page is money made &mdash; <strong>R above zero</strong> &mdash; not a label.</p>
  <?php // Fourteen columns do not belong in a 900px column of prose. The table
        // breaks out to the window width while the writing around it stays
        // readable, which is the point of a measure. ?>
  <div class="tbl-scroll<?= sma_setting('perf_wide_table', '1') === '1' ? ' perf-bleed' : '' ?>"
       id="tradeTable">
  <table class="perf-table">
    <?php // SETTLED CALLS ONLY - what is still running lives on the Scanner
          // ("Live setups" on charts.php) instead, not here. This table used
          // to carry live rows too, marked and tinted at the top with a
          // caption saying they were not in the figures yet - that caveat is
          // gone along with the rows it was protecting, because a call with no
          // outcome yet has no business next to ones that are finished.
          //
          // Nine columns, twelve with the detailed record on. The three extra
          // are excursion statistics: how far it went against you before it
          // worked, and how long you had to sit in it. The targets stay as one
          // cell either way - as three columns they tripled the width of the
          // table to repeat a number the Outcome column already interprets. ?>
    <tr><th>When</th>
        <th>Coin</th><th>TF</th><th>Signal</th><th>Entry</th><th>Stop</th>
        <th>Targets</th><th>Outcome</th><th title="Profit in units of the risk taken.">R</th>
        <?php if ($perfDetail): ?>
          <th class="num" title="Maximum adverse excursion: the furthest this went against the entry, in R, before it resolved.">Heat</th>
          <th class="num" title="Maximum favourable excursion: the furthest it went in your favour, in R.">Best</th>
          <th class="num" title="How many candles the trade stayed open.">Bars</th>
        <?php endif; ?></tr>
    <?php require __DIR__ . '/_perf_rows.php'; ?>
  </table>
  </div>
  <?php $shown = $renderedRows ?? 0; if ($shown < $tradeTotal): ?>
    <p style="text-align:center;margin:16px 0">
      <a class="btn-primary" id="tradeMore" href="performance.php?show=<?= (int)($shown + $perPage) ?>#tradeTable"
         data-from="<?= (int)$shown ?>" data-total="<?= (int)$tradeTotal ?>" data-page="<?= (int)$perPage ?>">
        Load <?= number_format(min($perPage, $tradeTotal - $shown)) ?> more</a>
      <a class="btn" href="performance.php?show=all#tradeTable" style="margin-left:8px">Show all
        <?= number_format($tradeTotal) ?></a>
      <span class="hint-p" style="display:block;margin-top:8px" id="tradeCount">
        Showing <?= number_format($shown) ?> of <?= number_format($tradeTotal) ?> settled signals.</span>
    </p>
    <script<?= sma_nonce() ?>>
    // Progressive: the link above already works on its own, this only turns a
    // page reload into an append so the reader keeps their place in a long list.
    (function () {
      var btn = document.getElementById('tradeMore');
      if (!btn || !window.fetch) return;
      var tbody = document.querySelector('#tradeTable table');
      var count = document.getElementById('tradeCount');
      btn.addEventListener('click', function (e) {
        e.preventDefault();
        var from = parseInt(btn.dataset.from, 10) || 0;
        var total = parseInt(btn.dataset.total, 10) || 0;
        var page = parseInt(btn.dataset.page, 10) || 50;
        btn.textContent = 'Loading\u2026';
        fetch('performance.php?rows=1&from=' + from,
              { headers: { 'X-Requested-With': 'fetch' } })
          .then(function (r) { return r.text(); })
          .then(function (html) {
            var tmp = document.createElement('tbody');
            tmp.innerHTML = html.trim();
            var added = 0;
            while (tmp.firstElementChild) { tbody.appendChild(tmp.firstElementChild); added++; }
            from += added;
            btn.dataset.from = from;
            count.textContent = 'Showing ' + from.toLocaleString() + ' of '
                              + total.toLocaleString() + ' settled signals.';
            if (from >= total || added === 0) {
              btn.parentNode.removeChild(btn);
            } else {
              btn.textContent = 'Load ' + Math.min(page, total - from).toLocaleString() + ' more';
              btn.href = 'performance.php?show=' + (from + page) + '#tradeTable';
            }
          })
          .catch(function () { btn.textContent = 'Load more'; window.location = btn.href; });
      });
    })();
    </script>
  <?php else: ?>
    <p class="hint-p" style="text-align:center;margin-top:12px">All
      <?= number_format($tradeTotal) ?> settled signal<?= $tradeTotal === 1 ? '' : 's' ?> shown.</p>
  <?php endif; ?>
  <?php endif; /* $tradesOpen */ ?>


  <?php // Folded away, not deleted. Eight hundred characters of definitions -
        // what counts as a win, how R is measured, how an ambiguous candle is
        // resolved - is the honest small print, and it belongs on the page.
        // Open, it was the largest block of text here and the last thing a
        // reader met before leaving. The risk warning stays outside it,
        // because a warning behind a click is not a warning. ?>
  <details class="perf-method">
    <summary>How a win is counted</summary>
    <?php // The part of the scope line that a reader only wants if they went
          // looking for it. Up at the top it read as an admission; here it
          // reads as what it is - a rule, stated, applied before the fact.
          if (\SignalMasterAi\Publish::floor() !== ''): ?>
    <p class="perf-note"><strong>What is counted.</strong> This site publishes setups graded
      <?= sma_e(\SignalMasterAi\Publish::floor()) ?> and above, and this record measures every
      one of them, win or lose. Lower-graded setups are never published, so they were never a call
      anybody could have acted on. The grade is decided when the signal is made, from the rules
      that fired - never afterwards.</p>
    <?php endif; ?>
    <p class="perf-note">A signal counts as a <em>win</em> when it finished with a profit after
    costs, not merely when it reached a target &mdash; a signal that banks part of the position at
    TP1 and is then scratched can still end fractionally negative. TP1 before the stop makes a
    signal <em>confirmed</em> (the stop then moves to break-even and TP2 is tracked); the stop
    first makes it a <em>loss</em>; neither level inside the time stop makes it <em>expired</em>,
    which counts as a no-trade and is excluded from the win rate. "R" measures profit in units of
    initial risk (&minus;1 = full stop loss). When one candle spans both stop and target, the stop
    is counted first. <?= $expired > 0 ? "Expired setups so far: $expired." : '' ?></p>
  </details>
  <p class="perf-note">Past performance does not predict future results.</p>
  <?php endif; ?>

  <?php // The risk warning is not part of any optional block: it is the one
        // line on this page that has to survive every switch. ?>
  <?php if (!$showTrades): ?>
    <p class="perf-note">⚠️ Past performance does not predict future results.</p>
  <?php endif; ?>
  <?php endif; ?>
</div>

<footer class="footer">
  <p><?= sma_e($siteName) ?> — <a href="page.php?p=terms" style="color:var(--muted)">Terms</a> ·
     <a href="page.php?p=privacy" style="color:var(--muted)">Privacy</a> ·
     <a href="page.php?p=risk" style="color:var(--muted)">Risk disclosure</a></p>
  <p><?= sma_e(sma_setting('site_notice')) ?></p>
</footer>
<script src="assets/ui.js?v=<?= @filemtime(__DIR__ . '/assets/ui.js') ?: 1 ?>" defer></script>
</body>
</html>
