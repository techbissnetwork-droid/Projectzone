<?php
/** A plain audit trail: who changed what, and when. */
require_once __DIR__ . '/../app/bootstrap.php';
require_installed();
require_staff();
require_once __DIR__ . '/_layout.php';

$page    = max(1, get_int('page', 1));
$perPage = 60;
$total   = db_count('SELECT COUNT(*) FROM activity_log');
$pages   = max(1, (int) ceil($total / $perPage));
$page    = min($page, $pages);
$offset  = ($page - 1) * $perPage;

$rows = db_all(
    'SELECT * FROM activity_log ORDER BY id DESC LIMIT ' . $perPage . ' OFFSET ' . $offset
);

admin_head('Activity', 'activity.php');
admin_page_head('Activity', 'A record of what has been changed in here, newest first.');
?>

<div class="panel">
<?php if (!$rows): ?>
  <div class="empty"><strong>Nothing recorded yet</strong></div>
<?php else: ?>
  <div class="tablewrap"><table>
    <thead><tr><th>When</th><th>Who</th><th>What</th></tr></thead>
    <tbody>
<?php foreach ($rows as $r): ?>
      <tr>
        <td class="num"><?= esc(datetime_human($r['created_at'])) ?></td>
        <td><?= esc($r['actor'] ?: 'System') ?></td>
        <td><?= esc($r['action']) ?><?php if ($r['entity']): ?>
          <span class="sub"><?= esc($r['entity']) ?><?= $r['entity_id'] ? ' #' . (int) $r['entity_id'] : '' ?></span>
<?php endif; ?></td>
      </tr>
<?php endforeach; ?>
    </tbody>
  </table></div>
<?php endif; ?>
</div>

<?php if ($pages > 1): ?>
<div class="filters">
<?php for ($i = 1; $i <= $pages; $i++): ?>
  <a class="<?= $i === $page ? 'on' : '' ?>" href="activity.php?page=<?= $i ?>"><?= $i ?></a>
<?php endfor; ?>
</div>
<?php endif; ?>

<?php admin_foot(); ?>
