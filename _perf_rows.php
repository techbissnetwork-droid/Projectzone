<?php
declare(strict_types=1);

// NOT A PAGE. This file is included by others and has no business answering a
// request of its own: fetched directly it still runs a bootstrap, opens the
// database and executes whatever its top level does, and on a degraded install
// the admin bootstrap below renders a missing-files report - internal paths,
// to whoever asked. It returns nothing useful today; the guard is so that
// stays true after the next edit rather than by luck.
//
// SCRIPT_FILENAME is what the server decided to run. If that is this file,
// this file was the request.
if (isset($_SERVER['SCRIPT_FILENAME'])
    && realpath($_SERVER['SCRIPT_FILENAME']) === realpath(__FILE__)) {
    http_response_code(404);
    exit;
}

// `use` is per-file, so the imports in performance.php do not reach here - an
// included partial that leans on its includer's namespace aliases works right
// up until someone includes it from somewhere else, and then fatals.
use SignalMasterAi\MemberAuth;
use SignalMasterAi\View;

/**
 * The rows of the verified-trade table, on their own.
 *
 * Included by performance.php for the first page, and requested directly by
 * the Load more button for each page after it - so the markup exists once and
 * the two paths cannot drift into showing different columns.
 *
 * Expects $rows and $viewerTier to be set by whoever includes it.
 */
if (!isset($rows, $viewerTier)) {
    return;
}
?>
<?php
// Counted here rather than with count($rows): $rows may be a PDOStatement
// being streamed, which has no length until it has been walked.
$renderedRows = 0;
foreach ($rows as $r):
    $renderedRows++;
      // The plan each trade was actually published with, straight from the row
      // it was stored on. Showing the entry price alone asked a reader to take
      // on trust that the stop and targets were ever there.
      $pl = json_decode((string)($r['indicators'] ?? ''), true);
      $pl = is_array($pl) ? ($pl['levels'] ?? null) : null;
      // A SETTLED PLAN IS OPEN TO EVERYONE, including on a premium coin.
      //
      // This locked the levels of a finished trade behind the coin's tier,
      // which protects nothing: the trade is over, the entry cannot be taken
      // and the result is already shown beside it. What it cost was the one
      // thing this page exists to do - let a stranger check the record rather
      // than take it on trust. A row reading "🔒 → 🔒, +1.2R" asks to be
      // believed. The tier still applies to what is still running - the
      // Scanner's "Live setups" sheet on charts.php - because those are calls
      // somebody could still act on.
      $locked = false;
      // A plan whose entry and stop are the same number is not a plan.
      //
      // Levels were once rounded to a fixed six decimal places, which on a
      // sub-cent coin collapsed the entry, the stop and all three targets onto
      // one value - so the row shows the same figure four times and an R
      // measured against a risk of zero. Those digits are gone; they were
      // never written. Saying so is the only honest option left, and it beats
      // presenting four identical numbers as a trade someone could have taken.
      $flatPlan = is_array($pl) && isset($pl['entry'], $pl['stop_loss'])
                  && (float)$pl['entry'] === (float)$pl['stop_loss'];
      $lv = static function ($v) use ($locked): string {
          if ($locked) {
              return '<span class="lock" title="This coin is for '
                   . 'members - the result is shown, the price is not"></span>';
          }
          return $v === null || $v === '' ? '—' : sma_e(\SignalMasterAi\View::price($v));
      };
    ?>
    <tr>
      <td><?= date('M j, H:i', (int)$r['closed_at']) ?></td>
      <td><strong><?= sma_e($r['symbol']) ?></strong></td>
      <td><?= sma_e($r['tf']) ?></td>
      <td class="sig-b <?= strtolower($r['signal']) ?>"><?= sma_e($r['signal']) ?></td>
      <?php if ($flatPlan): ?>
        <td colspan="3" class="muted" title="This signal predates the precision fix: its levels were
rounded to six decimals, which on a sub-cent coin rounds them all to the same number. The prices
were never stored at full precision, so they cannot be shown.">
          plan lost to rounding &mdash; pre-fix signal</td>
      <?php else: ?>
      <td><?= $lv($pl['entry'] ?? $r['price']) ?></td>
      <td class="r-neg"><?= $lv($pl['stop_loss'] ?? null) ?></td>
      <?php // The three targets in one cell. As three columns they tripled the
            // width of the table to repeat a number the Outcome column already
            // interprets - "TP2 reached" says which one mattered. ?>
      <td class="r-pos" style="white-space:nowrap"><?= $lv($pl['tp1'] ?? null) ?>
        <span class="muted">/</span> <?= $lv($pl['tp2'] ?? null) ?>
        <span class="muted">/</span> <?= $lv($pl['tp3'] ?? null) ?></td>
      <?php endif; ?>
      <td class="oc <?= sma_e($r['outcome']) ?>">
        <?= $r['outcome'] === 'confirmed' ? '✓ ' : ($r['outcome'] === 'invalid' ? '✗ ' : '⏱ ') ?><?= sma_e(\SignalMasterAi\View::shortOutcome($r['outcome_note'], (string)$r['outcome'])) ?>
      </td>
      <td>
        <?php if ($r['outcome'] === 'expired'): ?><span style="color:var(--muted)">0</span>
        <?php else: $rv = (float)$r['outcome_r']; ?>
          <span class="<?= $rv >= 0 ? 'r-pos' : 'r-neg' ?>"><?= $rv > 0 ? '+' : '' ?><?= $rv ?></span>
        <?php endif; ?>
      </td>
      <?php if (!empty($perfDetail)): ?>
        <?php // Excursion, shown only where it was actually measured.
              //
              // These three columns are NULL on every signal settled before
              // the telemetry existed, and a dash is the honest answer for
              // those. Printing 0.0 would say "this trade never went against
              // you", which is a claim about price action nobody recorded.
              //
              // Heat is stored as a negative R and rendered with its own sign,
              // so it reads the same way as the R column beside it.
              $mae = $r['mae_r'] === null || $r['mae_r'] === '' ? null : (float)$r['mae_r'];
              $mfe = $r['mfe_r'] === null || $r['mfe_r'] === '' ? null : (float)$r['mfe_r'];
              $bars = $r['bars_held'] === null || $r['bars_held'] === '' ? null : (int)$r['bars_held'];
        ?>
        <td class="num <?= $mae !== null && $mae < 0 ? 'r-neg' : '' ?>"><?= $mae === null ? '—' : round($mae, 2) ?></td>
        <td class="num <?= $mfe !== null && $mfe > 0 ? 'r-pos' : '' ?>"><?= $mfe === null ? '—' : ($mfe > 0 ? '+' : '') . round($mfe, 2) ?></td>
        <td class="num"><?= $bars === null ? '—' : $bars ?></td>
      <?php endif; ?>
    </tr>
<?php endforeach; ?>
