<?php
/** @var App\Core\View $view @var array $orders @var array $statusCounts @var string $activeStatus @var float $revenue */
$view->extends('layouts.portal');
$view->start('content');
?>
<div class="between">
  <?php $view->partial('partials.filter-tabs', ['counts' => $statusCounts, 'active' => $activeStatus, 'base' => '/admin/orders', 'allLabel' => 'All orders']); ?>
  <span class="badge badge--ok">Settled: <?= e(money($revenue)) ?></span>
</div>

<div class="panel mt-5">
  <div class="panel__head"><h3><?= count($orders) ?> orders</h3></div>
  <div class="table-wrap">
    <table class="table">
      <thead>
        <tr><th>Reference</th><th>Customer</th><th>Products</th><th>Method</th><th>Status</th><th class="num">Total</th></tr>
      </thead>
      <tbody>
        <?php foreach ($orders as $order): ?>
          <tr>
            <td data-label="Reference">
              <strong><?= e($order['reference']) ?></strong><br>
              <span class="tiny dim"><?= e(human_date($order['created_at'], 'M j, Y')) ?></span>
            </td>
            <td data-label="Customer">
              <?= e($order['customer_name']) ?><br>
              <span class="tiny dim"><?= e($order['company'] ?: $order['customer_email']) ?><?= $order['country'] ? ' · ' . e($order['country']) : '' ?></span>
            </td>
            <td data-label="Products"><span class="small"><?= e($order['product_names'] ?: '—') ?></span></td>
            <td data-label="Method"><span class="badge badge--neutral"><?= e(ucfirst((string) $order['payment_method'])) ?></span></td>
            <td data-label="Status"><?php $view->partial('partials.status-pill', ['value' => (string) $order['status']]); ?></td>
            <td data-label="Total" class="num"><strong><?= e(money((float) $order['total'], (string) $order['currency'])) ?></strong></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php $view->stop(); ?>
