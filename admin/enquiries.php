<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_installed();
require_staff();
require_once __DIR__ . '/_layout.php';

$status = get('status');
$sql    = 'SELECT * FROM enquiries';
$params = [];
if (in_array($status, ['new', 'read', 'quoted', 'won', 'lost'], true)) {
    $sql .= ' WHERE status = ?';
    $params[] = $status;
} else {
    $status = '';
}
$sql .= ' ORDER BY id DESC';
$rows = db_all($sql, $params);

/* CSV export — everything, ignoring the current filter. */
if (get('export') === 'csv') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="techbiss-enquiries-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    /* PHP 8.4 wants $escape stated outright; "" keeps Excel and Sheets happy. */
    fputcsv($out, ['Received', 'Name', 'Email', 'Phone', 'Company', 'Service', 'Budget',
                   'Status', 'Message'], ',', '"', '');
    foreach (db_all('SELECT * FROM enquiries ORDER BY id DESC') as $e) {
        fputcsv($out, [$e['created_at'], $e['name'], $e['email'], $e['phone'], $e['company'],
                       $e['service'], $e['budget'], $e['status'], $e['message']], ',', '"', '');
    }
    fclose($out);
    exit;
}

admin_head('Enquiries', 'enquiries.php');
admin_page_head('Enquiries', 'Messages from the contact form. Saved here before any email is sent, so none are lost.',
    [['enquiries.php?export=csv', 'Export CSV', 'ghost']]);
?>

<div class="filters">
  <a class="<?= $status === '' ? 'on' : '' ?>" href="enquiries.php">All</a>
<?php foreach (['new' => 'New', 'read' => 'Read', 'quoted' => 'Quoted', 'won' => 'Won', 'lost' => 'Lost'] as $s => $l): ?>
  <a class="<?= $status === $s ? 'on' : '' ?>" href="enquiries.php?status=<?= $s ?>"><?= $l ?>
    (<?= db_count('SELECT COUNT(*) FROM enquiries WHERE status = ?', [$s]) ?>)</a>
<?php endforeach; ?>
</div>

<div class="panel">
<?php if (!$rows): ?>
  <div class="empty"><strong>Nothing here</strong><p>Contact form messages arrive here.</p></div>
<?php else: ?>
  <div class="tablewrap"><table>
    <thead><tr><th>From</th><th>Wants</th><th>Budget</th><th>Received</th><th>Emailed</th><th class="right">Status</th></tr></thead>
    <tbody>
<?php foreach ($rows as $e): ?>
      <tr>
        <td><a class="rowlink" href="enquiry.php?id=<?= (int) $e['id'] ?>"><?= esc($e['name']) ?></a>
          <span class="sub"><?= esc($e['email']) ?><?= $e['company'] ? ' · ' . esc($e['company']) : '' ?></span></td>
        <td><?= esc($e['service'] ?: '—') ?></td>
        <td><?= esc($e['budget'] ?: '—') ?></td>
        <td class="num"><?= esc(date_human($e['created_at'])) ?></td>
        <td><?= $e['mail_sent'] ? '<span class="pill ok">Sent</span>' : '<span class="pill soon">Not sent</span>' ?></td>
        <td class="right"><?= status_pill($e['status']) ?></td>
      </tr>
<?php endforeach; ?>
    </tbody>
  </table></div>
<?php endif; ?>
</div>

<?php admin_foot(); ?>
