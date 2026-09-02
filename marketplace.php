<?php
declare(strict_types=1);
require_once __DIR__ . '/app/bootstrap.php';
if (!Settings::bool('show_marketplace', true)) {
    http_response_code(404);
    exit('Not found.');
}

$cat    = trim((string)($_GET['category'] ?? ''));
$where  = 'is_active = 1';
$params = [];
if ($cat !== '') {
    $where .= ' AND category = :c';
    $params['c'] = $cat;
}
$products = Database::all("SELECT * FROM products WHERE $where ORDER BY is_featured DESC, sort_order, id DESC", $params);
$cats = Database::all("SELECT DISTINCT category FROM products WHERE is_active = 1 AND category IS NOT NULL AND category <> '' ORDER BY category");

$PAGE_TITLE = 'Marketplace';
$META_DESC  = 'Premade projects ready to buy and launch — complete source code, admin panel and installation, from ' . Settings::get('site_name') . '.';
$CANONICAL  = url('marketplace.php');
require __DIR__ . '/partials/public_header.php';
?>
<section class="pagehead" data-theme="deep">
  <div class="shell">
    <p class="eyebrow reveal">Marketplace</p>
    <h1 class="pagehead__title reveal">Premade projects,<br><span class="grad">yours today.</span></h1>
    <p class="pagehead__lede reveal">Complete systems we have already built and tested. Buy the source, and we install and brand it for your business.</p>
  </div>
</section>

<section class="listing">
  <div class="shell">
    <?php if ($cats): ?>
      <nav class="chipnav reveal" aria-label="Filter by category">
        <a href="<?= e(url('marketplace.php')) ?>" class="<?= $cat === '' ? 'on' : '' ?>">Everything</a>
        <?php foreach ($cats as $c): ?>
          <a href="?category=<?= urlencode($c['category']) ?>" class="<?= $cat === $c['category'] ? 'on' : '' ?>"><?= e($c['category']) ?></a>
        <?php endforeach; ?>
      </nav>
    <?php endif; ?>

    <?php if (!$products): ?>
      <div class="blank reveal">
        <h2>Nothing listed right now</h2>
        <p>We are preparing the next batch of ready-to-launch projects.</p>
        <a class="btn btn--ghost magnetic" href="<?= e(url('contact.php')) ?>">Ask what is coming</a>
      </div>
    <?php else: ?>
      <?php /* Up to four products get a row of their own; more fall back to three. */
         $pcols = count($products) <= 4 ? max(2, count($products)) : 3; ?>
      <div class="pgrid" style="--n:<?= $pcols ?>">
        <?php foreach ($products as $p): require __DIR__ . '/partials/product_card.php'; endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php require __DIR__ . '/partials/section_cta.php'; ?>
<?php require __DIR__ . '/partials/public_footer.php'; ?>
