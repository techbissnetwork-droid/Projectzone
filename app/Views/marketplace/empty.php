<?php /** @var array $filters */ ?>
<div class="empty" style="grid-column:1/-1">
  <?= icon('search') ?>
  <h3>No products match that search</h3>
  <p>
    <?php if (!empty($filters['q'])): ?>
      Nothing in the catalogue matches “<?= e($filters['q']) ?>”. Try a broader term, or clear the filters.
    <?php else: ?>
      Nothing matches those filters yet. Try widening the price range or picking a different category.
    <?php endif; ?>
  </p>
  <a class="btn btn--ghost" href="<?= e(url('/marketplace')) ?>">Show all products</a>
</div>
