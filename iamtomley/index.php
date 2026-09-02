<?php
/**
 * iamtomley — public site (PHP-driven, content managed from /admin).
 * Design and markup are identical to the original static build; only the
 * content is now dynamic. Do not restyle here — edit assets/css/styles.css.
 */
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';   // pulls functions.php + session helpers

// Not set up yet → show a "down / under construction" page (503).
// (down_page() sends the security headers itself before it prints anything.)
if (!is_installed()) {
    down_page(
        'We\'re getting things ready',
        'This site is being set up and will be live soon. Please check back shortly.',
        503,
        'Set up this site',
        url('/install/')
    );
}

$S = settings();

// Maintenance mode → show a down page to visitors (logged-in admins can preview).
if (setting('maintenance_mode', '0') === '1' && !current_user()) {
    down_page(setting('brand_name', 'iamtomley') . ' — back soon', 'We\'re briefly down for maintenance and improvements. Please check back in a little while.');
}

/** media() with the shipped photo as a fallback, for the two decorative images. */
function media_or_bg(string $p): string
{
    return media($p) ?: url('/bg.jpg');
}

$projects = projects_slider();
$soldProjects = projects_sold();
$stats    = stats_all();
$games    = games_active();

// "For Sale" buy button target: chat link wins, else a pre-filled mailto.
$saleChat  = trim(setting('sale_chat_url'));
$saleEmail = trim(setting('sale_email')) ?: trim(setting('contact_email'));
/** Build the buy/enquire URL for a for-sale project. */
$buy_link = function (array $p) use ($saleChat, $saleEmail): string {
    if ($saleChat !== '') { return $saleChat; }
    if ($saleEmail !== '') {
        $subject = rawurlencode('Interested in "' . $p['title'] . '"');
        $price   = trim((string) ($p['price'] ?? ''));
        $body    = rawurlencode('Hi, I would like to buy "' . $p['title'] . '"'
                 . ($price !== '' ? ' (' . $price . ')' : '') . '. Is it still available?');
        return 'mailto:' . $saleEmail . '?subject=' . $subject . '&body=' . $body;
    }
    return '#contact';
};
$theme    = setting('theme_default', 'dark') === 'light' ? 'light' : 'dark';
$bgTheme  = bg_theme_key(setting('bg_theme', 'aurora'));
$backdrop = setting('backdrop_on', '1') === '1' ? 'on' : 'off';
$backdropImg = media_or_bg(setting('backdrop_image', '/bg.jpg'));

// Games payload for the client (grid render + filter/search/pagination).
// 'src' tells the player where the game comes from:
//   builtin → the shipped code in games-data.js, looked up by 'id'
//   html    → HTML pasted into the admin, served by game.php
//   url     → a game hosted elsewhere, shown in a frame
$gamesPayload = array_map(static function (array $g): array {
    $src = game_source_key((string) ($g['source'] ?? 'builtin'));
    $row = [
        'id'    => (int) $g['code_ref'],   // maps to gHTML()
        'row'   => (int) $g['id'],
        'title' => $g['title'],
        'cat'   => $g['cat'],
        'emoji' => $g['emoji'],
        'src'   => $src,
        'cover' => media((string) ($g['cover'] ?? '')),
    ];
    if ($src === 'url')  { $row['url']  = (string) $g['embed_url']; }
    if ($src === 'html') { $row['play'] = url('/game.php?g=' . (int) $g['id']); }
    return $row;
}, $games);

// Games linked to another site may be framed — name their origins in the CSP.
$gameFrameOrigins = [];
foreach ($games as $g) {
    if (game_source_key((string) ($g['source'] ?? 'builtin')) !== 'url') { continue; }
    $origin = url_origin((string) ($g['embed_url'] ?? ''));
    if ($origin !== '') { $gameFrameOrigins[] = $origin; }
}
security_headers($gameFrameOrigins);

// Slides grow automatically with the number of active projects (4 per slide).
$slides = chunk_slides($projects, 4);
if (empty($slides)) { $slides = [[]]; }          // no projects yet → one placeholder slide
$slideCount = count($slides);
$fillLast = setting('projects_coming_soon', '1') !== '0'; // fill last slide's empty spots

