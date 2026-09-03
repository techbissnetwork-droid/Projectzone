<?php
/** @var App\Core\View $view @var array $project @var array $milestones @var array $invoices */
$view->extends('layouts.portal');
$view->start('content');
?>
<div class="kpis">
  <?php foreach ([
    ['gauge', 'Progress', (int) $project['progress'] . '%'],
    ['compass', 'Phase', ucfirst((string) $project['phase'])],
    ['calendar', 'Due', human_date($project['due_at'])],
    ['check-circle', 'Milestones', count(array_filter($milestones, static fn ($m) => $m['status'] === 'complete')) . ' / ' . count($milestones)],
  ] as $kpi): ?>
    <div class="kpi">
      <span class="kpi__label"><?= icon($kpi[0]) ?><?= e($kpi[1]) ?></span>
      <span class="kpi__value"><?= e($kpi[2]) ?></span>
    </div>
  <?php endforeach; ?>
</div>

<div class="split split--wide-left mt-5" style="gap:var(--s-4);align-items:start">
  <div class="panel">
    <div class="panel__head"><h3>Milestones</h3><?php $view->partial('partials.status-pill', ['value' => (string) $project['health']]); ?></div>
    <div class="panel__body">
      <p class="small muted"><?= e($project['summary'] ?: '') ?></p>
      <div class="timeline mt-5">
        <?php foreach ($milestones as $milestone): ?>
          <div class="timeline__item">
            <div class="timeline__rail">
              <span class="timeline__dot" style="background:var(--<?= $milestone['status'] === 'complete' ? 'ok' : ($milestone['status'] === 'active' ? 'accent' : 'ink-3') ?>);box-shadow:none"></span>
              <span class="timeline__line"></span>
            </div>
            <div>
              <div class="between">
                <strong class="small" style="<?= $milestone['status'] === 'complete' ? 'opacity:.65' : '' ?>"><?= e($milestone['title']) ?></strong>
                <span class="badge badge--<?= $milestone['status'] === 'complete' ? 'ok' : ($milestone['status'] === 'active' ? 'warn' : 'neutral') ?>"><?= e(ucfirst((string) $milestone['status'])) ?></span>
              </div>
              <div class="tiny dim mt-3">Target <?= e(human_date($milestone['due_at'])) ?></div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <div class="stack" style="--flow:var(--s-4)">
    <?php if ($project['budget']): ?>
      <?php $burn = min(100, (int) round((float) $project['spent'] / max(1.0, (float) $project['budget']) * 100)); ?>
      <div class="panel">
        <div class="panel__head"><h3>Commercials</h3></div>
        <div class="panel__body">
          <div class="meter-row">
            <div class="meter-row__top"><span>Budget consumed</span><b><?= $burn ?>%</b></div>
            <div class="progress"><i style="--fill:calc(<?= $burn ?> / 100)"></i></div>
          </div>
          <table class="spec-table mt-4">
            <tbody>
              <tr><th>Budget</th><td><?= e(money((float) $project['budget'])) ?></td></tr>
              <tr><th>Invoiced to date</th><td><?= e(money((float) $project['spent'])) ?></td></tr>
              <tr><th>Remaining</th><td><?= e(money(max(0, (float) $project['budget'] - (float) $project['spent']))) ?></td></tr>
            </tbody>
          </table>
        </div>
      </div>
    <?php endif; ?>

    <div class="panel">
      <div class="panel__head"><h3>Invoices</h3><a class="btn btn--sm btn--quiet" href="<?= e(url('/client/invoices')) ?>">All</a></div>
      <div class="table-wrap">
        <table class="table">
          <thead><tr><th>Number</th><th>Status</th><th class="num">Amount</th></tr></thead>
          <tbody>
            <?php foreach ($invoices as $invoice): ?>
              <tr>
                <td data-label="Number"><strong><?= e($invoice['number']) ?></strong><br><span class="tiny dim"><?= e(human_date($invoice['issued_at'])) ?></span></td>
                <td data-label="Status"><?php $view->partial('partials.status-pill', ['value' => (string) $invoice['status']]); ?></td>
                <td data-label="Amount" class="num"><?= e(money((float) $invoice['amount'], (string) $invoice['currency'])) ?></td>
              </tr>
            <?php endforeach; ?>
            <?php if ($invoices === []): ?>
              <tr><td data-label="" colspan="3"><span class="small dim">No invoices for this project.</span></td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="panel">
      <div class="panel__body">
        <h3 class="h5">Need something changed?</h3>
        <p class="small muted mt-3">Raise a ticket and your delivery lead sees it the same day.</p>
        <a class="btn btn--ghost btn--sm btn--block mt-4" href="<?= e(url('/client/support')) ?>">Open a ticket</a>
      </div>
    </div>
  </div>
</div>
<?php $view->stop(); ?>
