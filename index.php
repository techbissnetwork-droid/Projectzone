<?php
require __DIR__ . '/app/bootstrap.php';
require_installed();
require __DIR__ . '/app/partials/sections.php';

$title = txt('home.meta.title', 'TECHBISS');
$desc  = txt('home.meta.desc');
$withLoader = true;
require __DIR__ . '/app/partials/head.php';
require __DIR__ . '/app/partials/header.php';

$services   = rows('services');
$industries = rows('industries');
$steps      = rows('steps');
$marquee    = lines(txt('home.marquee'));
$chips      = lines(txt('home.hero.chips'));

/* The offline→online story features one service, chosen in the admin. */
$featureAnchor  = txt('home.offline.service', 'offline');
$featureService = null;
foreach ($services as $s) {
    if ($s['anchor'] === $featureAnchor) {
        $featureService = $s;
        break;
    }
}
?>

<section class="hero">
  <canvas id="field" aria-hidden="true"></canvas>
  <div class="hero__scrim"></div>
  <div class="wrap hero__content">
    <p class="eyebrow"><span class="live"></span> <?= e(txt('home.hero.eyebrow')) ?></p>
    <h1 id="headline">
      <?php foreach (explode(' ', txt('home.hero.line1', 'One partner.')) as $w): ?>
        <span class="word"><span><?= e($w) ?></span></span>
      <?php endforeach; ?><br>
      <?php foreach (explode(' ', txt('home.hero.line2', 'Everything, live.')) as $w): ?>
        <span class="word"><span class="grad"><?= e($w) ?></span></span>
      <?php endforeach; ?>
    </h1>
    <p class="lead" id="leadText"><?= e(txt('home.hero.lead')) ?></p>
    <div class="hero-cta" id="heroCta">
      <a class="btn btn--primary magnetic" href="#work"><?= e(txt('home.hero.cta1')) ?></a>
      <a class="btn btn--ghost magnetic" href="<?= e(base_url('contact.php')) ?>"><?= e(txt('home.hero.cta2')) ?></a>
    </div>
    <div class="scroll-cue"><i></i>SCROLL</div>
  </div>
  <?php if ($chips): ?>
  <div class="chips" aria-hidden="true">
    <?php foreach ($chips as $c): ?><span class="chip"><b></b><?= e($c) ?></span><?php endforeach; ?>
  </div>
  <?php endif; ?>
</section>

<?php if ($marquee): ?>
<div class="marquee" aria-hidden="true">
  <div class="marquee__track">
    <?php for ($i = 0; $i < 2; $i++): foreach ($marquee as $m): ?>
      <span><?= e($m) ?></span><b>·</b>
    <?php endforeach; endfor; ?>
  </div>
</div>
<?php endif; ?>

<section class="sec" id="work">
  <div class="wrap">
    <div class="sec-head reveal">
      <h2><?= e(txt('home.services.heading')) ?></h2>
      <p><?= e(txt('home.services.sub')) ?></p>
    </div>
    <div class="bento reveal">
      <?php foreach ($services as $s): $sz = size_class($s['size']); ?>
        <a class="card card--<?= $sz ?>" data-tilt href="<?= e(base_url('services.php#' . $s['anchor'])) ?>">
          <?php if ($sz !== 'c'): ?><span class="card__ghost"><?= e($s['code']) ?></span><?php endif; ?>
          <span class="card__no"><?= e($s['code']) ?></span>
          <div class="card__glow"></div>
          <h3 class="card__name"><?= e($s['title']) ?></h3>
          <p class="card__desc"><?= e($s['summary']) ?></p>
          <span class="card__more">Explore →</span>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<?php if ($featureService): ?>
<section class="sec sec--line">
  <div class="wrap">
    <div class="sec-head reveal">
      <h2><?= e(txt('home.offline.heading')) ?></h2>
      <p><?= e(txt('home.offline.sub')) ?></p>
    </div>
    <?php feature_row($featureService, false); ?>
  </div>
</section>
<?php endif; ?>

<?php if ($steps): ?>
<section class="sec sec--line">
  <div class="wrap">
    <div class="sec-head reveal">
      <h2><?= e(txt('home.process.heading')) ?></h2>
      <p><?= e(txt('home.process.sub')) ?></p>
    </div>
    <div class="process">
      <div class="process__rail" id="rail"><i id="railFill"></i></div>
      <div class="steps" id="steps">
        <?php foreach ($steps as $st): ?>
          <div class="step" data-step>
            <span class="step__dot"></span>
            <span class="step__no"><?= e($st['code']) ?></span>
            <div class="step__body"><h3><?= e($st['title']) ?></h3><p><?= e($st['body']) ?></p></div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</section>
<?php endif; ?>

<?php if ($industries): ?>
<section class="sec sec--line">
  <div class="wrap">
    <div class="sec-head reveal">
      <h2><?= e(txt('home.industries.heading')) ?></h2>
      <p><?= e(txt('home.industries.sub')) ?></p>
    </div>
    <div class="strip reveal">
      <?php foreach ($industries as $i): ?>
        <a class="icard icard--<?= (int) $i['gradient'] ?>" href="<?= e(base_url('industries.php#' . $i['anchor'])) ?>">
          <span class="icard__no"><?= e($i['code']) ?></span>
          <h3 class="icard__name"><?= e($i['name']) ?></h3>
          <p class="icard__desc"><?= e($i['blurb']) ?></p>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php
section_stats();
section_statement('home.statement');
section_quotes('home.quotes.heading', 'home.quotes.sub');
section_cta('home');
require __DIR__ . '/app/partials/footer.php';