// Sold projects slide in exactly the same way, 4 to a slide.
$soldSlides = chunk_slides($soldProjects, 4);
$soldSlideCount = count($soldSlides);
$showSoldPrice = setting('sold_show_price', '1') === '1';

// How much the site moves. Anyone whose system asks for reduced motion gets
// the still version regardless — app.js and the stylesheet both honour that.
$motion = setting('motion_level', 'cinematic');
if (!in_array($motion, ['cinematic', 'subtle', 'off'], true)) { $motion = 'cinematic'; }

/** A single "coming soon" placeholder card. */
function coming_soon_card(): string
{
    return '<article class="card dim"><span class="project-tag dim">Soon</span>'
        . '<h3 class="project-name">Coming Soon</h3>'
        . '<p class="project-desc">Exciting project in the works…</p>'
        . '<a class="project-link disabled" href="#" aria-disabled="true">Under Construction</a></article>';
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= e($theme) ?>" data-bg="<?= e($bgTheme) ?>" data-backdrop="<?= e($backdrop) ?>" data-motion="<?= e($motion) ?>">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />

  <title><?= e(setting('seo_title', 'iamtomley')) ?></title>
  <meta name="description" content="<?= e(setting('seo_description')) ?>" />
  <meta name="author" content="<?= e(setting('brand_name', 'iamtomley')) ?>" />
  <?php $canonical = canonical_url(); ?>
  <link rel="canonical" href="<?= e($canonical) ?>" />
  <?php if (setting('seo_noindex', '0') === '1'): ?>
  <meta name="robots" content="noindex, nofollow" />
  <?php endif; ?>
  <meta name="theme-color" content="#05060d" id="theme-color-meta" />

  <meta property="og:type" content="website" />
  <meta property="og:title" content="<?= e(setting('seo_title')) ?>" />
  <meta property="og:description" content="<?= e(setting('seo_description')) ?>" />
  <meta property="og:url" content="<?= e($canonical) ?>" />
  <meta property="og:image" content="<?= e(site_url('/favicon/web-app-manifest-512x512.png')) ?>" />
  <meta name="twitter:card" content="summary_large_image" />
  <meta name="twitter:title" content="<?= e(setting('seo_title')) ?>" />
  <meta name="twitter:description" content="<?= e(setting('seo_description')) ?>" />
  <meta name="twitter:image" content="<?= e(site_url('/favicon/web-app-manifest-512x512.png')) ?>" />

  <link rel="icon" type="image/png" href="<?= e(url('/favicon/favicon-96x96.png')) ?>" sizes="96x96" />
  <link rel="icon" type="image/svg+xml" href="<?= e(url('/favicon/favicon.svg')) ?>" />
  <link rel="shortcut icon" href="<?= e(url('/favicon/favicon.ico')) ?>" />
  <link rel="apple-touch-icon" sizes="180x180" href="<?= e(url('/favicon/apple-touch-icon.png')) ?>" />
  <meta name="apple-mobile-web-app-title" content="<?= e(setting('brand_name', 'iamtomley')) ?>" />
  <link rel="manifest" href="<?= e(url('/favicon/site.webmanifest')) ?>" />

  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet" />

  <link rel="stylesheet" href="<?= e(asset_url('/assets/css/styles.css')) ?>" />

  <script type="application/ld+json">
  {
    "@context": "https://schema.org",
    "@type": "Person",
    "name": <?= json_encode(setting('brand_name', 'iamtomley')) ?>,
    "url": <?= json_encode($canonical) ?>,
    "jobTitle": "Developer",
    "email": <?= json_encode(setting('contact_email')) ?>,
    "description": <?= json_encode(setting('seo_description')) ?>
  }
  </script>

  <?php
    // Search engines get the project list as structured data, so the work shows
    // up as more than one line of page text.
    $ld = [];
    foreach (array_merge($projects, $soldProjects) as $p) {
        $item = [
            '@type'    => 'CreativeWork',
            'position' => count($ld) + 1,
            'name'     => (string) $p['title'],
        ];
        if (trim((string) $p['description']) !== '') { $item['description'] = (string) $p['description']; }
        if (preg_match('#^https?://#i', (string) $p['url'])) { $item['url'] = (string) $p['url']; }
        $pImg = trim((string) ($p['image'] ?? ''));
        if ($pImg !== '') { $item['image'] = str_starts_with($pImg, '/') ? site_url($pImg) : $pImg; }
        if (trim((string) $p['tag']) !== '') { $item['genre'] = (string) $p['tag']; }
        $ld[] = $item;
    }
    if ($ld):
  ?>
  <script type="application/ld+json">
<?= json_encode([
      '@context'        => 'https://schema.org',
      '@type'           => 'ItemList',
      'name'            => 'Projects by ' . setting('brand_name', 'iamtomley'),
      'numberOfItems'   => count($ld),
      'itemListElement' => $ld,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?>

  </script>
  <?php endif; ?>
</head>
<body>

  <a href="#projects" class="skip-link">Skip to content</a>

  <!-- Lifts away once, on the first visit of a session. app.js removes it
       entirely afterwards so it can never sit over the page. -->
  <div class="intro" id="intro" aria-hidden="true">
    <span class="intro-inner">
      <img class="intro-logo" src="<?= e(url('/favicon/favicon.svg')) ?>" alt="" width="52" height="52" />
      <span class="intro-name"><?= e(setting('brand_name', 'iamtomley')) ?></span>
    </span>
    <span class="intro-bar"><i class="intro-fill" id="introFill"></i></span>
  </div>

  <div class="bg" aria-hidden="true" style="--backdrop-image:url('<?= e($backdropImg) ?>')">
    <span class="bg-photo"></span>
    <span class="bg-grad"></span>
    <span class="bg-grad2"></span>
    <span class="bg-fx"></span>
    <span class="blob blob-1"></span>
    <span class="blob blob-2"></span>
    <span class="blob blob-3"></span>
  </div>
  <div class="grain" aria-hidden="true"></div>

  <div class="cursor-dot" id="cursorDot" aria-hidden="true"></div>
  <div class="cursor-ring" id="cursorRing" aria-hidden="true"><span class="cursor-label" id="cursorLabel"></span></div>
  <div class="scroll-progress" id="scrollProgress" aria-hidden="true"></div>

  <header class="header" id="header">
    <nav class="nav" aria-label="Primary">
      <a class="logo" href="#top" aria-label="<?= e(setting('brand_name')) ?> home"><span class="logo-mark" aria-hidden="true"></span><?= e(setting('brand_name', 'iamtomley')) ?></a>
      <div class="nav-links">
        <a href="#projects">Projects</a>
        <?php if (!empty($soldProjects)): ?><a href="#sold">Sold</a><?php endif; ?>
        <a href="#games">Games</a>
        <a href="#contact">Contact</a>
      </div>
      <div class="nav-actions">
        <button class="icon-btn" id="bgToggle" type="button" aria-label="Change background theme" aria-haspopup="true" aria-expanded="false">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="13.5" cy="6.5" r="2.5"/><circle cx="17.5" cy="10.5" r="2.5"/><circle cx="8.5" cy="7.5" r="2.5"/><circle cx="6.5" cy="12.5" r="2.5"/><path d="M12 2a10 10 0 1 0 0 20 2 2 0 0 0 2-2 2 2 0 0 1 2-2h1a4 4 0 0 0 4-4 8 8 0 0 0-9-8z"/></svg>
        </button>
        <button class="icon-btn theme-toggle" id="themeToggle" type="button" aria-label="Toggle color theme">
          <svg class="moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
          <svg class="sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="4"/><path d="M12 2v2m0 16v2M4.93 4.93l1.41 1.41m11.32 11.32 1.41 1.41M2 12h2m16 0h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"/></svg>
        </button>
        <button class="icon-btn nav-toggle" id="navToggle" type="button" aria-label="Open menu" aria-controls="mobileMenu">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="7" x2="21" y2="7"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="17" x2="21" y2="17"/></svg>
        </button>
      </div>
    </nav>
  </header>

  <div class="theme-panel" id="themePanel" role="menu" aria-label="Background theme">
    <h4>Background theme</h4>
    <div class="theme-grid">
      <?php foreach (bg_themes() as $key => [$label, $swatch]): ?>
        <button class="theme-opt" type="button" role="menuitemradio" aria-checked="<?= $key === $bgTheme ? 'true' : 'false' ?>" data-bg="<?= e($key) ?>">
          <span class="theme-swatch" style="background:<?= $swatch ?>"></span><?= e($label) ?>
        </button>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="mobile-menu" id="mobileMenu">
    <button class="icon-btn mm-close" id="mmClose" type="button" aria-label="Close menu">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
    </button>
    <a href="#projects">Projects</a>
    <?php if (!empty($soldProjects)): ?><a href="#sold">Sold</a><?php endif; ?>
    <a href="#games">Games</a>
    <a href="#contact">Contact</a>
  </div>

  <main id="top">

    <!-- Hero -->
    <section class="section hero wrap" aria-label="Intro">
      <div class="hero-left">
        <span class="eyebrow reveal"><span class="eyebrow-dot" aria-hidden="true"></span><?= e(setting('hero_eyebrow')) ?></span>
        <h1 class="hero-title reveal">
          <span class="line"><span class="line-in"><?= e(setting('hero_title_1')) ?></span></span>
          <span class="line"><span class="line-in grad-text"><?= e(setting('hero_title_2')) ?></span></span>
        </h1>
        <p class="hero-sub reveal"><?= e(setting('hero_sub')) ?></p>
        <div class="hero-ctas reveal">
          <a class="btn btn-primary btn-lg" href="<?= e(setting('hero_cta1_url', '#projects')) ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M9 21V9"/></svg>
            <?= e(setting('hero_cta1_label', 'View Projects')) ?>
          </a>
          <a class="btn btn-ghost btn-lg" href="<?= e(setting('hero_cta2_url', '#games')) ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="6" width="20" height="12" rx="2"/><path d="M6 12h4m-2-2v4M15 11h.01M17 13h.01"/></svg>
            <?= e(setting('hero_cta2_label', 'Play Games')) ?>
          </a>
        </div>
      </div>
      <div class="hero-right reveal" data-reveal="scale">
        <div class="hero-photo" id="heroPhoto">
          <img src="<?= e(media_or_bg(setting('hero_photo', '/bg.jpg'))) ?>" alt="Portrait of <?= e(setting('brand_name')) ?>" loading="eager" />
          <div class="hero-badge">
            <div class="badge-label"><?= e(setting('hero_badge_label', 'Status')) ?></div>
            <div class="badge-value"><?= e(setting('hero_badge_value')) ?></div>
          </div>
        </div>
      </div>
      <div class="scroll-hint" aria-hidden="true"><span class="scroll-line"></span>Scroll</div>
    </section>

    <!-- Projects -->
    <section id="projects" class="section wrap">
      <div class="section-head reveal">
        <span class="section-num">01 / Projects</span>
        <div class="section-line"></div>
        <h2 class="section-title">Things I've built</h2>
      </div>

      <div class="stats reveal">
        <?php foreach ($stats as $s): ?>
          <div class="stat">
            <div class="stat-num"<?= $s['target'] !== '' ? ' data-target="' . e($s['target']) . '"' : '' ?><?= $s['suffix'] !== '' ? ' data-suffix="' . e($s['suffix']) . '"' : '' ?>><?= e($s['value']) ?></div>
            <div class="stat-label"><?= e($s['label']) ?></div>
          </div>
        <?php endforeach; ?>
      </div>

      <div data-slider>
      <div class="slider-top reveal">
        <div class="slider-counter">Slide <b class="slider-active">1</b> / <b class="slider-total"><?= (int) $slideCount ?></b></div>
        <div class="slider-btns">
          <button class="slider-btn slider-prev" type="button" aria-label="Previous projects"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg></button>
          <button class="slider-btn slider-next" type="button" aria-label="Next projects"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg></button>
        </div>
      </div>

      <div class="slider-view">
        <div class="slider-track">
          <?php foreach ($slides as $idx => $slide): ?>
          <div class="slide"><div class="slide-grid">
            <?php foreach ($slide as $p):
              $ts = $p['tag_style'] ? ' ' . e($p['tag_style']) : '';
              $st = project_status_key($p['status'] ?? 'live');
              $badge = project_statuses()[$st][1] ?? '';
              $img = media((string) ($p['image'] ?? ''));
              // A card that leads somewhere is clickable across its whole face.
              // Sold projects have nowhere to send anyone, so they stay inert.
              $cardHref = ($st !== 'sold' && $p['url'] !== '' && $p['url'] !== '#') ? (string) $p['url'] : '';
            ?>
            <article class="card status-<?= e($st) ?><?= $img !== '' ? ' has-shot' : '' ?>"<?= $cardHref !== '' ? ' data-href="' . e($cardHref) . '"' : '' ?>>
              <?php if ($badge !== ''): ?><span class="project-ribbon ribbon-<?= e($st) ?>"><?= e($badge) ?></span><?php endif; ?>
              <?php if ($img !== ''): ?><span class="project-shot"><img src="<?= e($img) ?>" alt="<?= e($p['title']) ?> logo" loading="lazy" decoding="async" /></span><?php endif; ?>
              <?php if ($p['tag'] !== ''): ?><span class="project-tag<?= $ts ?>"><?= e($p['tag']) ?></span><?php endif; ?>
              <h3 class="project-name"><?= e($p['title']) ?></h3>
              <p class="project-desc"><?= e($p['description']) ?></p>
              <?php $price = trim((string) ($p['price'] ?? '')); ?>
              <?php if ($st === 'for_sale' && $price !== ''): ?><div class="project-price"><span class="price-label">Price</span><span class="price-val"><?= e($price) ?></span></div><?php endif; ?>
              <?php if ($st === 'sold'): ?>
              <span class="project-link disabled" aria-disabled="true">Sold</span>
              <?php elseif ($st === 'for_sale'): ?>
              <div class="buy-row">
                <?php $bl = $buy_link($p); $chat = ($saleChat !== ''); ?>
                <a class="project-link buy" href="<?= e($bl) ?>"<?= $chat ? ' target="_blank" rel="noopener"' : '' ?>><?= $chat ? 'Buy / Enquire' : 'Buy via email' ?> <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14"/><path d="M12 5l7 7-7 7"/></svg></a>
                <?php if ($p['url'] !== '' && $p['url'] !== '#'): ?>
                <a class="project-link ghost" href="<?= e($p['url']) ?>" target="_blank" rel="noopener">Preview</a>
                <?php endif; ?>
              </div>
              <?php elseif ($p['url'] !== '' && $p['url'] !== '#'): ?>
              <a class="project-link" href="<?= e($p['url']) ?>" target="_blank" rel="noopener"><?= e($p['link_label'] ?: 'Visit Site') ?> <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M7 17L17 7"/><path d="M7 7h10v10"/></svg></a>
              <?php endif; ?>
            </article>
            <?php endforeach; ?>
            <?php
              // Fill only the LAST slide's empty spots so the grid stays balanced.
              if ($fillLast && $idx === $slideCount - 1) {
                  $have = count($slide);
                  $fillN = $have === 0 ? 4 : ((4 - ($have % 4)) % 4);
                  for ($j = 0; $j < $fillN; $j++) { echo coming_soon_card(); }
              }
            ?>
          </div></div>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="slider-progress"><div class="slider-progress-fill" style="width:<?= $slideCount > 0 ? round(100 / $slideCount, 3) : 100 ?>%"></div></div>
      </div>
    </section>

    <?php
      // Decorative ticker: the names of the actual work, running past.
      $tickerItems = array_values(array_filter(array_map(
          static fn($p) => trim((string) $p['title']),
          array_merge($projects, $soldProjects)
      )));
      if (count($tickerItems) >= 2):
    ?>
    <div class="marquee" aria-hidden="true">
      <div class="marquee-track">
        <?php for ($pass = 0; $pass < 2; $pass++): ?>
          <?php foreach ($tickerItems as $t): ?>
            <span class="marquee-item"><?= e($t) ?></span><span class="marquee-dot">◆</span>
          <?php endforeach; ?>
        <?php endfor; ?>
      </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($soldProjects)): ?>
    <!-- Sold -->
    <section id="sold" class="section wrap">
      <div class="section-head reveal">
        <span class="section-num">✔ Sold</span>
        <div class="section-line"></div>
        <h2 class="section-title">Sold &amp; delivered</h2>
      </div>
      <div data-slider>
      <div class="slider-top reveal">
        <div class="slider-counter">Slide <b class="slider-active">1</b> / <b class="slider-total"><?= (int) $soldSlideCount ?></b></div>
        <div class="slider-btns">
          <button class="slider-btn slider-prev" type="button" aria-label="Previous sold projects"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg></button>
          <button class="slider-btn slider-next" type="button" aria-label="Next sold projects"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg></button>
        </div>
      </div>

      <div class="slider-view">
        <div class="slider-track">
          <?php foreach ($soldSlides as $slide): ?>
          <div class="slide"><div class="slide-grid">
            <?php foreach ($slide as $p):
              $ts = $p['tag_style'] ? ' ' . e($p['tag_style']) : '';
              $img = media((string) ($p['image'] ?? ''));
              $sp = trim((string) ($p['price'] ?? ''));
            ?>
            <article class="card status-sold<?= $img !== '' ? ' has-shot' : '' ?>">
              <span class="project-ribbon ribbon-sold">SOLD</span>
              <?php if ($img !== ''): ?><span class="project-shot"><img src="<?= e($img) ?>" alt="<?= e($p['title']) ?> logo" loading="lazy" decoding="async" /></span><?php endif; ?>
              <?php if ($p['tag'] !== ''): ?><span class="project-tag<?= $ts ?>"><?= e($p['tag']) ?></span><?php endif; ?>
              <h3 class="project-name"><?= e($p['title']) ?></h3>
              <p class="project-desc"><?= e($p['description']) ?></p>
              <?php if ($showSoldPrice && $sp !== ''): ?><div class="project-price sold"><span class="price-label">Sold for</span><span class="price-val"><?= e($sp) ?></span></div><?php endif; ?>
              <span class="project-link disabled" aria-disabled="true">Sold</span>
            </article>
            <?php endforeach; ?>
          </div></div>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="slider-progress"><div class="slider-progress-fill" style="width:<?= $soldSlideCount > 0 ? round(100 / $soldSlideCount, 3) : 100 ?>%"></div></div>
      </div>
    </section>
    <?php endif; ?>

    <!-- Games -->
    <section id="games" class="section wrap">
      <div class="section-head reveal">
        <span class="section-num">02 / Games</span>
        <div class="section-line"></div>
        <h2 class="section-title">Play instantly</h2>
      </div>

      <?php
        $cats = array_values(array_unique(array_map(fn($g) => $g['cat'], $games)));
        $catCount = count($cats);
      ?>
      <div class="games-meta reveal">
        <span>🎮 <b><?= count($games) ?></b> Games</span><span class="sep">·</span>
        <span>🗂 <b><?= $catCount ?></b> Categories</span><span class="sep">·</span>
        <span>📥 <b>0</b> Downloads</span><span class="sep">·</span>
        <span>✨ <b>∞</b> Fun</span>
      </div>

      <div class="games-controls reveal">
        <div class="filters" role="group" aria-label="Filter games by category">
          <button class="filter-btn active" data-cat="all" aria-pressed="true">All</button>
          <?php foreach (game_categories() as $ck => $cl): ?>
            <?php if (in_array($ck, $cats, true)): ?>
            <button class="filter-btn" data-cat="<?= e($ck) ?>" aria-pressed="false"><?= e($cl) ?></button>
            <?php endif; ?>
          <?php endforeach; ?>
        </div>
        <div class="search">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
          <input type="text" id="gameSearch" placeholder="Search games…" autocomplete="off" aria-label="Search games" />
        </div>
      </div>

      <!-- The games slide exactly like the projects. The track is filled by
           app.js, which rebuilds it whenever the filter or search changes. -->
      <div id="gamesSlider" data-slider="manual">
        <div class="slider-top reveal">
          <div class="slider-counter">Slide <b class="slider-active">1</b> / <b class="slider-total">1</b></div>
          <div class="slider-btns">
            <button class="slider-btn slider-prev" type="button" aria-label="Previous games"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg></button>
            <button class="slider-btn slider-next" type="button" aria-label="Next games"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg></button>
          </div>
        </div>
        <div class="slider-view">
          <div class="slider-track" id="gamesTrack" aria-live="polite"></div>
        </div>
        <div class="slider-progress"><div class="slider-progress-fill" style="width:100%"></div></div>
      </div>
    </section>

    <!-- Contact -->
    <section id="contact" class="section wrap">
      <div class="section-head reveal">
        <span class="section-num">03 / Contact</span>
        <div class="section-line"></div>
      </div>
      <div class="contact-box reveal" data-reveal="scale">
        <div class="contact-inner">
          <h2 class="contact-title"><?= nl2br(e(setting('contact_title'))) ?></h2>
          <p class="contact-sub"><?= nl2br(e(setting('contact_sub'))) ?></p>
          <a class="email-link" href="mailto:<?= e(setting('contact_email')) ?>" data-copy="<?= e(setting('contact_email')) ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,12 2,6"/></svg>
            <?= e(setting('contact_email')) ?>
          </a>
        </div>
      </div>
    </section>

  </main>

  <footer class="footer wrap">
    <div class="footer-logo"><?= e(setting('brand_name', 'iamtomley')) ?></div>
    <div class="footer-copy"><?= e(setting('footer_text')) ?></div>
  </footer>

  <!-- Game modal -->
  <div class="modal" id="gameModal" role="dialog" aria-modal="true" aria-labelledby="modalName">
    <div class="modal-box">
      <div class="modal-head">
        <span class="modal-emoji" id="modalEmoji" aria-hidden="true">🎮</span>
        <div>
          <div class="modal-name" id="modalName">Game</div>
          <div class="modal-cat" id="modalCat">arcade</div>
        </div>
        <a class="icon-btn" id="modalOpen" href="#" target="_blank" rel="noopener" aria-label="Open this game in a new tab" hidden>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 3h6v6"/><path d="M10 14L21 3"/><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/></svg>
        </a>
        <button class="icon-btn" id="modalFs" type="button" aria-label="Toggle fullscreen">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 3H5a2 2 0 0 0-2 2v3m18 0V5a2 2 0 0 0-2-2h-3m0 18h3a2 2 0 0 0 2-2v-3M3 16v3a2 2 0 0 0 2 2h3"/></svg>
        </button>
        <button class="icon-btn" id="modalClose" type="button" aria-label="Close game">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
      </div>
      <div class="modal-body">
        <div class="modal-loader" id="modalLoader">
          <div class="spinner"></div>
          <div class="spinner-label">Loading game…</div>
        </div>
        <iframe id="gameFrame" title="Game" allowfullscreen allow="fullscreen; autoplay"></iframe>
      </div>
    </div>
  </div>

  <div class="toasts" id="toasts" aria-live="polite"></div>
  <button class="to-top" id="toTop" type="button" aria-label="Back to top">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><polyline points="18 15 12 9 6 15"/></svg>
  </button>

  <script>
    window.GAMES = <?= json_encode($gamesPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    window.GAME_CATS = <?= json_encode(game_categories(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  </script>
  <script src="<?= e(asset_url('/assets/js/games-data.js')) ?>" defer></script>
  <script src="<?= e(asset_url('/assets/js/app.js')) ?>" defer></script>
</body>
</html>
