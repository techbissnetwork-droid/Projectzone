<?php
/** @var App\Core\View $view @var array $projects @var array $milestones */
$view->extends('layouts.portal');
$view->start('content');
?>
<div class="stack" style="--flow:var(--s-4)">
  <?php foreach ($projects as $project): ?>
    <?php $ms = $milestones[(int) $project['id']] ?? []; ?>
    <div class="panel">
      <div class="panel__head">
        <div style="min-width:0">
          <h3><?= e($project['name']) ?></h3>
          <span class="tiny dim mono"><?= e($project['code']) ?> · <?= e($project['company'] ?: $project['client_name']) ?> · lead <?= e($project['lead_name'] ?: 'unassigned') ?></span>
        </div>
        <div class="cluster" style="gap:.4rem">
          <span class="badge badge--neutral"><?= e(ucfirst((string) $project['phase'])) ?></span>
          <?php $view->partial('partials.status-pill', ['value' => (string) $project['health']]); ?>
        </div>
      </div>
      <div class="panel__body">
        <p class="small muted"><?= e($project['summary'] ?: '') ?></p>

        <div class="split split--wide-left mt-5" style="gap:var(--s-5);align-items:start">
          <div>
            <div class="meter-row">
              <div class="meter-row__top"><span>Delivery progress</span><b><?= (int) $project['progress'] ?>%</b></div>
              <div class="progress"><i style="--fill:calc(<?= (int) $project['progress'] ?> / 100)"></i></div>
            </div>

            <?php if ($project['budget']): ?>
              <?php $burn = min(100, (int) round((float) $project['spent'] / max(1.0, (float) $project['budget']) * 100)); ?>
              <div class="meter-row mt-4">
                <div class="meter-row__top">
                  <span>Budget consumed</span>
                  <b><?= e(money((float) $project['spent'])) ?> / <?= e(money((float) $project['budget'])) ?></b>
                </div>
                <div class="progress"><i style="--fill:calc(<?= $burn ?> / 100)"></i></div>
                <div class="tiny dim"><?= $burn ?>% of budget against <?= (int) $project['progress'] ?>% of scope</div>
              </div>
            <?php endif; ?>

            <div class="cluster mt-5" style="gap:var(--s-5)">
              <div><span class="tiny dim">Started</span><br><strong class="small"><?= e(human_date($project['started_at'])) ?></strong></div>
              <div><span class="tiny dim">Due</span><br><strong class="small"><?= e(human_date($project['due_at'])) ?></strong></div>
            </div>
          </div>

          <div>
            <h4 class="small" style="text-transform:uppercase;letter-spacing:var(--ls-eyebrow);color:var(--ink-3)">Milestones</h4>
            <div class="timeline mt-3">
              <?php foreach ($ms as $milestone): ?>
                <div class="timeline__item" style="padding-bottom:var(--s-4)">
                  <div class="timeline__rail">
                    <span class="timeline__dot" style="background:var(--<?= $milestone['status'] === 'complete' ? 'ok' : ($milestone['status'] === 'active' ? 'accent' : 'ink-3') ?>);box-shadow:none"></span>
                    <span class="timeline__line"></span>
                  </div>
                  <div>
                    <strong class="small" style="<?= $milestone['status'] === 'complete' ? 'opacity:.65' : '' ?>"><?= e($milestone['title']) ?></strong>
                    <div class="tiny dim"><?= e(ucfirst((string) $milestone['status'])) ?> · <?= e(human_date($milestone['due_at'])) ?></div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>
<?php $view->stop(); ?>
