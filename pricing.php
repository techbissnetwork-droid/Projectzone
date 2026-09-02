<?php
require __DIR__ . '/app/bootstrap.php';
require_installed();
require __DIR__ . '/app/partials/sections.php';

$title = txt('pricing.meta.title', 'Pricing — TECHBISS');
$desc  = txt('pricing.meta.desc');
require __DIR__ . '/app/partials/head.php';
require __DIR__ . '/app/partials/header.php';

$crumb = 'Pricing';
$eyebrowKey = 'pricing.hero.eyebrow';
$headingKey = 'pricing.hero.heading';
$leadKey    = 'pricing.hero.lead';
require __DIR__ . '/app/partials/pagehero.php';

$build   = all("SELECT * FROM tiers WHERE grp = 'build' AND is_active = 1 ORDER BY sort ASC, id ASC");
$care    = all("SELECT * FROM tiers WHERE grp = 'care'  AND is_active = 1 ORDER BY sort ASC, id ASC");
$compare = rows('compare_rows');
$addons  = rows('addons');

/** Tier cards are identical between the two groups, so render them once. */
function tier_cards(array $tiers): void
{
    ?>
    <div class="tiers reveal">
      <?php foreach ($tiers as $t): ?>
        <div class="tier<?= $t['is_featured'] ? ' tier--hot' : '' ?>">
          <?php if ($t['tag']): ?><span class="tier__tag"><?= e($t['tag']) ?></span><?php endif; ?>
          <h3><?= e($t['name']) ?></h3>
          <p class="tier__price"><?= e($t['price']) ?><small><?= e($t['period']) ?></small></p>
          <p class="tier__desc"><?= e($t['description']) ?></p>
          <?php $f = lines($t['features']); if ($f): ?>
            <ul class="checks">
              <?php foreach ($f as $line): ?><li><?= e($line) ?></li><?php endforeach; ?>
            </ul>
          <?php endif; ?>
          <a class="btn <?= $t['is_featured'] ? 'btn--primary' : 'btn--ghost' ?> magnetic" href="<?= e(base_url('contact.php')) ?>"><?= e($t['cta_label'] ?: 'Get started') ?></a>
        </div>
      <?php endforeach; ?>
    </div>
    <?php
}
?>

<?php if ($build): ?>
<section class="sec">
  <div class="wrap">
    <div class="sec-head reveal">
      <h2><?= e(txt('pricing.build.heading')) ?></h2>
      <p><?= e(txt('pricing.build.sub')) ?></p>
    </div>
    <?php tier_cards($build); ?>
  </div>
</section>
<?php endif; ?>

<?php if ($care): ?>
<section class="sec sec--line">
  <div class="wrap">
    <div class="sec-head reveal">
      <h2><?= e(txt('pricing.care.heading')) ?></h2>
      <p><?= e(txt('pricing.care.sub')) ?></p>
    </div>
    <?php tier_cards($care); ?>
  </div>
</section>
<?php endif; ?>

<?php if ($compare): ?>
<section class="sec sec--line">
  <div class="wrap">
    <div class="sec-head reveal">
      <h2><?= e(txt('pricing.compare.heading')) ?></h2>
      <p><?= e(txt('pricing.compare.sub')) ?></p>
    </div>
    <div class="tablewrap reveal">
      <table class="cmp">
        <thead>
          <tr>
            <th scope="col">Included</th>
            <th scope="col"><?= e(txt('pricing.compare.col1')) ?></th>
            <th scope="col"><?= e(txt('pricing.compare.col2')) ?></th>
            <th scope="col"><?= e(txt('pricing.compare.col3')) ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($compare as $r): ?>
            <tr>
              <th scope="row"><?= e($r['label']) ?></th>
              <?php foreach (['col1', 'col2', 'col3'] as $c):
                  $v = (string) $r[$c];
                  // "—" and empty read as not included; anything else is a yes.
                  $cls = ($v === '' || $v === '—' || $v === '-') ? 'no' : 'yes';
              ?>
                <td class="<?= $cls ?>"><?= e($v === '' ? '—' : $v) ?></td>
              <?php endforeach; ?>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</section>
<?php endif; ?>

<?php if ($addons): ?>
<section class="sec sec--line">
  <div class="wrap">
    <div class="sec-head reveal">
      <h2><?= e(txt('pricing.addons.heading')) ?></h2>
      <p><?= e(txt('pricing.addons.sub')) ?></p>
    </div>
    <div class="bento reveal">
      <?php foreach ($addons as $a): $sz = size_class($a['size']); ?>
        <div class="card card--<?= $sz ?>" data-tilt>
          <?php if ($sz !== 'c'): ?><span class="card__ghost">$</span><?php endif; ?>
          <span class="card__no"><?= e($a['code']) ?></span>
          <div class="card__glow"></div>
          <h3 class="card__name"><?= e($a['title']) ?></h3>
          <p class="card__desc"><?= e($a['description']) ?></p>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php
section_statement('pricing.statement');
section_faq('pricing', 'pricing.faq.heading', 'pricing.faq.sub');
section_cta('pricing');
require __DIR__ . '/app/partials/footer.php';
