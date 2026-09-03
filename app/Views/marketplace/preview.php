<?php
/** @var App\Core\View $view @var array $product */
$view->extends('layouts.app');
$view->start('content');
?>
<section class="section" style="padding-top:clamp(1.5rem,1rem + 2vw,2.5rem)">
  <div class="container container--wide">
    <div class="between">
      <div>
        <?php $view->partial('partials.crumbs', ['crumbs' => [
          'Marketplace' => '/marketplace',
          $product['name'] => '/marketplace/' . $product['slug'],
          'Preview' => '/marketplace/preview/' . $product['slug'],
        ]]); ?>
        <h1 class="h3"><?= e($product['name']) ?> preview</h1>
        <p class="small muted mt-3">The same composition rendered at three viewport widths.</p>
      </div>
      <div class="cluster">
        <a class="btn btn--ghost btn--sm" href="<?= e(url('/marketplace/' . $product['slug'])) ?>">Back to product</a>
        <a class="btn btn--primary btn--sm" href="<?= e(url('/marketplace/' . $product['slug'])) ?>#main">
          <?= money((float) $product['price']) ?> · Buy
        </a>
      </div>
    </div>

    <div class="mt-7" style="display:grid;gap:var(--s-6)">
      <?php foreach ([
        ['Desktop', '1440 × 900', 'hero', '100%'],
        ['Tablet', '834 × 1112', 'grid', 'min(680px,100%)'],
        ['Mobile', '390 × 844', 'commerce', 'min(340px,100%)'],
      ] as $i => $device): ?>
        <figure style="margin:0" data-reveal="<?= $i * 60 ?>">
          <figcaption class="between mb-3" style="margin-bottom:.75rem">
            <strong class="small"><?= e($device[0]) ?></strong>
            <span class="mono dim"><?= e($device[1]) ?></span>
          </figcaption>
          <div class="card card--flush" style="width:<?= e($device[3]) ?>;margin-inline:auto;box-shadow:var(--sh-3)">
            <?= art_mockup($product['slug'] . '-' . strtolower($device[0]), $device[2], ['label' => $product['name'] . ' on ' . $device[0]]) ?>
          </div>
        </figure>
      <?php endforeach; ?>
    </div>

    <div class="alert alert--info mt-7">
      <?= icon('info') ?>
      <div>
        <strong>Want to try it with your own content?</strong>
        <p>We will stand up a private instance loaded with your branding so you can click through the real thing before you buy. It is usually ready the same working day.</p>
      </div>
    </div>
    <div class="cluster mt-4">
      <a class="btn btn--primary" href="<?= e(url('/contact?topic=marketplace&product=' . $product['slug'])) ?>">
        Request a private demo<?= icon('arrow-right') ?>
      </a>
      <a class="btn btn--ghost" href="<?= e(url('/marketplace/' . $product['slug'])) ?>">Back to product</a>
    </div>
  </div>
</section>
<?php $view->stop(); ?>
