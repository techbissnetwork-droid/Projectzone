<?php /** @var array $rows @var array $roles */ ?>
<div class="page-header">
    <div>
        <h1>Admin users</h1>
        <p><?= count($rows) ?> account<?= count($rows) === 1 ? '' : 's' ?>. Each user's role decides which areas of this panel they can reach.</p>
    </div>
    <div class="page-header__actions">
        <a class="btn btn--ghost btn--sm" href="<?= e(url('/admin/roles')) ?>"><?= icon('lock') ?>Roles</a>
        <a class="btn btn--primary btn--sm" href="<?= e(url('/admin/users/create')) ?>"><?= icon('plus') ?>New user</a>
    </div>
</div>

<div class="panel">
    <div class="table-wrap" style="border:0;border-radius:0;background:none">
        <table class="data-table" style="min-width:720px">
            <thead><tr><th>User</th><th>Role</th><th>Status</th><th class="num">Last sign-in</th><th class="actions">Actions</th></tr></thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                <tr>
                    <td>
                        <div class="row row--tight row--nowrap">
                            <span class="avatar avatar--sm"><?= e(initials((string) $row['name'])) ?></span>
                            <a href="<?= e(url('/admin/users/' . (int) $row['id'] . '/edit')) ?>">
                                <span class="cell-title"><?= e($row['name']) ?></span>
                                <span class="cell-sub"><?= e($row['email']) ?></span>
                            </a>
                        </div>
                    </td>
                    <td><span class="badge badge--accent"><?= e($row['role_name']) ?></span></td>
                    <td>
                        <span class="status-dot status-dot--<?= (int) $row['is_active'] === 1 ? 'live' : 'draft' ?>">
                            <?= (int) $row['is_active'] === 1 ? 'Active' : 'Disabled' ?>
                        </span>
                    </td>
                    <td class="num"><span class="hint"><?= $row['last_login_at'] ? e(time_ago($row['last_login_at'])) : 'Never' ?></span></td>
                    <td class="actions">
                        <div class="row-actions">
                            <a class="icon-btn" title="Edit" aria-label="Edit" href="<?= e(url('/admin/users/' . (int) $row['id'] . '/edit')) ?>"><?= icon('edit') ?></a>
                            <?php if ((int) $row['id'] !== \Techbiss\Core\Auth::id()): ?>
                            <form method="post" style="display:inline" action="<?= e(url('/admin/users/' . (int) $row['id'] . '/delete')) ?>"
                                  data-confirm="Delete this admin account? They will lose access immediately.">
                                <?= csrf_field() ?>
                                <button class="icon-btn icon-btn--danger" type="submit" title="Delete" aria-label="Delete"><?= icon('trash') ?></button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
