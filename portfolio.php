<?php
declare(strict_types=1);
require_once __DIR__ . '/app/bootstrap.php';
if (!Settings::bool('show_portfolio', true)) {
    http_response_code(404);
    exit('Not found.');
}

$cat   = trim((string)($_GET['category'] ?? ''));
$where = "visibility = 'public'";
$params = [];
if ($cat !== '') {
    $where .= ' AND category = :c';
    $params['c'] = $cat;
}
$items = Database::all("SELECT * FROM portfolio WHERE $where ORDER BY is_featured DESC, sort_order, completed_on DESC, id DESC", $params);
$cats  = Database::all("SELECT DISTINCT category FROM portfolio WHERE visibility = 'public' AND category IS NOT NULL AND category <> '' ORDER BY category");

$PAGE_TITLE = 'Work';
$META_DESC  = 'Delivered projects — websites, apps, portals and infrastructure built by ' . Settings::get('site_name') . '.';
$CANONICAL  = url('portfolio.php');
require __DIR__ . '/partials/public_header.php';
?>
<section class="pagehead" data-theme="deep">
  <div class="shell">
    <p class="eyebrow reveal">Selected work</p>
    <h1 class="pagehead__title reveal">Businesses that moved<br>their operations <span class="grad">online.</span></h1>
    <p class="pagehead__lede reveal">Delivered projects, with the scope we built and the outcome it produced.</p>
  </div>
</section>

<section class="listing">
  <div class="shell">
    <?php if ($cats): ?>
      <nav class="chipnav reveal" aria-label="Filter by category">
        <a href="<?= e(url('portfolio.php')) ?>" class="<?= $cat === '' ? 'on' : '' ?>">All work</a>
        <?php foreach ($cats as $c): ?>
          <a href="?category=<?= urlencode($c['category']) ?>" class="<?= $cat === $c['category'] ? 'on' : '' ?>"><?= e($c['category']) ?></a>
        <?php endforeach; ?>
      </nav>
    <?php endif; ?>

    <?php if (!$items): ?>
      <div class="blank reveal">
        <h2>Nothing published yet</h2>
        <p>Completed projects appear here once they are made public.</p>
        <a class="btn btn--ghost magnetic" href="<?= e(url('contact.php')) ?>">Talk about your project</a>
      </div>
    <?php else: ?>
      <div class="cards">
        <?php foreach ($items as $i => $w): ?>
          <article class="wcard reveal<?= $i % 5 === 0 ? ' wcard--lead' : '' ?>">
            <a class="wcard__link" href="<?= e(url('portfolio-item.php?slug=' . urlencode($w['slug']))) ?>">
              <div class="wcard__media tilt">
                <?php if ($w['cover_image']): ?>
                  <img src="<?= e(url($w['cover_image'])) ?>" alt="<?= e($w['title']) ?>" loading="lazy" decoding="async">
                <?php else: ?>
                  <div class="wcard__ph" aria-hidden="true"><span class="sk sk--h"></span><span class="sk sk--t"></span>
                    <div class="browser__cards"><i></i><i></i><i></i></div></div>
                <?php endif; ?>
              </div>
              <div class="wcard__body">
                <p class="case__cat mono"><?= e($w['category'] ?: 'Project') ?></p>
                <h2 class="wcard__title"><?= e($w['title']) ?></h2>
                <p class="wcard__desc"><?= e(excerpt($w['summary'], $i % 5 === 0 ? 190 : 120)) ?></p>
                <?php if ($w['tech']): ?>
                  <div class="tags"><?php foreach (array_slice(csv_list($w['tech']), 0, 4) as $t): ?><span><?= e($t) ?></span><?php endforeach; ?></div>
                <?php endif; ?>
              </div>
            </a>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php require __DIR__ . '/partials/section_cta.php'; ?>
<?php require __DIR__ . '/partials/public_footer.php'; ?>
