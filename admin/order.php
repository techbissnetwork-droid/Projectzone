<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_installed();
require_staff();
require_once __DIR__ . '/_layout.php';

$id    = get_int('id');
$order = db_one('SELECT * FROM orders WHERE id = ?', [$id]);
if (!$order) {
    flash('That order no longer exists.', 'bad');
    redirect('orders.php');
}
$product = $order['product_id'] ? db_one('SELECT * FROM products WHERE id = ?', [$order['product_id']]) : null;
$sym     = setting('site.currency', '$');

if (is_post() && post('action') === 'update') {
    csrf_check();
    $new = post('status');
    if (in_array($new, ['new', 'paid', 'delivered', 'cancelled'], true)) {
        db_update('orders', (int) $order['id'], [
            'status'      => $new,
            'admin_notes' => post('admin_notes'),
        ]);
        log_activity('Order ' . $order['reference'] . ' set to ' . $new, 'order', (int) $order['id']);
        flash('Order updated.');
    }
    redirect('order.php?id=' . $order['id']);
}

/* Issue (or reissue) the buyer's download link and email it. */
if (is_post() && post('action') === 'deliver') {
    csrf_check();
    $days = max(1, min(365, post_int('days', 30)));

    if (!$product) {
        flash('This order has no listing attached, so there is nothing to send.', 'bad');
    } elseif (!$product['file_path'] || !is_file(APP_ROOT . '/' . $product['file_path'])) {
        flash('No files are attached to <strong>' . esc($product['title']) . '</strong> yet. '
            . 'Add the zip under Marketplace first, then come back here.', 'bad');
    } else {
        $token = bin2hex(random_bytes(24));
        db_update('orders', (int) $order['id'], [
            'download_token'   => $token,
            'download_expires' => date('Y-m-d H:i:s', time() + $days * 86400),
            'download_count'   => 0,
            'delivered_at'     => now(),
            'status'           => $order['status'] === 'new' ? 'delivered' : $order['status'],
        ]);
        $order = db_one('SELECT * FROM orders WHERE id = ?', [$order['id']]);
        $sent  = mail_order_delivery($order, $product, $days);
        log_activity('Sent files for order ' . $order['reference'], 'order', (int) $order['id']);
        flash($sent
            ? 'Download link emailed to ' . esc($order['buyer_email']) . '. It lasts ' . $days . ' days.'
            : 'Link created but <strong>the email could not be sent</strong>. Send it to them '
              . 'yourself: <code style="font-family:var(--mono);word-break:break-all">'
              . esc(url('download.php?token=' . $token)) . '</code>',
            $sent ? 'ok' : 'warn');
    }
    redirect('order.php?id=' . $order['id']);
}

if (is_post() && post('action') === 'delete') {
    csrf_check();
    db_delete('orders', (int) $order['id']);
    log_activity('Deleted order ' . $order['reference'], 'order', (int) $order['id']);
    flash('Order deleted.');
    redirect('orders.php');
}

admin_head('Order ' . $order['reference'], 'orders.php');
admin_page_head('Order ' . $order['reference'], '', [], [['orders.php', 'Orders'], [null, $order['reference']]]);
?>

<div class="split">
  <div>
    <div class="panel">
      <header><h2>What was ordered</h2></header>
      <div class="pad">
        <div class="kv"><span>Project</span><strong>
<?php if ($product): ?>
          <a href="resource.php?type=products&amp;action=edit&amp;id=<?= (int) $product['id'] ?>">
            <?= esc($product['title']) ?></a>
<?php else: ?>
          Listing has been deleted
<?php endif; ?>
        </strong></div>
        <div class="kv"><span>Setup service</span>
          <strong><?= $order['wants_setup'] ? 'Yes — they want us to set it up' : 'No' ?></strong></div>
        <div class="kv"><span>Total</span><strong><?= esc(money($order['amount'], $sym)) ?></strong></div>
        <div class="kv"><span>Placed</span><strong><?= esc(datetime_human($order['created_at'])) ?></strong></div>
      </div>
    </div>

<?php if ($order['notes']): ?>
    <div class="panel">
      <header><h2>What they told us</h2></header>
      <div class="pad"><p style="white-space:pre-wrap;color:var(--mute)"><?= esc($order['notes']) ?></p></div>
    </div>
<?php endif; ?>

    <form method="post" class="admin">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="update">
      <fieldset>
        <legend>Progress</legend>
        <div class="f"><label for="status">Status</label>
          <select id="status" name="status">
