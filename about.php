<?php
require __DIR__ . '/app/bootstrap.php';
require_installed();
require __DIR__ . '/app/partials/sections.php';

$title = txt('about.meta.title', 'About — TECHBISS');
$desc  = txt('about.meta.desc');
require __DIR__ . '/app/partials/head.php';
require __DIR__ . '/app/partials/header.php';

$crumb = 'About';
$eyebrowKey = 'about.hero.eyebrow';
$headingKey = 'about.hero.heading';
$leadKey    = 'about.hero.lead';
require __DIR__ . '/app/partials/pagehero.php';

$rules = rows('rules');
$team  = rows('team');
?>

<section class="sec">
  <div class="wrap">
    <div class="frow reveal">
      <div>
        <p class="frow__kicker"><?= e(txt('about.why.kicker')) ?></p>
        <h3><?= e(txt('about.why.heading')) ?></h3>
        <?= paragraphs_html(txt('about.why.body')) ?>
      </div>
      <div class="frow__media">
        <?php panel_html(txt('about.why.panel_title'), txt('about.why.panel')); ?>
      </div>
    </div>
  </div>
</section>

<?php section_stats(); ?>

<?php if ($rules): ?>
<section class="sec sec--line">
  <div class="wrap">
    <div class="sec-head reveal">
      <h2><?= e(txt('about.rules.heading')) ?></h2>
      <p><?= e(txt('about.rules.sub')) ?></p>
    </div>
    <div class="bento reveal">
      <?php foreach ($rules as $r): $sz = size_class($r['size']); ?>
        <div class="card card--<?= $sz ?>" data-tilt>
          <?php if ($sz !== 'c'): ?><span class="card__ghost"><?= e($r['code']) ?></span><?php endif; ?>
          <span class="card__no"><?= e($r['code']) ?></span>
          <div class="card__glow"></div>
          <h3 class="card__name"><?= e($r['title']) ?></h3>
          <p class="card__desc"><?= e($r['body']) ?></p>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php if ($team): ?>
<section class="sec sec--line">
  <div class="wrap">
    <div class="sec-head reveal">
      <h2><?= e(txt('about.team.heading')) ?></h2>
      <p><?= e(txt('about.team.sub')) ?></p>
    </div>
    <div class="team reveal">
      <?php foreach ($team as $m): ?>
        <div class="member">
          <div class="member__av"><?= e($m['initial'] ?: initials($m['name'])) ?></div>
          <h3><?= e($m['name']) ?></h3>
          <p class="role"><?= e($m['role']) ?></p>
          <p><?= e($m['body']) ?></p>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php
section_statement('about.statement');
section_quotes('home.quotes.heading', 'home.quotes.sub');
section_cta('about');
require __DIR__ . '/app/partials/footer.php';
