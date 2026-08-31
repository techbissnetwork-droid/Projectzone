<?php
/** @var array $row @var bool $isNew @var array $roles */
$id     = (int) ($row['id'] ?? 0);
$action = $isNew ? url('/admin/users') : url('/admin/users/' . $id);
$val    = static fn (string $k, $d = '') => old($k, $row[$k] ?? $d);
?>
<div class="page-header">
    <div>
        <h1><?= e($title) ?></h1>
        <p><?= $isNew ? 'A password is required for new accounts.' : 'Leave the password fields blank to keep the current password.' ?></p>
    </div>
    <div class="page-header__actions">
        <a class="btn btn--ghost btn--sm" href="<?= e(url('/admin/users')) ?>"><?= icon('arrow-left') ?>Back</a>
    </div>
</div>

<form method="post" action="<?= e($action) ?>" data-dirty-guard style="max-width:660px">
    <?= csrf_field() ?>
    <div class="panel">
        <div class="panel__body">
            <div class="form-section">
                <div class="field--row">
                    <div class="field">
                        <label class="label" for="u-name">Name <span class="req">*</span></label>
                        <input class="input<?= error_for('name') ? ' is-invalid' : '' ?>" id="u-name" type="text"
                               name="name" value="<?= e($val('name')) ?>" required maxlength="120">
                        <?php if (error_for('name')): ?><span class="field-error"><?= e(error_for('name')) ?></span><?php endif; ?>
                    </div>
                    <div class="field">
                        <label class="label" for="u-job">Job title</label>
                        <input class="input" id="u-job" type="text" name="job_title" value="<?= e($val('job_title')) ?>" maxlength="120">
                    </div>
                </div>

                <div class="field">
                    <label class="label" for="u-email">Email <span class="req">*</span></label>
                    <input class="input<?= error_for('email') ? ' is-invalid' : '' ?>" id="u-email" type="email"
                           name="email" value="<?= e($val('email')) ?>" required maxlength="190" autocomplete="off">
                    <?php if (error_for('email')): ?><span class="field-error"><?= e(error_for('email')) ?></span><?php endif; ?>
                </div>

                <div class="field">
                    <label class="label" for="u-role">Role <span class="req">*</span></label>
                    <select class="select<?= error_for('role_id') ? ' is-invalid' : '' ?>" id="u-role" name="role_id" required>
                        <?php foreach ($roles as $role): ?>
                        <option value="<?= (int) $role['id'] ?>" <?= (int) $val('role_id') === (int) $role['id'] ? 'selected' : '' ?>>
                            <?= e($role['name']) ?> — <?= e($role['description']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (error_for('role_id')): ?><span class="field-error"><?= e(error_for('role_id')) ?></span><?php endif; ?>
                </div>

                <div class="field">
                    <label class="label" for="u-bio">Bio</label>
                    <textarea class="textarea" id="u-bio" name="bio" maxlength="2000"><?= e($val('bio')) ?></textarea>
                    <span class="hint">Used as the author biography on blog posts.</span>
                </div>
            </div>

            <div class="form-section">
                <div class="form-section__title">Password</div>
                <div class="field--row">
                    <div class="field">
                        <label class="label" for="u-password"><?= $isNew ? 'Password' : 'New password' ?><?php if ($isNew): ?> <span class="req">*</span><?php endif; ?></label>
                        <input class="input<?= error_for('password') ? ' is-invalid' : '' ?>" id="u-password" type="password"
                               name="password" <?= $isNew ? 'required' : '' ?> minlength="10" autocomplete="new-password">
                        <span class="hint">At least 10 characters with letters and numbers.</span>
                        <?php if (error_for('password')): ?><span class="field-error"><?= e(error_for('password')) ?></span><?php endif; ?>
                    </div>
                    <div class="field">
                        <label class="label" for="u-confirm">Confirm password</label>
                        <input class="input" id="u-confirm" type="password" name="password_confirm" autocomplete="new-password">
                    </div>
                </div>
            </div>

            <div class="form-section">
                <label class="switch">
                    <input type="checkbox" name="is_active" value="1" <?= (int) $val('is_active', 1) === 1 ? 'checked' : '' ?>>
                    <span class="switch__track" aria-hidden="true"></span><span>Account is active</span>
                </label>
                <span class="hint">A disabled account cannot sign in, but its authored content is kept.</span>
            </div>
        </div>
        <div class="panel__foot">
            <button class="btn btn--primary btn--sm" type="submit"><?= icon('check') ?><?= $isNew ? 'Create user' : 'Save changes' ?></button>
            <a class="btn btn--quiet btn--sm" href="<?= e(url('/admin/users')) ?>">Cancel</a>
        </div>
    </div>
</form>
