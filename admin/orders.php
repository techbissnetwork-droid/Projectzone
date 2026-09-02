<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/guard.php';
require_admin();

$action = (string)($_GET['action'] ?? 'list');
$id     = (int)($_GET['id'] ?? 0);

if ($action === 'view') {
    $o = Database::one('SELECT o.*, p.title AS product_title, p.slug AS product_slug, u.name AS client_name
                        FROM orders o LEFT JOIN products p ON p.id = o.product_id
                        LEFT JOIN users u ON u.id = o.user_id WHERE o.id = :id', ['id' => $id]);
    if (!$o) {
        http_response_code(404);
        exit('Order not found.');
    }

    if (post()) {
        Csrf::check();
        $status = (string)($_POST['status'] ?? $o['status']);
        if (!in_array($status, ['pending','paid','delivered','cancelled'], true)) {
            $status = $o['status'];
        }
        /* Count the sale once, when it first reaches paid. */
        $wasUnpaid = $o['status'] === 'pending' || $o['status'] === 'cancelled';
        if ($wasUnpaid && in_array($status, ['paid','delivered'], true) && $o['product_id']) {
            Database::run('UPDATE products SET sales_count = sales_count + 1 WHERE id = :p', ['p' => (int)$o['product_id']]);
        }
        Database::update('orders', [
            'status'         => $status,
            'payment_method' => trim((string)($_POST['payment_method'] ?? '')) ?: null,
            'payment_ref'    => trim((string)($_POST['payment_ref'] ?? '')) ?: null,
            'notes'          => trim((string)($_POST['notes'] ?? '')) ?: null,
            'updated_at'     => now(),
        ], $id);
        log_activity('order.update', 'order', $id, $o['reference'] . ' → ' . $status);
        Flash::ok('Order updated.');
        redirect('admin/orders.php?action=view&id=' . $id);
    }

    $PAGE_TITLE = 'Order ' . $o['reference'];
    $AREA = 'admin';
    $PAGE_ACTIONS = '<a class="btn ghost sm" href="orders.php">All orders</a>';
    require __DIR__ . '/../partials/app_header.php';
    ?>
    <div class="split">
      <section class="card">
        <div class="card__head"><h2>Update</h2>
          <span class="badge <?= e(status_tone($o['status'])) ?>"><?= e(label($o['status'])) ?></span></div>
        <div class="card__body">
          <form method="post" class="form">
            <?= Csrf::field() ?>
            <div class="row two">
              <label class="field"><span>Status</span>
                <select name="status">
                  <?php foreach (['pending' => 'Pending payment', 'paid' => 'Paid', 'delivered' => 'Delivered', 'cancelled' => 'Cancelled'] as $k => $v): ?>
                    <option value="<?= $k ?>"<?= $o['status'] === $k ? ' selected' : '' ?>><?= e($v) ?></option>
                  <?php endforeach; ?>
                </select></label>
              <label class="field"><span>Payment method</span>
                <input name="payment_method" placeholder="Bank transfer / eSewa" value="<?= e($o['payment_method'] ?? '') ?>"></label>
            </div>
            <label class="field"><span>Payment reference</span>
              <input name="payment_ref" value="<?= e($o['payment_ref'] ?? '') ?>"></label>
            <label class="field"><span>Internal notes</span>
              <textarea name="notes" rows="4"><?= e($o['notes'] ?? '') ?></textarea></label>
            <div class="formfoot"><button class="btn" type="submit">Save order</button></div>
          </form>
          <p class="hint" style="margin-top:14px">Marking an order paid or delivered counts the sale once and shows it in the buyer's portal.</p>
        </div>
      </section>

      <section class="card">
        <div class="card__head"><h2>Order</h2></div>
        <div class="card__body">
          <table class="data" style="margin:-8px 0"><tbody>
            <tr><th>Reference</th><td class="right mono"><?= e($o['reference']) ?></td></tr>
            <tr><th>Product</th><td class="right"><?= $o['product_id']
              ? '<a class="linkish" href="products.php?action=edit&id=' . (int)$o['product_id'] . '">' . e((string)$o['product_title']) . '</a>'
              : '<span class="muted">Removed</span>' ?></td></tr>
            <tr><th>Amount</th><td class="right"><?= e(money($o['amount'], $o['currency'] . ' ')) ?></td></tr>
            <tr><th>Buyer</th><td class="right"><?= e($o['buyer_name']) ?></td></tr>
            <tr><th>Email</th><td class="right"><a class="linkish" href="mailto:<?= e($o['buyer_email']) ?>"><?= e($o['buyer_email']) ?></a></td></tr>
            <tr><th>Phone</th><td class="right"><?= e($o['buyer_phone'] ?: '—') ?></td></tr>
            <tr><th>Portal account</th><td class="right"><?= $o['user_id']
              ? '<a class="linkish" href="clients.php?action=edit&id=' . (int)$o['user_id'] . '">' . e((string)$o['client_name']) . '</a>'
              : '<span class="muted">Guest</span>' ?></td></tr>
            <tr><th>Placed</th><td class="right"><?= e(ftime($o['created_at'])) ?></td></tr>
          </tbody></table>
        </div>
      </section>
    </div>
    <?php
    require __DIR__ . '/../partials/app_footer.php';
    exit;
}

$status = (string)($_GET['status'] ?? '');
$where  = in_array($status, ['pending','paid','delivered','cancelled'], true) ? ' WHERE o.status = :s' : '';
$orders = Database::all('SELECT o.*, p.title AS product_title FROM orders o
                         LEFT JOIN products p ON p.id = o.product_id' . $where . '
                         ORDER BY o.created_at DESC', $where ? ['s' => $status] : []);

$PAGE_TITLE = 'Orders';
$AREA = 'admin';
require __DIR__ . '/../partials/app_header.php';
?>
<div class="filters">
  <?php foreach (['' => 'All', 'pending' => 'Pending', 'paid' => 'Paid', 'delivered' => 'Delivered', 'cancelled' => 'Cancelled'] as $k => $v): ?>
    <a href="?status=<?= e($k) ?>" class="<?= $status === $k ? 'on' : '' ?>"><?= e($v) ?></a>
  <?php endforeach; ?>
</div>
<section class="card">
  <?php if (!$orders): ?>
    <div class="empty"><b>No orders</b><p>Marketplace purchases appear here for you to confirm.</p></div>
  <?php else: ?>
    <div class="tablewrap"><table class="data">
      <thead><tr><th>Reference</th><th>Product</th><th>Buyer</th><th class="right">Amount</th><th>Status</th><th class="right">Placed</th></tr></thead>
      <tbody>
      <?php foreach ($orders as $o): ?>
        <tr>
          <td><a class="linkish t-main mono" href="orders.php?action=view&id=<?= (int)$o['id'] ?>"><?= e($o['reference']) ?></a></td>
          <td><?= e($o['product_title'] ?? '—') ?></td>
          <td><?= e($o['buyer_name']) ?><span class="t-sub"><?= e($o['buyer_email']) ?></span></td>
          <td class="num"><?= e(money($o['amount'], $o['currency'] . ' ')) ?></td>
          <td><span class="badge <?= e(status_tone($o['status'])) ?>"><?= e(label($o['status'])) ?></span></td>
          <td class="right nowrap muted"><?= e(ftime($o['created_at'])) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php endif; ?>
</section>
<?php require __DIR__ . '/../partials/app_footer.php'; ?>
