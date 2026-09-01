<?php
declare(strict_types=1);

$config = require __DIR__ . '/_admin.php';

use SignalMasterAi\Auth;
use SignalMasterAi\Database;
use SignalMasterAi\Maintenance;

Auth::requireLogin();
$pdo = Database::pdo();

/**
 * Turn the prune form into a WHERE clause, shared by the preview count and the
 * delete itself so the number shown and the number removed cannot drift apart.
 *
 * Every field is optional and an empty form matches everything - which is why
 * the button says how many rows it is about to take with it.
 */
function sma_signal_filter(array $in): array
{
    $w = [];
    $a = [];
    $sym = strtoupper((string)preg_replace('/[^A-Za-z0-9:._-]/', '', (string)($in['f_symbol'] ?? '')));
    if ($sym !== '') {
        $w[] = 'symbol = ?';
        $a[] = $sym;
    }
    $tf = (string)preg_replace('/[^0-9a-z]/', '', (string)($in['f_tf'] ?? ''));
    if ($tf !== '') {
        $w[] = 'tf = ?';
        $a[] = $tf;
    }
    $side = (string)($in['f_signal'] ?? '');
    if (in_array($side, ['BUY', 'SELL', 'NEUTRAL'], true)) {
        $w[] = '`signal` = ?';
        $a[] = $side;
    }
    $outcome = (string)($in['f_outcome'] ?? '');
    if ($outcome === 'open') {
        $w[] = "outcome = ''";
    } elseif (in_array($outcome, ['confirmed', 'invalid', 'expired', 'none'], true)) {
        $w[] = 'outcome = ?';
        $a[] = $outcome;
    }
    $days = max(0, min(3650, (int)($in['f_days'] ?? 0)));
    if ($days > 0) {
        $w[] = 'created_at < ?';
        $a[] = time() - $days * 86400;
    }
    return [$w ? ' WHERE ' . implode(' AND ', $w) : '', $a];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::verifyCsrf();
    // 'clear' lived here and is now System > Delete data, which asks which
    // kinds as well as which coins. The handler goes with the form rather than
    // sitting behind a URL an old bookmark could still post to.

    // Targeted delete. "Clear everything" was the only tool here, so removing
    // one bad coin's rows, or a timeframe that was never meant to be live, or
    // the NEUTRAL noise that dwarfs the actionable calls, meant throwing away
    // the whole record instead.
    if (($_POST['act'] ?? '') === 'prune') {
        [$where, $args] = sma_signal_filter($_POST);
        $del = $pdo->prepare('DELETE FROM signals' . $where);
        $del->execute($args);
        Maintenance::syncSignalState();
        \SignalMasterAi\Audit::log('signals.prune', 'targeted prune', '',
            $del->rowCount() . ' row(s) deleted');
        flash($del->rowCount() . ' signal(s) deleted.');
        header('Location: signals.php');
        exit;
    }
    // Re-settle the exits that were scored under the old, self-contradictory
    // position-management model: a trade that tagged TP1 and came back was
    // booked at the full TP1 profit as if the whole position had been sold
    // there, while one that ran to TP2 was booked at the full TP2 profit as if
    // none of it had. Only those two exits are reopened - stop-outs, expiries
    // and clean TP2 hits are unaffected - and only where the candles needed to
    // walk them again are still on disk. Everything else is left exactly as it
    // was recorded.
    if (($_POST['act'] ?? '') === 'resettle') {
        $rows = $pdo->query(
            "SELECT id, symbol, tf, created_at, indicators FROM signals
             WHERE outcome = 'confirmed'
               AND (outcome_note LIKE 'TP1 reached, stopped at break-even%'
                 OR outcome_note = 'TP1 reached'
                 OR outcome_note = 'TP2 reached')"
        )->fetchAll();
        $has = $pdo->prepare('SELECT COUNT(*) FROM candles WHERE symbol = ? AND tf = ? AND open_time > ?');
        $reopen = $pdo->prepare(
            // closed_at and outcome_r are NOT NULL, so an unsettled row is
            // zeroed rather than nulled - which is exactly how a signal looks
            // before the verifier has ever reached it.
            "UPDATE signals SET outcome = '', outcome_note = '', closed_at = 0, outcome_r = 0
             WHERE id = ?"
        );
        $queued = 0;
        $skipped = 0;
        foreach ($rows as $r) {
            // A row is only reopened when it can actually be settled again.
            // Without its stored trade plan the verifier would mark it
            // unverifiable and a real recorded outcome would be lost to a
            // maintenance action, which is the opposite of the point.
            $inds = json_decode((string)$r['indicators'], true);
            $lv = is_array($inds) ? ($inds['levels'] ?? null) : null;
            if (!is_array($lv) || !isset($lv['stop_loss'], $lv['tp1'])) {
                $skipped++;
                continue;
            }
            $has->execute([$r['symbol'], $r['tf'], ((int)$r['created_at']) * 1000]);
            if ((int)$has->fetchColumn() < 2) {
                $skipped++;      // history pruned from under it; leave it alone
                continue;
            }
            $reopen->execute([$r['id']]);
            $queued++;
        }
        // Settle them straight away rather than waiting for the next cron.
        [$won, $lost] = \SignalMasterAi\Outcomes::evaluate(max(300, $queued));
        flash("Re-settled $queued outcome(s) under the current exit plan "
            . "($won confirmed, $lost stopped)."
            . ($skipped > 0 ? " $skipped left as recorded - their trade plan or candles are no longer on disk." : ''));
        header('Location: signals.php');
        exit;
    }
}

