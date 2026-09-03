<?php
/** @var App\Core\View $view @var array $board @var array $stages @var array $topics */
$view->extends('layouts.portal');
$view->start('content');
$totals = [];
foreach ($board as $stage => $leads) {
    $totals[$stage] = array_sum(array_map(static fn ($l) => (float) ($l['value'] ?? 0), $leads));
}
?>
<div class="kpis">
  <?php foreach (['new' => 'New', 'qualified' => 'Qualified', 'proposal' => 'Proposal', 'won' => 'Won'] as $stage => $label): ?>
    <div class="kpi">
      <span class="kpi__label"><?= icon('trend') ?><?= e($label) ?></span>
      <span class="kpi__value"><?= count($board[$stage] ?? []) ?></span>
      <span class="kpi__delta kpi__delta--up"><?= e(money($totals[$stage] ?? 0)) ?></span>
    </div>
  <?php endforeach; ?>
</div>

<div class="mt-5" style="display:grid;gap:var(--s-3);grid-auto-flow:column;grid-auto-columns:min(82vw,300px);overflow-x:auto;padding-bottom:.75rem;scroll-snap-type:x mandatory">
  <?php foreach ($stages as $stage): ?>
    <section class="panel" style="scroll-snap-align:start;display:flex;flex-direction:column">
      <div class="panel__head" style="padding:var(--s-4)">
        <h3 style="font-size:var(--t-0)"><?= e(ucfirst($stage)) ?></h3>
        <span class="badge badge--neutral"><?= count($board[$stage]) ?></span>
      </div>
      <div style="padding:var(--s-4);display:grid;gap:.6rem;flex:1">
        <?php if ($board[$stage] === []): ?>
          <p class="tiny dim">Empty</p>
        <?php endif; ?>
        <?php foreach ($board[$stage] as $lead): ?>
          <article class="card" style="padding:.85rem">
            <strong style="font-size:var(--t-0)"><?= e($lead['name']) ?></strong>
            <div class="tiny dim mt-3"><?= e($lead['company'] ?: 'Independent') ?></div>
            <div class="between mt-4">
              <span class="tiny dim"><?= e($topics[$lead['topic']] ?? $lead['topic']) ?></span>
              <strong class="tiny"><?= $lead['value'] ? e(money((float) $lead['value'])) : '—' ?></strong>
            </div>
            <form method="post" action="<?= e(url('/staff/pipeline/status')) ?>" class="cluster mt-3" style="gap:.3rem;flex-wrap:nowrap">
              <?= csrf_field() ?>
              <input type="hidden" name="id" value="<?= (int) $lead['id'] ?>">
              <label class="sr-only" for="pl-<?= (int) $lead['id'] ?>">Move <?= e($lead['name']) ?></label>
              <select class="select" id="pl-<?= (int) $lead['id'] ?>" name="status" style="min-height:32px;padding:.2rem 1.8rem .2rem .5rem;font-size:var(--t--2)">
                <?php foreach ($stages as $option): ?>
                  <option value="<?= e($option) ?>" <?= $option === $stage ? 'selected' : '' ?>><?= e(ucfirst($option)) ?></option>
                <?php endforeach; ?>
              </select>
              <button class="btn btn--sm btn--quiet" type="submit"><?= icon('arrow-right', ['size' => 14]) ?></button>
            </form>
          </article>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endforeach; ?>
</div>
<p class="rail-hint"><?= icon('arrow-right') ?>Swipe to see later stages</p>
<?php $view->stop(); ?>
