<?php
/** One portfolio case study. Private items 404 here — they are admin-only. */
require_once __DIR__ . '/app/bootstrap.php';
require_installed();
require_once APP_DIR . '/partials/sections.php';

$item = portfolio_by_slug(get('slug'));

if (!$item) {
    http_response_code(404);
    $pageTitle = 'Not found — ' . setting('site.name');
    $pageDesc  = '';
    $activeNav = 'portfolio';
    include APP_DIR . '/partials/head.php';
    page_head('404', 'That project', 'is not here.',
        'It may have been taken down, or the address may be wrong. The rest of our work is still up.',
        [['index.php', 'Home'], ['portfolio.php', 'Work'], [null, 'Not found']],
        [['portfolio.php', 'See all work &rarr;']]);
    include APP_DIR . '/partials/footer.php';
    exit;
}

$pageTitle = $item['title'] . ' — ' . setting('site.name');
$pageDesc  = $item['summary'];
$activeNav = 'portfolio';
$more      = array_values(array_filter(public_portfolio(4), fn($p) => $p['id'] !== $item['id']));

include APP_DIR . '/partials/head.php';
?>

<section class="phead"><div class="wrap">
  <div class="crumbs">
    <a href="index.php">Home</a> <span>/</span>
    <a href="portfolio.php">Work</a> <span>/</span>
    <span><?= esc($item['title']) ?></span>
  </div>
  <span class="badge"><i aria-hidden="true"></i><?= esc($item['sector'] ?: 'Project') ?><?php
    if ($item['completed_on']) { echo ' &middot; ' . esc(date('M Y', strtotime($item['completed_on']))); } ?></span>
  <h1><span class="chrome"><?= esc($item['title']) ?></span></h1>
  <p><?= esc($item['summary']) ?></p>
<?php if ($item['live_url']): ?>
  <div class="hr-acts">
    <a class="pill" href="<?= esc($item['live_url']) ?>" rel="noopener" target="_blank">Visit the live site &rarr;</a>
  </div>
<?php endif; ?>
</div></section>

<section><div class="wrap">
  <div class="article">
    <div class="body">
<?php if ($item['cover_image']): ?>
      <div class="shot big"><img src="<?= esc($item['cover_image']) ?>" alt="<?= esc($item['title']) ?>"></div>
<?php endif; ?>
      <?= esc_para($item['body']) ?>
    </div>
    <aside class="side">
      <div class="factbox">
        <h4>Project</h4>
<?php if ($item['client_name']): ?>
        <div class="fact"><span>Client</span><strong><?= esc($item['client_name']) ?></strong></div>
<?php endif; ?>
<?php if ($item['sector']): ?>
        <div class="fact"><span>Sector</span><strong><?= esc($item['sector']) ?></strong></div>
<?php endif; ?>
<?php if ($item['completed_on']): ?>
        <div class="fact"><span>Completed</span><strong><?= esc(date_human($item['completed_on'])) ?></strong></div>
<?php endif; ?>
      </div>
<?php if (lines($item['services_used'])): ?>
      <div class="factbox">
        <h4>What we did</h4>
        <ul class="ticks">
<?php foreach (lines($item['services_used']) as $s): ?>
          <li><?= esc($s) ?></li>
<?php endforeach; ?>
        </ul>
      </div>
<?php endif; ?>
<?php if (lines($item['tech'])): ?>
      <div class="factbox">
        <h4>Built with</h4>
        <div class="tagrow">
<?php foreach (lines($item['tech']) as $t): ?><b><?= esc($t) ?></b><?php endforeach; ?>
        </div>
      </div>
<?php endif; ?>
      <a class="pill lg" href="contact.php" style="width:100%">Start something like this</a>
    </aside>
  </div>
</div></section>

<?php if ($more): ?>
<section><div class="wrap">
  <div class="sh rv"><span class="no">More work</span><h2>Other things<br>we run.</h2></div>
  <div class="grid-3">
<?php foreach (array_slice($more, 0, 3) as $p) { portfolio_card($p); } ?>
  </div>
</div></section>
<?php endif; ?>

<?php
closing_cta('Want one of these for your business?',
    'One conversation, no script. Describe what you run and what is missing.',
    ['contact.php', 'Talk to us &rarr;'], ['portfolio.php', 'See all work']);
include APP_DIR . '/partials/footer.php';
