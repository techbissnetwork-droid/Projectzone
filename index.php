<?php
declare(strict_types=1);
require_once __DIR__ . '/app/bootstrap.php';

$services = Database::all('SELECT * FROM services WHERE is_active = 1 ORDER BY sort_order, id LIMIT 9');
$stats    = Content::items('stat');
$featured = Settings::bool('show_portfolio', true)
    ? Database::all("SELECT * FROM portfolio WHERE visibility = 'public' ORDER BY is_featured DESC, sort_order, completed_on DESC LIMIT 4")
    : [];
$products = Settings::bool('show_marketplace', true)
    ? Database::all('SELECT * FROM products WHERE is_active = 1 ORDER BY is_featured DESC, sort_order LIMIT 3')
    : [];

$PAGE_TITLE = '';
$WITH_VIZ = true;
$CANONICAL = url();
require __DIR__ . '/partials/public_header.php';
?>
<!-- HERO -->
<section class="hero" id="hero" data-theme="deep">
  <div class="hero__field" aria-hidden="true"><div class="hero__aurora"></div><div class="hero__grid"></div></div>

  <div class="hero__viz" id="viz" aria-hidden="true">
    <canvas class="viz__canvas" id="vizCanvas"></canvas>
    <div class="viz__nodes" id="vizNodes">
      <div class="vnode vnode--core" data-node="core" data-ring="0">
        <span class="vnode__label"><?= e(Settings::get('site_name', 'TECHBISS')) ?></span><span class="vnode__sub">CORE</span></div>
      <?php
      /* The orbit uses the service names, so it always matches what you sell. */
      $orbit = array_slice(array_map(static fn($s) => $s['title'], $services), 0, 9);
      if (count($orbit) < 4) { $orbit = ['Business','Domain','Website','App','Hosting','Email','Security','Payments','Growth']; }
      foreach ($orbit as $n): ?>
        <div class="vnode" data-node="<?= e(slugify($n)) ?>" data-ring="1"><span class="vnode__label"><?= e($n) ?></span></div>
      <?php endforeach; ?>
    </div>
    <div class="viz__hud">
      <div class="hud__row"><i class="dot dot--live"></i><span>ecosystem</span><b id="hudNode">synced</b></div>
      <div class="hud__row"><span>uptime</span><b>99.98%</b></div>
      <div class="hud__row"><span>edge</span><b id="hudLat">18 ms</b></div>
    </div>
  </div>

  <div class="shell hero__inner">
    <?php if (Settings::get('hero_eyebrow')): ?>
      <p class="eyebrow reveal" data-d="0"><i class="dot dot--live"></i> <?= e(Settings::get('hero_eyebrow')) ?></p>
    <?php endif; ?>
    <h1 class="hero__title">
      <?php foreach (['hero_title_a', 'hero_title_b'] as $i => $k):
        if (Settings::get($k) === '') { continue; } ?>
        <span class="mask" data-d="<?= $i + 1 ?>"><span><?= e(Settings::get($k)) ?></span></span>
      <?php endforeach; ?>
      <?php if (Settings::get('hero_title_c')): ?>
        <span class="mask" data-d="3"><span class="grad"><?= e(Settings::get('hero_title_c')) ?></span></span>
      <?php endif; ?>
    </h1>
    <?php if (Settings::get('hero_lede')): ?>
      <p class="hero__lede reveal" data-d="4"><?= e(Settings::get('hero_lede')) ?></p>
    <?php endif; ?>
    <div class="hero__actions reveal" data-d="5">
      <a class="btn btn--primary magnetic" href="<?= e(url('contact.php')) ?>"><?= e(Settings::get('hero_cta_primary')) ?> <span class="btn__arrow">→</span></a>
      <a class="btn btn--ghost magnetic" href="<?= e(url('services.php')) ?>"><?= e(Settings::get('hero_cta_secondary')) ?></a>
    </div>
    <?php if ($stats): ?>
      <dl class="hero__stats reveal" data-d="6">
        <?php foreach ($stats as $s): ?>
          <div><dt><?= e($s['label']) ?></dt>
            <?php $num = (string)$s['title']; $isNum = (bool)preg_match('/^\d+$/', $num); ?>
            <dd class="<?= $isNum ? '' : 'is-text' ?>"<?= $isNum ? ' data-count="' . e($num) . '"' : '' ?>><?= e($num) ?></dd></div>
        <?php endforeach; ?>
      </dl>
    <?php endif; ?>
  </div>

  <a class="hero__scroll" href="#next" aria-label="Scroll to next section">
    <span class="hero__scrollLine" aria-hidden="true"></span><span>Scroll</span></a>
