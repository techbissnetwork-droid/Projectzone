<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_installed();
require_client();
require_once __DIR__ . '/_layout.php';

$me       = current_user();
$projects = projects_for_user((int) $me['id']);
$ids      = array_column($projects, 'id');

$logs = [];
if ($ids) {
    $in   = implode(',', array_fill(0, count($ids), '?'));
    $logs = db_all(
        "SELECT m.*, p.name AS project_name FROM maintenance_logs m
         LEFT JOIN projects p ON p.id = m.project_id
         WHERE m.project_id IN ($in) AND m.visible_to_client = 1
         ORDER BY m.performed_on DESC, m.id DESC",
        $ids
    );
}

client_head('Maintenance', 'maintenance.php');
?>

<div class="hero-line">
  <h1>Maintenance history</h1>
  <p>Everything we have done to your site since it went live &mdash; backups, updates, fixes and
     renewals. This is the part most vendors never show you.</p>
</div>

<div class="panel">
<?php if (!$logs): ?>
  <div class="empty"><strong>Nothing logged yet</strong>
    <p>Once we start working on your site, every job appears here with the date it was done.</p></div>
<?php else: ?>
  <div class="pad"><div class="thread">
<?php foreach ($logs as $l): ?>
    <div class="msg">
      <div class="who">
        <b><?= esc($l['title']) ?></b>
        <span class="pill"><?= esc(ucfirst($l['kind'])) ?></span>
        <?= esc(date_human($l['performed_on'])) ?>
<?php if (count($projects) > 1 && $l['project_name']): ?>
        &middot; <?= esc($l['project_name']) ?>
<?php endif; ?>
      </div>
<?php if ($l['body']): ?>      <p><?= esc($l['body']) ?></p><?php endif; ?>
    </div>
<?php endforeach; ?>
  </div></div>
<?php endif; ?>
</div>

<?php client_foot(); ?>
