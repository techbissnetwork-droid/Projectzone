<?php
declare(strict_types=1);

/**
 * Paper portfolio and trade journal.
 *
 * The site could publish the engine's overall win rate but never what happened
 * to a particular member - which signals they followed, at what size, with what
 * result. Positions here settle against real price action using the same
 * conservative walker as the public track record, so a member's numbers and the
 * site's numbers are produced by identical logic.
 */

$config = require __DIR__ . '/src/bootstrap.php';

use SignalMasterAi\Database;
use SignalMasterAi\MemberAuth;
use SignalMasterAi\MemberPrefs;
use SignalMasterAi\Paper;
use SignalMasterAi\View;

MemberAuth::start();
$member = MemberAuth::current();
if (!$member) {
    header('Location: account.php?next=portfolio.php');
    exit;
}
// Who may use it - Admin > Settings > What free and premium accounts get.
// Checked after the sign-in redirect, so a signed-out visitor is asked to log
// in rather than told to buy something they may already own.
if (!\SignalMasterAi\Gate::allows('portfolio')) {
    \SignalMasterAi\Gate::wall('portfolio', 'portfolio');
}
$mid = (int)$member['id'];
$prefs = MemberPrefs::get($mid);

$notice = '';
// Which mode the wallet panel should re-open in. Empty leaves it closed: a
// completed deposit is finished business, but a rejected one has to come back
// with the field still open, or the member is told what went wrong next to a
// button that has forgotten what they were doing.
$walletMode = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && MemberAuth::verifyCsrf()) {
    $act = $_POST['act'] ?? '';
    if ($act === 'exit_target') {
        // Changeable while the trade is open: a reader who decides mid-trade
        // to take the first target instead of holding for the second is making
        // a trading decision, and the portfolio should record the plan they
        // are actually following rather than the one they picked on entry.
        $tid = (int)($_POST['id'] ?? 0);
        $want = Paper::exitChoice($_POST['exit_target'] ?? Paper::EXIT_DEFAULT);
        Database::pdo()->prepare(
            "UPDATE paper_trades SET exit_target = ? WHERE id = ? AND member_id = ? AND status = 'open'"
        )->execute([$want, $tid, $mid]);
        $notice = match (true) {
            $want > 0 => "Auto-close set to target $want.",
            $want === 0 => 'Auto-close set to the site plan.',
            default => 'Auto-close set to no target - only the stop or the time stop.',
        };
    } elseif ($act === 'close') {
        Paper::close($mid, (int)($_POST['id'] ?? 0), (float)($_POST['price'] ?? 0),
            (string)($_POST['note'] ?? ''));
        $notice = 'Position closed.';
    } elseif ($act === 'deposit' || $act === 'withdraw') {
        // Funds behave like a wallet: money is paid in, trades draw on it,
        // and results move it. A single "account size" field could only be
        // overwritten, which is not how a balance works.
        $amt = round(max(0.0, min(1e9, (float)($_POST['amount'] ?? 0))), 2);
        $funds = Paper::funds($mid, $prefs);
        $current = max(0.0, (float)$prefs['account_size']);
        if ($amt <= 0) {
            $notice = 'Enter an amount above zero.';
            $walletMode = $act;
        } elseif ($act === 'withdraw' && $amt > $funds['available']) {
            // Margin behind an open position is not yours to take out yet.
            $notice = 'You can only withdraw what is not committed to open trades ($'
                    . number_format($funds['available'], 2) . ').';
            $walletMode = $act;
        } else {
            MemberPrefs::save($mid, [
                'account_size' => $act === 'deposit' ? $current + $amt : max(0.0, $current - $amt),
            ]);
            $notice = ($act === 'deposit' ? 'Added $' : 'Withdrew $') . number_format($amt, 2) . '.';
        }
    } elseif ($act === 'reset_wallet') {
        MemberPrefs::save($mid, ['account_size' => 0.0]);
        $notice = 'Wallet reset. Add funds to start again.';
    } elseif ($act === 'journal') {
        // A real trade the member took themselves. Recorded in the same table
        // as paper positions but tagged 'live', so their actual results can be
        // compared against what the engine said - which is the only comparison
        // that answers "is this helping me".
        $sym = strtoupper((string)preg_replace('/[^A-Z0-9]/i', '', (string)($_POST['symbol'] ?? '')));
        $jtf = (string)($_POST['tf'] ?? '');
        $entry = (float)($_POST['entry'] ?? 0);
        $stop  = (float)($_POST['stop_loss'] ?? 0);
        if ($sym !== '' && in_array($jtf, $config['market']['intervals'], true)
            && $entry > 0 && $stop > 0 && abs($entry - $stop) > 0) {
            Database::pdo()->prepare(
                'INSERT INTO paper_trades (member_id, symbol, tf, side, entry, stop_loss, tp1, tp2, tp3,
                    units, status, note, source, opened_at, exit_target)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
            )->execute([
                $mid, $sym, $jtf,
                ($_POST['side'] ?? 'BUY') === 'SELL' ? 'SELL' : 'BUY',
                $entry, $stop,
                (float)($_POST['tp1'] ?? 0), 0, 0,
                max(0.0, (float)($_POST['units'] ?? 0)),
                'open', mb_substr((string)($_POST['note'] ?? ''), 0, 500), 'live', time(),
                Paper::EXIT_DEFAULT,
            ]);
            $notice = 'Trade logged to your journal.';
        } else {
            $notice = 'A journal entry needs a coin, timeframe, entry and stop.';
        }
    }
    // Post/redirect/get, so the mode has to survive the redirect or a rejected
    // amount comes back to a closed panel.
    header('Location: portfolio.php?done=' . rawurlencode($notice)
         . ($walletMode !== '' ? '&wallet=' . rawurlencode($walletMode) . '#wallet' : ''));
    exit;
}
if (isset($_GET['done'])) {
    $notice = mb_substr((string)$_GET['done'], 0, 120);
}
if (in_array((string)($_GET['wallet'] ?? ''), ['deposit', 'withdraw'], true)) {
    $walletMode = (string)$_GET['wallet'];
}