// Twenty-five a page, unless the operator wants more - or all of them.
//
// Twenty-five was fine for a glance and useless for the job this page is
// actually used for: finding the run of signals around something that went
// wrong. `per` is remembered in the URL so the pager keeps it.
$allowedPer = [25, 50, 100, 250, 500, 0];
$perPage = (int)($_GET['per'] ?? (int)Database::setting('admin_signals_per', '25'));
if (!in_array($perPage, $allowedPer, true)) {
    $perPage = 25;
}
// And remembered, which is what the setting was for.
//
// admin_signals_per was read here as the default and written by nothing, in
// this file or any other - it had a default, a sanitiser in the settings save
// whitelist and no code path that could ever change it. So the choice lived in
// the URL only: pick 250, follow any link off this page, come back, and you
// are on 25 again. Found by listing settings the application reads against
// settings something can write.
if (isset($_GET['per']) && $perPage !== (int)Database::setting('admin_signals_per', '25')) {
    Database::setSetting('admin_signals_per', (string)$perPage);
}
$total = (int)$pdo->query('SELECT COUNT(*) FROM signals')->fetchColumn();
$pages = $perPage > 0 ? max(1, (int)ceil($total / $perPage)) : 1;
$page = max(1, min((int)($_GET['page'] ?? 1), $pages));

