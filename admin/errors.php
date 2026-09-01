<?php
declare(strict_types=1);

/**
 * What is failing, and what to do about it.
 *
 * The app is built to fail soft - a refused price fetch, a delisted coin, an
 * SMTP box that rejects a login, a gateway callback that will not verify. Each
 * one is caught so it cannot take a page down, which is right for the visitor
 * and leaves the operator running a site that looks fine and quietly is not.
 * Shared hosting rarely gives anyone a readable PHP error log, and the cron
 * report only reaches whoever opens that minute's mail.
 *
 * So: one row per distinct problem, ordered by when it last happened, with the
 * setting that fixes it named in plain words. An empty page here is a real
 * statement - nothing has failed - not an absence of instrumentation.
 */

$config = require __DIR__ . '/_admin.php';

use SignalMasterAi\Auth;
use SignalMasterAi\Database;
use SignalMasterAi\ErrorLog;

Auth::requireLogin();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    Auth::verifyCsrf();
    $act = (string)($_POST['action'] ?? '');
    if ($act === 'resolve') {
        ErrorLog::resolve((int)($_POST['id'] ?? 0));
        flash('Marked as handled. If it happens again it comes straight back.');
    } elseif ($act === 'resolve_all') {
        $n = ErrorLog::resolveAll();
        flash($n . ' problem(s) marked as handled. Anything still broken will reappear '
            . 'the next time it happens.');
    }
    header('Location: errors.php' . (($_POST['area'] ?? '') !== '' ? '?area=' . urlencode((string)$_POST['area']) : ''));
    exit;
}

$area = (string)($_GET['area'] ?? '');
$showAll = ($_GET['all'] ?? '') === '1';
$rows = ErrorLog::recent(300, $area, $showAll);
$areas = ErrorLog::areas();
$day = ErrorLog::summary(24);
$week = ErrorLog::summary(24 * 7);
$open = count(array_filter($rows, static fn($r) => (int)$r['resolved'] === 0));

admin_header('Error log', 'errors',
    'Things that went wrong that the site carried on through. Nothing here means nothing has '
    . 'failed quietly since the log was last cleared.');
show_flash();
$csrf = Auth::csrfToken();
?>
<div class="cards">
  <div class="card"><div class="num"><?= $day['problems'] ?></div><div class="lbl">Problems in 24h</div></div>
  <div class="card"><div class="num"><?= $day['events'] ?></div><div class="lbl">Times they happened</div></div>
  <div class="card"><div class="num"><?= $week['problems'] ?></div><div class="lbl">Problems this week</div></div>
</div>

<?php if ($day['problems'] === 0 && !$showAll && $area === ''): ?>
<?php admin_panel('Nothing has failed in the last 24 hours'); ?>
  <p class="hint">Price fetches, signal analysis, settlement, alerts and payment callbacks all
    report their failures here. An empty list means they ran without one &mdash; not that nothing
    is being watched.
    <?php if ($rows): ?>
      <a href="errors.php?all=1">Show older and handled problems</a> (<?= count($rows) ?>).
    <?php endif; ?></p>
<?php admin_panel_end(); ?>
<?php endif; ?>

