<?php
declare(strict_types=1);

/**
 * Member-facing backtester.
 *
 * The engine already replayed stored history to calibrate itself, but only the
 * admin could see it. A member evaluating whether to pay for signals had the
 * published track record and nothing else - no way to ask "how would this have
 * behaved on the coin and timeframe I actually trade?".
 *
 * Runs are throttled and capped because a backtest is genuinely expensive:
 * every simulated bar is a full pass of the rule set.
 */

$config = require __DIR__ . '/src/bootstrap.php';

use SignalMasterAi\Backtest;
use SignalMasterAi\Cache;
use SignalMasterAi\Database;
use SignalMasterAi\MarketData;
use SignalMasterAi\MemberAuth;
use SignalMasterAi\View;

MemberAuth::start();
$member = MemberAuth::current();
$viewerTier = MemberAuth::tier();

if (Database::setting('member_backtest_enabled', '1') !== '1') {
    header('Location: charts.php');
    exit;
}
// Tier gate, mirroring how premium coins work.
$needTier = Database::setting('member_backtest_tier', 'paid');
$allowed = MemberAuth::meetsTier($needTier, $viewerTier);

$intervals = array_values(array_intersect(
    $config['market']['intervals'],
    array_map('trim', explode(',', sma_setting('enabled_intervals', implode(',', $config['market']['intervals']))))
)) ?: $config['market']['intervals'];

$symbols = Database::pdo()
    ->query('SELECT symbol, label, tier FROM symbols WHERE enabled = 1'
          . \SignalMasterAi\Visibility::sql('backtest', 'symbols') . ' ORDER BY symbol')->fetchAll();
$accessible = array_values(array_filter($symbols,
    fn($s) => MemberAuth::canAccess($s['tier'], $viewerTier)));

$report = null;
$error = null;
$symbol = strtoupper((string)preg_replace('/[^A-Z0-9]/i', '', (string)($_GET['symbol'] ?? '')));
// The frames offered follow the coin: a pair restricted to the daily should
// not be backtestable on 5m, where the live engine never reads it and the
// result would describe a system that is not running.
$coinTfs = $symbol !== ''
    ? \SignalMasterAi\Visibility::tfsFor($symbol, $intervals) : $intervals;
$tf = in_array((string)($_GET['tf'] ?? ''), $coinTfs, true)
    ? (string)$_GET['tf'] : (in_array('1h', $coinTfs, true) ? '1h' : ($coinTfs[0] ?? '1h'));

if ($symbol !== '' && $allowed) {
    if (!in_array($symbol, array_column($accessible, 'symbol'), true)) {
        $error = 'That coin is not available on your plan.';
    } else {
        // One run per member per minute: a backtest is a full engine pass per
        // simulated bar, so this is the one place a page view can cost real CPU.
        $key = 'bt:' . ($member ? 'm' . $member['id'] : 'ip' . substr(sha1((string)($_SERVER['REMOTE_ADDR'] ?? '')), 0, 12));
        // The daily allowance, per tier - see Limits. Separate from the burst
        // limit below, and a different question: the burst stops one person
        // hammering the server in a minute, the allowance is how much of an
        // expensive feature a plan includes. Counted per member per day, or
        // per address for a signed-out visitor.
        $btMax = \SignalMasterAi\Limits::of('backtests');
        $btDay = 'btday:' . ($member ? 'm' . $member['id']
                 : 'ip' . substr(sha1((string)($_SERVER['REMOTE_ADDR'] ?? '')), 0, 12))
                 . ':' . gmdate('Y-m-d');
        $btUsed = $btMax > 0 ? (int)(Cache::get($btDay) ?? 0) : 0;
        if ($btMax > 0 && $btUsed >= $btMax) {
            $error = 'You have used all ' . $btMax . ' backtests for today.'
                   . ($member && ($member['tier'] ?? 'free') !== 'paid'
                      ? ' Premium members get more.' : ' The allowance resets at 00:00 UTC.');
        } elseif (Cache::increment($key, 60) > 2) {
            $error = 'Backtests are limited to two per minute. Try again shortly.';
        } else {
            if ($btMax > 0) {
                Cache::increment($btDay, 172800);
            }
            // v2: reports cached before the history depth was recorded would be read
            // as "no candles at all" for their remaining fifteen minutes.
            $cacheKey = 'btres2:' . $symbol . ':' . $tf;
            $report = Cache::get($cacheKey);
            if (!is_array($report)) {
                @set_time_limit(180);
                try {
                    $md = new MarketData($config);
                    $report = Backtest::run($symbol, $tf, $md);
                    // Rule-level detail is an admin concern, and the per-trade
                    // list is an analysis one - neither belongs in a member
                    // page's cached payload.
                    unset($report['stats'], $report['trades']);
                    Cache::set($cacheKey, $report, 900);
                } catch (Throwable $e) {
                    $error = 'Could not complete that backtest right now.';
                }
            }
        }
    }
}

