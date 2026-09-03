<?php
/** @var App\Core\View $view @var array $product */
$view->extends('layouts.amp');
$view->start('content');
?>
<section class="hero">
  <div class="wrap">
    <span class="tag"><?= e(App\Models\Product::CATEGORIES[$product['category']] ?? $product['category']) ?></span>
    <span class="tag">v<?= e($product['version']) ?></span>
    <h1 style="margin-top:12px"><?= e($product['name']) ?></h1>
    <p class="lede"><?= e($product['tagline']) ?></p>
    <p class="price" style="margin-top:18px"><?= e(money((float) $product['price'], (string) $product['currency'])) ?> <span style="font-size:13px;color:var(--ink3);font-weight:400">standard licence</span></p>
    <a class="btn btn-primary" href="<?= e(url('/marketplace/' . $product['slug'])) ?>">View and buy</a>
  </div>
</section>

<section class="sec">
  <div class="wrap">
    <h2>About</h2>
    <div class="prose">
      <?php foreach (preg_split('/\n\n+/', (string) $product['description']) ?: [] as $paragraph): ?>
        <p><?= e(trim($paragraph)) ?></p>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="sec">
  <div class="wrap">
    <h2>What is included</h2>
    <ul>
      <?php foreach ($product['features'] as $feature): ?>
        <li style="color:var(--ink2);font-size:14px;margin-bottom:8px">— <?= e($feature) ?></li>
      <?php endforeach; ?>
    </ul>
    <h2 style="margin-top:26px">Specifications</h2>
    <?php foreach ($product['specs'] as $label => $value): ?>
      <div class="meta" style="margin-top:8px"><strong style="color:var(--ink2)"><?= e($label) ?>:</strong> <?= e($value) ?></div>
    <?php endforeach; ?>
    <a class="btn btn-primary full" href="<?= e(url('/marketplace/' . $product['slug'])) ?>">Buy this product</a>
  </div>
</section>
<?php $view->stop(); ?>
