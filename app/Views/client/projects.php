<?php
/** @var App\Core\View $view @var array $projects @var array $milestones */
$view->extends('layouts.portal');
$view->start('content');
?>
<?php if ($projects === []): ?>
  <div class="empty">
    <?= icon('layers') ?>
    <h3>No projects yet</h3>
    <p>Engagements appear here once a statement of work is signed. Marketplace licences live under Licences.</p>
    <a class="btn btn--ghost" href="<?= e(url('/marketplace')) ?>">Browse the Marketplace</a>
  </div>
<?php endif; ?>

<div class="stack" style="--flow:var(--s-4)">
  <?php foreach ($projects as $project): ?>
    <div class="panel">
      <div class="panel__head">
        <div style="min-width:0">
          <h3><a href="<?= e(url('/client/projects/' . (int) $project['id'])) ?>"><?= e($project['name']) ?></a></h3>
          <span class="tiny dim mono"><?= e($project['code']) ?> · <?= e(ucfirst((string) $project['phase'])) ?> phase</span>
        </div>
        <div class="cluster" style="gap:.4rem">
          <?php $view->partial('partials.status-pill', ['value' => (string) $project['health']]); ?>
          <a class="btn btn--sm btn--ghost" href="<?= e(url('/client/projects/' . (int) $project['id'])) ?>">Open</a>
        </div>
      </div>
      <div class="panel__body">
        <p class="small muted"><?= e($project['summary'] ?: '') ?></p>
        <div class="meter-row mt-4">
          <div class="meter-row__top"><span>Delivery progress</span><b><?= (int) $project['progress'] ?>%</b></div>
          <div class="progress"><i style="--fill:calc(<?= (int) $project['progress'] ?> / 100)"></i></div>
        </div>
        <div class="cluster mt-4" style="gap:var(--s-5)">
          <div><span class="tiny dim">Started</span><br><strong class="small"><?= e(human_date($project['started_at'])) ?></strong></div>
          <div><span class="tiny dim">Due</span><br><strong class="small"><?= e(human_date($project['due_at'])) ?></strong></div>
          <div><span class="tiny dim">Milestones</span><br><strong class="small">
            <?php $ms = $milestones[(int) $project['id']] ?? []; ?>
            <?= count(array_filter($ms, static fn ($m) => $m['status'] === 'complete')) ?> of <?= count($ms) ?> complete
          </strong></div>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>
<?php $view->stop(); ?>
