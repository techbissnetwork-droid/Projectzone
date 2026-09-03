<?php
/** @var App\Core\View $view @var array $snippets @var string $url @var array $meta */
$view->extends('layouts.install');
$view->start('content');
?>
<div class="install-card">
  <div class="install-card__head">
    <h1><?= e($meta['label']) ?></h1>
    <p><?= e($meta['blurb']) ?></p>
  </div>
  <div class="install-card__body">
    <?php $view->partial('install.partials.deployment', ['snippets' => $snippets, 'url' => $url]); ?>
  </div>
  <div class="install-card__foot">
    <a class="btn btn--ghost" href="<?= e(url('/install/step/install')) ?>">Back</a>
    <a class="btn btn--primary" href="<?= e(url('/')) ?>">Open the site<?= icon('arrow-right') ?></a>
  </div>
</div>
<?php $view->stop(); ?>
