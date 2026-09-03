<?php
/** @var App\Core\View $view @var array $users @var array $roleCounts @var string $activeRole */
$view->extends('layouts.portal');
$view->start('content');
?>
<?php $view->partial('partials.filter-tabs', ['counts' => $roleCounts, 'active' => $activeRole, 'base' => '/admin/users', 'param' => 'role', 'allLabel' => 'All roles']); ?>

<div class="panel mt-5">
  <div class="panel__head">
    <h3><?= count($users) ?> accounts</h3>
    <span class="small dim">Suspending an account revokes its sessions immediately.</span>
  </div>
  <div class="table-wrap">
    <table class="table">
      <thead>
        <tr><th>Person</th><th>Role</th><th>Organisation</th><th>Last sign-in</th><th>Status</th><th></th></tr>
      </thead>
      <tbody>
        <?php foreach ($users as $user): ?>
          <tr>
            <td data-label="Person">
              <div class="cluster" style="gap:.6rem;flex-wrap:nowrap">
                <span class="avatar avatar--sm"><?= e(initials((string) $user['name'])) ?></span>
                <span style="min-width:0">
                  <strong><?= e($user['name']) ?></strong><br>
                  <span class="tiny dim"><?= e($user['email']) ?></span>
                </span>
              </div>
            </td>
            <td data-label="Role"><span class="badge badge--neutral"><?= e(ucfirst((string) $user['role'])) ?></span></td>
            <td data-label="Organisation"><?= e($user['company'] ?: '—') ?><br><span class="tiny dim"><?= e($user['job_title'] ?: '') ?></span></td>
            <td data-label="Last sign-in"><?= e($user['last_login_at'] ? time_ago($user['last_login_at']) : 'Never') ?></td>
            <td data-label="Status"><?php $view->partial('partials.status-pill', ['value' => (string) $user['status']]); ?></td>
            <td data-label="" class="num">
              <form method="post" action="<?= e(url('/admin/users/status')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int) $user['id'] ?>">
                <input type="hidden" name="status" value="<?= $user['status'] === 'active' ? 'suspended' : 'active' ?>">
                <button class="btn btn--sm btn--ghost" type="submit">
                  <?= $user['status'] === 'active' ? 'Suspend' : 'Reinstate' ?>
                </button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php $view->stop(); ?>