$open    = Paper::positions($mid, 'open');
$closed  = Paper::positions($mid, 'closed', 60);
$summary = Paper::summary($mid);
// Followed signals and self-logged trades are summarised separately: the
// interesting question is whether the member does better or worse than the
// engine they are following.
$paperSum = Paper::summary($mid, 'paper');
$liveSum  = Paper::summary($mid, 'live');
$symbolList = Database::pdo()
    ->query('SELECT symbol, label FROM symbols WHERE enabled = 1'
          . \SignalMasterAi\Visibility::sql('portfolio', 'symbols') . ' ORDER BY symbol')->fetchAll();
$intervals = $config['market']['intervals'];
$csrf    = MemberAuth::csrfToken();

/**
 * The two prices at the front of every position card: what the engine
 * published, and what the member actually filled at.
 *
 * They are rarely the same. A setup found forty minutes ago has usually
 * moved, and entering at market keeps the stop and the targets while moving
 * the entry - so the trade taken is not quite the trade described. Holding
 * only the fill made that invisible; the gap existed but as a slippage
 * percentage nobody reads. Shown together, with the difference beside the
 * fill and signed so positive is always the worse price, on both sides.
 *
 * Journal entries have no signal behind them, and positions opened before
 * the published price was recorded cannot recover one, so the signal row is
 * left out rather than filled with a guess.
 */
$priceRows = static function (array $t): string {
    $sig = isset($t['signal_price']) && $t['signal_price'] !== null ? (float)$t['signal_price'] : null;
    $entry = (float)$t['entry'];
    $rows = '';
    if ($sig !== null && $sig > 0) {
        $rows .= '<div><dt>Signal price</dt><dd class="muted"'
               . ' title="The price this setup was published at."'
               . '>' . sma_e(View::price($sig)) . '</dd></div>';
    }
    $note = '';
    if ($sig !== null && $sig > 0 && $entry > 0) {
        $diff = ($entry - $sig) / $sig * 100 * ($t['side'] === 'BUY' ? 1 : -1);
        if (abs($diff) >= 0.005) {
            $note = ' <span class="' . ($diff > 0 ? 'neg' : 'pos') . '">'
                  . ($diff > 0 ? '+' : '−') . sma_e(number_format(abs($diff), 2)) . '%</span>';
        }
    }
    return $rows . '<div><dt>Your entry</dt><dd'
         . ($note !== '' ? ' title="How your fill compared with the published price. Positive is the worse price."' : '')
         . '>' . sma_e(View::price($entry)) . $note . '</dd></div>';
};

// Equity curve points, downsampled so the sparkline stays readable.
$curve = $summary['curve'];
if (count($curve) > 120) {
    $step = (int)ceil(count($curve) / 120);
    $curve = array_values(array_filter($curve, fn($_, $i) => $i % $step === 0, ARRAY_FILTER_USE_BOTH));
}

