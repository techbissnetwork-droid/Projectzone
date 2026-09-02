<?php
require __DIR__ . '/../app/bootstrap.php';
require_installed();
auth_require();
require __DIR__ . '/../app/resources.php';
require __DIR__ . '/_layout.php';

$statuses = ['new' => 'New', 'read' => 'Read', 'replied' => 'Replied', 'closed' => 'Closed'];

/* CSV export, before any output is sent. */
if (($_GET['export'] ?? '') === 'csv') {
    $rows = all('SELECT * FROM enquiries ORDER BY created_at DESC, id DESC');
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="enquiries-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Received', 'Name', 'Email', 'Business', 'Phone', 'Needs', 'Budget', 'Status', 'Message']);
    foreach ($rows as $r) {
        fputcsv($out, [$r['created_at'], $r['name'], $r['email'], $r['company'], $r['phone'],
                       $r['service'], $r['budget'], $r['status'], $r['message']]);
    }
    fclose($out);
    exit;
}

if (is_post()) {
    csrf_check();
    $id = (int) post('id');
    if (post('action') === 'delete' && $id) {
        db_delete('enquiries', $id);
        flash('Enquiry deleted.');
    } elseif (post('action') === 'status' && $id) {
        $s = post('status');
        if (isset($statuses[$s])) {
            db_update('enquiries', $id, ['status' => $s]);
            flash('Status updated.');
        }
    }
    redirect('admin/enquiries.php' . (($q = post('q')) !== '' ? '?q=' . urlencode($q) : ''));
}

$q = trim((string) ($_GET['q'] ?? ''));
$filter = (string) ($_GET['status'] ?? '');

$sql = 'SELECT * FROM enquiries WHERE 1=1';
$params = [];
if ($q !== '') {
    $sql .= ' AND (name LIKE :q OR email LIKE :q OR company LIKE :q OR message LIKE :q)';
    $params['q'] = '%' . $q . '%';
}
if (isset($statuses[$filter])) {
    $sql .= ' AND status = :s';
    $params['s'] = $filter;
}
$sql .= ' ORDER BY created_at DESC, id DESC LIMIT 300';
$list = all($sql, $params);

admin_header('Enquiries');
?>
<h1>Enquiries</h1>

<form class="afilters" method="get">
  <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search name, email, business or message">
  <select name="status">
    <option value="">All statuses</option>
    <?php foreach ($statuses as $k => $label): ?>
      <option value="<?= e($k) ?>"<?= $filter === $k ? ' selected' : '' ?>><?= e($label) ?></option>
    <?php endforeach; ?>
  </select>
  <button type="submit">Filter</button>
  <a class="abtn" href="<?= e(base_url('admin/enquiries.php?export=csv')) ?>">Export CSV</a>
</form>

<?php if (!$list): ?>
  <p class="amuted">No enquiries match.</p>
<?php else: ?>
  <table class="atable">
    <thead><tr><th>Received</th><th>From</th><th>Needs</th><th>Budget</th><th>Status</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($list as $en): ?>
        <tr>
          <td class="anowrap"><?= e(date('j M Y, H:i', strtotime($en['created_at']))) ?></td>
          <td><?= e($en['name']) ?><br><span class="amuted"><?= e($en['company'] ?: $en['email']) ?></span></td>
          <td><?= e($en['service'] ?: '—') ?></td>
          <td><?= e($en['budget'] ?: '—') ?></td>
          <td><span class="atag atag--<?= e($en['status']) ?>"><?= e($en['status']) ?></span></td>
          <td><a class="abtn abtn--sm" href="<?= e(base_url('admin/enquiry.php?id=' . (int) $en['id'])) ?>">Open</a></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>
<?php admin_footer(); ?>
