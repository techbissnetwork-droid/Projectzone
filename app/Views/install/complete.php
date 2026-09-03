<?php
/** @var App\Core\View $view @var array $log @var array $snippets @var string $url @var string $adminEmail */
$view->extends('layouts.install');
$view->start('content');
?>
<div class="install-card">
  <div class="install-card__head" style="background:linear-gradient(150deg,var(--accent-soft),transparent 70%),var(--surface-2)">
    <span class="feature__icon" style="width:48px;height:48px;border-radius:14px;margin-bottom:var(--s-4)">
      <?= icon('check', ['size' => 22, 'stroke' => 2]) ?>
    </span>
    <h1>Installation complete</h1>
    <p>
      Your platform is live at <strong><?= e($url) ?></strong> and the installer has locked itself.
      Sign in to the admin console with <strong><?= e($adminEmail) ?></strong>.
    </p>
  </div>
  <div class="install-card__body">
    <div class="cluster">
      <a class="btn btn--primary" href="<?= e(url('/admin/login')) ?>"><?= icon('shield') ?>Admin console</a>
      <a class="btn btn--ghost" href="<?= e(url('/')) ?>">View the site</a>
      <a class="btn btn--ghost" href="<?= e(url('/marketplace')) ?>">Marketplace</a>
    </div>

    <?php if ($log !== []): ?>
      <h2 class="h5 mt-7">Install log</h2>
      <div class="install-log mt-3">
        <?php foreach ($log as $line): ?>
          <?php [$level, $message] = array_pad(explode('|', $line, 2), 2, ''); ?>
          <div>
            <span class="t <?= e($level === 'err' ? 'err' : ($level === 'warn' ? 'warn' : 'ok')) ?>">
              <?= $level === 'err' ? '✗' : ($level === 'warn' ? '!' : '✓') ?>
            </span>
            <span><?= e($message) ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <hr class="divider" style="margin-block:var(--s-7)">
    <?php $view->partial('install.partials.deployment', ['snippets' => $snippets, 'url' => $url]); ?>
  </div>
  <div class="install-card__foot">
    <span class="small dim">Re-running the installer requires creating <code class="mono">storage/install.unlock</code>.</span>
    <a class="btn btn--primary" href="<?= e(url('/admin/login')) ?>">Sign in<?= icon('arrow-right') ?></a>
  </div>
</div>
<?php $view->stop(); ?>
