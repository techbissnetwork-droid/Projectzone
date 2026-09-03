<?php
/** @var App\Core\View $view @var array $stats @var array $topProducts @var array $recentOrders @var array $recentLeads @var array $activity */
$view->extends('layouts.portal');
$view->start('content');
?>
<?php if ($findings !== []): ?>
  <section class="panel" style="border-color:rgba(245,165,36,.34);margin-bottom:var(--s-4)">
    <div class="panel__head" style="background:rgba(245,165,36,.07)">
      <h3><?= icon('shield') ?> Before you launch</h3>
      <span class="badge badge--warn"><?= count($findings) ?> to resolve</span>
    </div>
    <div class="panel__body" style="padding-block:var(--s-4)">
      <div class="checklist">
        <?php foreach ($findings as $finding): ?>
          <div class="checkrow checkrow--<?= $finding['level'] === 'high' ? 'fail' : 'warn' ?>">
            <span class="checkrow__icon"><?= icon($finding['level'] === 'high' ? 'alert' : 'info') ?></span>
            <span>
              <strong><?= e($finding['title']) ?></strong>
              <span><?= e($finding['detail']) ?></span>
            </span>
            <?php if ($finding['path']): ?>
              <a class="btn btn--sm btn--ghost" href="<?= e(url($finding['path'])) ?>"><?= e($finding['action']) ?></a>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
<?php endif; ?>

<div class="kpis">
  <?php foreach ([
    ['cart', 'Marketplace revenue', money($stats['revenue']), $stats['orders'] . ' orders', 'up'],
    ['tag', 'Active licences', number_format($stats['licenses']), $stats['products'] . ' products live', 'up'],
    ['rocket', 'Deployments', number_format($stats['deployments']), $stats['live'] . ' live', 'up'],
    ['trend', 'Open pipeline', money($stats['pipeline']), $stats['newLeads'] . ' new this period', 'up'],
  ] as $kpi): ?>
    <div class="kpi">
      <span class="kpi__label"><?= icon($kpi[0]) ?><?= e($kpi[1]) ?></span>
      <span class="kpi__value"><?= e($kpi[2]) ?></span>
      <span class="kpi__delta kpi__delta--<?= e($kpi[4]) ?>"><?= icon('trend', ['size' => 12]) ?><?= e($kpi[3]) ?></span>
    </div>
  <?php endforeach; ?>
</div>

<div class="mt-5" style="display:grid;gap:var(--s-4)">
  <div class="split split--wide-left" style="gap:var(--s-4);align-items:start">
    <div class="panel">
      <div class="panel__head">
        <h3>Top products by revenue</h3>
        <a class="btn btn--sm btn--quiet" href="<?= e(url('/admin/products')) ?>">All products</a>
      </div>
      <div class="panel__body" style="padding-block:var(--s-4)">
        <?php
          $max = 0.0;
          foreach ($topProducts as $p) { $max = max($max, (float) $p['revenue']); }
          $max = $max > 0 ? $max : 1.0;
        ?>
        <div class="stack" style="--flow:1rem">
          <?php foreach ($topProducts as $product): ?>
            <div class="meter-row">
              <div class="meter-row__top">
                <span><?= e($product['name']) ?></span>
                <b><?= e(money((float) $product['revenue'])) ?></b>
              </div>
              <div class="progress"><i style="--fill:calc(<?= round((float) $product['revenue'] / $max * 100) ?> / 100)"></i></div>
              <div class="tiny dim"><?= number_format((int) $product['sales_count']) ?> total deployments · <?= number_format((float) $product['rating'], 1) ?> rating</div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <div class="panel">
      <div class="panel__head"><h3>Platform health</h3></div>
      <div class="panel__body">
        <div class="stack" style="--flow:.9rem">
          <?php foreach ([
            ['Active users', number_format($stats['users']), 'ok'],
            ['Published products', number_format($stats['products']), 'ok'],
            ['Open tickets', number_format($stats['tickets']), $stats['tickets'] > 5 ? 'warn' : 'ok'],
            ['Pending payments', money($stats['pending']), $stats['pending'] > 0 ? 'warn' : 'ok'],
          ] as $row): ?>
            <div class="between">
              <span class="small dim"><?= e($row[0]) ?></span>
              <span class="badge badge--<?= e($row[2]) ?>"><?= e($row[1]) ?></span>
            </div>
          <?php endforeach; ?>
        </div>
        <hr class="divider" style="margin-block:var(--s-5)">
        <div class="cluster">
          <a class="btn btn--sm btn--ghost" href="<?= e(url('/admin/settings')) ?>"><?= icon('settings') ?>Settings</a>
          <a class="btn btn--sm btn--ghost" href="<?= e(url('/health')) ?>"><?= icon('gauge') ?>Health</a>
        </div>
      </div>
    </div>
  </div>

  <div class="split" style="gap:var(--s-4);align-items:start">
    <div class="panel">
      <div class="panel__head">
        <h3>Recent orders</h3>
        <a class="btn btn--sm btn--quiet" href="<?= e(url('/admin/orders')) ?>">All orders</a>
      </div>
      <div class="table-wrap">
        <table class="table">
          <thead><tr><th>Reference</th><th>Customer</th><th>Status</th><th class="num">Total</th></tr></thead>
          <tbody>
            <?php foreach ($recentOrders as $order): ?>
              <tr>
                <td data-label="Reference"><strong><?= e($order['reference']) ?></strong><br><span class="tiny dim"><?= e(time_ago($order['created_at'])) ?></span></td>
                <td data-label="Customer"><?= e($order['customer_name']) ?><br><span class="tiny dim"><?= e($order['company'] ?: $order['customer_email']) ?></span></td>
                <td data-label="Status"><?php $view->partial('partials.status-pill', ['value' => (string) $order['status']]); ?></td>
                <td data-label="Total" class="num"><?= e(money((float) $order['total'], (string) $order['currency'])) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="panel">
      <div class="panel__head">
        <h3>Recent enquiries</h3>
        <a class="btn btn--sm btn--quiet" href="<?= e(url('/admin/leads')) ?>">Pipeline</a>
      </div>
      <div class="table-wrap">
        <table class="table">
          <thead><tr><th>Contact</th><th>Topic</th><th>Stage</th><th class="num">Value</th></tr></thead>
          <tbody>
            <?php foreach ($recentLeads as $lead): ?>
              <tr>
                <td data-label="Contact"><strong><?= e($lead['name']) ?></strong><br><span class="tiny dim"><?= e($lead['company'] ?: $lead['email']) ?></span></td>
                <td data-label="Topic"><?= e(ucwords(str_replace('-', ' ', (string) $lead['topic']))) ?></td>
                <td data-label="Stage"><?php $view->partial('partials.status-pill', ['value' => (string) $lead['status']]); ?></td>
                <td data-label="Value" class="num"><?= $lead['value'] ? e(money((float) $lead['value'])) : '—' ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="panel">
    <div class="panel__head">
      <h3>Activity</h3>
      <a class="btn btn--sm btn--quiet" href="<?= e(url('/admin/activity')) ?>">Full log</a>
    </div>
    <ul class="feed">
      <?php foreach ($activity as $entry): ?>
        <li>
          <span class="feed__dot"></span>
          <div>
            <strong><?= e($entry['description']) ?></strong>
            <time><?= e($entry['user_name'] ?? 'System') ?> · <?= e(time_ago($entry['created_at'])) ?> · <span class="mono"><?= e($entry['action']) ?></span></time>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
</div>
<?php $view->stop(); ?>
