<?php
/** @var App\Core\View $view @var array $invoices @var array $orders @var float $due @var float $paid */
$view->extends('layouts.portal');
$view->start('content');
?>
<div class="kpis">
  <div class="kpi"><span class="kpi__label"><?= icon('file') ?>Outstanding</span><span class="kpi__value"><?= e(money($due)) ?></span></div>
  <div class="kpi"><span class="kpi__label"><?= icon('check-circle') ?>Paid to date</span><span class="kpi__value"><?= e(money($paid)) ?></span></div>
  <div class="kpi"><span class="kpi__label"><?= icon('cart') ?>Marketplace orders</span><span class="kpi__value"><?= count($orders) ?></span></div>
  <div class="kpi"><span class="kpi__label"><?= icon('clock') ?>Invoices issued</span><span class="kpi__value"><?= count($invoices) ?></span></div>
</div>

<div class="panel mt-5">
  <div class="panel__head"><h3>Engagement invoices</h3></div>
  <div class="table-wrap">
    <table class="table">
      <thead><tr><th>Number</th><th>Description</th><th>Issued</th><th>Due</th><th>Status</th><th class="num">Amount</th></tr></thead>
      <tbody>
        <?php foreach ($invoices as $invoice): ?>
          <tr>
            <td data-label="Number"><strong><?= e($invoice['number']) ?></strong></td>
            <td data-label="Description"><?= e($invoice['description']) ?><br><span class="tiny dim"><?= e($invoice['project_name'] ?: '') ?></span></td>
            <td data-label="Issued"><?= e(human_date($invoice['issued_at'])) ?></td>
            <td data-label="Due"><?= e(human_date($invoice['due_at'])) ?></td>
            <td data-label="Status"><?php $view->partial('partials.status-pill', ['value' => (string) $invoice['status']]); ?></td>
            <td data-label="Amount" class="num"><strong><?= e(money((float) $invoice['amount'], (string) $invoice['currency'])) ?></strong></td>
          </tr>
        <?php endforeach; ?>
        <?php if ($invoices === []): ?>
          <tr><td data-label="" colspan="6"><span class="small dim">No engagement invoices issued.</span></td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="panel mt-4">
  <div class="panel__head"><h3>Marketplace orders</h3><a class="btn btn--sm btn--quiet" href="<?= e(url('/client/licenses')) ?>">Licences</a></div>
  <div class="table-wrap">
    <table class="table">
      <thead><tr><th>Reference</th><th>Date</th><th>Method</th><th>Status</th><th class="num">Total</th></tr></thead>
      <tbody>
        <?php foreach ($orders as $order): ?>
          <tr>
            <td data-label="Reference"><strong><a href="<?= e(url('/marketplace/order/' . $order['reference'])) ?>"><?= e($order['reference']) ?></a></strong></td>
            <td data-label="Date"><?= e(human_date($order['created_at'])) ?></td>
            <td data-label="Method"><span class="badge badge--neutral"><?= e(ucfirst((string) $order['payment_method'])) ?></span></td>
            <td data-label="Status"><?php $view->partial('partials.status-pill', ['value' => (string) $order['status']]); ?></td>
            <td data-label="Total" class="num"><?= e(money((float) $order['total'], (string) $order['currency'])) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if ($orders === []): ?>
          <tr><td data-label="" colspan="5"><span class="small dim">No marketplace orders yet.</span></td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php $view->stop(); ?>
