<?php
require_once __DIR__ . '/app/bootstrap.php';
require_installed();
require_once APP_DIR . '/partials/sections.php';

$pageTitle = setting('services.meta.title');
$pageDesc  = setting('services.meta.desc');
$activeNav = 'services';
$services  = active_services();

include APP_DIR . '/partials/head.php';

page_head(
    setting('services.hero.eyebrow'),
    setting('services.hero.line1'),
    setting('services.hero.line2'),
    setting('services.hero.lead'),
    [['index.php', 'Home'], [null, 'Services']],
    [['contact.php', 'Get a straight answer &rarr;'], ['pricing.php', 'See pricing']]
);

runner_strip();
?>

<section><div class="wrap">
  <div class="sh rv"><span class="no">The full list</span>
    <h2><?= esc(setting('services.list.heading')) ?></h2>
    <p><?= esc(setting('services.list.sub')) ?></p>
  </div>
  <div class="rows">
<?php foreach ($services as $i => $s): ?>
    <article class="row rv" id="<?= esc($s['slug']) ?>">
      <span class="n"><?= sprintf('%02d', $i + 1) ?></span>
      <div>
        <h3><?= esc($s['title']) ?></h3>
        <div class="sub"><?= esc($s['subtitle']) ?></div>
      </div>
      <div>
        <p><?= esc($s['body']) ?></p>
        <ul><?php foreach (lines($s['bullets']) as $b): ?><li><?= esc($b) ?></li><?php endforeach; ?></ul>
      </div>
    </article>
<?php endforeach; ?>
  </div>
</div></section>

<?php
statement(setting('services.statement'));
faq_block('services', 'Asked first, every time.',
    'If yours is not here, ask it directly — we answer within one business day.');
closing_cta(setting('services.cta.heading'), setting('services.cta.body'),
    ['contact.php', 'Get a straight answer &rarr;'], ['industries.php', 'See your industry']);
include APP_DIR . '/partials/footer.php';
