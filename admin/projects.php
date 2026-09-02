<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_installed();
require_staff();
require_once __DIR__ . '/_layout.php';

$statusFilter = get('status');
$search       = get('q');

$sql    = 'SELECT p.*, u.name AS client_name, u.email AS client_email
           FROM projects p LEFT JOIN users u ON u.id = p.user_id';
$where  = [];
$params = [];

if (in_array($statusFilter, ['active', 'building', 'paused', 'ended'], true)) {
    $where[]  = 'p.status = ?';
    $params[] = $statusFilter;
} else {
    $statusFilter = '';
}
if ($search !== '') {
    $where[]  = '(p.name LIKE ? OR p.domain LIKE ? OR p.company LIKE ? OR p.owner_email LIKE ?)';
    $like     = '%' . $search . '%';
    array_push($params, $like, $like, $like, $like);
}
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY p.id DESC';

$projects = db_all($sql, $params);

admin_head('Projects', 'projects.php');
admin_page_head(
    'Client projects',
    'Every site you run, with its domain, hosting, SSL and email renewal dates.',
    [['project-edit.php?action=new', 'Add a project', '']]
);
?>

<div class="filters">
  <a class="<?= $statusFilter === '' ? 'on' : '' ?>" href="projects.php">All</a>
<?php foreach (['active' => 'Live', 'building' => 'Building', 'paused' => 'Paused', 'ended' => 'Ended'] as $s => $label): ?>
  <a class="<?= $statusFilter === $s ? 'on' : '' ?>" href="projects.php?status=<?= $s ?>"><?= $label ?>
    (<?= db_count('SELECT COUNT(*) FROM projects WHERE status = ?', [$s]) ?>)</a>
<?php endforeach; ?>
  <form method="get">
    <input type="text" name="q" placeholder="Search name, domain, email" value="<?= esc($search) ?>">
    <button class="btn ghost sm" type="submit">Search</button>
  </form>
</div>

<div class="panel">
<?php if (!$projects): ?>
  <div class="empty">
    <strong><?= $search !== '' || $statusFilter !== '' ? 'Nothing matches' : 'No projects yet' ?></strong>
    <p><?= $search !== '' || $statusFilter !== ''
        ? 'Try a different search, or clear the filters.'
        : 'Add a project and you can record its domain, hosting, SSL and email renewals, and give the owner a login.' ?></p>
    <p style="margin-top:16px"><a class="btn" href="project-edit.php?action=new">Add a project</a></p>
  </div>
<?php else: ?>
  <div class="tablewrap">
    <table>
      <thead><tr>
        <th>Project</th><th>Client</th><th>Domain</th>
        <th>Hosting</th><th>SSL</th><th>Email</th><th class="right">Status</th>
      </tr></thead>
      <tbody>
<?php foreach ($projects as $p):
        $r = project_renewals($p); ?>
        <tr>
          <td><a class="rowlink" href="project-edit.php?id=<?= (int) $p['id'] ?>"><?= esc($p['name']) ?></a>
            <span class="sub"><?= esc($p['company'] ?: $p['owner_name'] ?: 'No company recorded') ?></span></td>
          <td>
<?php if ($p['client_name']): ?>
            <?= esc($p['client_name']) ?><span class="sub"><?= esc($p['client_email']) ?></span>
<?php else: ?>
            <span class="pill soon">No login yet</span>
<?php endif; ?>
          </td>
<?php foreach ($r as $item): ?>
          <td><span class="pill <?= esc($item['state']) ?>" title="<?= esc($item['label'] . ': ' . date_human($item['date'])) ?>">
            <?= $item['state'] === 'none' ? 'Not set' : esc($item['human']) ?></span></td>
<?php endforeach; ?>
          <td class="right"><?= status_pill($p['status']) ?></td>
        </tr>
<?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>
</div>

<p style="color:var(--mute);font-size:13.5px">
  The four coloured pills are domain, hosting, SSL and email renewal dates in that order.
  Orange means inside 14 days, amber inside 45.
</p>

<?php admin_foot(); ?>
