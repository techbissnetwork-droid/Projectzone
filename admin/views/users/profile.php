<?php
/** @var array $row @var array $permissions */
$val = static fn (string $k, $d = '') => old($k, $row[$k] ?? $d);
?>
<div class="page-header">
    <div>
        <h1>My profile</h1>
        <p>Signed in as <?= e($row['role_name']) ?>.</p>
    </div>
</div>

<div class="form-cols">
    <form method="post" action="<?= e(url('/admin/profile')) ?>" data-dirty-guard>
        <?= csrf_field() ?>
        <div class="panel">
            <div class="panel__head"><span class="panel__title">Your details</span></div>
            <div class="panel__body">
                <div class="form-section">
                    <div class="field--row">
                        <div class="field">
                            <label class="label" for="pr-name">Name</label>
                            <input class="input" id="pr-name" type="text" name="name" value="<?= e($val('name')) ?>" required maxlength="120">
                        </div>
                        <div class="field">
                            <label class="label" for="pr-job">Job title</label>
                            <input class="input" id="pr-job" type="text" name="job_title" value="<?= e($val('job_title')) ?>" maxlength="120">
                        </div>
                    </div>
                    <div class="field">
                        <label class="label" for="pr-email">Email</label>
                        <input class="input<?= error_for('email') ? ' is-invalid' : '' ?>" id="pr-email" type="email"
                               name="email" value="<?= e($val('email')) ?>" required maxlength="190"
                               <?= $view->partial('partials/field-invalid', ['key' => 'email']) ?>>
                        <?= $view->partial('partials/field-error', ['key' => 'email', 'withIcon' => false]) ?>
                    </div>
                </div>

                <div class="form-section">
                    <div class="form-section__title">Change password</div>
                    <div class="field">
                        <label class="label" for="pr-current">Current password</label>
                        <input class="input<?= error_for('current_password') ? ' is-invalid' : '' ?>" id="pr-current"
                               type="password" name="current_password" autocomplete="current-password"
                               <?= $view->partial('partials/field-invalid', ['key' => 'current_password']) ?>>
                        <?= $view->partial('partials/field-error', ['key' => 'current_password', 'withIcon' => false]) ?>
                    </div>
                    <div class="field--row">
                        <div class="field">
                            <label class="label" for="pr-password">New password</label>
                            <input class="input<?= error_for('password') ? ' is-invalid' : '' ?>" id="pr-password"
                                   type="password" name="password" minlength="10" autocomplete="new-password"
                                   <?= $view->partial('partials/field-invalid', ['key' => 'password']) ?>>
                            <?= $view->partial('partials/field-error', ['key' => 'password', 'withIcon' => false]) ?>
                        </div>
                        <div class="field">
                            <label class="label" for="pr-confirm">Confirm new password</label>
                            <input class="input" id="pr-confirm" type="password" name="password_confirm" autocomplete="new-password">
                        </div>
                    </div>
                    <span class="hint">Leave all three blank to keep your current password.</span>
                </div>
            </div>
            <div class="panel__foot">
                <button class="btn btn--primary btn--sm" type="submit"><?= icon('check') ?>Save profile</button>
            </div>
        </div>
    </form>

    <aside class="form-aside">
        <div class="panel">
            <div class="panel__head"><span class="panel__title">Account</span></div>
            <div class="panel__body">
                <div class="kv-list">
                    <div class="kv"><span class="kv__label">Role</span><span class="kv__value"><?= e($row['role_name']) ?></span></div>
                    <div class="kv"><span class="kv__label">Last sign-in</span>
                        <span class="kv__value"><?= $row['last_login_at'] ? e(format_date($row['last_login_at'], 'j M Y, H:i')) : 'This is your first' ?></span></div>
                    <div class="kv"><span class="kv__label">From</span><span class="kv__value mono-sm"><?= e($row['last_login_ip'] ?: '—') ?></span></div>
                    <div class="kv"><span class="kv__label">Member since</span><span class="kv__value"><?= e(format_date($row['created_at'], 'j M Y')) ?></span></div>
                </div>
            </div>
        </div>

        <div class="panel">
            <div class="panel__head"><span class="panel__title">Your permissions</span></div>
            <div class="panel__body">
                <?php if (\Techbiss\Core\Auth::isSuperAdmin()): ?>
                    <p class="hint">As a super admin you hold every permission, including any added in future.</p>
                <?php else: ?>
                <div class="chip-row">
                    <?php foreach ($permissions as $perm): ?>
                    <span class="pill"><?= icon('check') ?><?= e($perm) ?></span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </aside>
</div>