View::head('My portfolio', 'Your followed signals, settled against real price action.', ['noindex' => true]);
View::topbar('portfolio');
?>
<main class="page-wrap">
  <div class="page-head">
    <h1>My portfolio</h1>
    <p>Signals you followed, settled against the price action that actually happened. Nothing here
       is a brokerage account and no orders are placed anywhere.</p>
  </div>

  <?php if ($notice !== ''): ?><div class="flash ok"><?= sma_e($notice) ?></div><?php endif; ?>

  <?= \SignalMasterAi\Onboarding::render($mid) ?>

  <?php $funds = Paper::funds($mid, $prefs); ?>
  <!-- The wallet. Equity and unrealised P/L are filled in by script from live
       prices; the rest is settled fact from the database, so the page is still
       correct with no JavaScript. -->
  <section class="panel wallet" id="wallet">
    <div class="wallet-head">
      <h2>Wallet</h2>
      <?php if ($funds['unfunded']): ?>
        <span class="funds-default-tag">no funds yet</span>
      <?php endif; ?>
    </div>
    <div class="wallet-figures">
      <div class="wallet-main">
        <span>Equity</span>
        <strong id="wEquity" data-balance="<?= sma_e((string)$funds['balance']) ?>">
          $<?= number_format($funds['balance'], 2) ?></strong>
        <small id="wEquityNote">balance plus open profit and loss</small>
      </div>
      <div class="wallet-grid">
        <div><span>Balance</span><strong>$<?= number_format($funds['balance'], 2) ?></strong></div>
        <div><span>Available</span><strong>$<?= number_format($funds['available'], 2) ?></strong></div>
        <div><span>In open trades</span><strong>$<?= number_format($funds['used'], 2) ?></strong></div>
        <div><span>Open P&amp;L</span><strong id="wUnreal">&mdash;</strong></div>
        <div><span>Realised</span><strong class="<?= $funds['realised'] >= 0 ? 'pos' : 'neg' ?>">
          <?= ($funds['realised'] >= 0 ? '+$' : '−$') . number_format(abs($funds['realised']), 2) ?></strong></div>
        <div><span>Deposited</span><strong>$<?= number_format($funds['start'], 2) ?></strong></div>
      </div>
      <?php if ($funds['unfunded']): ?>
        <p class="wallet-empty">Nothing added yet, so there is nothing to size a position against.
          Add simulated funds below and follow a signal &mdash; no money moves anywhere and no order
          reaches an exchange.</p>
      <?php endif; ?>
    </div>
    <div class="wallet-actions">
      <?php
      // The amount field used to sit open permanently, so every visit to read
      // your own results opened with a deposit box. Adding funds is occasional;
      // reading the wallet is the reason the page exists. The two actions now
      // open the field, each in its own mode, and it stays shut otherwise -
      // including on an untouched wallet, where opening it by itself put the
      // same box back on the page of the people most likely to be just
      // looking. The one exception is a rejected amount, which has to come
      // back with the field still open or the message lands next to nothing.
      //
      // The mode lives in a radio group named act, so the checked radio *is*
      // the action posted. That keeps the whole thing working with JavaScript
      // off, and means pressing Enter in the field cannot submit the mode the
      // member is not in - which is exactly what a pair of CSS-hidden submit
      // buttons would have done, since the browser picks the first one whether
      // it is visible or not.
      $walletOpen = $walletMode;
      ?>
      <form method="post" action="portfolio.php" class="wallet-form">
        <input type="hidden" name="csrf" value="<?= sma_e($csrf) ?>">
        <input class="wallet-mode" type="radio" name="act" id="wmNone" value=""
               <?= $walletOpen === '' ? 'checked' : '' ?>>
        <input class="wallet-mode" type="radio" name="act" id="wmAdd" value="deposit"
               <?= $walletOpen === 'deposit' ? 'checked' : '' ?>>
        <input class="wallet-mode" type="radio" name="act" id="wmTake" value="withdraw"
               <?= $walletOpen === 'withdraw' ? 'checked' : '' ?>>
        <div class="wallet-choose">
          <?php // "Deposit" opens the field; "Add funds" is what the field does
                // once an amount is in it. Naming the two steps differently is
                // what stops the panel reading as the same button twice. ?>
          <label class="btn-primary" for="wmAdd">Deposit</label>
          <label class="wallet-withdraw" for="wmTake">Withdraw</label>
        </div>
        <div class="wallet-panel">
          <div class="funds-grid" id="depositPresets">
            <button type="button" data-amt="100"><b>$100</b><small>add</small></button>
            <button type="button" data-amt="500"><b>$500</b><small>add</small></button>
            <button type="button" data-amt="1000"><b>$1k</b><small>add</small></button>
            <button type="button" data-amt="5000"><b>$5k</b><small>add</small></button>
            <button type="button" data-amt="10000"><b>$10k</b><small>add</small></button>
            <button type="button" data-amt="50000"><b>$50k</b><small>add</small></button>
          </div>
          <div class="wallet-row">
            <input type="number" name="amount" id="depositAmount" min="1" step="any"
                   inputmode="decimal" placeholder="Amount in $" required>
            <button class="btn-primary" type="submit">
              <span class="only-add">Add funds</span><span class="only-take">Withdraw</span></button>
            <label class="wallet-withdraw wallet-cancel" for="wmNone">Cancel</label>
          </div>
          <p class="wallet-mode-note only-take">Available to withdraw:
            <strong>$<?= number_format($funds['available'], 2) ?></strong> — the rest is committed
            to open trades.</p>
        </div>
      </form>
      <p class="tp-note">Simulated funds for tracking your own results — no real money moves and
        nothing is deposited anywhere. Trades can only be opened from what is available.</p>
    </div>
  </section>

  <?php if ($summary['trades'] === 0 && $summary['open'] === 0): ?>
    <div class="panel empty-state">
      <h2>No positions yet</h2>
      <p>Open a chart and use <strong>Paper-trade this signal</strong> on any BUY or SELL setup. You
         choose how much of your balance to commit and whether to enter at the signal's price or the
         market's, then it settles itself as the market resolves it.</p>
      <p style="margin-top:14px"><a class="btn-primary" href="charts.php">Find a setup →</a></p>
    </div>
  <?php else: ?>

  <div class="stat-row">
    <div class="stat"><b><?= (int)$summary['trades'] ?></b><span>closed trades</span></div>
    <div class="stat"><b><?= $summary['winrate'] !== null ? $summary['winrate'] . '%' : '—' ?></b><span>win rate</span></div>
    <div class="stat"><b class="<?= ($summary['expectancy'] ?? 0) >= 0 ? 'pos' : 'neg' ?>">
      <?= $summary['expectancy'] !== null ? ($summary['expectancy'] > 0 ? '+' : '') . $summary['expectancy'] : '—' ?></b>
      <span>expectancy (R)</span></div>
    <div class="stat"><b><?= $summary['profit_factor'] !== null ? $summary['profit_factor'] : '—' ?></b><span>profit factor</span></div>
    <div class="stat"><b class="neg">−<?= sma_e((string)$summary['max_drawdown_r']) ?></b><span>max drawdown (R)</span></div>
    <div class="stat"><b><?= (int)$summary['open'] ?></b><span>open now</span></div>
  </div>
  <p class="stat-note">Expectancy is average R per trade — the number that decides whether a system
     makes money. A 40% win rate at 2R beats a 60% win rate at 0.5R, which is why win rate alone is
     shown here as a secondary figure.</p>

  <?php if (count($curve) > 1): ?>
  <section class="panel">
    <h2>Equity curve <span class="h2-sub">cumulative R</span></h2>
    <?php
    $vals = array_column($curve, 'equity');
    $min = min($vals); $max = max($vals);
    $span = ($max - $min) ?: 1;
    $w = 900; $h = 180; $pad = 8;
    $pts = [];
    foreach ($vals as $i => $v) {
        $x = $pad + ($i / max(1, count($vals) - 1)) * ($w - 2 * $pad);
        $y = $h - $pad - (($v - $min) / $span) * ($h - 2 * $pad);
        $pts[] = round($x, 1) . ',' . round($y, 1);
    }
    $zeroY = $h - $pad - ((0 - $min) / $span) * ($h - 2 * $pad);
    $last = end($vals);
    ?>
    <svg class="equity" viewBox="0 0 <?= $w ?> <?= $h ?>" preserveAspectRatio="none" role="img"
         aria-label="Cumulative R across <?= count($vals) ?> closed trades, ending at <?= sma_e((string)round((float)$last, 2)) ?>R">
      <?php if ($zeroY >= 0 && $zeroY <= $h): ?>
        <line x1="0" y1="<?= round($zeroY, 1) ?>" x2="<?= $w ?>" y2="<?= round($zeroY, 1) ?>"
              stroke="currentColor" stroke-dasharray="4 4" opacity="0.25"/>
      <?php endif; ?>
      <polyline points="<?= sma_e(implode(' ', $pts)) ?>" fill="none"
                stroke="<?= ((float)$last) >= 0 ? 'var(--up)' : 'var(--down)' ?>" stroke-width="2"/>
    </svg>
    <p class="hint-p">Each point is one closed trade. Past results do not predict future results.</p>
  </section>
  <?php endif; ?>

  <?php if ($open): ?>
  <section class="panel table-panel">
    <h2>Open positions</h2>
    <!-- One card per position instead of a nine-column table. The table only
         fitted by scrolling sideways, which hid the columns that matter, and
         it had nowhere to put what a member actually opens this page for:
         what the trade is worth right now. -->
    <div class="pos-grid" id="posGrid">
      <?php foreach ($open as $t): ?>
        <?php
        $asset = sma_base_asset((string)$t['symbol']);
        $units = (float)$t['units'];
        ?>
        <article class="pos-card <?= strtolower($t['side']) ?>"
                 data-id="<?= (int)$t['id'] ?>"
                 data-symbol="<?= sma_e($t['symbol']) ?>"
                 data-side="<?= sma_e($t['side']) ?>"
                 data-entry="<?= sma_e((string)$t['entry']) ?>"
                 data-units="<?= sma_e((string)$units) ?>">
          <header class="pos-head">
            <?php // Reopen the chart the way the trade was taken. A position
                  // opened from a signal belongs on the engine's plan; one the
                  // member drew themselves belongs in their own workspace, and
                  // landing in the wrong one means the lines on the chart are
                  // not the lines of the trade being looked at. ?>
            <a class="pos-sym" href="charts.php?symbol=<?= urlencode($t['symbol']) ?>&amp;tf=<?= urlencode($t['tf']) ?>&amp;plan=<?= ($t['source'] ?? '') === 'manual' ? 'manual' : 'auto' ?>">
              <?= sma_e($t['symbol']) ?></a>
            <span class="pos-tf"><?= sma_e($t['tf']) ?></span>
            <span class="sig-pill <?= strtolower($t['side']) ?>"><?= sma_e($t['side']) ?></span>
            <?php // Whose plan this was - the engine's or the member's. Shown on
                  // every card rather than only on the odd one out, because
                  // "no badge" is not a statement anybody reads. ?>
            <?php [$srcLabel, $srcTitle] = \SignalMasterAi\Paper::sourceTag((string)($t['source'] ?? 'paper')); ?>
            <span class="src-tag src-<?= sma_e((string)($t['source'] ?? 'paper')) ?>"
                  title="<?= sma_e($srcTitle) ?>"><?= sma_e($srcLabel) ?></span>
          </header>

          <div class="pos-pnl" data-pnl>&mdash;</div>
          <div class="pos-now">Now <strong data-now>&mdash;</strong></div>

          <?php
          // All three targets, and which of them this position has actually
          // reached. See Paper::reached() for why "reached" rather than
          // "currently past". Only Target 1 was shown before, permanently in
          // green - so a position that passed it an hour ago looked identical
          // to one that has never been near it, and the rest of the plan was
          // hidden inside the auto-close dropdown.
          $hit = Paper::reached($t);
          ?>
          <dl class="pos-facts">
            <?= $priceRows($t) ?>
            <div><dt>Stop</dt><dd class="neg"><?= sma_e(View::price((float)$t['stop_loss'])) ?></dd></div>
            <?php foreach ([1, 2, 3] as $n):
                $tp = (float)($t['tp' . $n] ?? 0);
                if ($tp <= 0) { continue; }
                $done = !empty($hit['tp' . $n]);
            ?>
            <div><dt>Target <?= $n ?></dt>
              <dd class="tp-lvl <?= $done ? 'tp-hit' : '' ?>"
                  <?= $done ? 'title="Reached since this position opened"' : '' ?>>
                <?= sma_e(View::price($tp)) ?><?= $done ? ' <span aria-label="reached">&check;</span>' : '' ?></dd></div>
            <?php endforeach; ?>
            <div><dt>Size</dt><dd><?= $units > 0
              ? sma_e(rtrim(rtrim(number_format($units, 4), '0'), '.') . ' ' . $asset)
              : '&mdash;' ?></dd></div>
            <div><dt>Value</dt><dd data-value>&mdash;</dd></div>
            <div><dt>Opened</dt><dd class="muted"><?= sma_e(View::ago(time() - (int)$t['opened_at'])) ?></dd></div>
            <?php // Same reference charts.php shows on the signal panel this
                  // position was opened from - absent on a trade the member
                  // logged themselves, same as there. ?>
            <?php if (!empty($t['signal_ref'])): ?>
            <div><dt>Signal ID</dt><dd class="muted"><code><?= sma_e((string)$t['signal_ref']) ?></code></dd></div>
            <?php endif; ?>
          </dl>

          <?php
          // What will close this position, and the one part of it the member
          // gets to choose. The stop and the time stop are not optional - they
          // are what makes it a trade rather than a hope - so they are stated,
          // not offered.
          $want = Paper::exitChoice($t['exit_target'] ?? Paper::EXIT_DEFAULT);
          $tfBars = max(1, (int)($t['expires_bars'] ?? 0) ?: (int)Database::setting('time_stop_bars', '24'));
          ?>
          <form class="exit-form" method="post" action="portfolio.php">
            <input type="hidden" name="csrf" value="<?= sma_e($csrf) ?>">
            <input type="hidden" name="act" value="exit_target">
            <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
            <label>Auto-close at
              <select name="exit_target" data-submit-on-change>
                <?php foreach ([3, 2, 1] as $n): ?>
                  <?php if ((float)($t['tp' . $n] ?? 0) > 0): ?>
                    <option value="<?= $n ?>" <?= $want === $n ? 'selected' : '' ?>>
                      Target <?= $n ?> — <?= sma_e(View::price((float)$t['tp' . $n])) ?></option>
                  <?php endif; ?>
                <?php endforeach; ?>
                <option value="-1" <?= $want === -1 ? 'selected' : '' ?>>No target — stop loss or time stop only</option>
                <option value="0" <?= $want === 0 ? 'selected' : '' ?>>Auto — part at TP 1, rest at TP 2</option>
              </select>
            </label>
            <p class="exit-note">Closes itself at whichever comes first:
              <?= $want === -1 ? 'the stop loss' : 'the target above, the stop loss' ?>
              at <?= sma_e(View::price((float)$t['stop_loss'])) ?>, or the time stop after
              <?= (int)$tfBars ?> <?= sma_e($t['tf']) ?> candles. Closing by hand below overrides
              <?= $want === -1 ? 'both' : 'all three' ?>.</p>
          </form>

          <details class="close-form">
            <summary>Close position</summary>
            <form method="post" action="portfolio.php">
              <input type="hidden" name="csrf" value="<?= sma_e($csrf) ?>">
              <input type="hidden" name="act" value="close">
              <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
              <label>Exit price
                <input type="number" step="any" name="price" required
                       value="<?= sma_e((string)$t['entry']) ?>" data-exit>
              </label>
              <label>Note <input type="text" name="note" maxlength="200" placeholder="why you exited"></label>
              <button class="btn-primary" type="submit">Close position</button>
            </form>
          </details>
        </article>
      <?php endforeach; ?>
    </div>
    <p class="tbl-note">Open positions settle automatically when the stop, a target, or the time stop
      is reached. Closing manually records your own exit instead. Live profit and loss is marked
      against the last traded price and refreshes while this page is open.</p>
  </section>
  <?php endif; ?>

  <?php if ($paperSum['trades'] > 0 && $liveSum['trades'] > 0): ?>
  <section class="panel">
    <h2>Followed signals vs your own trades</h2>
    <p class="hint-p">The comparison that actually matters: whether following the engine works better
      than your own judgement, measured the same way for both.</p>
    <!-- Two rows of four figures. As a table the row labels were the first
         thing to slide off the edge, leaving numbers with nothing to name
         them - the one part of a comparison you cannot lose. -->
    <div class="cmp-grid">
      <?php foreach ([['Followed signals', $paperSum], ['Your own trades', $liveSum]] as [$label, $sum]): ?>
        <div class="cmp-card">
          <h3><?= sma_e($label) ?></h3>
          <dl class="pos-facts">
            <div><dt>Trades</dt><dd><?= (int)$sum['trades'] ?></dd></div>
            <div><dt>Win rate</dt><dd><?= $sum['winrate'] !== null ? sma_e((string)$sum['winrate']) . '%' : '—' ?></dd></div>
            <div><dt>Expectancy</dt>
              <dd class="<?= ($sum['expectancy'] ?? 0) >= 0 ? 'pos' : 'neg' ?>">
                <?= sma_e((string)($sum['expectancy'] ?? '—')) ?>R</dd></div>
            <div><dt>Profit factor</dt><dd><?= sma_e((string)($sum['profit_factor'] ?? '—')) ?></dd></div>
          </dl>
        </div>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

  <?php if ($closed): ?>
  <section class="panel table-panel">
    <h2>Closed trades</h2>
    <!-- Cards, like the open positions. An eight-column table needed sideways
         scrolling on a phone, which put the result and the P&L - the two
         columns anyone opens this list for - off the right-hand edge. -->
    <div class="pos-grid closed-grid">
      <?php foreach ($closed as $t): $r = (float)$t['outcome_r']; $pnl = (float)$t['pnl']; ?>
        <article class="pos-card closed <?= $r >= 0 ? 'win' : 'loss' ?>">
          <header class="pos-head">
            <span class="pos-sym"><?= sma_e($t['symbol']) ?></span>
            <span class="pos-tf"><?= sma_e($t['tf']) ?></span>
            <span class="sig-pill <?= strtolower($t['side']) ?>"><?= sma_e($t['side']) ?></span>
            <?php // Whose plan this was - the engine's or the member's. Shown on
                  // every card rather than only on the odd one out, because
                  // "no badge" is not a statement anybody reads. ?>
            <?php [$srcLabel, $srcTitle] = \SignalMasterAi\Paper::sourceTag((string)($t['source'] ?? 'paper')); ?>
            <span class="src-tag src-<?= sma_e((string)($t['source'] ?? 'paper')) ?>"
                  title="<?= sma_e($srcTitle) ?>"><?= sma_e($srcLabel) ?></span>
          </header>
          <div class="pos-pnl <?= $pnl >= 0 ? 'pos' : 'neg' ?>">
            <?= $pnl != 0.0
                ? (($pnl > 0 ? '+$' : '−$') . sma_e(number_format(abs($pnl), 2)))
                : '—' ?>
            <span class="pos-r <?= $r >= 0 ? 'pos' : 'neg' ?>"><?= $r > 0 ? '+' : '' ?><?= sma_e((string)$r) ?>R</span>
          </div>
          <div class="pos-now"><?= sma_e($t['note'] !== '' ? $t['note'] : ($r >= 0 ? 'Target reached' : 'Stopped out')) ?></div>
          <?php
          // In at what, out at what. A finished trade used to show its entry
          // and the date and nothing else, so there was no way to check the
          // record against a chart - the exit was worked out and thrown away.
          // A plan that took part off at TP1 and let the rest run has no
          // single fill, so what is shown there is the average the whole
          // position achieved; the title says so rather than implying a tick.
          $exit = isset($t['exit_price']) && $t['exit_price'] !== null ? (float)$t['exit_price'] : null;
          $held = (int)$t['closed_at'] - (int)$t['opened_at'];
          $units = (float)$t['units'];
          $asset = sma_base_asset((string)$t['symbol']);
          ?>
          <dl class="pos-facts">
            <?= $priceRows($t) ?>
            <div><dt>Your exit</dt>
              <dd class="<?= $exit === null ? '' : ($r >= 0 ? 'pos' : 'neg') ?>"
                  <?= $exit === null ? '' : 'title="Where the position left the market. If the plan took part off at target 1 and let the rest run, this is the average the whole position achieved."' ?>>
                <?= $exit === null ? '&mdash;' : sma_e(View::price($exit)) ?></dd></div>
            <div><dt>Stop</dt><dd class="neg"><?= sma_e(View::price((float)$t['stop_loss'])) ?></dd></div>
            <?php // Same three targets as the open card, judged over the window
                  // this position was actually open for - not to now, which
                  // would credit a stopped-out trade with a price reached days
                  // after it was closed.
                  $hit = Paper::reached($t);
                  foreach ([1, 2, 3] as $n):
                      $tp = (float)($t['tp' . $n] ?? 0);
                      if ($tp <= 0) { continue; }
                      $done = !empty($hit['tp' . $n]);
            ?>
            <div><dt>Target <?= $n ?></dt>
              <dd class="tp-lvl <?= $done ? 'tp-hit' : '' ?>"
                  <?= $done ? 'title="Reached while this position was open"' : '' ?>>
                <?= sma_e(View::price($tp)) ?><?= $done ? ' <span aria-label="reached">&check;</span>' : '' ?></dd></div>
            <?php endforeach; ?>
            <div><dt>Size</dt><dd><?= $units > 0
              ? sma_e(rtrim(rtrim(number_format($units, 4), '0'), '.') . ' ' . $asset)
              : '&mdash;' ?></dd></div>
            <?php $cWant = Paper::exitChoice($t['exit_target'] ?? Paper::EXIT_DEFAULT); ?>
            <div><dt>Closed by</dt><dd class="muted"><?= $cWant > 0
              ? 'target ' . $cWant . ' plan'
              : ($cWant === 0 ? 'site plan' : 'no-target plan') ?></dd></div>
            <div><dt>Held</dt><dd class="muted"><?= $held > 0 ? sma_e(View::span($held)) : '&mdash;' ?></dd></div>
            <div><dt>Closed</dt><dd class="muted"><?= sma_e(MemberPrefs::memberDate($prefs, 'M j, H:i', (int)$t['closed_at'])) ?></dd></div>
            <?php if (!empty($t['signal_ref'])): ?>
            <div><dt>Signal ID</dt><dd class="muted"><code><?= sma_e((string)$t['signal_ref']) ?></code></dd></div>
            <?php endif; ?>
          </dl>
        </article>
      <?php endforeach; ?>
    </div>
    <p class="tbl-note">R is profit in units of the initial risk, after the configured round-trip
      trading cost. Cash P&amp;L is worked out from the funds in your wallet above.</p>
  </section>
  <?php endif; ?>
  <?php endif; ?>

  <section class="panel">
    <h2>Log a trade</h2>
    <p class="hint-p">See how your own trades stack up — scored the same way as every signal on this
      site. Entry and stop are enough; the result settles itself as the price plays out.</p>
    <form method="post" action="portfolio.php" class="journal-form">
      <input type="hidden" name="csrf" value="<?= sma_e($csrf) ?>">
      <input type="hidden" name="act" value="journal">
      <label>Coin
        <select name="symbol" required>
          <?php foreach ($symbolList as $s): ?>
            <option value="<?= sma_e($s['symbol']) ?>"><?= sma_e($s['symbol']) ?></option>
          <?php endforeach; ?>
        </select></label>
      <label>Timeframe
        <select name="tf">
          <?php foreach ($intervals as $iv): ?>
            <option value="<?= sma_e($iv) ?>" <?= $iv === '1h' ? 'selected' : '' ?>><?= sma_e($iv) ?></option>
          <?php endforeach; ?>
        </select></label>
      <label>Side
        <select name="side"><option value="BUY">Long</option><option value="SELL">Short</option></select></label>
      <label>Entry <input type="number" step="any" name="entry" required></label>
      <label>Stop loss <input type="number" step="any" name="stop_loss" required></label>
      <label>Target <input type="number" step="any" name="tp1" placeholder="optional"></label>
      <label>Size <input type="number" step="any" name="units" placeholder="optional"></label>
      <label class="j-note">Note <input type="text" name="note" maxlength="200" placeholder="why you took it"></label>
      <button class="btn-primary" type="submit">Log trade</button>
    </form>
  </section>

