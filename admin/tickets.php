<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/guard.php';
require_admin();

$status = (string)($_GET['status'] ?? 'open_all');
$map = [
    'open_all'   => "t.status IN ('open','in_progress','answered')",
    'open'       => "t.status = 'open'",
    'in_progress'=> "t.status = 'in_progress'",
    'resolved'   => "t.status = 'resolved'",
    'closed'     => "t.status = 'closed'",
    'all'        => '1=1',
];
$cond = $map[$status] ?? $map['open_all'];

$tickets = Database::all(
    "SELECT t.*, u.name AS client_name, u.email AS client_email, p.name AS project_name,
            (SELECT COUNT(*) FROM ticket_messages m WHERE m.ticket_id = t.id) AS replies
     FROM tickets t
     LEFT JOIN users u ON u.id = t.user_id
     LEFT JOIN projects p ON p.id = t.project_id
     WHERE $cond
     ORDER BY CASE t.priority WHEN 'urgent' THEN 0 WHEN 'high' THEN 1 WHEN 'normal' THEN 2 ELSE 3 END,
              t.updated_at DESC"
);

$PAGE_TITLE = 'Support';
$AREA = 'admin';
require __DIR__ . '/../partials/app_header.php';
?>
<div class="filters">
  <?php foreach (['open_all' => 'Needs attention', 'open' => 'Open', 'in_progress' => 'In progress', 'resolved' => 'Resolved', 'closed' => 'Closed', 'all' => 'Everything'] as $k => $v): ?>
    <a href="?status=<?= e($k) ?>" class="<?= $status === $k ? 'on' : '' ?>"><?= e($v) ?></a>
  <?php endforeach; ?>
</div>

<section class="card">
  <?php if (!$tickets): ?>
    <div class="empty"><b>Nothing here</b><p>No tickets match this filter.</p></div>
  <?php else: ?>
    <div class="tablewrap"><table class="data">
      <thead><tr><th>Subject</th><th>Client</th><th>Project</th><th>Category</th><th>Priority</th><th>Status</th><th class="right">Updated</th></tr></thead>
      <tbody>
      <?php foreach ($tickets as $t): ?>
        <tr>
          <td><a class="linkish t-main" href="ticket.php?id=<?= (int)$t['id'] ?>"><?= e($t['subject']) ?></a>
              <span class="t-sub mono"><?= e($t['reference']) ?> · <?= (int)$t['replies'] ?> messages
              <?= $t['last_reply_by'] === 'client' && $t['status'] !== 'closed' ? ' · awaiting your reply' : '' ?></span></td>
          <td><?= e($t['client_name'] ?? '—') ?></td>
          <td><?= $t['project_name'] ? e($t['project_name']) : '<span class="muted">—</span>' ?></td>
          <td><span class="badge muted"><?= e(label($t['category'])) ?></span></td>
          <td><span class="badge <?= in_array($t['priority'], ['urgent','high'], true) ? 'danger' : 'muted' ?>"><?= e(label($t['priority'])) ?></span></td>
          <td><span class="badge <?= e(status_tone($t['status'])) ?>"><?= e(label($t['status'])) ?></span></td>
          <td class="right nowrap muted"><?= e(ftime($t['updated_at'])) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php endif; ?>
</section>
<?php require __DIR__ . '/../partials/app_footer.php'; ?>
