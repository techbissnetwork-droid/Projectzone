<?php
/** @var App\Core\View $view @var int $status @var string $message */
$view->extends('layouts.app');
$view->start('content');
?>
<section class="hero" style="min-height:64svh;display:grid;align-items:center">
  <div class="aura"></div>
  <div class="container container--tight center">
    <span class="eyebrow eyebrow--plain" style="justify-content:center">Error <?= (int) $status ?></span>
    <h1 class="display mt-4"><?= e($message) ?></h1>
    <p class="lede mt-4" style="margin-inline:auto">
      <?php if ($status === 404): ?>
        The page you were looking for has moved or never existed. The links below cover most of what people are after.
      <?php elseif ($status === 403): ?>
        Your account does not have access to that area. If you think that is wrong, your account manager can adjust it.
      <?php elseif ($status === 419): ?>
        Your session expired while the page was open. Go back and submit the form again — nothing was lost.
      <?php else: ?>
        Something went wrong on our side. The error has been logged and we are looking at it.
      <?php endif; ?>
    </p>
    <div class="cluster mt-6" style="justify-content:center">
      <a class="btn btn--primary" href="<?= e(url('/')) ?>">Back to home</a>
      <a class="btn btn--ghost" href="<?= e(url('/marketplace')) ?>">Marketplace</a>
      <a class="btn btn--ghost" href="<?= e(url('/contact')) ?>">Contact us</a>
    </div>
  </div>
</section>
<?php $view->stop(); ?>
