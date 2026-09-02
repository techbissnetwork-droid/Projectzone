<?php
require __DIR__ . '/app/bootstrap.php';
require_installed();
require __DIR__ . '/app/partials/sections.php';

$title = txt('industries.meta.title', 'Industries — TECHBISS');
$desc  = txt('industries.meta.desc');
require __DIR__ . '/app/partials/head.php';
require __DIR__ . '/app/partials/header.php';

$crumb = 'Industries';
$eyebrowKey = 'industries.hero.eyebrow';
$headingKey = 'industries.hero.heading';
$leadKey    = 'industries.hero.lead';
require __DIR__ . '/app/partials/pagehero.php';

$industries = rows('industries');
?>

<section class="sec">
  <div class="wrap">
    <div class="sec-head reveal">
      <h2><?= e(txt('industries.strip.heading')) ?></h2>
      <p><?= e(txt('industries.strip.sub')) ?></p>
    </div>
    <div class="strip reveal">
      <?php foreach ($industries as $i): ?>
        <a class="icard icard--<?= (int) $i['gradient'] ?>" href="#<?= e($i['anchor']) ?>">
          <span class="icard__no"><?= e($i['code']) ?></span>
          <h3 class="icard__name"><?= e($i['name']) ?></h3>
          <p class="icard__desc"><?= e($i['blurb']) ?></p>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="sec sec--line">
  <div class="wrap">
    <div class="sec-head reveal">
      <h2><?= e(txt('industries.detail.heading')) ?></h2>
      <p><?= e(txt('industries.detail.sub')) ?></p>
    </div>
    <div class="sgrid reveal">
      <?php foreach ($industries as $i): ?>
        <div class="sgrid-item" id="<?= e($i['anchor']) ?>">
          <?php if ($i['kicker']): ?><p class="frow__kicker"><?= e($i['kicker']) ?></p><?php endif; ?>
          <h3><?= e($i['heading'] ?: $i['name']) ?></h3>
          <?php if ($i['summary']): ?><p><?= e($i['summary']) ?></p><?php endif; ?>
          <?php $bul = lines($i['bullets']); if ($bul): ?>
            <ul class="checks">
              <?php foreach ($bul as $b): ?><li><?= e($b) ?></li><?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<?php
section_statement('industries.statement');
section_cta('industries');
require __DIR__ . '/app/partials/footer.php';
