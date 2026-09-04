<?php
require_once __DIR__ . '/includes/db.php';
require_installed('install/');

$products = db()->query('SELECT id, name, category AS cat, icon, price, pricing_type, rating, tagline, description AS `desc`, specs_json FROM products ORDER BY sort_order ASC')->fetchAll();
foreach ($products as &$p) {
    $p['price'] = (float)$p['price'];
    $p['rating'] = (float)$p['rating'];
    $p['specs'] = json_decode($p['specs_json'], true) ?: [];
    unset($p['specs_json']);
}
unset($p);

$siteSettings = [
    'heroHeadlineMain' => get_setting('hero_headline_main', 'Your business, finally'),
    'heroHeadlineAccent' => get_setting('hero_headline_accent', 'open online.'),
    'heroSubheadline' => get_setting('hero_subheadline', 'TECHBISS builds your website or app from the ground up, then handles the domain, hosting, SSL, business email and app store publishing — so you launch with everything already working, and people can actually find you.'),
    'contactEmail' => get_setting('contact_email', 'hello@techbiss.com'),
    'contactPhone' => get_setting('contact_phone', '+1 (415) 555-0148'),
    'defaultTheme' => get_setting('default_theme', 'auto'),
];
$siteTagline = get_setting('site_tagline', 'We help offline businesses get online — website or app, domain, hosting, email and everything after launch.');

$seoTitle = get_setting('seo_title', 'TECHBISS — Get your business online');
$metaDescription = get_setting('meta_description', 'TECHBISS helps offline businesses get online: websites, apps, domains, hosting, email and everything after launch.');
$faviconPath = get_setting('favicon_path', 'assets/favicon.ico');
$socialImagePath = get_setting('social_image_path', 'assets/social-default.png');
$baseUrl = defined('SITE_URL') ? rtrim(SITE_URL, '/') : '';
$canonicalUrl = $baseUrl !== '' ? $baseUrl . '/' : '';
$socialImageUrl = $baseUrl !== '' ? $baseUrl . '/' . ltrim($socialImagePath, '/') : $socialImagePath;
?>
<!doctype html>
<html lang="en"<?= palette_attr() ?>>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($seoTitle) ?></title>
<meta name="description" content="<?= e($metaDescription) ?>">
<?php if ($canonicalUrl !== ''): ?><link rel="canonical" href="<?= e($canonicalUrl) ?>"><?php endif; ?>
<link rel="icon" href="<?= e($faviconPath) ?>">
<link rel="apple-touch-icon" href="assets/apple-touch-icon.png">
<meta property="og:type" content="website">
<meta property="og:title" content="<?= e($seoTitle) ?>">
<meta property="og:description" content="<?= e($metaDescription) ?>">
<meta property="og:image" content="<?= e($socialImageUrl) ?>">
<?php if ($canonicalUrl !== ''): ?><meta property="og:url" content="<?= e($canonicalUrl) ?>"><?php endif; ?>
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?= e($seoTitle) ?>">
<meta name="twitter:description" content="<?= e($metaDescription) ?>">
<meta name="twitter:image" content="<?= e($socialImageUrl) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/style.css?v=<?= @filemtime(__DIR__ . '/assets/style.css') ?: '1' ?>">
</head>
<body>

<svg width="0" height="0" style="position:absolute" aria-hidden="true" focusable="false">
  <defs>
    <linearGradient id="logoGrad" x1="3" y1="3" x2="21" y2="21" gradientUnits="userSpaceOnUse">
      <stop offset="0%" style="stop-color:var(--accent-1)"/>
      <stop offset="100%" style="stop-color:var(--accent-3)"/>
    </linearGradient>
  </defs>
</svg>

<a href="#main" class="skip-link">Skip to content</a>

<div class="splash" id="splash" aria-hidden="true">
  <div class="splash-inner">
    <svg class="splash-logo" viewBox="0 0 24 24" fill="none" aria-hidden="true">
      <g>
        <rect class="s-square" x="3" y="3" width="18" height="18" rx="6" fill="url(#logoGrad)"/>
        <rect class="s-bar" x="7.5" y="7.5" width="9" height="2.6" rx="1.3" fill="#fff2ea"/>
        <rect class="s-stem" x="10.7" y="7.5" width="2.6" height="9.5" rx="1.3" fill="#fff2ea"/>
      </g>
    </svg>
    <div class="splash-word">techbiss</div>
    <div class="splash-hint">Websites &amp; apps, fully handled.</div>
  </div>
</div>

<div class="blob-field" id="blobField" aria-hidden="true"></div>
<div class="route-wipe" id="routeWipe" aria-hidden="true">
  <svg viewBox="0 0 200 200"><path id="wipePath" fill="var(--accent-1)" d="M100,20 C150,20 180,60 180,100 C180,150 150,180 100,180 C50,180 20,150 20,100 C20,50 50,20 100,20 Z"/></svg>
  <div class="route-wipe-logo"><svg viewBox="0 0 24 24" fill="none"><rect x="3" y="3" width="18" height="18" rx="6" fill="#fff2ea"/><rect x="7.5" y="7.5" width="9" height="2.6" rx="1.3" fill="var(--accent-1)"/><rect x="10.7" y="7.5" width="2.6" height="9.5" rx="1.3" fill="var(--accent-1)"/></svg></div>
