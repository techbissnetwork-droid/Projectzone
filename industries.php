<?php
require_once __DIR__ . '/app/bootstrap.php';
require_installed();
require_once APP_DIR . '/partials/sections.php';

$pageTitle  = setting('industries.meta.title');
$pageDesc   = setting('industries.meta.desc');
$activeNav  = 'industries';
$industries = active_industries();

include APP_DIR . '/partials/head.php';

page_head(
    setting('industries.hero.eyebrow'),
    setting('industries.hero.line1'),
    setting('industries.hero.line2'),
    setting('industries.hero.lead'),
    [['index.php', 'Home'], [null, 'Industries']],
    [['contact.php', 'Talk to us &rarr;'], ['services.php', 'See what we do']]
);

runner_strip();
?>

<section><div class="wrap">
  <div class="sh rv"><span class="no">Sectors</span>
    <h2><?= esc(setting('industries.list.heading')) ?></h2>
    <p><?= esc(setting('industries.list.sub')) ?></p>
  </div>
  <div class="grid-auto">
<?php foreach ($industries as $ind): ?>
    <article class="card rv">
      <div class="ic" aria-hidden="true"><?= esc($ind['icon']) ?></div>
      <h3><?= esc($ind['title']) ?></h3>
      <p><?= esc($ind['body']) ?></p>
      <ul><?php foreach (lines($ind['bullets']) as $b): ?><li><?= esc($b) ?></li><?php endforeach; ?></ul>
    </article>
<?php endforeach; ?>
  </div>
</div></section>

<?php statement(setting('industries.statement')); ?>

<?php $work = public_portfolio(3); if ($work): ?>
<section><div class="wrap">
  <div class="sh rv"><span class="no">In practice</span><h2>Same setup,<br>different job.</h2>
    <p>Three real businesses, and what their website actually had to do each day.</p></div>
  <div class="grid-3">
<?php foreach ($work as $p) { portfolio_card($p); } ?>
  </div>
</div></section>
<?php endif; ?>

<?php
closing_cta(setting('industries.cta.heading'), setting('industries.cta.body'),
    ['contact.php', 'Talk to us &rarr;'], ['pricing.php', 'See pricing']);
include APP_DIR . '/partials/footer.php';
