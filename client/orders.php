<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/guard.php';
$me = require_client();

$orders = Database::all('SELECT o.*, p.title AS product_title, p.slug AS product_slug, p.demo_url
                         FROM orders o LEFT JOIN products p ON p.id = o.product_id
                         WHERE o.user_id = :u ORDER BY o.created_at DESC', ['u' => (int)$me['id']]);

$PAGE_TITLE = 'Purchases';
$AREA = 'client';
require __DIR__ . '/../partials/app_header.php';
?>
<?php if (!$orders): ?>
  <section class="card"><div class="empty"><b>No purchases yet</b>
    <p>Premade projects you buy from the marketplace appear here.</p>
    <a class="btn sm" href="<?= e(url('marketplace.php')) ?>">Browse the marketplace</a></div></section>
<?php else: ?>
  <div class="stack">
    <?php foreach ($orders as $o): ?>
      <section class="card">
        <div class="card__head">
          <h2><?= e($o['product_title'] ?? 'Product no longer listed') ?></h2>
          <span class="badge <?= e(status_tone($o['status'])) ?>"><?= e(label($o['status'])) ?></span>
          <span class="mono muted" style="margin-left:auto"><?= e($o['reference']) ?></span>
        </div>
        <div class="card__body">
          <div class="rowline">
            <span><b><?= e(money($o['amount'], $o['currency'] . ' ')) ?></b></span>
            <span class="muted">Ordered <?= e(ftime($o['created_at'])) ?></span>
            <?php if ($o['payment_ref']): ?><span class="muted mono">Ref <?= e($o['payment_ref']) ?></span><?php endif; ?>
            <span style="margin-left:auto"></span>
            <?php if ($o['product_slug']): ?>
              <a class="btn ghost sm" href="<?= e(url('product.php?slug=' . urlencode($o['product_slug']))) ?>">View listing</a>
            <?php endif; ?>
            <a class="btn ghost sm" href="tickets.php?action=new&category=billing">Ask about this order</a>
          </div>
          <?php if ($o['status'] === 'pending'): ?>
            <div class="alert warn" style="margin-top:14px">
              <p><b>Waiting for payment confirmation.</b></p>
              <p style="white-space:pre-wrap"><?= e(Settings::get('payment_instructions')) ?></p>
              <p>Quote your reference <b class="mono"><?= e($o['reference']) ?></b> when you pay.</p>
              <?php /* Without this the payment page is unreachable once the tab
                       that opened it is closed, and the order is stranded. */ ?>
              <p style="margin-top:12px">
                <a class="btn sm" href="<?= e(Payments::orderUrl($o)) ?>">Complete payment <span aria-hidden="true">&rarr;</span></a>
              </p>
            </div>
          <?php elseif ($o['status'] === 'delivered'): ?>
            <div class="alert ok" style="margin-top:14px"><p>Delivered. Raise a support request if you need the files again or want help installing.</p></div>
          <?php endif; ?>
        </div>
      </section>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
<?php require __DIR__ . '/../partials/app_footer.php'; ?>
