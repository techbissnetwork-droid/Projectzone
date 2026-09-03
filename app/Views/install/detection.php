<?php
/** @var App\Core\View $view @var array $scan @var array $state @var array $meta @var string|null $previous */
$view->extends('layouts.install');
$view->start('content');
$recommended = $scan['recommended_mode'];
$selected = (string) ($state['mode'] ?? $recommended);
$platformNames = ['techbiss' => 'a previous TECHBISS installation', 'wordpress' => 'WordPress', 'joomla' => 'Joomla',
                  'drupal' => 'Drupal', 'laravel' => 'Laravel', 'magento' => 'Magento', 'static' => 'a static HTML site'];
?>
<div class="install-card">
  <div class="install-card__head">
    <h1><?= e($meta['label']) ?></h1>
    <p><?= e($meta['blurb']) ?></p>
  </div>
  <div class="install-card__body">
    <div id="scan-result">
      <?php $view->partial('install.partials.scan-result', ['scan' => $scan]); ?>
    </div>

    <form method="post" action="<?= e(url('/install/scan')) ?>" class="mt-4"
          data-async-form data-async-target="scan-result">
      <?= csrf_field() ?>
      <button class="btn btn--ghost btn--sm" type="submit"><?= icon('refresh') ?>Re-scan</button>
    </form>

    <form method="post" action="<?= e(url('/install/step/detection')) ?>" class="mt-6">
      <?= csrf_field() ?>
      <fieldset style="border:0;padding:0;margin:0">
        <legend class="field__label" style="padding:0;margin-bottom:.6rem">How should we proceed?</legend>
        <div class="mode-grid">
          <label class="mode">
            <input type="radio" name="mode" value="clean" <?= $selected === 'clean' ? 'checked' : '' ?>>
            <span class="mode__box">
              <?= icon('spark') ?>
              <strong>Clean install<?= $recommended === 'clean' ? ' · recommended' : '' ?></strong>
              <span>Create the schema fresh and seed the catalogue. Existing content is not imported.</span>
            </span>
          </label>
          <label class="mode">
            <input type="radio" name="mode" value="upgrade" <?= $selected === 'upgrade' ? 'checked' : '' ?>>
            <span class="mode__box">
              <?= icon('refresh') ?>
              <strong>Upgrade in place<?= $recommended === 'upgrade' ? ' · recommended' : '' ?></strong>
              <span>Apply any missing schema to an existing TECHBISS database. All records are preserved.</span>
            </span>
          </label>
          <label class="mode">
            <input type="radio" name="mode" value="migrate" <?= $selected === 'migrate' ? 'checked' : '' ?>>
            <span class="mode__box">
              <?= icon('upload') ?>
              <strong>Migrate<?= $recommended === 'migrate' ? ' · recommended' : '' ?></strong>
              <span>Install fresh, then import content from another platform and rewrite its URLs.</span>
            </span>
          </label>
        </div>
      </fieldset>

      <?php if ($scan['platform'] !== null): ?>
        <div class="alert alert--warn mt-5">
          <?= icon('alert') ?>
          <div>
            <strong>We found <?= e($platformNames[$scan['platform']] ?? $scan['platform']) ?>.</strong>
            <p>
              A clean install will not delete those files, but it will not import them either.
              Back up anything you cannot lose before continuing — that is true of every install mode.
            </p>
          </div>
        </div>
      <?php endif; ?>

      <div class="install-card__foot" style="margin:var(--s-6) calc(var(--s-6) * -1) calc(var(--s-6) * -1)">
        <a class="btn btn--ghost" href="<?= e(url('/install/step/' . $previous)) ?>">Back</a>
        <button class="btn btn--primary" type="submit">Continue<?= icon('arrow-right') ?></button>
      </div>
    </form>
  </div>
</div>
<?php $view->stop(); ?>
