<?php
/** @var array $item */
$tags = json_decode((string) ($item['tags'] ?? '[]'), true) ?: [];
?>
<article class="product spotlight edge-light">
  <a class="product__thumb" href="<?= e(url('/marketplace/' . $item['slug'])) ?>" aria-label="<?= e($item['name']) ?>">
    <?php if (!empty($item['featured'])): ?>
      <span class="product__tags"><span class="badge">Featured</span></span>
    <?php endif; ?>
    <?= art_mockup($item['slug'], (string) ($item['layout'] ?? 'auto'), ['label' => $item['name'] . ' preview']) ?>
  </a>
  <div class="product__body">
    <h3 class="product__title"><a href="<?= e(url('/marketplace/' . $item['slug'])) ?>"><?= e($item['name']) ?></a></h3>
    <p class="product__desc"><?= e($item['tagline']) ?></p>
    <div class="product__meta">
      <span><?= icon('gauge') ?><?= (int) $item['lighthouse'] ?> Lighthouse</span>
      <span><?= icon('download') ?><?= number_format((int) $item['sales_count']) ?></span>
      <span class="stars">
        <?= icon('star', ['fill' => true]) ?><?= number_format((float) $item['rating'], 1) ?>
        <span>(<?= number_format((int) $item['reviews_count']) ?>)</span>
      </span>
    </div>
  </div>
  <div class="product__foot">
    <span class="product__price">
      <?php if (!empty($item['compare_price'])): ?><del><?= money((float) $item['compare_price']) ?></del><?php endif; ?>
      <?= money((float) $item['price']) ?><small>/ licence</small>
    </span>
    <a class="btn btn--sm btn--ghost" href="<?= e(url('/marketplace/' . $item['slug'])) ?>">View<?= icon('arrow-right') ?></a>
  </div>
</article>