</div>

<header class="site-header">
  <div class="container nav-wrap">
    <a href="#/" class="logo" aria-label="TECHBISS home">
      <?= logo_mark_html(true) ?>
      <b>techbiss</b>
    </a>
    <nav class="nav-links" id="navLinks" aria-label="Primary">
      <div class="nav-blob" id="navBlob"></div>
    </nav>
    <div class="nav-actions">
      <button class="theme-toggle" id="themeToggle" aria-label="Toggle dark mode" aria-pressed="false">
        <span class="theme-icon-wrap">
          <svg id="themeIconSun" class="theme-icon sun" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="4.2" stroke="currentColor" stroke-width="1.8"/><path d="M12 2v2M12 20v2M4.2 4.2l1.4 1.4M18.4 18.4l1.4 1.4M2 12h2M20 12h2M4.2 19.8l1.4-1.4M18.4 5.6l1.4-1.4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
          <svg id="themeIconMoon" class="theme-icon moon" viewBox="0 0 24 24" fill="none"><path d="M21 12.79A9 9 0 1 1 11.21 3a7 7 0 0 0 9.79 9.79Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </span>
      </button>
      <a href="#/login" class="btn btn-ghost btn-sm" id="navLoginBtn">Log in</a>
      <a href="#/contact" class="btn btn-primary btn-sm">Book a call</a>
      <button class="nav-burger" id="navBurger" aria-label="Open menu" aria-expanded="false">
        <svg viewBox="0 0 24 24" fill="none"><path d="M4 7h16M4 12h16M4 17h16" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
      </button>
    </div>
  </div>
</header>

<div class="sheet-backdrop" id="sheetBackdrop"></div>
<div class="mobile-sheet" id="mobileSheet">
  <div class="grabber"></div>
  <nav id="mobileNav" aria-label="Mobile"></nav>
</div>

<nav class="bottom-dock" aria-label="Quick access">
  <a href="#/" class="dock-item" data-path="/">
    <svg viewBox="0 0 24 24" fill="none"><path d="M4 11.5 12 4l8 7.5M6 10v9.5a1 1 0 0 0 1 1h3.5V15a1.5 1.5 0 0 1 1.5-1.5v0A1.5 1.5 0 0 1 13.5 15v5.5H17a1 1 0 0 0 1-1V10" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
    <span>Home</span>
  </a>
  <a href="#/marketplace" class="dock-item" data-path="/marketplace">
    <svg viewBox="0 0 24 24" fill="none"><circle cx="9" cy="20" r="1.4" stroke="currentColor" stroke-width="1.6"/><circle cx="17" cy="20" r="1.4" stroke="currentColor" stroke-width="1.6"/><path d="M3 4h2l2.2 11h10.4L20 8H6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
    <span>Marketplace</span>
  </a>
  <a href="#/login" class="dock-item" data-path="/login">
    <svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="8.2" r="3.4" stroke="currentColor" stroke-width="1.8"/><path d="M4.8 20c1.1-3.6 4-5.6 7.2-5.6s6.1 2 7.2 5.6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
    <span>Log in</span>
  </a>
  <button class="dock-item" id="dockMenuBtn" aria-label="Open navigation menu" aria-expanded="false" aria-controls="mobileSheet">
    <svg viewBox="0 0 24 24" fill="none"><path d="M4 7h16M4 12h16M4 17h16" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
    <span>Menu</span>
  </button>
</nav>

<main id="main">
  <div id="view"></div>
</main>

<footer class="footer">
  <div class="container">
    <div class="footer-grid">
      <div>
        <a href="#/" class="logo" style="margin-bottom:14px;">
          <?= logo_mark_html(true) ?>
          <b>techbiss</b>
        </a>
        <p style="max-width:32ch;"><?= e($siteTagline) ?></p>
        <div class="flex gap-12" style="margin-top:18px;">
          <a href="#/contact" class="badge"><?= e($siteSettings['contactEmail']) ?></a>
        </div>
      </div>
      <div>
        <h4>Company</h4>
        <a href="#/about">About</a><a href="#/work">Case studies</a><a href="#/process">How we work</a><a href="#/resources">Resources</a>
      </div>
      <div>
        <h4>Platform</h4>
        <a href="#/services">Services</a><a href="#/solutions">Solutions</a><a href="#/marketplace">Marketplace</a>
      </div>
      <div>
        <h4>Get started</h4>
        <a href="#/pricing">Pricing</a><a href="#/contact">Contact</a><a href="#/login">Log in</a>
      </div>
    </div>
    <div class="footer-bottom">
      <span>© 2026 TECHBISS. All rights reserved.</span>
    </div>
  </div>
</footer>

<script>
  var PRODUCTS_DATA = <?= json_encode($products, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
  var SITE_SETTINGS = <?= json_encode($siteSettings, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
</script>
<script src="assets/app.js?v=<?= @filemtime(__DIR__ . '/assets/app.js') ?: '1' ?>"></script>
</body>
</html>
