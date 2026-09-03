<?php
/** @var App\Core\View $view @var array $entries @var array $attempts */
$view->extends('layouts.portal');
$view->start('content');
?>
<div class="split split--wide-left" style="gap:var(--s-4);align-items:start">
  <div class="panel">
    <div class="panel__head"><h3>Platform activity</h3><span class="small dim"><?= count($entries) ?> most recent</span></div>
    <ul class="feed">
      <?php foreach ($entries as $entry): ?>
        <li>
          <span class="feed__dot"></span>
          <div style="min-width:0">
            <strong><?= e($entry['description']) ?></strong>
            <time>
              <?= e($entry['user_name'] ?? 'System') ?>
              <?php if (!empty($entry['role'])): ?><span class="badge badge--neutral" style="margin-left:.3rem"><?= e($entry['role']) ?></span><?php endif; ?>
              · <?= e(time_ago($entry['created_at'])) ?> · <span class="mono"><?= e($entry['action']) ?></span> · <?= e($entry['ip_address']) ?>
            </time>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>

  <div class="panel">
    <div class="panel__head"><h3>Sign-in attempts</h3></div>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>Email</th><th>Result</th><th>When</th></tr></thead>
        <tbody>
          <?php if ($attempts === []): ?>
            <tr><td data-label="" colspan="3"><span class="small dim">No recorded attempts.</span></td></tr>
          <?php endif; ?>
          <?php foreach ($attempts as $attempt): ?>
            <tr>
              <td data-label="Email"><span class="small"><?= e($attempt['email']) ?></span><br><span class="tiny dim mono"><?= e($attempt['ip_address']) ?></span></td>
              <td data-label="Result">
                <span class="badge badge--<?= $attempt['successful'] ? 'ok' : 'bad' ?>"><?= $attempt['successful'] ? 'Success' : 'Failed' ?></span>
              </td>
              <td data-label="When"><span class="tiny dim"><?= e(time_ago($attempt['created_at'])) ?></span></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php $view->stop(); ?>
