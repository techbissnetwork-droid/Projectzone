<?php
/** @var App\Core\View $view @var array $deployments @var array $statusCounts */
$view->extends('layouts.portal');
$view->start('content');
?>
<div class="kpis">
  <?php foreach ([
    ['rocket', 'Total', (string) count($deployments)],
    ['check-circle', 'Live', (string) ($statusCounts['live'] ?? 0)],
    ['clock', 'In progress', (string) ($statusCounts['running'] ?? 0)],
    ['alert', 'Pending', (string) ($statusCounts['pending'] ?? 0)],
  ] as $kpi): ?>
    <div class="kpi">
      <span class="kpi__label"><?= icon($kpi[0]) ?><?= e($kpi[1]) ?></span>
      <span class="kpi__value"><?= e($kpi[2]) ?></span>
    </div>
  <?php endforeach; ?>
</div>

<div class="panel mt-5">
  <div class="panel__head">
    <h3>Installations</h3>
    <a class="btn btn--sm btn--quiet" href="<?= e(url('/marketplace/installer')) ?>">Installer guide</a>
  </div>
  <div class="table-wrap">
    <table class="table">
      <thead>
        <tr><th>Site</th><th>Product</th><th>Client</th><th>Mode</th><th>Progress</th><th>Status</th></tr>
      </thead>
      <tbody>
        <?php foreach ($deployments as $d): ?>
          <tr>
            <td data-label="Site">
              <strong><?= e($d['site_name']) ?></strong><br>
              <span class="tiny dim mono"><?= e($d['target_url']) ?></span>
            </td>
            <td data-label="Product"><?= e($d['product_name'] ?: '—') ?><br><span class="tiny dim"><?= e(ucfirst((string) $d['environment'])) ?> · <?= e($d['database_driver']) ?></span></td>
            <td data-label="Client"><?= e($d['client_name'] ?: '—') ?><br><span class="tiny dim"><?= e($d['company'] ?: '') ?></span></td>
            <td data-label="Mode">
              <span class="badge badge--neutral"><?= e(ucfirst((string) $d['install_mode'])) ?></span>
              <?php if ($d['source_platform']): ?><br><span class="tiny dim">from <?= e($d['source_platform']) ?></span><?php endif; ?>
            </td>
            <td data-label="Progress" style="min-width:120px">
              <div class="progress"><i style="width:<?= (int) $d['progress'] ?>%"></i></div>
              <span class="tiny dim"><?= (int) $d['progress'] ?>%</span>
            </td>
            <td data-label="Status"><?php $view->partial('partials.status-pill', ['value' => (string) $d['status']]); ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php $view->stop(); ?>
