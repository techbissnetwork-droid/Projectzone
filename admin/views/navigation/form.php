<?php
/** @var array $row @var bool $isNew @var array $menus @var array $parents */
$id     = (int) ($row['id'] ?? 0);
$action = $isNew ? url('/admin/navigation') : url('/admin/navigation/' . $id);
$val    = static fn (string $k, $d = '') => old($k, $row[$k] ?? $d);
?>
<div class="page-header">
    <div>
        <h1><?= e($title) ?></h1>
        <p>Leave the URL blank on a top-level item to make it a dropdown label rather than a link.</p>
    </div>
    <div class="page-header__actions">
        <a class="btn btn--ghost btn--sm" href="<?= e(url('/admin/navigation?menu=' . urlencode((string) $val('menu', 'primary')))) ?>">
            <?= icon('arrow-left') ?>Back
        </a>
    </div>
</div>

<form method="post" action="<?= e($action) ?>" data-dirty-guard style="max-width:640px">
    <?= csrf_field() ?>
    <div class="panel">
        <div class="panel__body">
            <div class="form-section">
                <div class="field">
                    <label class="label" for="n-label">Label <span class="req">*</span></label>
                    <input class="input<?= error_for('label') ? ' is-invalid' : '' ?>" id="n-label" type="text"
                           name="label" value="<?= e($val('label')) ?>" required maxlength="120">
                    <?php if (error_for('label')): ?><span class="field-error"><?= e(error_for('label')) ?></span><?php endif; ?>
                </div>

                <div class="field--row">
                    <div class="field">
                        <label class="label" for="n-menu">Menu</label>
                        <select class="select" id="n-menu" name="menu">
                            <?php foreach ($menus as $m): ?>
                            <option value="<?= e($m) ?>" <?= $val('menu', 'primary') === $m ? 'selected' : '' ?>><?= e(ucfirst($m)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label class="label" for="n-parent">Parent item</label>
                        <select class="select" id="n-parent" name="parent_id">
                            <option value="">Top level</option>
                            <?php foreach ($parents as $p): ?>
                            <option value="<?= (int) $p['id'] ?>" <?= (int) $val('parent_id') === (int) $p['id'] ? 'selected' : '' ?>><?= e($p['label']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <span class="hint">Choosing a parent turns this into a dropdown entry.</span>
                    </div>
                </div>

                <div class="field--row">
                    <div class="field">
                        <label class="label" for="n-type">Link type</label>
                        <select class="select" id="n-type" name="link_type">
                            <option value="internal" <?= $val('link_type', 'internal') === 'internal' ? 'selected' : '' ?>>Internal page</option>
                            <option value="external" <?= $val('link_type') === 'external' ? 'selected' : '' ?>>External URL</option>
                        </select>
                    </div>
                    <div class="field">
                        <label class="label" for="n-url">Destination</label>
                        <input class="input<?= error_for('url') ? ' is-invalid' : '' ?>" id="n-url" type="text"
                               name="url" value="<?= e($val('url')) ?>" maxlength="500" placeholder="/services">
                        <?php if (error_for('url')): ?><span class="field-error"><?= e(error_for('url')) ?></span><?php endif; ?>
                    </div>
                </div>

                <div class="field">
                    <label class="label" for="n-desc">Dropdown description</label>
                    <input class="input" id="n-desc" type="text" name="description" value="<?= e($val('description')) ?>" maxlength="190">
                    <span class="hint">Shown under the label inside a desktop dropdown.</span>
                </div>

                <div class="field">
                    <label class="label" for="n-target">Open in</label>
                    <select class="select" id="n-target" name="target">
                        <option value="_self" <?= $val('target', '_self') === '_self' ? 'selected' : '' ?>>Same tab</option>
                        <option value="_blank" <?= $val('target') === '_blank' ? 'selected' : '' ?>>New tab</option>
                    </select>
                </div>

                <label class="switch">
                    <input type="checkbox" name="is_active" value="1" <?= (int) $val('is_active', 1) === 1 ? 'checked' : '' ?>>
                    <span class="switch__track" aria-hidden="true"></span><span>Visible in the menu</span>
                </label>

                <label class="switch">
                    <input type="checkbox" name="is_button" value="1" <?= (int) $val('is_button', 0) === 1 ? 'checked' : '' ?>>
                    <span class="switch__track" aria-hidden="true"></span><span>Render as the header call-to-action button</span>
                </label>
            </div>
        </div>
        <div class="panel__foot">
            <button class="btn btn--primary btn--sm" type="submit"><?= icon('check') ?><?= $isNew ? 'Add item' : 'Save changes' ?></button>
            <a class="btn btn--quiet btn--sm" href="<?= e(url('/admin/navigation')) ?>">Cancel</a>
        </div>
    </div>
</form>
