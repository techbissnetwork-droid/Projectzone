<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_installed();
require_staff();
require_once __DIR__ . '/_layout.php';

$status = get('status');
$sql    = 'SELECT o.*, p.title AS product_title FROM orders o
           LEFT JOIN products p ON p.id = o.product_id';
$params = [];
if (in_array($status, ['new', 'paid', 'delivered', 'cancelled'], true)) {
    $sql .= ' WHERE o.status = ?';
    $params[] = $status;
} else {
    $status = '';
}
$sql .= ' ORDER BY o.id DESC';
$orders = db_all($sql, $params);
$sym    = setting('site.currency', '$');

admin_head('Orders', 'orders.php');
admin_page_head('Marketplace orders',
    'Nothing is charged online. You confirm each order, invoice it, then mark it paid and delivered.');
?>

<div class="filters">
  <a class="<?= $status === '' ? 'on' : '' ?>" href="orders.php">All</a>
<?php foreach (['new' => 'New', 'paid' => 'Paid', 'delivered' => 'Delivered', 'cancelled' => 'Cancelled'] as $s => $l): ?>
  <a class="<?= $status === $s ? 'on' : '' ?>" href="orders.php?status=<?= $s ?>"><?= $l ?>
    (<?= db_count('SELECT COUNT(*) FROM orders WHERE status = ?', [$s]) ?>)</a>
<?php endforeach; ?>
</div>

<div class="panel">
<?php if (!$orders): ?>
  <div class="empty"><strong>No orders</strong>
    <p>Orders placed on the marketplace land here, and a copy is emailed to you.</p></div>
<?php else: ?>
  <div class="tablewrap"><table>
    <thead><tr><th>Reference</th><th>Project</th><th>Buyer</th><th>Setup</th><th>Placed</th>
      <th class="right">Amount</th><th class="right">Status</th></tr></thead>
    <tbody>
<?php foreach ($orders as $o): ?>
      <tr>
        <td><a class="rowlink" href="order.php?id=<?= (int) $o['id'] ?>"><?= esc($o['reference']) ?></a></td>
        <td><?= esc($o['product_title'] ?? 'Deleted listing') ?></td>
        <td><?= esc($o['buyer_name']) ?><span class="sub"><?= esc($o['buyer_email']) ?></span></td>
        <td><?= $o['wants_setup'] ? '<span class="pill acc">Wants setup</span>' : '<span class="pill none">No</span>' ?></td>
        <td class="num"><?= esc(date_human($o['created_at'])) ?></td>
        <td class="right num"><?= esc(money($o['amount'], $sym)) ?></td>
        <td class="right"><?= status_pill($o['status']) ?></td>
      </tr>
<?php endforeach; ?>
    </tbody>
  </table></div>
<?php endif; ?>
</div>

<?php admin_foot(); ?>
