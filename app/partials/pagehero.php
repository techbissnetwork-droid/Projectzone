<?php
/** Expects: $crumb, $eyebrowKey, $headingKey, $leadKey. */
?>
<section class="page-hero">
  <div class="wrap">
    <p class="crumbs"><a href="<?= e(base_url('index.php')) ?>">Home</a> / <?= e($crumb) ?></p>
    <p class="eyebrow"><span class="live"></span> <?= e(txt($eyebrowKey)) ?></p>
    <h1><?= e(txt($headingKey)) ?></h1>
    <p class="lead"><?= e(txt($leadKey)) ?></p>
  </div>
</section>
