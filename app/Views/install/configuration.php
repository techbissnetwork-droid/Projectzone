<?php
/** @var App\Core\View $view @var array $state @var array $timezones @var array $meta @var string|null $previous @var array $flash */
$view->extends('layouts.install');
$view->start('content');
?>
<div class="install-card">
  <div class="install-card__head">
    <h1><?= e($meta['label']) ?></h1>
    <p><?= e($meta['blurb']) ?> This account owns the platform and cannot be locked out by another administrator.</p>
  </div>
  <div class="install-card__body">
    <?php $view->partial('partials.flash', ['flash' => $flash]); ?>

    <form method="post" action="<?= e(url('/install/step/configuration')) ?>" novalidate>
      <?= csrf_field() ?>

      <h2 class="h5">Site</h2>
      <div class="field-row mt-4">
        <div class="field">
          <label class="field__label" for="site_name">Site name <span class="req">*</span></label>
          <input class="input" type="text" id="site_name" name="site_name" required
                 value="<?= e(old('site_name', (string) ($state['site_name'] ?? 'TECHBISS'))) ?>">
          <?php if ($error = error_for('site_name')): ?><span class="field__error"><?= e($error) ?></span><?php endif; ?>
        </div>
        <div class="field">
          <label class="field__label" for="timezone">Timezone <span class="req">*</span></label>
          <select class="select" id="timezone" name="timezone" required>
            <?php $current = old('timezone', (string) ($state['timezone'] ?? 'UTC')); ?>
            <?php foreach ($timezones as $zone): ?>
              <option value="<?= e($zone) ?>" <?= $current === $zone ? 'selected' : '' ?>><?= e(str_replace('_', ' ', $zone)) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <hr class="divider" style="margin-block:var(--s-6)">

      <h2 class="h5">Owner account</h2>
      <p class="small dim mt-3">Full administrative access. Sign in afterwards at <code class="mono">/admin/login</code>.</p>

      <div class="field-row mt-4">
        <div class="field">
          <label class="field__label" for="admin_name">Full name <span class="req">*</span></label>
          <input class="input" type="text" id="admin_name" name="admin_name" required autocomplete="name"
                 value="<?= e(old('admin_name', (string) ($state['admin_name'] ?? ''))) ?>">
          <?php if ($error = error_for('admin_name')): ?><span class="field__error"><?= e($error) ?></span><?php endif; ?>
        </div>
        <div class="field">
          <label class="field__label" for="admin_email">Email address <span class="req">*</span></label>
          <input class="input" type="email" id="admin_email" name="admin_email" required autocomplete="username"
                 autocapitalize="none" spellcheck="false"
                 value="<?= e(old('admin_email', (string) ($state['admin_email'] ?? ''))) ?>">
          <?php if ($error = error_for('admin_email')): ?><span class="field__error"><?= e($error) ?></span><?php endif; ?>
        </div>
      </div>

      <div class="field-row">
        <div class="field">
          <label class="field__label" for="admin_password">
            Password <span class="req">*</span>
            <span class="field__hint">Minimum 10 characters</span>
          </label>
          <input class="input" type="password" id="admin_password" name="admin_password" required
                 autocomplete="new-password" minlength="10">
          <?php if ($error = error_for('admin_password')): ?><span class="field__error"><?= e($error) ?></span><?php endif; ?>
        </div>
        <div class="field">
          <label class="field__label" for="admin_password_confirmation">Confirm password <span class="req">*</span></label>
          <input class="input" type="password" id="admin_password_confirmation" name="admin_password_confirmation"
                 required autocomplete="new-password" minlength="10">
        </div>
      </div>

      <hr class="divider" style="margin-block:var(--s-6)">

      <label class="check">
        <input type="checkbox" name="demo_data" value="1" <?= ($state['demo_data'] ?? true) ? 'checked' : '' ?>>
        <span>
          <strong style="color:var(--ink)">Include the demo catalogue and sample records</strong><br>
          Populates the marketplace, case studies, insights and portal data so every screen has
          something real to show. Leave it unchecked for a production install you will fill yourself.
        </span>
      </label>

      <div class="install-card__foot" style="margin:var(--s-6) calc(var(--s-6) * -1) calc(var(--s-6) * -1)">
        <a class="btn btn--ghost" href="<?= e(url('/install/step/' . $previous)) ?>">Back</a>
        <button class="btn btn--primary" type="submit">Review and install<?= icon('arrow-right') ?></button>
      </div>
    </form>
  </div>
</div>
<?php $view->stop(); ?>
