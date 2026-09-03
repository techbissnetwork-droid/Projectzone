<?php
/** @var App\Core\View $view @var array $checks @var bool $satisfied @var array $meta @var string|null $next */
$view->extends('layouts.install');
$view->start('content');
$failed = array_filter($checks, static fn ($c) => $c['status'] === 'fail' && $c['required']);
?>
<div class="install-card">
  <div class="install-card__head">
    <h1><?= e($meta['label']) ?></h1>
    <p><?= e($meta['blurb']) ?> Nothing is written to disk or to a database until the final step.</p>
  </div>
  <div class="install-card__body">
    <?php if ($satisfied): ?>
      <div class="alert alert--ok">
        <?= icon('check-circle') ?>
        <div>
          <strong>This server can host the platform.</strong>
          <p>All required checks passed. Any warnings below are optional and will not block installation.</p>
        </div>
      </div>
    <?php else: ?>
      <div class="alert alert--bad">
        <?= icon('alert') ?>
        <div>
          <strong><?= count($failed) ?> required <?= count($failed) === 1 ? 'check has' : 'checks have' ?> failed.</strong>
          <p>Resolve the items marked in red, then reload this page. The hint under each one says what is needed.</p>
        </div>
      </div>
    <?php endif; ?>

    <div class="checklist mt-5">
      <?php foreach ($checks as $check): ?>
        <div class="checkrow checkrow--<?= $check['status'] === 'pass' ? 'pass' : ($check['status'] === 'warn' ? 'warn' : 'fail') ?>">
          <span class="checkrow__icon">
            <?= icon($check['status'] === 'pass' ? 'check' : ($check['status'] === 'warn' ? 'alert' : 'x')) ?>
          </span>
          <span>
            <strong><?= e($check['label']) ?></strong>
            <span><?= e($check['hint']) ?></span>
          </span>
          <span class="checkrow__value"><?= e($check['value']) ?></span>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="install-card__foot">
    <a class="btn btn--quiet" href="<?= e(url('/marketplace/installer')) ?>">What does the installer do?</a>
    <?php if ($satisfied): ?>
      <a class="btn btn--primary" href="<?= e(url('/install/step/' . $next)) ?>">Continue<?= icon('arrow-right') ?></a>
    <?php else: ?>
      <a class="btn btn--ghost" href="<?= e(url('/install/step/requirements')) ?>"><?= icon('refresh') ?>Re-check</a>
    <?php endif; ?>
  </div>
</div>
<?php $view->stop(); ?>
