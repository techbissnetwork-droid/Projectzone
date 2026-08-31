<?php
/** @var array $row @var bool $isNew @var array $permissions @var array $granted */
$id      = (int) ($row['id'] ?? 0);
$action  = $isNew ? url('/admin/roles') : url('/admin/roles/' . $id);
$val     = static fn (string $k, $d = '') => old($k, $row[$k] ?? $d);
$isSuper = ($row['slug'] ?? '') === 'super-admin';
$granted = (array) old('permissions', $granted);
?>
<div class="page-header">
    <div>
        <h1><?= e($title) ?></h1>
        <p><?= $isSuper ? 'The Super Admin role always holds every permission — only its description can be edited.' : 'Tick the areas users with this role should be able to reach.' ?></p>
    </div>
    <div class="page-header__actions">
        <a class="btn btn--ghost btn--sm" href="<?= e(url('/admin/roles')) ?>"><?= icon('arrow-left') ?>Back</a>
    </div>
</div>

<form method="post" action="<?= e($action) ?>" data-dirty-guard>
    <?= csrf_field() ?>
    <div class="panel">
        <div class="panel__body">
            <div class="form-section">
                <div class="field--row">
                    <div class="field">
                        <label class="label" for="r-name">Role name <span class="req">*</span></label>
                        <input class="input<?= error_for('name') ? ' is-invalid' : '' ?>" id="r-name" type="text"
                               name="name" value="<?= e($val('name')) ?>" required maxlength="80" <?= $isSuper ? 'readonly' : '' ?>
                               <?= $view->partial('partials/field-invalid', ['key' => 'name']) ?>>
                        <?= $view->partial('partials/field-error', ['key' => 'name', 'withIcon' => false]) ?>
                    </div>
                    <div class="field">
                        <label class="label" for="r-desc">Description</label>
                        <input class="input" id="r-desc" type="text" name="description" value="<?= e($val('description')) ?>" maxlength="255">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if (!$isSuper): ?>
    <div class="panel">
        <div class="panel__head">
            <div>
                <span class="panel__title">Permissions</span>
                <div class="panel__sub">A user without a permission never sees that section in the sidebar.</div>
            </div>
        </div>
        <div class="panel__body">
            <div class="perm-grid">
                <?php foreach ($permissions as $groupName => $perms): ?>
                <div class="perm-group">
                    <div class="perm-group__title"><?= e($groupName) ?></div>
                    <div class="stack stack-2">
                        <?php foreach ($perms as $perm): ?>
                        <label class="check">
                            <input type="checkbox" name="permissions[]" value="<?= e($perm['slug']) ?>"
                                   <?= in_array($perm['slug'], $granted, true) ? 'checked' : '' ?>>
                            <span class="check__box" aria-hidden="true"></span>
                            <span>
                                <?= e($perm['name']) ?>
                                <span class="hint" style="display:block"><?= e($perm['slug']) ?></span>
                            </span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="sticky-actions">
        <button class="btn btn--primary" type="submit"><?= icon('check') ?><?= $isNew ? 'Create role' : 'Save role' ?></button>
        <a class="btn btn--quiet" href="<?= e(url('/admin/roles')) ?>">Cancel</a>
    </div>
</form>
