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
    [['contact.php', 'Ask us a question &rarr;'], ['pricing.php', 'See prices']]
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
faq_block('services', 'The questions we get asked most.',
    'If yours is not here, just ask — we reply within one working day.');
closing_cta(setting('services.cta.heading'), setting('services.cta.body'),
    ['contact.php', 'Ask us &rarr;'], ['industries.php', 'Find your type of business']);
include APP_DIR . '/partials/footer.php';
