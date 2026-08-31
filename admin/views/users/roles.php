<?php /** @var array $roles @var array $permissions */ ?>
<div class="page-header">
    <div>
        <h1>Roles &amp; permissions</h1>
        <p>Roles decide what each admin user can see and change. The Super Admin role always holds every permission.</p>
    </div>
    <div class="page-header__actions">
        <a class="btn btn--primary btn--sm" href="<?= e(url('/admin/roles/create')) ?>"><?= icon('plus') ?>New role</a>
    </div>
</div>

<div class="grid-panels">
    <?php foreach ($roles as $role): ?>
    <div class="panel">
        <div class="panel__head">
            <div>
                <span class="panel__title"><?= e($role['name']) ?></span>
                <div class="panel__sub"><?= e($role['description']) ?></div>
            </div>
            <?php if ((int) $role['is_system'] === 1): ?><span class="badge">Built-in</span><?php endif; ?>
        </div>
        <div class="panel__body">
            <div class="row row--between mb-4">
                <span class="hint"><?= (int) $role['user_count'] ?> user<?= (int) $role['user_count'] === 1 ? '' : 's' ?></span>
                <span class="hint">
                    <?= $role['slug'] === 'super-admin' ? 'All permissions' : (int) $role['permission_count'] . ' permissions' ?>
                </span>
            </div>
            <div class="chip-row">
                <?php if ($role['slug'] === 'super-admin'): ?>
                    <span class="pill"><?= icon('check') ?>Everything</span>
                <?php else: ?>
                    <?php foreach (array_slice($role['permissions'], 0, 8) as $perm): ?>
                    <span class="pill"><?= e($perm) ?></span>
                    <?php endforeach; ?>
                    <?php if (count($role['permissions']) > 8): ?>
                    <span class="pill">+<?= count($role['permissions']) - 8 ?> more</span>
                    <?php endif; ?>
                    <?php if (!$role['permissions']): ?>
                    <span class="hint">No permissions granted — users with this role can only sign in.</span>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
        <div class="panel__foot">
            <a class="btn btn--ghost btn--sm" href="<?= e(url('/admin/roles/' . (int) $role['id'] . '/edit')) ?>"><?= icon('edit') ?>Edit</a>
            <?php if ((int) $role['is_system'] === 0): ?>
            <form method="post" style="display:inline;margin-left:auto" action="<?= e(url('/admin/roles/' . (int) $role['id'] . '/delete')) ?>"
                  data-confirm="Delete this role? Users must be reassigned first.">
                <?= csrf_field() ?>
                <button class="btn btn--quiet btn--sm" type="submit"><?= icon('trash') ?>Delete</button>
            </form>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>
