<?php
/** @var App\Core\View $view @var array $tasks @var string $scope */
$view->extends('layouts.portal');
$view->start('content');
?>
<div class="cluster" role="group" aria-label="Task scope">
  <a class="btn btn--sm <?= $scope !== 'all' ? 'btn--solid' : 'btn--ghost' ?>" href="<?= e(url('/staff/tasks')) ?>">Assigned to me</a>
  <a class="btn btn--sm <?= $scope === 'all' ? 'btn--solid' : 'btn--ghost' ?>" href="<?= e(url('/staff/tasks?scope=all')) ?>">Whole team</a>
</div>

<div class="panel mt-5">
  <div class="panel__head"><h3><?= count($tasks) ?> tasks</h3></div>
  <div class="table-wrap">
    <table class="table">
      <thead><tr><th>Task</th><th>Assignee</th><th>Priority</th><th>Due</th><th>Status</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($tasks as $task): ?>
          <?php $late = $task['status'] === 'open' && $task['due_at'] && $task['due_at'] < gmdate('c'); ?>
          <tr>
            <td data-label="Task">
              <strong style="<?= $task['status'] === 'done' ? 'text-decoration:line-through;opacity:.6' : '' ?>"><?= e($task['title']) ?></strong><br>
              <span class="tiny dim"><?= e($task['detail'] ?: '') ?></span>
            </td>
            <td data-label="Assignee"><?= e($task['assignee_name'] ?: 'Unassigned') ?></td>
            <td data-label="Priority">
              <span class="badge badge--<?= $task['priority'] === 'high' ? 'bad' : ($task['priority'] === 'low' ? 'neutral' : 'warn') ?>"><?= e($task['priority']) ?></span>
            </td>
            <td data-label="Due" style="<?= $late ? 'color:var(--bad)' : '' ?>">
              <?= e(human_date($task['due_at'])) ?><?= $late ? ' · overdue' : '' ?>
            </td>
            <td data-label="Status"><?php $view->partial('partials.status-pill', ['value' => (string) $task['status']]); ?></td>
            <td data-label="" class="num">
              <form method="post" action="<?= e(url('/staff/tasks/toggle')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int) $task['id'] ?>">
                <button class="btn btn--sm btn--ghost" type="submit"><?= $task['status'] === 'open' ? 'Complete' : 'Reopen' ?></button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php $view->stop(); ?>
