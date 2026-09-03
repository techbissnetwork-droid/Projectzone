<?php
/** @var App\Core\View $view @var string $reference */
$view->extends('layouts.app');
$view->start('content');
?>
<section class="hero" style="min-height:70svh;display:grid;align-items:center">
  <div class="aura"></div>
  <div class="container container--tight center">
    <span class="feature__icon" style="margin-inline:auto;width:56px;height:56px;border-radius:16px">
      <?= icon('check', ['size' => 26, 'stroke' => 2]) ?>
    </span>
    <h1 class="h1 mt-5">Your enquiry is with us.</h1>
    <p class="lede mt-4" style="margin-inline:auto">
      An architect will read it and reply within one business day. If it is
      urgent, call the office nearest you and quote the reference below.
    </p>
    <?php if ($reference !== ''): ?>
      <div class="card mt-6" style="max-width:340px;margin-inline:auto">
        <span class="tiny dim">Your reference</span>
        <div class="mono mt-3" style="font-size:var(--t-2);color:var(--accent-2);letter-spacing:.06em"><?= e($reference) ?></div>
      </div>
    <?php endif; ?>
    <div class="cluster mt-7" style="justify-content:center">
      <a class="btn btn--primary" href="<?= e(url('/work')) ?>">Read our case studies</a>
      <a class="btn btn--ghost" href="<?= e(url('/marketplace')) ?>">Browse the Marketplace</a>
    </div>
  </div>
</section>
<?php $view->stop(); ?>