</section>

<span id="next"></span>
<?php
/* Sections render in the order set in Admin → Home page. */
$secNum = 1;
foreach (Content::sectionOrder() as $section):
    $secNum++;
    switch ($section):

    case 'journey':
        require __DIR__ . '/partials/section_shift.php';
        break;

    case 'services':
        if (!$services) { break; } ?>
        <section class="svcs" id="services">
          <div class="shell">
            <header class="sec__head">
              <p class="eyebrow reveal"><span class="num"><?= sprintf('%02d', $secNum) ?></span> <?= e(Settings::get('services_eyebrow')) ?></p>
              <h2 class="sec__title reveal"><?= nl2br(e(Settings::get('services_title')), false) ?></h2>
              <p class="sec__lede reveal"><?= e(Settings::get('services_lede')) ?></p>
            </header>
            <div class="svcs__grid" id="svcGrid"><?php require __DIR__ . '/partials/service_cards.php'; ?></div>
            <p class="svcs__foot reveal"><a class="link" href="<?= e(url('services.php')) ?>">See every service in detail <span aria-hidden="true">→</span></a></p>
          </div>
        </section>
        <?php break;

    case 'arch':
        require __DIR__ . '/partials/section_arch.php';
        break;

    case 'process':
        require __DIR__ . '/partials/section_process.php';
        break;

    case 'work':
        if (!$featured) { break; } ?>
        <section class="work" id="work" data-theme="deep">
          <div class="shell">
            <header class="sec__head sec__head--split">
              <div>
                <p class="eyebrow reveal"><span class="num"><?= sprintf('%02d', $secNum) ?></span> <?= e(Settings::get('work_eyebrow')) ?></p>
                <h2 class="sec__title reveal"><?= nl2br(e(Settings::get('work_title')), false) ?></h2>
              </div>
              <p class="sec__lede reveal"><?= e(Settings::get('work_lede')) ?>
                <a class="link" href="<?= e(url('portfolio.php')) ?>">See all work <span aria-hidden="true">→</span></a></p>
            </header>
            <div class="cards">
              <?php foreach ($featured as $i => $w): ?>
                <article class="wcard reveal<?= $i === 0 ? ' wcard--lead' : '' ?>">
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
                      <h3 class="wcard__title"><?= e($w['title']) ?></h3>
                      <p class="wcard__desc"><?= e(excerpt($w['summary'], $i === 0 ? 190 : 120)) ?></p>
                      <?php if ($w['tech']): ?>
                        <div class="tags"><?php foreach (array_slice(csv_list($w['tech']), 0, 4) as $t): ?><span><?= e($t) ?></span><?php endforeach; ?></div>
                      <?php endif; ?>
                    </div>
                  </a>
                </article>
              <?php endforeach; ?>
            </div>
          </div>
        </section>
        <?php break;

    case 'transform':
        require __DIR__ . '/partials/section_transform.php';
        break;

    case 'pillars':
        require __DIR__ . '/partials/section_pillars.php';
        break;

    case 'marketplace':
        if (!$products) { break; } ?>
        <section class="mkt-strip">
          <div class="shell">
            <header class="sec__head sec__head--split">
              <div>
                <p class="eyebrow reveal"><span class="num"><?= sprintf('%02d', $secNum) ?></span> <?= e(Settings::get('mkt_eyebrow')) ?></p>
                <h2 class="sec__title reveal"><?= nl2br(e(Settings::get('mkt_title')), false) ?></h2>
              </div>
              <p class="sec__lede reveal"><?= e(Settings::get('mkt_lede')) ?>
                <a class="link" href="<?= e(url('marketplace.php')) ?>">Browse the marketplace <span aria-hidden="true">→</span></a></p>
            </header>
            <div class="pgrid">
              <?php foreach ($products as $p): require __DIR__ . '/partials/product_card.php'; endforeach; ?>
            </div>
          </div>
        </section>
        <?php break;

    case 'quote':
        if (Settings::get('quote') === '') { break; } ?>
        <section class="trust" data-theme="deep">
          <div class="shell"><p class="trust__quote reveal">“<?= e(Settings::get('quote')) ?>”</p></div>
        </section>
        <?php break;

    endswitch;
endforeach;

$secNum++;
require __DIR__ . '/partials/section_cta.php';
require __DIR__ . '/partials/public_footer.php';