<?php admin_panel('What went wrong',
    'One row per distinct problem, not per occurrence - a delisted coin fails on every run, and '
    . 'that is one thing to fix rather than four hundred lines to scroll past. The count is how '
    . 'many times it happened; the fix line names the setting that ends it where the cause is '
    . 'known. Problems are dropped '
    . (int)Database::setting('error_retention_days', '30') . ' days after they last happened.'); ?>

  <form method="get" action="errors.php" class="inline-form" style="margin:0 0 12px">
    <select name="area" aria-label="Filter by area" onchange="this.form.submit()">
      <option value="">Everything</option>
      <?php foreach ($areas as $a): ?>
        <option value="<?= sma_e($a) ?>" <?= $area === $a ? 'selected' : '' ?>>
          <?= sma_e(ErrorLog::areaLabel($a)) ?></option>
      <?php endforeach; ?>
    </select>
    <label class="chk"><input type="checkbox" name="all" value="1" <?= $showAll ? 'checked' : '' ?>
      onchange="this.form.submit()"> Include handled</label>
    <?php if ($area !== '' || $showAll): ?>
      <a class="btn small gray" href="errors.php">Clear</a>
    <?php endif; ?>
  </form>

  <?php if (!$rows): ?>
    <?php admin_empty($area !== '' || $showAll ? 'Nothing matches this filter' : 'Nothing recorded',
        'Problems appear here from the moment they happen - this page cannot show what broke before it existed.'); ?>
  <?php else: ?>
  <div class="tbl-scroll">
  <?php // Stacks on a phone like every other admin table.
        //
        // It was a plain .grid, so on a narrow screen the columns kept their
        // widths and the Problem column - the only one worth reading - was cut
        // off mid-sentence at the right edge. The message, the "last seen on"
        // line and the fix were all clipped, which makes the page that exists
        // to tell an operator what is broken the one page they cannot read.
        //
        // .stack needs a data-label on every cell; that is what the stacked
        // layout prints where the column heading used to be. ?>
  <table class="grid stack">
    <tr class="head"><th>Area</th><th>Problem</th><th>Times</th><th>First</th><th>Last</th><th></th></tr>
    <?php foreach ($rows as $r): ?>
      <?php $hint = ErrorLog::hint((string)$r['area'], (string)$r['message']); ?>
    <tr<?= (int)$r['resolved'] === 1 ? ' class="dim"' : '' ?>>
      <td data-label="Area"><span class="badge"><?= sma_e(ErrorLog::areaLabel((string)$r['area'])) ?></span></td>
      <td data-label="Problem" style="display:block">
        <strong><?= sma_e((string)$r['message']) ?></strong>
        <?php if ((string)$r['context'] !== ''): ?>
          <div class="muted small">Last seen on: <?= sma_e((string)$r['context']) ?></div>
        <?php endif; ?>
        <?php if ($hint !== ''): ?>
          <div class="small" style="margin-top:4px"><b>Fix:</b> <?= sma_e($hint) ?></div>
        <?php endif; ?>
      </td>
      <td data-label="Times"><strong><?= (int)$r['seen'] ?></strong></td>
      <td data-label="First seen" class="muted" style="white-space:nowrap"><?= sma_e(gmdate('M j, H:i', (int)$r['first_at'])) ?></td>
      <td data-label="Last seen" class="muted" style="white-space:nowrap"><?= sma_e(gmdate('M j, H:i', (int)$r['last_at'])) ?></td>
      <td data-label="">
        <?php if ((int)$r['resolved'] === 1): ?>
          <span class="muted small">handled</span>
        <?php else: ?>
        <form method="post" action="errors.php" class="inline-form">
          <input type="hidden" name="csrf" value="<?= $csrf ?>">
          <input type="hidden" name="action" value="resolve">
          <input type="hidden" name="area" value="<?= sma_e($area) ?>">
          <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
          <button class="btn small gray" type="submit">Handled</button>
        </form>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
  </div>

  <?php if ($open > 0): ?>
  <form method="post" action="errors.php" style="margin-top:12px">
    <input type="hidden" name="csrf" value="<?= $csrf ?>">
    <input type="hidden" name="action" value="resolve_all">
    <button class="btn gray" type="submit">Mark all <?= $open ?> as handled</button>
    <?php // Was an inline <span> beside the button, which on a phone wrapped
          // its second line back under the button and read as two unrelated
          // fragments. Its own paragraph below. ?>
    <p class="hint">Clears the list without hiding anything: a problem that is still real
      reappears the next time it happens, which is how you find out a fix did not hold.</p>
  </form>
  <?php endif; ?>
  <?php endif; ?>
<?php admin_panel_end(); ?>
<?php admin_footer(); ?>
