<?php
/** One marketplace card. Expects $p (product row). */
declare(strict_types=1);
if (!class_exists('Settings')) { http_response_code(404); exit('Not found.'); }
$price = $p['sale_price'] !== null ? (float)$p['sale_price'] : (float)$p['price'];
?>
<article class="pcard reveal">
  <a class="pcard__link" href="<?= e(url('product.php?slug=' . urlencode($p['slug']))) ?>">
    <div class="pcard__media tilt">
      <?php if ($p['cover_image']): ?>
        <img src="<?= e(url($p['cover_image'])) ?>" alt="<?= e($p['title']) ?>" loading="lazy" decoding="async">
      <?php else: ?>
        <div class="pcard__ph" aria-hidden="true">
          <span class="sk sk--h"></span><span class="sk sk--t"></span>
          <div class="browser__cards"><i></i><i></i><i></i></div>
        </div>
      <?php endif; ?>
      <?php if ($p['sale_price'] !== null): ?><span class="pcard__flag">Sale</span><?php endif; ?>
    </div>
    <div class="pcard__body">
      <p class="pcard__cat mono"><?= e($p['category'] ?: 'Project') ?></p>
      <h3 class="pcard__title"><?= e($p['title']) ?></h3>
      <p class="pcard__desc"><?= e(excerpt($p['summary'], 118)) ?></p>
      <div class="pcard__foot">
        <span class="pcard__price">
          <?= e(money($price)) ?>
          <?php if ($p['sale_price'] !== null): ?><s><?= e(money($p['price'])) ?></s><?php endif; ?>
        </span>
        <span class="pcard__go" aria-hidden="true">→</span>
      </div>
    </div>
  </a>
</article>
