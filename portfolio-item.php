<?php
declare(strict_types=1);
require_once __DIR__ . '/app/bootstrap.php';
if (!Settings::bool('show_portfolio', true)) {
    http_response_code(404);
    exit('Not found.');
}

$slug = (string)($_GET['slug'] ?? '');
$w = Database::one("SELECT * FROM portfolio WHERE slug = :s AND visibility = 'public'", ['s' => $slug]);
if (!$w) {
    http_response_code(404);
    $PAGE_TITLE = 'Not found';
    require __DIR__ . '/partials/public_header.php';
    echo '<section class="pagehead" data-theme="deep"><div class="shell">'
       . '<h1 class="pagehead__title">This project is not public.</h1>'
       . '<p class="pagehead__lede">It may have been unpublished. <a class="link" href="' . e(url('portfolio.php')) . '">See all work →</a></p>'
       . '</div></section>';
    require __DIR__ . '/partials/public_footer.php';
    exit;
}

$more = Database::all("SELECT * FROM portfolio WHERE visibility = 'public' AND id <> :id ORDER BY is_featured DESC, sort_order LIMIT 3", ['id' => (int)$w['id']]);

$PAGE_TITLE = $w['title'];
$META_DESC  = excerpt($w['summary'] ?: $w['body'], 158);
$CANONICAL  = url('portfolio-item.php?slug=' . urlencode($w['slug']));
$OG_IMAGE   = $w['cover_image'] ? url($w['cover_image']) : null;
require __DIR__ . '/partials/public_header.php';
?>
<article class="detail" data-theme="deep">
  <div class="shell">
    <p class="eyebrow reveal"><a class="link" href="<?= e(url('portfolio.php')) ?>">← Work</a>
      <?php if ($w['category']): ?><span class="num">/</span> <?= e($w['category']) ?><?php endif; ?></p>
    <h1 class="detail__title reveal"><?= e($w['title']) ?></h1>
    <?php if ($w['summary']): ?><p class="detail__lede reveal"><?= e($w['summary']) ?></p><?php endif; ?>

    <?php if ($w['cover_image']): ?>
      <figure class="detail__hero reveal tilt">
        <img src="<?= e(url($w['cover_image'])) ?>" alt="<?= e($w['title']) ?>" loading="eager" decoding="async">
      </figure>
    <?php endif; ?>

    <div class="detail__grid">
      <div class="detail__body reveal">
        <?php if ($w['body']): ?><p><?= enl($w['body']) ?></p><?php endif; ?>
        <?php $results = lines($w['results']); if ($results): ?>
          <h2 class="detail__h2">Outcome</h2>
          <ul class="reslist">
            <?php foreach ($results as $r):
              $parts = array_map('trim', explode('·', $r, 2)); ?>
              <li><b><?= e($parts[0]) ?></b><span><?= e($parts[1] ?? '') ?></span></li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>

      <aside class="detail__meta reveal">
        <dl>
          <?php if ($w['client_name']): ?><div><dt>Client</dt><dd><?= e($w['client_name']) ?></dd></div><?php endif; ?>
          <?php if ($w['category']): ?><div><dt>Category</dt><dd><?= e($w['category']) ?></dd></div><?php endif; ?>
          <?php if ($w['completed_on']): ?><div><dt>Completed</dt><dd><?= e(fdate($w['completed_on'], 'F Y')) ?></dd></div><?php endif; ?>
        </dl>
        <?php $tech = csv_list($w['tech']); if ($tech): ?>
          <h3>Built with</h3>
          <div class="tags"><?php foreach ($tech as $t): ?><span><?= e($t) ?></span><?php endforeach; ?></div>
        <?php endif; ?>
        <?php if ($w['live_url']): ?>
          <a class="btn btn--ghost btn--block magnetic" href="<?= e($w['live_url']) ?>" target="_blank" rel="noopener noreferrer">Visit the site <span class="btn__arrow">↗</span></a>
        <?php endif; ?>
        <a class="btn btn--primary btn--block magnetic" href="<?= e(url('contact.php')) ?>">Discuss a build like this <span class="btn__arrow">→</span></a>
      </aside>
    </div>
  </div>
</article>

<?php if ($more): ?>
<section class="listing">
  <div class="shell">
    <header class="sec__head"><h2 class="sec__title reveal">More work</h2></header>
    <div class="cards">
      <?php foreach ($more as $m): ?>
        <article class="wcard reveal">
          <a class="wcard__link" href="<?= e(url('portfolio-item.php?slug=' . urlencode($m['slug']))) ?>">
            <div class="wcard__media tilt">
              <?php if ($m['cover_image']): ?>
                <img src="<?= e(url($m['cover_image'])) ?>" alt="<?= e($m['title']) ?>" loading="lazy" decoding="async">
              <?php else: ?>
                <div class="wcard__ph" aria-hidden="true"><span class="sk sk--h"></span><span class="sk sk--t"></span>
                  <div class="browser__cards"><i></i><i></i><i></i></div></div>
              <?php endif; ?>
            </div>
            <div class="wcard__body">
              <p class="case__cat mono"><?= e($m['category'] ?: 'Project') ?></p>
              <h3 class="wcard__title"><?= e($m['title']) ?></h3>
            </div>
          </a>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>
<?php require __DIR__ . '/partials/public_footer.php'; ?>