if ($perPage > 0) {
    $stmt = $pdo->prepare('SELECT * FROM signals ORDER BY created_at DESC LIMIT ? OFFSET ?');
    $stmt->bindValue(1, $perPage, PDO::PARAM_INT);
    $stmt->bindValue(2, ($page - 1) * $perPage, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt;
} else {
    // Streamed, not fetched into an array: the ledger is the one table here
    // that grows without bound, and "all of them" has to stay affordable on a
    // shared host however long the install has been running.
    $rows = $pdo->query('SELECT * FROM signals ORDER BY created_at DESC');
}
$csrf = Auth::csrfToken();
$detailId = (int)($_GET['view'] ?? 0);

// FIND ONE BY ITS PUBLIC REFERENCE.
//
// A member writes in quoting SM-MCSK6B, and until now the only way to reach
// that call from here was to know roughly when it fired and page through the
// ledger. The reference is on the chart card, in the alert, and on the row
// tooltip, so it is what a support message will actually contain.
//
// Normalised rather than matched literally: it arrives lower-cased, without
// the prefix, or with an O where the alphabet has none, and a search that
// answers "no such signal" to a reference that exists teaches the operator to
// distrust the search. Anything unrecognisable is reported as not found
// rather than run as a LIKE across the largest table on the install.
$refQuery = trim((string)($_GET['ref'] ?? ''));
if ($refQuery !== '' && $detailId === 0) {
    $norm = \SignalMasterAi\SignalRef::normalise($refQuery);
    $found = 0;
    if (\SignalMasterAi\SignalRef::valid($norm)) {
        $rs = $pdo->prepare('SELECT id FROM signals WHERE ref = ? LIMIT 1');
        $rs->execute([$norm]);
        $found = (int)$rs->fetchColumn();
        $rs->closeCursor();
    }
    if ($found > 0) {
        header('Location: signals.php?view=' . $found);
        exit;
    }
    // Raw, not escaped: show_flash escapes it on the way out, and escaping
    // here as well prints &amp;lt; to anybody who pastes a tag into the box.
    flash('No signal has the reference ' . ($norm !== '' ? $norm : $refQuery)
        . '. References look like SM-MCSK6B.', 'warn');
    header('Location: signals.php');
    exit;
}

admin_header('Signal history', 'signals',
    'Every call the engine has ever made - ' . number_format($total) . ' of them - with the exact '
    . 'indicator snapshot and the rules that fired at the moment it decided.');
show_flash();
?>
<?php if ($detailId):
    $ds = $pdo->prepare('SELECT * FROM signals WHERE id = ?');
    $ds->execute([$detailId]);
    $d = $ds->fetch();
      $ds->closeCursor();
      if ($d):
        $reasons = json_decode($d['reasons'], true) ?: [];
        $inds = json_decode($d['indicators'], true) ?: [];
?>
<?php admin_panel(''); ?>
  <h2>
    <?= sma_e($d['symbol']) ?> @ <?= sma_e($d['tf']) ?> &mdash;
    <span class="badge <?= strtolower($d['signal']) ?>"><?= sma_e($d['signal']) ?></span>
    <span class="hint" style="display:inline">score <?= sma_e((string)$d['score']) ?>, confidence <?= sma_e((string)$d['confidence']) ?>%, <?= gmdate('Y-m-d H:i:s', (int)$d['created_at']) ?> UTC</span>
    <?php if (($d['ref'] ?? '') !== ''): ?>
      <?php // The one string that names THIS call. Printed in full in the
            // heading because this page is where somebody arrives holding it,
            // and confirming they landed on the right signal is the first
            // thing they need. ?>
      <code class="sig-ref-tag"><?= sma_e((string)$d['ref']) ?></code>
    <?php endif; ?>
  </h2>
  <?php
  // The trade plan, spelled out. It was already stored - buried inside the
  // indicator snapshot as a raw JSON blob, next to sixty other keys - so the
  // one thing an admin opens a signal to check was the hardest thing on the
  // page to read.
  $lv = is_array($inds['levels'] ?? null) ? $inds['levels'] : null;
  $num = static function ($v): string {
      return $v === null || $v === '' ? '—' : rtrim(rtrim(number_format((float)$v, 6, '.', ','), '0'), '.');
  };
  ?>
  <h3 style="font-size:13px;margin:18px 0 6px">Trade plan</h3>
  <?php if ($lv === null): ?>
    <p class="hint">No plan stored — levels were switched off, or the verdict was NEUTRAL.</p>
  <?php else: ?>
    <table class="grid">
      <tr><th style="width:190px">Level</th><th>Price</th><th>Distance from entry</th></tr>
      <?php
      $entry = (float)($lv['entry'] ?? 0);
      $plan = [
          ['Entry',       $lv['entry'] ?? null,      ''],
          ['Entry zone',  null,                      ''],
          ['Stop loss',   $lv['stop_loss'] ?? null,  'neg'],
          ['Take profit 1', $lv['tp1'] ?? null,      'pos'],
          ['Take profit 2', $lv['tp2'] ?? null,      'pos'],
          ['Take profit 3', $lv['tp3'] ?? null,      'pos'],
      ];
      foreach ($plan as [$label, $val, $cls]):
          if ($label === 'Entry zone'):
              if (!isset($lv['entry_low'], $lv['entry_high'])) { continue; } ?>
              <tr><td style="color:var(--muted)">Entry zone</td>
                  <td><?= $num($lv['entry_low']) ?> &ndash; <?= $num($lv['entry_high']) ?></td>
                  <td class="hint">tolerance around the entry, because a reader acts minutes later</td></tr>
          <?php continue; endif;
          if ($val === null) { continue; }
          $pct = $entry > 0 ? ((float)$val - $entry) / $entry * 100 : null; ?>
        <tr>
          <td style="color:var(--muted)"><?= $label ?></td>
          <td style="font-weight:600<?= $cls === 'neg' ? ';color:var(--down)' : ($cls === 'pos' ? ';color:var(--up)' : '') ?>">
            <?= $num($val) ?></td>
          <td class="hint"><?= $pct === null || $label === 'Entry' ? '' : sprintf('%+.2f%%', $pct) ?></td>
        </tr>
      <?php endforeach; ?>
    </table>
    <table class="grid" style="margin-top:10px">
      <tr>
        <th>Risk / reward</th><th>After costs</th><th>Fees as share of risk</th>
        <th>Risk per unit</th><th>Time stop</th>
      </tr>
      <tr>
        <td><?= !empty($lv['rr_type'])
              ? '<span class="rr-type rr' . (int)($lv['rr_tier'] ?? 1) . '">' . sma_e((string)$lv['rr_type']) . '</span>'
              : (isset($lv['rr']) ? '1 : ' . sma_e((string)$lv['rr']) : '—') ?></td>
        <td<?= isset($lv['rr_net']) && (float)$lv['rr_net'] < 1 ? ' style="color:var(--down)"' : '' ?>>
          <?= isset($lv['rr_net']) ? '1 : ' . sma_e((string)$lv['rr_net']) : '—' ?></td>
        <td<?= isset($lv['cost_r']) && (float)$lv['cost_r'] > 0.33 ? ' style="color:var(--down)"' : '' ?>>
          <?= isset($lv['cost_r']) ? sma_e((string)round((float)$lv['cost_r'] * 100)) . '%' : '—' ?></td>
        <td><?= isset($lv['risk_pct']) ? sma_e((string)$lv['risk_pct']) . '%' : '—' ?></td>
        <td><?= isset($lv['expires_bars']) ? (int)$lv['expires_bars'] . ' candles'
              . (isset($lv['expires_in']) ? ' (~' . sma_e((string)$lv['expires_in']) . ')' : '') : '—' ?></td>
      </tr>
    </table>
    <?php if (!empty($lv['exit_rules'])): ?>
      <p class="hint" style="margin-top:10px"><strong>Exit rules published with this signal:</strong></p>
      <ul class="hint" style="margin:6px 0 0 18px">
        <?php foreach ((array)$lv['exit_rules'] as $er): ?><li><?= sma_e((string)$er) ?></li><?php endforeach; ?>
      </ul>
    <?php endif; ?>
  <?php endif; ?>

  <h3 style="font-size:13px;margin:18px 0 6px">How it settled</h3>
  <table class="grid">
    <tr><th style="width:190px">Outcome</th><th>Result</th><th>Heat taken (MAE)</th><th>Best point (MFE)</th><th>Bars held</th></tr>
    <tr>
      <td><span class="badge <?= $d['outcome'] === 'confirmed' ? 'buy' : ($d['outcome'] === 'invalid' ? 'sell' : '') ?>">
        <?= $d['outcome'] === '' ? 'still open' : sma_e((string)$d['outcome']) ?></span></td>
      <td style="font-weight:600<?= (float)$d['outcome_r'] < 0 ? ';color:var(--down)' : ((float)$d['outcome_r'] > 0 ? ';color:var(--up)' : '') ?>">
        <?= $d['outcome'] === '' ? '—' : sprintf('%+.2fR', (float)$d['outcome_r']) ?>
        <?php if (($d['outcome_note'] ?? '') !== ''): ?>
          <span class="hint" style="display:block"><?= sma_e((string)$d['outcome_note']) ?></span>
        <?php endif; ?></td>
      <td><?= isset($d['mae_r']) && $d['mae_r'] !== null ? sma_e((string)$d['mae_r']) . 'R' : '—' ?></td>
      <td><?= isset($d['mfe_r']) && $d['mfe_r'] !== null ? '+' . sma_e((string)$d['mfe_r']) . 'R' : '—' ?></td>
      <td><?= isset($d['bars_held']) && $d['bars_held'] !== null ? (int)$d['bars_held'] : '—' ?></td>
    </tr>
  </table>

  <h3 style="font-size:13px;margin:18px 0 6px">Rules fired</h3>
  <?php if (!$reasons): ?><p class="hint">No rules fired (flat market).</p><?php else: ?>
  <table class="grid">
    <tr><th>Rule</th><th>Side</th><th>Weight</th><th>Detail</th></tr>
    <?php foreach ($reasons as $r): ?>
    <tr>
      <td><?= sma_e($r['rule'] ?? '') ?></td>
      <td><span class="badge <?= ($r['side'] ?? '') === 'bullish' ? 'buy' : 'sell' ?>"><?= sma_e($r['side'] ?? '') ?></span></td>
      <td><?= sma_e((string)($r['weight'] ?? '')) ?></td>
      <td><?= sma_e($r['detail'] ?? '') ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php endif; ?>
  <h3 style="font-size:13px;margin:18px 0 6px">Indicator snapshot</h3>
  <p class="hint">Everything the engine measured at the moment it decided. The trade plan is lifted
    out above rather than repeated here.</p>
  <table class="grid">
    <?php foreach ($inds as $k => $v): if ($k === 'levels') { continue; } ?>
      <tr><td style="width:200px;color:var(--muted)"><?= sma_e((string)$k) ?></td>
          <td><?= sma_e($v === null ? '—' : (is_array($v) ? json_encode($v) : (string)$v)) ?></td></tr>
    <?php endforeach; ?>
  </table>
  <p style="margin-top:14px"><a class="btn gray" href="signals.php">&larr; Back to list</a></p>
<?php admin_panel_end(); ?>
<?php endif; endif; ?>

<?php admin_panel('All signals',
    'Newest first. OPEN means the trade is still running against its plan; the rest have settled '
    . 'and are what the public track record is built from. Open one to see the exact evidence '
    . 'behind it.'); ?>
<form method="get" action="signals.php" class="ref-find">
  <label for="refFind">Find by Signal ID</label>
  <input type="text" id="refFind" name="ref" placeholder="SM-MCSK6B" maxlength="16"
         autocomplete="off" spellcheck="false">
  <button class="btn small gray" type="submit">Open</button>
  <span class="hint">The reference a member sees on the chart and in their alert. Case and the
    <code>SM-</code> do not matter.</span>
</form>
<?php if (!$rows): ?>
  <?php admin_empty('No signals stored', 'The engine writes a row here the moment a coin flips. If this stays empty, check that cron is running and that at least one coin and timeframe are enabled.'); ?>
<?php else: ?>
<div class="tbl-scroll">
<table class="grid">
  <tr class="head"><th>When (UTC)</th><th>Symbol</th><th>TF</th><th>Signal</th><th>Score</th><th>Conf.</th><th>Price</th><th>Outcome</th><th></th></tr>
  <?php foreach ($rows as $s): ?>
  <tr>
    <td><?= gmdate('Y-m-d H:i', (int)$s['created_at']) ?></td>
    <td><?= sma_e($s['symbol']) ?>
      <?php if (($s['ref'] ?? '') !== ''): ?>
        <?php // Under the coin rather than in a column of its own: this table
              // already scrolls sideways on a phone, and a tenth column would
              // push the outcome - the thing being scanned for - off the edge. ?>
        <span class="hint" style="display:block"><?= sma_e((string)$s['ref']) ?></span>
      <?php endif; ?></td>
    <td><?= sma_e($s['tf']) ?></td>
    <td><span class="badge <?= strtolower($s['signal']) ?>"><?= sma_e($s['signal']) ?></span></td>
    <td><?= $s['score'] > 0 ? '+' : '' ?><?= sma_e((string)$s['score']) ?></td>
    <td><?= sma_e((string)$s['confidence']) ?>%</td>
    <td><?= sma_e((string)$s['price']) ?></td>
    <td>
      <?php $oc = (string)($s['outcome'] ?? ''); ?>
      <?php if ($oc === 'confirmed'): ?>
        <span class="badge buy" title="<?= sma_e($s['outcome_note'] ?? '') ?>">✓ CONFIRMED</span>
      <?php elseif ($oc === 'invalid'): ?>
        <span class="badge sell" title="<?= sma_e($s['outcome_note'] ?? '') ?>">✗ INVALID</span>
      <?php elseif ($oc === 'expired'): ?>
        <span class="badge off" title="<?= sma_e($s['outcome_note'] ?? '') ?>">⏱ EXPIRED</span>
      <?php elseif ($s['signal'] === 'NEUTRAL' || $oc === 'none'): ?>
        <span class="badge off">—</span>
      <?php else: ?>
        <span class="badge neutral">OPEN</span>
      <?php endif; ?>
    </td>
    <td><a class="btn small gray" href="signals.php?view=<?= (int)$s['id'] ?>">Details</a></td>
  </tr>
  <?php endforeach; ?>
</table>
</div>

<p style="margin-top:14px;display:flex;gap:8px;align-items:center;flex-wrap:wrap">
  <?php $pq = fn(int $pg) => 'signals.php?page=' . $pg . '&per=' . (int)$perPage; ?>
  <?php if ($perPage > 0 && $page > 1): ?><a class="btn small gray" href="<?= $pq($page - 1) ?>">&larr; Newer</a><?php endif; ?>
  <?php if ($perPage > 0 && $page < $pages): ?><a class="btn small gray" href="<?= $pq($page + 1) ?>">Older &rarr;</a><?php endif; ?>
  <span class="hint" style="display:inline">
    <?php if ($perPage > 0): ?>Page <?= $page ?> / <?= number_format($pages) ?>
      &middot; <?= number_format($total) ?> signal<?= $total === 1 ? '' : 's' ?> stored
    <?php else: ?>All <?= number_format($total) ?> signal<?= $total === 1 ? '' : 's' ?> shown<?php endif; ?>
  </span>
  <?php // Twenty-five rows is a glance. Finding the run of signals around
        // something that went wrong needs the whole page, and sometimes the
        // whole table - so "all" is on the list and has no ceiling above it. ?>
  <span class="hint" style="display:inline;margin-left:auto">Show
    <?php foreach ([25, 50, 100, 250, 500, 0] as $n): ?>
      <?php if ($n === $perPage): ?><strong><?= $n === 0 ? 'all' : $n ?></strong>
      <?php else: ?><a href="signals.php?per=<?= $n ?>"><?= $n === 0 ? 'all' : $n ?></a><?php endif; ?>
      <?= $n === 0 ? '' : '&middot;' ?>
    <?php endforeach; ?>
    per page</span>
</p>
<?php endif; ?>
<?php admin_panel_end(); ?>

<?php
// 'TP2 reached' is on this list because those rows were settled by a walk
// that closed the whole remainder there, before a third leg existed. Until
// they are re-walked the record shows a ceiling the market never imposed.
$stale = (int)$pdo->query(
    "SELECT COUNT(*) FROM signals WHERE outcome = 'confirmed'
       AND (outcome_note LIKE 'TP1 reached, stopped at break-even%'
            OR outcome_note = 'TP1 reached'
            OR outcome_note = 'TP2 reached')"
)->fetchColumn();
?>
<?php if ($stale > 0): ?>
<?php admin_panel('Re-settle past outcomes',
    '' . number_format($stale) . ' settled call(s) were closed by an older walk that had no third '
    . 'target, so the record shows a ceiling the market never imposed.', '', 'warn'); ?>
  <p class="hint"><strong><?= number_format($stale) ?></strong> settled signal(s) were scored before
    the verifier settled every exit under one position-management plan. A trade that reached TP1 and
    came back to entry was recorded at the <em>full</em> TP1 profit, as if the whole position had
    been sold there, while a trade that carried on to TP2 was recorded at the full TP2 profit, as if
    none of it had — two contradictory plans, and each trade booked at whichever paid more. On the
    replayed history that was worth about +0.21R per trade, which was the entire measured edge.</p>
  <p class="hint">This walks those trades again under the plan set in
    <a href="settings.php#signals">Trade levels</a> (currently
    <strong><?= (int)Database::setting('tp1_partial_pct', '50') ?>%</strong> off at TP1). Stop-outs,
    expiries and clean TP2 hits are not touched, and neither is anything whose candles have since
    been pruned. Expect the published track record to fall — that is the point.</p>
  <form method="post" action="signals.php"
        onsubmit="return confirm('Re-settle <?= (int)$stale ?> outcome(s)? The published track record will change.')">
    <input type="hidden" name="csrf" value="<?= $csrf ?>">
    <input type="hidden" name="act" value="resettle">
    <button class="btn" type="submit">Re-settle under the current exit plan</button>
  </form>
<?php admin_panel_end(); ?>
<?php endif; ?>

<?php if ($total > 0):
// The filter is read from the query string so the count below is produced by
// the same clause the delete will run, rather than by a second query that
// could disagree with it.
[$pWhere, $pArgs] = sma_signal_filter($_GET);
$pStmt = $pdo->prepare('SELECT COUNT(*) FROM signals' . $pWhere);
$pStmt->execute($pArgs);
$pCount = (int)$pStmt->fetchColumn();
$filtered = $pWhere !== '';
$tfList = $pdo->query('SELECT DISTINCT tf FROM signals ORDER BY tf')->fetchAll(PDO::FETCH_COLUMN);
$symList = $pdo->query('SELECT DISTINCT symbol FROM signals ORDER BY symbol')->fetchAll(PDO::FETCH_COLUMN);
$g = fn(string $k): string => (string)($_GET[$k] ?? '');
?>
<?php admin_panel('Delete some of it',
    'One coin that should never have been listed, a timeframe that was only ever a test, or the '
    . 'NEUTRAL rows that outnumber the actionable calls many times over. Leave a field on '
    . '<em>Any</em> to ignore it.', '', 'bad'); ?>
  <form method="get" action="signals.php">
    <div class="row3">
      <div><label>Coin</label>
        <select aria-label="Coin" name="f_symbol">
          <option value="">Any</option>
          <?php foreach ($symList as $sy): ?>
            <option value="<?= sma_e($sy) ?>" <?= $g('f_symbol') === $sy ? 'selected' : '' ?>><?= sma_e($sy) ?></option>
          <?php endforeach; ?>
        </select></div>
      <div><label>Timeframe</label>
        <select aria-label="Timeframe" name="f_tf">
          <option value="">Any</option>
          <?php foreach ($tfList as $tfv): ?>
            <option value="<?= sma_e($tfv) ?>" <?= $g('f_tf') === $tfv ? 'selected' : '' ?>><?= sma_e($tfv) ?></option>
          <?php endforeach; ?>
        </select></div>
      <div><label>Verdict</label>
        <select aria-label="Verdict" name="f_signal">
          <option value="">Any</option>
          <?php foreach (['BUY', 'SELL', 'NEUTRAL'] as $sv): ?>
            <option value="<?= $sv ?>" <?= $g('f_signal') === $sv ? 'selected' : '' ?>><?= $sv ?></option>
          <?php endforeach; ?>
        </select></div>
    </div>
    <div class="row2">
      <div><label>Outcome</label>
        <select aria-label="Outcome" name="f_outcome">
          <option value="">Any</option>
          <?php foreach (['open' => 'Still open', 'confirmed' => 'Hit target',
                          'invalid' => 'Stopped out', 'expired' => 'Expired',
                          'none' => 'Unverifiable'] as $ov => $ol): ?>
            <option value="<?= $ov ?>" <?= $g('f_outcome') === (string)$ov ? 'selected' : '' ?>><?= $ol ?></option>
          <?php endforeach; ?>
        </select></div>
      <div><label>Older than (days, 0 = any age)</label>
        <input aria-label="Older than (days, 0 = any age)" type="number" min="0" max="3650" step="1" name="f_days" value="<?= sma_e($g('f_days')) ?>"></div>
    </div>
    <p style="margin-top:14px"><button class="btn gray" type="submit">Count matching signals</button>
      <?php if ($filtered): ?><a class="btn small gray" href="signals.php">Reset</a><?php endif; ?></p>
  </form>
  <?php if ($filtered): ?>
    <p class="hint"><strong><?= number_format($pCount) ?></strong> signal(s) match this filter
      out of <?= number_format($total) ?> stored.</p>
    <?php
    // What deleting VERIFIED rows would do to the published figures, spelled
    // out before it happens.
    //
    // Removing losses is the fastest way to a better-looking win rate and it
    // changes nothing about the engine - the same setups still fire, they just
    // stop being counted. Worse, the weight tuner and the learned model read
    // these rows: delete the trades that went wrong and the engine is taught
    // that the rules behind them work. So the panel does not block it, it
    // prices it, and it says which of the two numbers is doing the work.
    // The published set and the money definition, so the "before" figure is
    // the one actually on the site. It measured every settled row on the
    // label definition, which matched nothing the reader could see.
    $now = $pdo->query("SELECT COUNT(*) t, SUM(CASE WHEN outcome_r > 0 THEN 1 ELSE 0 END) w,
                               SUM(outcome_r) r FROM signals WHERE outcome IN ('confirmed','invalid')")->fetch();
    $goStmt = $pdo->prepare("SELECT COUNT(*) t, SUM(CASE WHEN outcome_r > 0 THEN 1 ELSE 0 END) w,
                                    SUM(outcome_r) r FROM signals"
        . ($pWhere === '' ? " WHERE outcome IN ('confirmed','invalid')"
                          : $pWhere . " AND outcome IN ('confirmed','invalid')"));
    $goStmt->execute($pArgs);
    $go = $goStmt->fetch();
    $goStmt->closeCursor();
    $nowT = (int)($now['t'] ?? 0);
    $goT  = (int)($go['t'] ?? 0);
    $leftT = $nowT - $goT;
    if ($goT > 0):
        $wrNow = $nowT > 0 ? round((int)$now['w'] / $nowT * 100, 1) : null;
        $wrLeft = $leftT > 0 ? round(((int)$now['w'] - (int)$go['w']) / $leftT * 100, 1) : null;
        $rNow = $nowT > 0 ? round((float)$now['r'] / $nowT, 3) : null;
        $rLeft = $leftT > 0 ? round(((float)$now['r'] - (float)$go['r']) / $leftT, 3) : null;
    ?>
      <div class="flash warn" style="margin-top:10px">
        <strong><?= number_format($goT) ?></strong> of these are verified outcomes, so this changes
        the published track record: win rate
        <strong><?= $wrNow !== null ? $wrNow . '%' : '—' ?></strong> &rarr;
        <strong><?= $wrLeft !== null ? $wrLeft . '%' : '—' ?></strong>, expectancy
        <strong><?= $rNow !== null ? ($rNow > 0 ? '+' : '') . $rNow . 'R' : '—' ?></strong> &rarr;
        <strong><?= $rLeft !== null ? ($rLeft > 0 ? '+' : '') . $rLeft . 'R' : '—' ?></strong>,
        over <?= number_format($leftT) ?> remaining trade(s).
        <span class="hint" style="display:block;margin-top:6px">If the win rate goes up here, nothing
          about the engine improved — the same setups will still fire, they just stop being counted,
          and the weight tuner and the learned model both read these rows, so deleting the trades
          that went wrong teaches the engine that the rules behind them worked. Expectancy is the
          number to watch: it is what decides whether the system makes money. Rows that were never
          real calls (tests, unverifiable ones with no trade plan, a coin that should never have
          been listed) are worth clearing; losses are not.</span>
      </div>
    <?php endif; ?>
    <?php if ($pCount > 0): ?>
      <form method="post" action="signals.php"
            onsubmit="return confirm('Delete <?= (int)$pCount ?> signal(s)? This cannot be undone.')">
        <input type="hidden" name="csrf" value="<?= $csrf ?>">
        <input type="hidden" name="act" value="prune">
        <?php foreach (['f_symbol', 'f_tf', 'f_signal', 'f_outcome', 'f_days'] as $k): ?>
          <input type="hidden" name="<?= $k ?>" value="<?= sma_e($g($k)) ?>">
        <?php endforeach; ?>
        <button class="btn danger" type="submit">Delete these <?= number_format($pCount) ?></button>
      </form>
    <?php endif; ?>
  <?php endif; ?>
<?php admin_panel_end(); ?>

<?php admin_panel('Clear the whole history', ''); ?>
<?php // MOVED, NOT REMOVED.
      //
      // This was a form with two checkboxes - clear the live board too, clear
      // the learning too - and the Coins page had its own form asking two of
      // the same three questions with different defaults. Both are now one
      // screen that asks which coins and which kinds, with a count against
      // every line, so an operator can see what "clear everything" is about to
      // mean before it means it.
      //
      // The TARGETED PRUNE above stays where it is: it deletes by filter
      // rather than by coin - a timeframe that was never meant to be live, the
      // NEUTRAL rows that dwarf the actionable ones - and its filter is the
      // one on this page, sitting above the table it describes. ?>
<p class="hint">Clearing the whole record, the live board it feeds, or what the engine learned from
  it are three separate decisions. They are asked together - with a count against each - under
  <a href="data.php"><strong>System &rsaquo; Delete data</strong></a>.</p>
<p><a class="btn gray" href="data.php">Open Delete data</a></p>
<p class="hint">This clears the <em>published</em> record: this page, the public track record and
  the figures drawn from them. It does not empty the backtester, and that is not a leftover &mdash;
  the backtester never reads this table. It replays the engine over the stored candles and
  generates its own simulated signals each time you press Run, which is what makes it a test of
  the rules rather than a re-reading of what they once said. Cached candles are on the same screen.</p>
<?php admin_panel_end(); ?>
<?php endif; ?>
<?php admin_footer(); ?>
