<?php
/** @var App\Core\Seo $seo @var App\Core\View $view @var array $stepKeys @var array $steps @var string $current @var int $index */
$icons = ['requirements' => 'gauge', 'environment' => 'globe', 'database' => 'database', 'detection' => 'search',
          'migration' => 'refresh', 'configuration' => 'settings', 'install' => 'terminal', 'deploy' => 'rocket'];
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($seo->title()) ?></title>
<meta name="description" content="<?= e($seo->description()) ?>">
<meta name="robots" content="noindex, nofollow">
<meta name="theme-color" content="#06080d">
<link rel="icon" href="<?= e(asset('favicon.svg')) ?>" type="image/svg+xml">
<?= font_preloads() ?>
<style><?= inline_css('assets/css/critical.css') ?></style>
<link rel="preload" as="style" href="<?= e(asset('assets/css/main.css')) ?>" onload="this.onload=null;this.rel='stylesheet'">
<link rel="preload" as="style" href="<?= e(asset('assets/css/motion.css')) ?>" onload="this.onload=null;this.rel='stylesheet'">
<noscript>
<link rel="stylesheet" href="<?= e(asset('assets/css/main.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('assets/css/motion.css')) ?>">
</noscript>
<script>(function(){var d=document.documentElement;d.classList.add('js');try{var t=localStorage.getItem('tb-theme');if(t==='light'||t==='dark'){d.setAttribute('data-theme',t)}}catch(e){}})();</script>
</head>
<body>
<div class="install">
  <header class="install__top">
    <div class="between" style="max-width:1080px;margin-inline:auto">
      <a class="logo" href="<?= e(url('/')) ?>">
        <span class="logo__mark"><?= icon('layers', ['stroke' => 1.8]) ?></span>
        <span class="logo__text">TECHBISS<small>ADVANCED INSTALLER</small></span>
      </a>
      <div class="cluster">
        <span class="badge badge--neutral">Step <?= $index + 1 ?> of <?= count($stepKeys) ?></span>
        <button type="button" class="theme-toggle" data-theme-toggle aria-label="Switch theme">
          <?= icon('moon', ['class' => 'i-moon']) ?><?= icon('sun', ['class' => 'i-sun']) ?>
        </button>
      </div>
    </div>
  </header>

  <div class="install__body">
    <nav class="wizard-steps" aria-label="Installation steps">
      <?php $n = 1; foreach ($steps as $key => $step): ?>
        <?php
          $state = $key === $current ? 'is-current' : ($n <= $index ? 'is-done' : '');
        ?>
        <span class="wstep <?= $state ?>" <?= $key === $current ? 'aria-current="step"' : '' ?>>
          <span class="wstep__num"><?= $n <= $index ? '✓' : $n ?></span>
          <?= e($step['label']) ?>
        </span>
      <?php $n++; endforeach; ?>
    </nav>

    <div><?= $view->section('content') ?></div>
  </div>
</div>
<script src="<?= e(asset('assets/js/app.js')) ?>" defer></script>
</body>
</html>
