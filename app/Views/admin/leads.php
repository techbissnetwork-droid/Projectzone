<?php
/** @var App\Core\View $view @var array $leads @var array $statusCounts @var string $activeStatus @var float $pipelineValue @var array $topics */
$view->extends('layouts.portal');
$view->start('content');
$stages = ['new', 'contacted', 'qualified', 'proposal', 'won', 'lost'];
?>
<div class="between">
  <?php $view->partial('partials.filter-tabs', ['counts' => $statusCounts, 'active' => $activeStatus, 'base' => '/admin/leads', 'allLabel' => 'All leads']); ?>
  <span class="badge">Open pipeline: <?= e(money($pipelineValue)) ?></span>
</div>

<div class="panel mt-5">
  <div class="panel__head"><h3><?= count($leads) ?> enquiries</h3></div>
  <div class="table-wrap">
    <table class="table">
      <thead>
        <tr><th>Contact</th><th>Topic</th><th>Budget</th><th>Owner</th><th class="num">Value</th><th>Stage</th></tr>
      </thead>
      <tbody>
        <?php foreach ($leads as $lead): ?>
          <tr>
            <td data-label="Contact">
              <strong><?= e($lead['name']) ?></strong><br>
              <span class="tiny dim"><?= e($lead['company'] ?: '—') ?> · <?= e($lead['email']) ?></span>
              <details style="margin-top:.4rem">
                <summary class="tiny" style="cursor:pointer;color:var(--accent-2)">Message</summary>
                <p class="tiny dim" style="margin-top:.35rem;max-width:52ch;line-height:1.55"><?= e($lead['message']) ?></p>
              </details>
            </td>
            <td data-label="Topic">
              <?= e($topics[$lead['topic']] ?? ucwords(str_replace('-', ' ', (string) $lead['topic']))) ?><br>
              <span class="tiny dim"><?= e($lead['timeline'] ?: 'no timeline') ?> · via <?= e($lead['source'] ?: 'unknown') ?></span>
            </td>
            <td data-label="Budget"><?= e($lead['budget'] ? ucwords(str_replace('-', ' ', (string) $lead['budget'])) : '—') ?></td>
            <td data-label="Owner"><?= e($lead['owner_name'] ?: 'Unassigned') ?></td>
            <td data-label="Value" class="num"><?= $lead['value'] ? e(money((float) $lead['value'])) : '—' ?></td>
            <td data-label="Stage">
              <form method="post" action="<?= e(url('/admin/leads/status')) ?>" class="cluster" style="gap:.35rem;flex-wrap:nowrap">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int) $lead['id'] ?>">
                <label class="sr-only" for="stage-<?= (int) $lead['id'] ?>">Stage for <?= e($lead['name']) ?></label>
                <select class="select" id="stage-<?= (int) $lead['id'] ?>" name="status" style="min-height:34px;padding:.25rem 2rem .25rem .6rem;font-size:var(--t--2)">
                  <?php foreach ($stages as $stage): ?>
                    <option value="<?= e($stage) ?>" <?= $lead['status'] === $stage ? 'selected' : '' ?>><?= e(ucfirst($stage)) ?></option>
                  <?php endforeach; ?>
                </select>
                <button class="btn btn--sm btn--ghost" type="submit">Save</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php $view->stop(); ?>