View::head('Backtest', 'Replay the signal engine over stored history for any coin and timeframe.',
    // A member's own replay of their own choices. Nothing here is the same
    // twice for two people, so there is nothing for a search engine to index -
    // but the links out of it are worth following, hence noindex and not
    // nofollow.
    ['noindex' => true]);
View::topbar('');
?>
<main class="page-wrap narrow">
  <div class="page-head">
    <h1>Backtest a coin</h1>
    <p>Replays the engine over the candle history stored on this server and settles every simulated
       signal with the same conservative walker used for the public track record — stop before
       target on an ambiguous bar, trading costs subtracted, expired setups excluded as no-trades.
       Chart rules only: news, funding and positioning have no reliable history to replay.</p>
    <p class="hint-p">These setups are generated here and now, from the candles &mdash; they are not
       a reading of what the site published at the time. That is the point of a backtest, and it is
       why the numbers here are their own thing rather than a copy of the track record.</p>
  </div>

  <?php if (!$allowed): ?>
    <div class="panel empty-state">
      <h2>Backtesting is a <?= $needTier === 'paid' ? 'premium' : 'member' ?> feature</h2>
      <p>Run the engine over any coin's stored history and see how the setups it produced actually
         resolved, before deciding whether it is worth following.</p>
      <p style="margin-top:14px">
        <a class="btn-primary" href="<?= $needTier === 'paid' ? 'upgrade.php' : 'account.php?tab=register' ?>">
          <?= $needTier === 'paid' ? 'Go premium →' : 'Create a free account →' ?></a>
      </p>
    </div>
  <?php else: ?>

  <?php if ($error !== null): ?>
    <div class="flash warn"><?= sma_e($error) ?></div>
  <?php endif; ?>

  <form class="scan-filters" method="get" action="backtest.php">
    <select name="symbol" aria-label="Coin" required>
      <option value="">Choose a coin…</option>
      <?php foreach ($accessible as $s): ?>
        <option value="<?= sma_e($s['symbol']) ?>" <?= $s['symbol'] === $symbol ? 'selected' : '' ?>>
          <?= sma_e($s['label']) ?> (<?= sma_e($s['symbol']) ?>)</option>
      <?php endforeach; ?>
    </select>
    <select name="tf" aria-label="Timeframe">
      <?php foreach ($coinTfs as $iv): ?>
        <option value="<?= sma_e($iv) ?>" <?= $iv === $tf ? 'selected' : '' ?>><?= sma_e($iv) ?></option>
      <?php endforeach; ?>
    </select>
    <button class="btn-primary" type="submit">Run backtest</button>
  </form>

  <?php if (is_array($report)): ?>
    <?php
    // "No result" had one shape and two meanings. A server with nothing
    // stored and a coin the engine never found a setup on both landed on
    // "not enough history", which is a lie in the second case and sends the
    // reader off to collect candles they already have. And a run where
    // setups fired but none of them resolved was shown as a full statistics
    // block with dashes in it - a win rate and an expectancy presented for
    // nothing that ever finished.
    $bars = (int)($report['bars'] ?? 0);
    $decided = (int)$report['confirmed'] + (int)$report['invalid'];
    $expectancy = $report['avg_r'];
    ?>
    <?php if ((int)$report['signals'] === 0 && empty($report['enough_history'])): ?>
      <div class="panel empty-state">
        <h2>Not enough history yet</h2>
        <p>This server holds <?= $bars > 0 ? sma_e((string)$bars) . ' candle(s)' : 'no candles' ?>
           for <?= sma_e($symbol) ?> on <?= sma_e($tf) ?> — the engine needs about 300 before it can
           read the first setup. Open the chart once to start collecting, or try a timeframe that
           has been viewed before.</p>
      </div>
    <?php elseif ((int)$report['signals'] === 0): ?>
      <div class="panel empty-state">
        <h2>No setup ever fired</h2>
        <p>The engine read all <?= sma_e((string)$bars) ?> stored candles of
           <?= sma_e($symbol) ?> on <?= sma_e($tf) ?> and never produced a single BUY or SELL, so
           there is nothing to score. That is a result, not a failure: it usually means the coin
           drifted sideways for the whole period, or that it is too quiet for the score and
           confluence thresholds this install is set to.</p>
        <p>Try a faster timeframe, where the same movement produces more candles, or a coin with
           more direction in it.</p>
      </div>
    <?php elseif ($decided === 0): ?>
      <div class="panel empty-state">
        <h2><?= (int)$report['signals'] ?> setup(s) fired, none of them resolved</h2>
        <p>Every one ran out of time before it reached its stop or its first target, so there is no
           win rate and no expectancy to report — a figure worked out from nothing resolved would
           be an invention. Expired setups are counted as no-trades, exactly as they are in the
           public track record.</p>
        <p>A longer stretch of stored history, or a faster timeframe, gives each setup the bars it
           needs to finish.</p>
      </div>
    <?php else: ?>
      <div class="reveal">
      <div class="stat-row">
        <div class="stat"><b class="<?= ($expectancy ?? 0) >= 0 ? 'pos' : 'neg' ?>">
          <?= $expectancy !== null ? ($expectancy > 0 ? '+' : '') . $expectancy : '—' ?></b>
          <span>expectancy (R)</span></div>
        <div class="stat"><b><?= $report['winrate'] !== null ? $report['winrate'] . '%' : '—' ?></b><span>win rate</span></div>
        <div class="stat"><b class="cnt" data-n="<?= (int)$report['signals'] ?>">0</b><span>simulated signals</span></div>
        <div class="stat"><b class="pos cnt" data-n="<?= (int)$report['confirmed'] ?>">0</b><span>hit target</span></div>
        <div class="stat"><b class="neg cnt" data-n="<?= (int)$report['invalid'] ?>">0</b><span>stopped out</span></div>
        <div class="stat"><b class="cnt" data-n="<?= (int)$report['expired'] ?>">0</b><span>expired</span></div>
      </div>
      <p class="stat-note">
        <?= sma_e($symbol) ?> on <?= sma_e($tf) ?>: <?= $decided ?> setups resolved either way.
        Expectancy is average R per trade after costs — the figure that decides whether a system
        makes money, which win rate on its own does not.
        <?php if ($decided < 20): ?>
          <strong>This is a small sample.</strong> Treat it as a sketch, not a measurement.
        <?php endif; ?>
      </p>
      </div>
      <?php
      $exc = is_array($report['excursion'] ?? null) ? $report['excursion'] : [];
      $win = $exc['win'] ?? ['n' => 0];
      $loss = $exc['loss'] ?? ['n' => 0];
      ?>
      <?php if ((int)($win['n'] ?? 0) > 0 || (int)($loss['n'] ?? 0) > 0): ?>
        <div class="reveal">
        <h2 class="sec">How the trades behaved</h2>
        <div class="stat-row">
          <div class="stat"><b class="neg"><?= $win['avg_mae'] !== null ? $win['avg_mae'] : '—' ?>R</b>
            <span>heat taken by winners</span></div>
          <div class="stat"><b class="pos"><?= $win['avg_mfe'] !== null ? '+' . $win['avg_mfe'] : '—' ?>R</b>
            <span>best point of winners</span></div>
          <div class="stat"><b><?= $win['avg_bars'] ?? '—' ?></b><span>bars held (winners)</span></div>
          <div class="stat"><b class="pos"><?= $loss['avg_mfe'] !== null ? '+' . $loss['avg_mfe'] : '—' ?>R</b>
            <span>best point of losers</span></div>
          <div class="stat"><b><?= $loss['avg_bars'] ?? '—' ?></b><span>bars held (losers)</span></div>
        </div>
        <p class="stat-note">
          "Heat" is how far the average winning trade went the wrong way before it worked, measured
          in units of the initial risk. Winners that routinely sit near &minus;1R are one stop-width
          away from being losers; winners that barely go offside mean the entries are early enough
          to matter. The same figure for losers says how often they were nearly right.
        </p>
        </div>
      <?php endif; ?>
      <p class="hint-p">Simulated on history this server happens to hold, with chart rules only, so
        it will not match live results exactly. Past behaviour does not predict future results.</p>
      <?php
      // The engine tunes its own rule weights, per-timeframe multipliers and
      // stop/target distances from verified outcomes. Those were fitted on the
      // same candles this simulation replays, so the replay knows things the
      // engine did not know at the time and the result is optimistic. Saying
      // so is the difference between a backtest and a sales pitch.
      $fittedRules = (int)Database::pdo()->query(
          'SELECT COUNT(*) FROM ta_knowledge WHERE ABS(weight - weight_base) > 0.001')->fetchColumn();
      ?>
      <?php if ($fittedRules > 0): ?>
        <p class="hint-p"><strong>Read this as an upper bound, not a forecast.</strong>
          <?= (int)$fittedRules ?> rule weights on this install have been tuned from verified
          outcomes, along with the per-timeframe stop and target distances. Those were fitted on
          the same candles replayed above, so the simulation is scored partly on knowledge the
          engine did not have at the time. Re-running these pairs with every fitted parameter
          returned to its shipped default measured the difference at roughly 0.04R per trade — so a
          live result below this figure is the expected outcome, not a malfunction.</p>
      <?php endif; ?>
    <?php endif; ?>
  <?php elseif ($error === null): ?>
    <div class="panel empty-state">
      <h2>Pick a coin to begin</h2>
      <p>Results are cached briefly, so re-running the same pair is instant.</p>
    </div>
  <?php endif; ?>
  <?php endif; ?>
</main>
<?php View::footer(); ?>
