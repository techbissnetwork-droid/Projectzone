<?php
declare(strict_types=1);
require_once __DIR__ . '/app/bootstrap.php';

/** An order is reachable by its reference plus the token issued when it was placed. */
$ref   = (string)($_GET['ref'] ?? '');
$token = (string)($_GET['t'] ?? '');
$order = Database::one('SELECT o.*, p.title AS product_title, p.slug AS product_slug
                        FROM orders o LEFT JOIN products p ON p.id = o.product_id
                        WHERE o.reference = :r', ['r' => $ref]);

$mayView = $order
    && ($token !== '' && $order['access_token'] !== null && hash_equals((string)$order['access_token'], $token)
        || Auth::isAdmin()
        || (Auth::check() && (int)$order['user_id'] === Auth::id()));

if (!$mayView) {
    http_response_code(404);
    $PAGE_TITLE = 'Order not found';
    require __DIR__ . '/partials/public_header.php';
    echo '<section class="pagehead" data-theme="deep"><div class="shell">'
       . '<h1 class="pagehead__title">We cannot find that order.</h1>'
       . '<p class="pagehead__lede">Check the link from your confirmation, or '
       . '<a class="link" href="' . e(url('contact.php')) . '">ask us about it →</a></p></div></section>';
    require __DIR__ . '/partials/public_footer.php';
    exit;
}

$method  = $order['payment_method_id'] ? Payments::method((int)$order['payment_method_id']) : null;
$isNew   = !empty($_GET['new']);
$paid    = in_array($order['status'], ['paid', 'delivered'], true);
$gateway = $method && Payments::isGateway((string)$method['provider']);

$PAGE_TITLE = 'Order ' . $order['reference'];
$META_DESC  = 'Your order with ' . Settings::get('site_name') . '.';
require __DIR__ . '/partials/public_header.php';
?>
<section class="pagehead" data-theme="deep">
  <div class="shell">
    <p class="eyebrow reveal"><?= $isNew ? 'Order received' : 'Your order' ?></p>
    <h1 class="pagehead__title reveal"><?= $paid ? 'Payment confirmed.' : 'Almost there.' ?></h1>
    <p class="pagehead__lede reveal">
      <?php if ($paid): ?>
        We have your payment for <b><?= e($order['product_title'] ?? 'your order') ?></b> and will be in touch about handover.
      <?php else: ?>
        Reference <b class="mono"><?= e($order['reference']) ?></b> for
        <b><?= e($order['product_title'] ?? 'your order') ?></b>. Follow the payment steps below.
      <?php endif; ?>
    </p>
  </div>
</section>

<section class="listing">
  <div class="shell detail__grid">
    <div class="detail__body reveal">
      <?php foreach (Flash::take() as $f): ?>
        <div class="notice <?= $f['type'] === 'ok' ? 'notice--ok' : 'notice--err' ?>"><p><?= e($f['message']) ?></p></div>
      <?php endforeach; ?>

      <?php if ($paid): ?>
        <div class="notice notice--ok">
          <p><b>Paid<?= $order['payment_ref'] ? ' · reference ' . e($order['payment_ref']) : '' ?>.</b></p>
          <p>A receipt is on its way to <?= e($order['buyer_email']) ?>. We will contact you about delivery and installation.</p>
        </div>

      <?php elseif ($order['status'] === 'cancelled'): ?>
        <div class="notice notice--err">
          <p><b>This order was cancelled.</b> If that is a mistake,
            <a class="link" href="<?= e(url('contact.php')) ?>">let us know</a>.</p>
        </div>

      <?php elseif ($gateway): ?>
        <h2 class="detail__h2">Pay with <?= e($method['name']) ?></h2>
        <p>You will be taken to <?= e($method['name']) ?> to complete the payment, then brought straight back here.
           We confirm the result with <?= e($method['name']) ?> before marking anything paid.</p>
        <form method="post" action="<?= e(url('payment.php')) ?>" class="wform">
          <?= Csrf::field() ?>
          <input type="hidden" name="ref" value="<?= e($order['reference']) ?>">
          <input type="hidden" name="t" value="<?= e((string)$order['access_token']) ?>">
          <button class="btn btn--primary btn--lg magnetic" type="submit">
            Pay <?= e(money($order['amount'], $order['currency'] . ' ')) ?> with <?= e($method['name']) ?>
            <span class="btn__arrow">→</span></button>
        </form>

      <?php elseif ($method): ?>
        <h2 class="detail__h2">Pay by <?= e($method['name']) ?></h2>
        <?php if ($method['instructions']): ?><p><?= enl($method['instructions']) ?></p><?php endif; ?>
        <?php if ($method['account_name'] || $method['account_number']): ?>
          <div class="payblock">
            <?php if ($method['account_name']): ?>
              <div><span>Account name</span><b><?= e($method['account_name']) ?></b></div><?php endif; ?>
            <?php if ($method['account_number']): ?>
              <div><span>Account number</span><b class="mono"><?= e($method['account_number']) ?></b></div><?php endif; ?>
            <div><span>Amount</span><b><?= e(money($order['amount'], $order['currency'] . ' ')) ?></b></div>
            <div><span>Use this reference</span><b class="mono"><?= e($order['reference']) ?></b></div>
          </div>
        <?php endif; ?>
        <p class="wform__note">Once we see the payment we confirm the order by email, usually within one business day.</p>

      <?php else: ?>
        <div class="notice">
          <p><b>We will email you the payment details.</b></p>
          <p>Quote reference <b class="mono"><?= e($order['reference']) ?></b> when you pay.</p>
        </div>
      <?php endif; ?>

      <p style="margin-top:8px"><a class="link" href="<?= e(url('marketplace.php')) ?>">Back to the marketplace <span aria-hidden="true">→</span></a></p>
    </div>

    <aside class="detail__meta reveal">
      <dl>
        <div><dt>Reference</dt><dd class="mono"><?= e($order['reference']) ?></dd></div>
        <div><dt>Item</dt><dd><?= e($order['product_title'] ?? '—') ?></dd></div>
        <div><dt>Amount</dt><dd><?= e(money($order['amount'], $order['currency'] . ' ')) ?></dd></div>
        <div><dt>Method</dt><dd><?= e($method['name'] ?? 'To be arranged') ?></dd></div>
        <div><dt>Status</dt><dd><?= e(label($order['status'])) ?></dd></div>
        <div><dt>Placed</dt><dd><?= e(ftime($order['created_at'])) ?></dd></div>
      </dl>
      <p class="pricebox__note">Keep this page bookmarked — it stays up to date.</p>
      <?php if ($order['user_id'] && Auth::check()): ?>
        <a class="btn btn--ghost btn--block magnetic" href="<?= e(url('client/orders.php')) ?>">All my purchases <span class="btn__arrow">→</span></a>
      <?php endif; ?>
    </aside>
  </div>
</section>
<?php require __DIR__ . '/partials/public_footer.php'; ?>
