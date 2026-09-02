<?php
/** Every maintenance entry across every project, newest first. */
require_once __DIR__ . '/../app/bootstrap.php';
require_installed();
require_staff();
require_once __DIR__ . '/_layout.php';

if (is_post() && post('action') === 'delete') {
    csrf_check();
    db_delete('maintenance_logs', post_int('id'));
    flash('Entry deleted.');
    redirect('maintenance.php');
}

$kind = get('kind');
$sql  = 'SELECT m.*, p.name AS project_name FROM maintenance_logs m
         LEFT JOIN projects p ON p.id = m.project_id';
$params = [];
if (in_array($kind, ['update', 'backup', 'fix', 'upgrade', 'renewal', 'other'], true)) {
    $sql .= ' WHERE m.kind = ?';
    $params[] = $kind;
} else {
    $kind = '';
}
$sql .= ' ORDER BY m.performed_on DESC, m.id DESC';
$logs = db_all($sql, $params);

admin_head('Maintenance log', 'maintenance.php');
admin_page_head('Maintenance log',
    'Everything done to every project. Entries marked internal never reach the client portal.');
?>

<div class="filters">
  <a class="<?= $kind === '' ? 'on' : '' ?>" href="maintenance.php">Everything</a>
<?php foreach (['update' => 'Updates', 'backup' => 'Backups', 'fix' => 'Fixes',
                'upgrade' => 'Upgrades', 'renewal' => 'Renewals', 'other' => 'Other'] as $k => $l): ?>
  <a class="<?= $kind === $k ? 'on' : '' ?>" href="maintenance.php?kind=<?= $k ?>"><?= $l ?></a>
<?php endforeach; ?>
</div>

<div class="panel">
<?php if (!$logs): ?>
  <div class="empty"><strong>Nothing logged yet</strong>
    <p>Add entries from a project page. They build the history a client sees in their portal.</p>
    <p style="margin-top:16px"><a class="btn" href="projects.php">Go to projects</a></p></div>
<?php else: ?>
  <div class="tablewrap"><table>
    <thead><tr><th>What</th><th>Project</th><th>Type</th><th>When</th><th>By</th><th>Visible</th><th class="right">&nbsp;</th></tr></thead>
    <tbody>
<?php foreach ($logs as $l): ?>
      <tr>
        <td><strong><?= esc($l['title']) ?></strong>
<?php if ($l['body']): ?>          <span class="sub"><?= esc(excerpt($l['body'], 14)) ?></span><?php endif; ?></td>
        <td><?php if ($l['project_name']): ?>
          <a class="rowlink" href="project-edit.php?id=<?= (int) $l['project_id'] ?>"><?= esc($l['project_name']) ?></a>
<?php else: ?>—<?php endif; ?></td>
        <td><span class="pill"><?= esc(ucfirst($l['kind'])) ?></span></td>
        <td class="num"><?= esc(date_human($l['performed_on'])) ?></td>
        <td><?= esc($l['performed_by'] ?: '—') ?></td>
        <td><?= $l['visible_to_client'] ? '<span class="pill ok">Client</span>'
             : '<span class="pill soon">Internal</span>' ?></td>
        <td class="right">
          <form method="post" data-confirm="Delete this maintenance entry?">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= (int) $l['id'] ?>">
            <button class="btn danger sm" type="submit">Delete</button>
          </form>
        </td>
      </tr>
<?php endforeach; ?>
    </tbody>
  </table></div>
<?php endif; ?>
</div>

<?php admin_foot(); ?>
