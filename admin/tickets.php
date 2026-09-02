<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_installed();
require_staff();
require_once __DIR__ . '/_layout.php';

$status = get('status', 'open_all');
$sql    = 'SELECT t.*, u.name AS client_name, u.email AS client_email, p.name AS project_name
           FROM tickets t
           LEFT JOIN users u ON u.id = t.user_id
           LEFT JOIN projects p ON p.id = t.project_id';
$params = [];

if ($status === 'open_all') {
    $sql .= " WHERE t.status IN ('open','answered','in_progress')";
} elseif (in_array($status, ['open', 'answered', 'in_progress', 'closed'], true)) {
    $sql .= ' WHERE t.status = ?';
    $params[] = $status;
} else {
    $status = 'all';
}
$sql .= ' ORDER BY t.updated_at DESC, t.id DESC';

$tickets = db_all($sql, $params);

admin_head('Support', 'tickets.php');
admin_page_head('Support and maintenance requests',
    'Everything clients have raised from their portal. Replying emails them and shows in their portal.');
?>

<div class="filters">
<?php
$tabs = [
    'open_all'    => 'Needs attention',
    'open'        => 'Open',
    'answered'    => 'Answered',
    'in_progress' => 'In progress',
    'closed'      => 'Closed',
    'all'         => 'Everything',
];
foreach ($tabs as $s => $label): ?>
  <a class="<?= $status === $s ? 'on' : '' ?>" href="tickets.php?status=<?= $s ?>"><?= $label ?></a>
<?php endforeach; ?>
</div>

<div class="panel">
<?php if (!$tickets): ?>
  <div class="empty"><strong>Nothing here</strong>
    <p>When a client raises a support, maintenance or upgrade request it appears here.</p></div>
<?php else: ?>
  <div class="tablewrap"><table>
    <thead><tr><th>Request</th><th>Client</th><th>Project</th><th>Type</th><th>Updated</th><th class="right">Status</th></tr></thead>
    <tbody>
<?php foreach ($tickets as $t): ?>
      <tr>
        <td><a class="rowlink" href="ticket.php?id=<?= (int) $t['id'] ?>"><?= esc($t['subject']) ?></a>
          <span class="sub"><?= esc($t['reference']) ?></span></td>
        <td><?= esc($t['client_name'] ?? 'Unknown') ?>
          <span class="sub"><?= esc($t['client_email'] ?? '') ?></span></td>
        <td><?= esc($t['project_name'] ?? '—') ?></td>
        <td><span class="pill"><?= esc(ucfirst($t['category'])) ?></span>
<?php if ($t['priority'] === 'urgent'): ?> <span class="pill urgent">Urgent</span><?php endif; ?></td>
        <td class="num"><?= esc(datetime_human($t['updated_at'] ?: $t['created_at'])) ?></td>
        <td class="right"><?= status_pill($t['status']) ?></td>
      </tr>
<?php endforeach; ?>
    </tbody>
  </table></div>
<?php endif; ?>
</div>

<?php admin_foot(); ?>
