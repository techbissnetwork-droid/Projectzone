<?php
/** @var App\Core\View $view @var array $deployments @var array $licenses */
$view->extends('layouts.portal');
$view->start('content');
?>
<div class="split split--wide-left" style="gap:var(--s-4);align-items:start">
  <div class="stack" style="--flow:var(--s-4)">
    <?php if ($deployments === []): ?>
      <div class="empty">
        <?= icon('rocket') ?>
        <h3>No deployments yet</h3>
        <p>Create one on the right. You get an install token to paste into the Advanced Installer on your server.</p>
      </div>
    <?php endif; ?>

    <?php foreach ($deployments as $d): ?>
      <div class="panel">
        <div class="panel__head">
          <div style="min-width:0">
            <h3><?= e($d['site_name']) ?></h3>
            <span class="tiny dim mono"><?= e($d['target_url']) ?></span>
          </div>
          <?php $view->partial('partials.status-pill', ['value' => (string) $d['status']]); ?>
        </div>
        <div class="panel__body">
          <div class="meter-row">
            <div class="meter-row__top"><span>Installation progress</span><b><?= (int) $d['progress'] ?>%</b></div>
            <div class="progress"><i style="--fill:calc(<?= (int) $d['progress'] ?> / 100)"></i></div>
          </div>

          <div class="cluster mt-4" style="gap:var(--s-5)">
            <div><span class="tiny dim">Product</span><br><strong class="small"><?= e($d['product_name'] ?: '—') ?></strong></div>
            <div><span class="tiny dim">Environment</span><br><strong class="small"><?= e(ucfirst((string) $d['environment'])) ?></strong></div>
            <div><span class="tiny dim">Mode</span><br><strong class="small"><?= e(ucfirst((string) $d['install_mode'])) ?><?= $d['source_platform'] ? ' from ' . e($d['source_platform']) : '' ?></strong></div>
            <div><span class="tiny dim">Database</span><br><strong class="small"><?= e($d['database_driver']) ?></strong></div>
          </div>

          <?php if ($d['status'] !== 'live'): ?>
            <label class="field__label mt-5" for="tok-<?= (int) $d['id'] ?>">Install token</label>
            <div class="codeblock" style="padding:.7rem .9rem">
              <span id="tok-<?= (int) $d['id'] ?>"><?= e($d['token']) ?></span>
              <button type="button" class="copy-btn" data-copy="tok-<?= (int) $d['id'] ?>">Copy</button>
            </div>
            <p class="tiny dim mt-3">
              Upload the package to <span class="mono"><?= e($d['target_url']) ?></span>, open
              <span class="mono">/install</span> and paste this token when asked.
            </p>
          <?php else: ?>
            <div class="alert alert--ok mt-5">
              <?= icon('check-circle') ?>
              <div>
                <strong>Live since <?= e(human_date($d['completed_at'])) ?></strong>
                <p>Point monitoring at <span class="mono"><?= e(rtrim((string) $d['target_url'], '/')) ?>/health</span>.</p>
              </div>
            </div>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="panel">
    <div class="panel__head"><h3>New deployment</h3></div>
    <div class="panel__body">
      <?php if ($licenses === []): ?>
        <p class="small dim">You need an active licence before you can deploy. <a href="<?= e(url('/marketplace')) ?>" style="color:var(--accent-2)">Browse the marketplace</a>.</p>
      <?php else: ?>
        <form method="post" action="<?= e(url('/client/deployments')) ?>" novalidate>
          <?= csrf_field() ?>
          <div class="field">
            <label class="field__label" for="license_id">Licence <span class="req">*</span></label>
            <select class="select" id="license_id" name="license_id" required>
              <?php foreach ($licenses as $license): ?>
                <option value="<?= (int) $license['id'] ?>"><?= e($license['product_name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="field">
            <label class="field__label" for="site_name">Site name <span class="req">*</span></label>
            <input class="input" type="text" id="site_name" name="site_name" required
                   placeholder="Acme corporate site" value="<?= e(old('site_name')) ?>">
            <?php if ($error = error_for('site_name')): ?><span class="field__error"><?= e($error) ?></span><?php endif; ?>
          </div>
          <div class="field">
            <label class="field__label" for="target_url">Target URL <span class="req">*</span></label>
            <input class="input" type="url" id="target_url" name="target_url" required
                   placeholder="https://www.example.com" value="<?= e(old('target_url')) ?>">
            <?php if ($error = error_for('target_url')): ?><span class="field__error"><?= e($error) ?></span><?php endif; ?>
          </div>
          <div class="field">
            <label class="field__label" for="environment">Environment</label>
            <select class="select" id="environment" name="environment" required>
              <option value="production">Production</option>
              <option value="staging">Staging</option>
              <option value="development">Development</option>
            </select>
          </div>
          <div class="field">
            <label class="field__label" for="install_mode">Install mode</label>
            <select class="select" id="install_mode" name="install_mode" required>
              <option value="clean">Clean install</option>
              <option value="migrate">Migrate an existing site</option>
              <option value="upgrade">Upgrade in place</option>
            </select>
          </div>
          <div class="field">
            <label class="field__label" for="source_platform">
              Existing platform
              <span class="field__hint">If migrating</span>
            </label>
            <select class="select" id="source_platform" name="source_platform">
              <option value="">None</option>
              <?php foreach (['wordpress' => 'WordPress', 'joomla' => 'Joomla', 'drupal' => 'Drupal', 'magento' => 'Magento', 'static' => 'Static HTML', 'other' => 'Other'] as $key => $label): ?>
                <option value="<?= e($key) ?>"><?= e($label) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="field">
            <label class="field__label" for="database_driver">Database</label>
            <select class="select" id="database_driver" name="database_driver" required>
              <option value="mysql">MySQL / MariaDB</option>
              <option value="sqlite">SQLite</option>
            </select>
          </div>
          <button class="btn btn--primary btn--block" type="submit"><?= icon('rocket') ?>Create deployment</button>
        </form>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php $view->stop(); ?>
