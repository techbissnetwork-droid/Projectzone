<?php
declare(strict_types=1);

/**
 * Change log.
 *
 * The engine retunes its own weights, benches rules that stop paying, and
 * applies setting changes through the champion/challenger loop. All of that
 * is meant to happen and none of it was visible, so a shift in results could
 * never be traced to a cause. This page is the trace.
 */

$config = require __DIR__ . '/_admin.php';

use SignalMasterAi\Audit;
use SignalMasterAi\Auth;
use SignalMasterAi\Database;
use SignalMasterAi\Maintenance;

Auth::requireLogin();

$actor  = (string)($_GET['actor'] ?? '');
$action = (string)($_GET['action'] ?? '');
$rows   = Audit::recent(300, $actor, $action);
$actors = Audit::actors();
$actions = [];
foreach (Database::pdo()->query('SELECT DISTINCT action FROM admin_log ORDER BY action')
         ->fetchAll(PDO::FETCH_COLUMN) as $a) {
    $actions[] = $a;
}

// How many of the recent changes the software made on its own, versus a
// person. The ratio is the point: on a quiet week the engine should be the
// only thing in here.
$byEngine = count(array_filter($rows, fn($r) => $r['actor'] === Audit::ACTOR_ENGINE));

admin_header('Change log', 'audit',
    'Who changed what, and when - including the changes the engine makes to itself when it '
    . 'retunes a weight or benches a rule.');
show_flash();
?>
<div class="cards">
  <div class="card"><div class="num"><?= count($rows) ?></div><div class="lbl">Changes shown</div></div>
  <div class="card"><div class="num"><?= $byEngine ?></div><div class="lbl">Made by the engine</div></div>
  <div class="card"><div class="num"><?= count($rows) - $byEngine ?></div><div class="lbl">Made by a person</div></div>
</div>

<?php admin_panel('What changed, and who changed it',
    'The engine retunes rule weights from settled outcomes, benches rules that stop paying, and '
    . 'applies its own setting changes - so "the numbers moved this week" has two possible causes, '
    . 'and this is how you tell them apart. Entries older than '
    . (int)Database::setting('audit_retention_days', '180') . ' days are dropped by cron.'); ?>

  <form method="get" action="audit.php" class="inline-form" style="margin:0 0 12px">
    <select name="actor" aria-label="Filter by who made the change" onchange="this.form.submit()">
      <option value="">Anyone</option>
      <?php foreach ($actors as $a): ?>
        <option value="<?= sma_e($a) ?>" <?= $actor === $a ? 'selected' : '' ?>>
          <?= $a === Audit::ACTOR_ENGINE ? 'The engine' : sma_e($a) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="action" aria-label="Filter by kind of change" onchange="this.form.submit()">
      <option value="">Any change</option>
      <?php foreach ($actions as $a): ?>
        <option value="<?= sma_e($a) ?>" <?= $action === $a ? 'selected' : '' ?>><?= sma_e($a) ?></option>
      <?php endforeach; ?>
    </select>
    <?php if ($actor !== '' || $action !== ''): ?>
      <a class="btn small gray" href="audit.php">Clear</a>
    <?php endif; ?>
  </form>

  <?php if (!$rows): ?>
    <?php admin_empty($actor !== '' || $action !== '' ? 'Nothing matches this filter' : 'Nothing recorded yet',
        'Changes appear here from the moment they are made - this page cannot show what happened before it existed.'); ?>
  <?php else: ?>
  <div class="tbl-scroll">
  <table class="grid stack">
    <tr class="head"><th>When</th><th>Who</th><th>Change</th><th>What</th><th>From</th><th>To</th></tr>
    <?php foreach ($rows as $r): ?>
    <tr>
      <td class="muted" style="white-space:nowrap" data-label="When"><?= sma_e(gmdate('M j, H:i', (int)$r['at'])) ?></td>
      <td data-label="Who"><?php if ($r['actor'] === Audit::ACTOR_ENGINE): ?>
            <span class="badge" style="background:var(--warn-bg);color:var(--warn)">ENGINE</span>
          <?php else: ?><strong><?= sma_e($r['actor']) ?></strong><?php endif; ?></td>
      <td data-label="Change"><code><?= sma_e($r['action']) ?></code></td>
      <td data-label="What"><?= sma_e($r['target']) ?></td>
      <td class="muted" data-label="From"><?= $r['before_val'] === '' ? '&mdash;' : sma_e($r['before_val']) ?></td>
      <td data-label="To"><strong><?= $r['after_val'] === '' ? '&mdash;' : sma_e($r['after_val']) ?></strong></td>
    </tr>
    <?php endforeach; ?>
  </table>
  </div>
  <?php endif; ?>
<?php admin_panel_end(); ?>
<?php admin_footer(); ?>
