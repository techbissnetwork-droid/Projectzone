<?php
/** @var App\Core\View $view @var array $stats @var array $projects @var array $milestones
 *  @var array $licenses @var array $deployments @var array $invoices */
$view->extends('layouts.portal');
$view->start('content');
?>
<div class="kpis">
  <?php foreach ([
    ['layers', 'Active projects', (string) $stats['projects']],
    ['tag', 'Active licences', (string) $stats['licenses']],
    ['rocket', 'Deployments', (string) $stats['deployments']],
    ['file', 'Outstanding', money($stats['dueInvoices'])],
  ] as $kpi): ?>
    <div class="kpi">
      <span class="kpi__label"><?= icon($kpi[0]) ?><?= e($kpi[1]) ?></span>
      <span class="kpi__value"><?= e($kpi[2]) ?></span>
    </div>
  <?php endforeach; ?>
</div>

<div class="split split--wide-left mt-5" style="gap:var(--s-4);align-items:start">
  <div class="stack" style="--flow:var(--s-4)">
    <div class="panel">
      <div class="panel__head">
        <h3>Your projects</h3>
        <a class="btn btn--sm btn--quiet" href="<?= e(url('/client/projects')) ?>">All projects</a>
      </div>
      <div class="panel__body" style="padding-block:var(--s-4)">
        <?php if ($projects === []): ?>
          <p class="small dim">No projects on your account yet.</p>
        <?php endif; ?>
        <div class="stack" style="--flow:1rem">
          <?php foreach ($projects as $project): ?>
            <a class="card" href="<?= e(url('/client/projects/' . (int) $project['id'])) ?>" style="padding:var(--s-4);display:block">
              <div class="between">
                <div style="min-width:0">
                  <strong style="font-size:var(--t-0)"><?= e($project['name']) ?></strong>
                  <span class="tiny dim" style="display:block"><?= e($project['code']) ?> · <?= e(ucfirst((string) $project['phase'])) ?> phase</span>
                </div>
                <?php $view->partial('partials.status-pill', ['value' => (string) $project['health']]); ?>
              </div>
              <div class="meter-row mt-4">
                <div class="meter-row__top"><span class="tiny dim">Progress</span><b class="tiny"><?= (int) $project['progress'] ?>%</b></div>
                <div class="progress"><i style="--fill:calc(<?= (int) $project['progress'] ?> / 100)"></i></div>
              </div>
              <div class="tiny dim mt-3">Due <?= e(human_date($project['due_at'])) ?></div>
            </a>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <div class="panel">
      <div class="panel__head"><h3>Coming up</h3></div>
      <ul class="feed">
        <?php foreach ($milestones as $milestone): ?>
          <li>
            <span class="feed__dot" style="background:var(--<?= $milestone['status'] === 'active' ? 'accent' : 'ink-3' ?>)"></span>
            <div style="min-width:0">
              <strong><?= e($milestone['title']) ?></strong>
              <time><?= e($milestone['project_name']) ?> · <?= e(ucfirst((string) $milestone['status'])) ?> · due <?= e(human_date($milestone['due_at'])) ?></time>
            </div>
          </li>
        <?php endforeach; ?>
        <?php if ($milestones === []): ?><li><span class="small dim">No upcoming milestones.</span></li><?php endif; ?>
      </ul>
    </div>
  </div>

  <div class="stack" style="--flow:var(--s-4)">
    <div class="panel">
      <div class="panel__head">
        <h3>Licences</h3>
        <a class="btn btn--sm btn--quiet" href="<?= e(url('/client/licenses')) ?>">Manage</a>
      </div>
      <div class="panel__body" style="padding-block:var(--s-4)">
        <div class="stack" style="--flow:.6rem">
          <?php foreach ($licenses as $license): ?>
            <div class="card" style="padding:.8rem">
              <div class="between">
                <strong class="small"><?= e($license['product_name']) ?></strong>
                <span class="badge badge--neutral"><?= e(ucfirst((string) $license['tier'])) ?></span>
              </div>
              <div class="tiny dim mono mt-3"><?= e($license['license_key']) ?></div>
            </div>
          <?php endforeach; ?>
          <?php if ($licenses === []): ?>
            <p class="small dim">No licences yet. <a href="<?= e(url('/marketplace')) ?>" style="color:var(--accent-2)">Browse the marketplace</a>.</p>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="panel">
      <div class="panel__head">
        <h3>Deployments</h3>
        <a class="btn btn--sm btn--quiet" href="<?= e(url('/client/deployments')) ?>">Manage</a>
      </div>
      <div class="panel__body" style="padding-block:var(--s-4)">
        <div class="stack" style="--flow:.7rem">
          <?php foreach ($deployments as $d): ?>
            <div>
              <div class="between">
                <strong class="small"><?= e($d['site_name']) ?></strong>
                <?php $view->partial('partials.status-pill', ['value' => (string) $d['status']]); ?>
              </div>
              <div class="progress mt-3"><i style="--fill:calc(<?= (int) $d['progress'] ?> / 100)"></i></div>
            </div>
          <?php endforeach; ?>
          <?php if ($deployments === []): ?><p class="small dim">No deployments yet.</p><?php endif; ?>
        </div>
      </div>
    </div>

    <div class="panel">
      <div class="panel__head">
        <h3>Invoices</h3>
        <a class="btn btn--sm btn--quiet" href="<?= e(url('/client/invoices')) ?>">All invoices</a>
      </div>
      <div class="panel__body" style="padding-block:var(--s-4)">
        <div class="stack" style="--flow:.6rem">
          <?php foreach ($invoices as $invoice): ?>
            <div class="between">
              <div style="min-width:0">
                <strong class="small"><?= e($invoice['number']) ?></strong>
                <span class="tiny dim" style="display:block"><?= e(human_date($invoice['issued_at'])) ?></span>
              </div>
              <div style="text-align:right">
                <strong class="small"><?= e(money((float) $invoice['amount'], (string) $invoice['currency'])) ?></strong>
                <div><?php $view->partial('partials.status-pill', ['value' => (string) $invoice['status']]); ?></div>
              </div>
            </div>
          <?php endforeach; ?>
          <?php if ($invoices === []): ?><p class="small dim">No invoices issued.</p><?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>
<?php $view->stop(); ?>