<?php foreach (['new' => 'New — needs a reply', 'paid' => 'Paid', 'delivered' => 'Delivered',
                'cancelled' => 'Cancelled'] as $v => $l): ?>
            <option value="<?= $v ?>"<?= $order['status'] === $v ? ' selected' : '' ?>><?= $l ?></option>
<?php endforeach; ?>
          </select></div>
        <div class="f"><label for="admin_notes">Internal notes</label>
          <textarea id="admin_notes" name="admin_notes"><?= esc((string) $order['admin_notes']) ?></textarea>
          <span class="hint">Invoice number, payment date, handover notes. The buyer never sees this.</span></div>
        <div class="formbar"><button class="btn" type="submit">Save</button></div>
      </fieldset>
    </form>
  </div>

  <div>
    <div class="panel">
      <header><h2>Buyer</h2></header>
      <div class="pad">
        <div class="kv"><span>Name</span><strong><?= esc($order['buyer_name']) ?></strong></div>
        <div class="kv"><span>Email</span>
          <strong><a href="mailto:<?= esc($order['buyer_email']) ?>"><?= esc($order['buyer_email']) ?></a></strong></div>
        <div class="kv"><span>Phone</span><strong><?= esc($order['buyer_phone'] ?: '—') ?></strong></div>
        <div class="kv"><span>Company</span><strong><?= esc($order['buyer_company'] ?: '—') ?></strong></div>
      </div>
    </div>

    <div class="panel">
      <header><h2>Send the files</h2></header>
      <div class="pad">
<?php if (!$product): ?>
        <p style="color:var(--mute);font-size:13.5px">The listing this order was for has been
          deleted, so there is nothing to send.</p>
<?php elseif (!$product['file_path']): ?>
        <p style="color:var(--mute);font-size:13.5px;margin-bottom:12px">
          No files are attached to <strong><?= esc($product['title']) ?></strong> yet.</p>
        <a class="btn ghost sm" href="resource.php?type=products&amp;action=edit&amp;id=<?= (int) $product['id'] ?>">
          Attach the files</a>
<?php else: ?>
<?php if ($order['delivered_at']): ?>
        <div class="kv"><span>Sent</span>
          <strong><?= esc(datetime_human($order['delivered_at'])) ?></strong></div>
        <div class="kv"><span>Link expires</span>
          <strong><?= esc(date_human($order['download_expires'])) ?>
            <?php [$st, $hu] = expiry_state($order['download_expires']); ?>
            <span class="pill <?= esc($st) ?>"><?= esc($hu) ?></span></strong></div>
        <div class="kv"><span>Downloaded</span>
          <strong><?= (int) $order['download_count'] ?> time<?= (int) $order['download_count'] === 1 ? '' : 's' ?></strong></div>
<?php endif; ?>
        <form method="post" class="admin" style="margin-top:12px">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="deliver">
          <div class="f"><label for="days">Link valid for</label>
            <select id="days" name="days">
              <option value="7">7 days</option>
              <option value="30" selected>30 days</option>
              <option value="90">90 days</option>
              <option value="365">A year</option>
            </select></div>
          <button class="btn" type="submit">
            <?= $order['delivered_at'] ? 'Send a fresh link' : 'Email the download link' ?></button>
        </form>
        <p style="color:var(--mute);font-size:12.5px;margin-top:10px">
          Only do this once payment has cleared. Sending again replaces the old link, which stops
          working immediately.</p>
<?php endif; ?>
      </div>
    </div>

    <div class="panel">
      <header><h2>Next step</h2></header>
      <div class="pad" style="color:var(--mute);font-size:14px">
        <p style="margin-bottom:12px">Reply with payment details and a handover date. Once paid,
          mark it here.</p>
<?php if ($order['wants_setup']): ?>
        <p style="margin-bottom:12px">They want the setup service, so
          <a href="project-edit.php?action=new" style="color:var(--acc)">create a project</a> for
          them once payment clears — that also gives them a portal login.</p>
<?php endif; ?>
        <a class="btn ghost sm" href="mailto:<?= esc($order['buyer_email']) ?>?subject=<?= rawurlencode('Your order ' . $order['reference']) ?>">Email the buyer</a>
      </div>
    </div>

    <div class="panel">
      <header><h2>Danger</h2></header>
      <div class="pad">
        <form method="post" data-confirm="Delete this order permanently?">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="delete">
          <button class="btn danger" type="submit">Delete this order</button>
        </form>
      </div>
    </div>
  </div>
</div>

<?php admin_foot(); ?>