</main>
<script<?= sma_nonce() ?>>
// Marks every open position to the last traded price and rolls the result up
// into equity. Without this the page could only ever show what had already
// settled, which is the one thing a member does not need to be told.
// Deposit presets are wired on their own: they belong to an empty wallet
// more than to a full one, and sharing the marking-to-market guard below
// meant they did nothing at all until a position existed.
(function () {
  var presets = document.getElementById('depositPresets');
  var amount = document.getElementById('depositAmount');
  if (!presets || !amount) return;
  presets.addEventListener('click', function (e) {
    var b = e.target.closest('button[data-amt]');
    if (!b) return;
    amount.value = b.dataset.amt;
    Array.prototype.forEach.call(this.children, function (c) { c.classList.toggle('on', c === b); });
  });
  amount.addEventListener('input', function () {
    var v = parseFloat(this.value);
    Array.prototype.forEach.call(presets.children, function (c) {
      c.classList.toggle('on', parseFloat(c.dataset.amt) === v);
    });
  });
})();

(function () {
  var cards = Array.prototype.slice.call(document.querySelectorAll('.pos-card'));
  var eq = document.getElementById('wEquity');
  var un = document.getElementById('wUnreal');
  if (!cards.length || !eq) return;
  var balance = parseFloat(eq.dataset.balance) || 0;

  function money(v) {
    return (v < 0 ? '−$' : '$') + Math.abs(v)
      .toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }
  function signed(v) {
    return (v >= 0 ? '+$' : '−$') + Math.abs(v)
      .toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }
  function price(v) {
    var dp = Math.abs(v) >= 1000 ? 2 : (Math.abs(v) >= 1 ? 4 : 6);
    return v.toLocaleString(undefined, { maximumFractionDigits: dp });
  }

  async function tick() {
    if (document.visibilityState !== 'visible') return;
    var syms = cards.map(function (c) { return c.dataset.symbol; })
      .filter(function (v, i, a) { return v && a.indexOf(v) === i; });
    // No open positions means nothing to price. Asking anyway sent an empty
    // symbol list every ten seconds and got a 404 back each time.
    if (!syms.length) return;
    var prices;
    try {
      var r = await fetch('api.php?action=ticker&symbols=' + encodeURIComponent(syms.join(',')));
      var j = await r.json();
      if (!j.ok) return;
      prices = j.prices || {};
    } catch (e) { return; }

    var unreal = 0, priced = 0;
    cards.forEach(function (c) {
      var p = prices[c.dataset.symbol];
      if (typeof p !== 'number') return;
      priced++;
      var dir = c.dataset.side === 'SELL' ? -1 : 1;
      var entry = parseFloat(c.dataset.entry) || 0;
      var units = parseFloat(c.dataset.units) || 0;
      var pnl = dir * (p - entry) * units;
      var notion = entry * units;
      unreal += pnl;

      var el = c.querySelector('[data-pnl]');
      el.textContent = signed(pnl) + (notion > 0 ? '  (' + (pnl >= 0 ? '+' : '') + (pnl / notion * 100).toFixed(2) + '%)' : '');
      el.className = 'pos-pnl ' + (pnl >= 0 ? 'pos' : 'neg');
      c.querySelector('[data-now]').textContent = price(p);
      var val = c.querySelector('[data-value]');
      if (val) val.textContent = money(p * units);
      // Pre-fill the close form with the live price rather than the entry, so
      // closing "now" records now instead of a flat result.
      var exit = c.querySelector('[data-exit]');
      if (exit && !exit.matches(':focus')) exit.value = p;
    });

    if (priced) {
      un.textContent = signed(unreal);
      un.className = unreal >= 0 ? 'pos' : 'neg';
      var equity = balance + unreal;
      eq.textContent = money(equity);
      eq.className = unreal === 0 ? '' : (unreal > 0 ? 'pos' : 'neg');
    }
  }
  tick();
  setInterval(tick, 10000);
})();
</script>
<?php View::footer(); ?>
