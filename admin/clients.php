<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_installed();
require_staff();
require_once __DIR__ . '/_layout.php';

$search = get('q');
$sql    = "SELECT u.*, (SELECT COUNT(*) FROM projects p WHERE p.user_id = u.id) AS project_count
           FROM users u WHERE u.role = 'client'";
$params = [];
if ($search !== '') {
    $sql .= ' AND (u.name LIKE ? OR u.email LIKE ? OR u.company LIKE ?)';
    $like = '%' . $search . '%';
    array_push($params, $like, $like, $like);
}
$sql .= ' ORDER BY u.id DESC';
$clients = db_all($sql, $params);

admin_head('Client accounts', 'clients.php');
admin_page_head(
    'Client accounts',
    'People who can sign in to the client portal. Accounts are normally created from a project.',
    [['client-edit.php?action=new', 'Add a client', '']]
);
?>

<div class="filters">
  <form method="get" style="margin-left:0">
    <input type="text" name="q" placeholder="Search name, email, company" value="<?= esc($search) ?>">
    <button class="btn ghost sm" type="submit">Search</button>
<?php if ($search !== ''): ?>
    <a class="btn ghost sm" href="clients.php">Clear</a>
<?php endif; ?>
  </form>
</div>

<div class="panel">
<?php if (!$clients): ?>
  <div class="empty">
    <strong><?= $search !== '' ? 'Nothing matches' : 'No client accounts yet' ?></strong>
    <p>The usual way to create one is from a project — put the owner's email in and tick
       <em>Give the owner a login</em>.</p>
    <p style="margin-top:16px"><a class="btn" href="projects.php">Go to projects</a></p>
  </div>
<?php else: ?>
  <div class="tablewrap"><table>
    <thead><tr><th>Client</th><th>Company</th><th>Projects</th><th>Last signed in</th><th class="right">Status</th></tr></thead>
    <tbody>
<?php foreach ($clients as $c): ?>
      <tr>
        <td><a class="rowlink" href="client-edit.php?id=<?= (int) $c['id'] ?>"><?= esc($c['name']) ?></a>
          <span class="sub"><?= esc($c['email']) ?></span></td>
        <td><?= esc($c['company'] ?: '—') ?></td>
        <td class="num"><?= (int) $c['project_count'] ?></td>
        <td class="num"><?= esc($c['last_login_at'] ? datetime_human($c['last_login_at']) : 'Never') ?></td>
        <td class="right"><?= status_pill($c['status']) ?></td>
      </tr>
<?php endforeach; ?>
    </tbody>
  </table></div>
<?php endif; ?>
</div>

<?php admin_foot(); ?>
