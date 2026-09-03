<?php
/** @var App\Core\View $view @var array $stats @var array $myTasks @var int $overdue @var array $projects @var array $tickets @var array $leads */
$view->extends('layouts.portal');
$view->start('content');
?>
<div class="kpis">
  <?php foreach ([
    ['check-circle', 'Open tasks', (string) $stats['openTasks'], $overdue > 0 ? $overdue . ' overdue' : 'all on schedule', $overdue > 0 ? 'down' : 'up'],
    ['trend', 'My pipeline', (string) $stats['myLeads'], 'leads you own', 'up'],
    ['ticket', 'Support queue', (string) $stats['openTickets'], 'needing a reply', 'up'],
    ['layers', 'Active projects', (string) $stats['activeProjects'], 'across the portfolio', 'up'],
  ] as $kpi): ?>
    <div class="kpi">
      <span class="kpi__label"><?= icon($kpi[0]) ?><?= e($kpi[1]) ?></span>
      <span class="kpi__value"><?= e($kpi[2]) ?></span>
      <span class="kpi__delta kpi__delta--<?= e($kpi[4]) ?>"><?= e($kpi[3]) ?></span>
    </div>
  <?php endforeach; ?>
</div>

<div class="split split--wide-left mt-5" style="gap:var(--s-4);align-items:start">
  <div class="panel">
    <div class="panel__head">
      <h3>Your tasks</h3>
      <a class="btn btn--sm btn--quiet" href="<?= e(url('/staff/tasks')) ?>">All tasks</a>
    </div>
    <div class="panel__body" style="padding-block:var(--s-4)">
      <?php if ($myTasks === []): ?>
        <p class="small dim">Nothing assigned to you right now.</p>
      <?php endif; ?>
      <div class="stack" style="--flow:.6rem">
        <?php foreach ($myTasks as $task): ?>
          <?php $late = $task['due_at'] && $task['due_at'] < gmdate('c'); ?>
          <div class="card" style="padding:.85rem 1rem">
            <div class="between" style="gap:.75rem;align-items:flex-start">
              <div style="min-width:0">
                <strong style="font-size:var(--t-0)"><?= e($task['title']) ?></strong>
                <p class="tiny dim mt-3"><?= e($task['detail'] ?: '') ?></p>
              </div>
              <div style="text-align:right;flex:none">
                <span class="badge badge--<?= $task['priority'] === 'high' ? 'bad' : ($task['priority'] === 'low' ? 'neutral' : 'warn') ?>"><?= e($task['priority']) ?></span>
                <div class="tiny <?= $late ? '' : 'dim' ?>" style="margin-top:.35rem;<?= $late ? 'color:var(--bad)' : '' ?>">
                  <?= $late ? 'Overdue ' : 'Due ' ?><?= e(human_date($task['due_at'])) ?>
                </div>
              </div>
            </div>
            <form method="post" action="<?= e(url('/staff/tasks/toggle')) ?>" class="mt-3">
              <?= csrf_field() ?>
              <input type="hidden" name="id" value="<?= (int) $task['id'] ?>">
              <button class="btn btn--sm btn--ghost" type="submit"><?= icon('check') ?>Mark done</button>
            </form>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <div class="stack" style="--flow:var(--s-4)">
    <div class="panel">
      <div class="panel__head">
        <h3>Support queue</h3>
        <a class="btn btn--sm btn--quiet" href="<?= e(url('/staff/tickets')) ?>">Open queue</a>
      </div>
      <ul class="feed">
        <?php foreach ($tickets as $ticket): ?>
          <li>
            <span class="feed__dot" style="background:var(--<?= $ticket['priority'] === 'high' ? 'bad' : 'accent' ?>)"></span>
            <div style="min-width:0">
              <strong><?= e($ticket['subject']) ?></strong>
              <time><?= e($ticket['company'] ?: $ticket['client_name']) ?> · <?= e($ticket['reference']) ?> · <?= e(time_ago($ticket['created_at'])) ?></time>
            </div>
          </li>
        <?php endforeach; ?>
        <?php if ($tickets === []): ?><li><span class="small dim">The queue is clear.</span></li><?php endif; ?>
      </ul>
    </div>

    <div class="panel">
      <div class="panel__head">
        <h3>Your leads</h3>
        <a class="btn btn--sm btn--quiet" href="<?= e(url('/staff/pipeline')) ?>">Pipeline</a>
      </div>
      <ul class="feed">
        <?php foreach ($leads as $lead): ?>
          <li>
            <span class="feed__dot"></span>
            <div style="min-width:0">
              <strong><?= e($lead['name']) ?> · <?= e($lead['company'] ?: 'Independent') ?></strong>
              <time><?= e(ucfirst((string) $lead['status'])) ?> · <?= $lead['value'] ? e(money((float) $lead['value'])) : 'unvalued' ?> · <?= e(time_ago($lead['created_at'])) ?></time>
            </div>
          </li>
        <?php endforeach; ?>
        <?php if ($leads === []): ?><li><span class="small dim">No leads assigned to you.</span></li><?php endif; ?>
      </ul>
    </div>
  </div>
</div>

<div class="panel mt-4">
  <div class="panel__head">
    <h3>Active projects</h3>
    <a class="btn btn--sm btn--quiet" href="<?= e(url('/staff/projects')) ?>">All projects</a>
  </div>
  <div class="table-wrap">
    <table class="table">
      <thead><tr><th>Project</th><th>Client</th><th>Phase</th><th>Progress</th><th>Health</th><th>Due</th></tr></thead>
      <tbody>
        <?php foreach ($projects as $project): ?>
          <tr>
            <td data-label="Project"><strong><?= e($project['name']) ?></strong><br><span class="tiny dim mono"><?= e($project['code']) ?></span></td>
            <td data-label="Client"><?= e($project['company'] ?: $project['client_name']) ?></td>
            <td data-label="Phase"><span class="badge badge--neutral"><?= e(ucfirst((string) $project['phase'])) ?></span></td>
            <td data-label="Progress" style="min-width:120px">
              <div class="progress"><i style="width:<?= (int) $project['progress'] ?>%"></i></div>
              <span class="tiny dim"><?= (int) $project['progress'] ?>%</span>
            </td>
            <td data-label="Health"><?php $view->partial('partials.status-pill', ['value' => (string) $project['health']]); ?></td>
            <td data-label="Due"><?= e(human_date($project['due_at'])) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php $view->stop(); ?>
