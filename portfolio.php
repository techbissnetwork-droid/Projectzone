<?php
require_once __DIR__ . '/app/bootstrap.php';
require_installed();
require_once APP_DIR . '/partials/sections.php';

$pageTitle = setting('portfolio.meta.title');
$pageDesc  = setting('portfolio.meta.desc');
$activeNav = 'portfolio';

/* Only public items are ever listed here. Anything marked "admin only" stays
   inside the admin area, where the team can still see it. */
$items   = public_portfolio();
$sectors = [];
foreach ($items as $it) {
    if ($it['sector'] !== '') {
        $sectors[$it['sector']] = true;
    }
}
$filter = get('sector');
if ($filter !== '' && isset($sectors[$filter])) {
    $items = array_values(array_filter($items, fn($i) => $i['sector'] === $filter));
} else {
    $filter = '';
}

include APP_DIR . '/partials/head.php';

page_head(
    setting('portfolio.hero.eyebrow'),
    setting('portfolio.hero.line1'),
    setting('portfolio.hero.line2'),
    setting('portfolio.hero.lead'),
    [['index.php', 'Home'], [null, 'Work']],
    [['contact.php', 'Start a project &rarr;'], ['marketplace.php', 'Buy one ready-made']]
);
?>

<section><div class="wrap">
<?php if (!$items && $filter === ''): ?>
  <div class="empty rv">
    <h2>Examples on the way.</h2>
    <p><?= esc(setting('portfolio.empty')) ?></p>
    <a class="pill" href="contact.php">Ask for examples &rarr;</a>
  </div>
<?php else: ?>
<?php if ($sectors): ?>
  <div class="filters rv">
    <a class="chip<?= $filter === '' ? ' on' : '' ?>" href="portfolio.php">All work</a>
<?php foreach (array_keys($sectors) as $s): ?>
    <a class="chip<?= $filter === $s ? ' on' : '' ?>" href="portfolio.php?sector=<?= urlencode($s) ?>"><?= esc($s) ?></a>
<?php endforeach; ?>
  </div>
<?php endif; ?>
  <div class="grid-3">
<?php foreach ($items as $p) { portfolio_card($p); } ?>
  </div>
<?php if (!$items): ?>
  <p class="formnote rv">Nothing in that sector yet. <a href="portfolio.php" style="color:var(--acc)">Show all work</a>.</p>
<?php endif; ?>
<?php endif; ?>
</div></section>

<?php
closing_cta('Want something like this?',
    'Tell us what your business does and what is not working. You will get a written list of what you would get, and a fixed price.',
    ['contact.php', 'Start a project &rarr;'], ['pricing.php', 'See prices']);
include APP_DIR . '/partials/footer.php';
