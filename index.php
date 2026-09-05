<?php
require_once __DIR__ . '/includes/db.php';
require_installed('install/');

$products = db()->query('SELECT id, name, category AS cat, icon, image_path, price, pricing_type, rating, tagline, description AS `desc`, specs_json, download_path FROM products ORDER BY sort_order ASC')->fetchAll();
foreach ($products as &$p) {
    $p['price'] = (float)$p['price'];
    $p['rating'] = (float)$p['rating'];
    $p['specs'] = json_decode($p['specs_json'], true) ?: [];
    $p['hasDownload'] = $p['download_path'] !== null;
    $p['image'] = $p['image_path'];
    unset($p['specs_json'], $p['download_path'], $p['image_path']);
}
unset($p);

$siteSettings = [
    'siteName' => get_setting('site_name', 'TECHBISS'),
    'heroHeadlineMain' => get_setting('hero_headline_main', 'We help offline businesses'),
    'heroHeadlineAccent' => get_setting('hero_headline_accent', 'thrive online.'),
    'heroSubheadline' => get_setting('hero_subheadline', 'TECHBISS builds your website or app, then sets up your domain, hosting, email and app store listing — so you launch with everything working and ready to be found.'),
    'contactEmail' => get_setting('contact_email', 'hello@techbiss.com'),
    'contactPhone' => get_setting('contact_phone', '+1 (415) 555-0148'),
    'whatsappNumber' => get_setting('whatsapp_number', ''),
    'studiosLocations' => get_setting('studios_locations', 'San Francisco · Lisbon · Singapore'),
    'pricingStartingPrice' => (int)get_setting('pricing_starting_price', '5'),
    'priceStartBuild' => (int)get_setting('price_start_build', '900'),
    'priceStartBuy' => (int)get_setting('price_start_buy', '59'),
    'priceStartPublish' => (int)get_setting('price_start_publish', '1500'),
    'aboutStory' => get_setting('about_story', 'TECHBISS began by building one shop owner a website over a weekend. Nine years later, the same conviction runs the platform: we build every project like it\'s the most important one — because to the person running the business, it is.'),
    'careersQuote' => get_setting('careers_quote', 'We\'re always looking for people who\'d rather ship a small business\'s first website than polish a slide deck.'),
    'privacyPolicy' => get_setting('privacy_policy', ''),
    'privacyUpdatedAt' => get_setting('privacy_updated_at', ''),
    'termsConditions' => get_setting('terms_conditions', ''),
    'termsUpdatedAt' => get_setting('terms_updated_at', ''),
    'paymentsEnabled' => get_setting('payments_enabled', 'off') === 'on',
    'splashEnabled' => get_setting('splash_enabled', 'on') !== 'off',
    'pageTransitionEnabled' => get_setting('page_transition_enabled', 'on') !== 'off',
    'defaultTheme' => get_setting('default_theme', 'auto'),
    'stat1Value' => get_setting('stat1_value', '1,900+'),
    'stat1Label' => get_setting('stat1_label', 'Businesses & apps launched'),
    'stat2Value' => get_setting('stat2_value', '38'),
    'stat2Label' => get_setting('stat2_label', 'Countries served'),
    'stat3Value' => get_setting('stat3_value', '4.9/5'),
    'stat3Label' => get_setting('stat3_label', 'Customer rating'),
    'stat4Value' => get_setting('stat4_value', '72 hrs'),
    'stat4Label' => get_setting('stat4_label', 'To your first draft'),
    'stat5Value' => get_setting('stat5_value', '9'),
    'stat5Label' => get_setting('stat5_label', 'Years in business'),
];
$contentSections = [
    'services' => content_services_rows(),
    'solutions' => content_industries_rows(),
    'caseStudies' => content_case_studies_rows(),
    'pricingFaq' => content_pricing_faqs_rows(),
    'team' => content_team_rows(),
    'values' => content_values_rows(),
    'portfolio' => content_portfolio_rows(),
];

$siteName = get_setting('site_name', 'TECHBISS');
$siteTagline = get_setting('site_tagline', 'TECHBISS builds the complete digital presence of your business — websites, apps, hosting, security, email, e-commerce, automation and payments.');

$seoTitle = get_setting('seo_title', 'TECHBISS — Helping offline businesses go online');
$metaDescription = get_setting('meta_description', 'TECHBISS builds the complete digital presence of your business — websites, apps, hosting, security, email, e-commerce, automation and payments.');
$faviconPath = get_setting('favicon_path', 'assets/favicon.ico');
$socialImagePath = get_setting('social_image_path', 'assets/social-default.png');
$baseUrl = defined('SITE_URL') ? rtrim(SITE_URL, '/') : '';
$canonicalUrl = $baseUrl !== '' ? $baseUrl . '/' : '';
$socialImageUrl = $baseUrl !== '' ? $baseUrl . '/' . ltrim($socialImagePath, '/') : $socialImagePath;

// The URL path the app is installed under — usually '' (domain root), but
// support a subfolder install too (e.g. SITE_URL = https://example.com/techbiss).
// Used to build clean, hash-free links (/services) that still resolve
// correctly if the site isn't at the domain root.
$basePath = '';
if ($baseUrl !== '') {
    $urlPath = (string)parse_url($baseUrl, PHP_URL_PATH);
    $basePath = rtrim($urlPath, '/');
}

/**
 * Unknown paths used to render the homepage with HTTP 200, which is an
 * unlimited supply of duplicate-content URLs for crawlers and a silent
 * dead end for visitors. The router knows the same list; sending the
 * status here is what search engines actually read.
 */
const KNOWN_ROUTES = [
    '/', '/services', '/solutions', '/marketplace', '/work', '/process',
    '/pricing', '/about', '/resources', '/contact', '/login', '/dashboard',
    '/account', '/privacy', '/terms',
];
$requestPath = (string)parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
if ($basePath !== '' && str_starts_with($requestPath, $basePath)) {
    $requestPath = substr($requestPath, strlen($basePath));
}
$requestPath = rtrim($requestPath, '/') ?: '/';
$isKnownRoute = in_array($requestPath, KNOWN_ROUTES, true)
    || str_starts_with($requestPath, '/marketplace/detail/');
if (!$isKnownRoute) {
    http_response_code(404);
}
?>
<!doctype html>
<html lang="en"<?= palette_attr() . logo_motion_attr() ?>>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=<?= ui_zoom_scale() ?>">
<title><?= e($seoTitle) ?></title>
<meta name="description" content="<?= e($metaDescription) ?>">
<?php if ($canonicalUrl !== ''): ?><link rel="canonical" href="<?= e($canonicalUrl) ?>"><?php endif; ?>
<link rel="icon" href="<?= e($basePath) ?>/<?= e($faviconPath) ?>">
<link rel="apple-touch-icon" href="<?= e($basePath) ?>/assets/apple-touch-icon.png">
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
<link rel="stylesheet" href="<?= e($basePath) ?>/assets/style.css?v=<?= asset_version() ?>">
<?= ui_zoom_style() ?>
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
    <div class="splash-word"><?= e($siteName) ?></div>
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
    <a href="/" class="logo" aria-label="TECHBISS home">
      <?= logo_mark_html(true, $basePath !== '' ? $basePath . '/' : '/') ?>
      <?= logo_wordmark_html() ?>
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
      <a href="/login" class="btn btn-ghost btn-sm" id="navLoginBtn">Log in</a>
      <a href="/contact" class="btn btn-primary btn-sm">Book a call</a>
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
  <a href="/" class="dock-item" data-path="/">
    <svg viewBox="0 0 24 24" fill="none"><path d="M4 11.5 12 4l8 7.5M6 10v9.5a1 1 0 0 0 1 1h3.5V15a1.5 1.5 0 0 1 1.5-1.5v0A1.5 1.5 0 0 1 13.5 15v5.5H17a1 1 0 0 0 1-1V10" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
    <span>Home</span>
  </a>
  <a href="/marketplace" class="dock-item" data-path="/marketplace">
    <svg viewBox="0 0 24 24" fill="none"><circle cx="9" cy="20" r="1.4" stroke="currentColor" stroke-width="1.6"/><circle cx="17" cy="20" r="1.4" stroke="currentColor" stroke-width="1.6"/><path d="M3 4h2l2.2 11h10.4L20 8H6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
    <span>Marketplace</span>
  </a>
  <a href="/login" class="dock-item" data-path="/login">
    <svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="8.2" r="3.4" stroke="currentColor" stroke-width="1.8"/><path d="M4.8 20c1.1-3.6 4-5.6 7.2-5.6s6.1 2 7.2 5.6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
    <span>Log in</span>
  </a>
  <button class="dock-item" id="dockMenuBtn" aria-label="Open navigation menu" aria-expanded="false" aria-controls="mobileSheet">
    <svg viewBox="0 0 24 24" fill="none"><path d="M4 7h16M4 12h16M4 17h16" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
    <span>Menu</span>
  </button>
</nav>

<?php if ($siteSettings['whatsappNumber'] !== ''): ?>
<a class="wa-float" href="https://wa.me/<?= e(preg_replace('/\D+/', '', $siteSettings['whatsappNumber'])) ?>" target="_blank" rel="noopener" aria-label="Chat on WhatsApp">
  <svg viewBox="0 0 24 24" fill="none"><path d="M4 6h16v10H9l-4 3.5V16H4Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></svg>
</a>
<?php endif; ?>

<main id="main">
  <div id="view"></div>
</main>

<footer class="footer">
  <div class="container">
    <div class="footer-grid">
      <div class="footer-brand">
        <a href="/" class="logo" style="margin-bottom:14px;">
          <?= logo_mark_html(true, $basePath !== '' ? $basePath . '/' : '/') ?>
          <?= logo_wordmark_html() ?>
        </a>
        <p><?= e($siteTagline) ?></p>
      </div>
      <div>
        <h4>Company</h4>
        <a href="/about">About</a><a href="/work">Case studies</a><a href="/process">How we work</a>
      </div>
      <div>
        <h4>Platform</h4>
        <a href="/services">Services</a><a href="/solutions">Solutions</a><a href="/marketplace">Marketplace</a>
      </div>
      <div>
        <h4>Get started</h4>
        <a href="/pricing">Pricing</a><a href="/contact">Contact</a>
      </div>
      <div>
        <h4>Legal</h4>
        <a href="/privacy">Privacy Policy</a><a href="/terms">Terms &amp; Conditions</a>
      </div>
    </div>
    <div class="footer-bottom">
      <span>© <?= date('Y') ?> <?= e($siteName) ?>. All rights reserved.</span>
    </div>
  </div>
</footer>

<script>
  var BASE_PATH = <?= json_encode($basePath) ?>;
  var PRODUCTS_DATA = <?= json_encode($products, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
  var SITE_SETTINGS = <?= json_encode($siteSettings, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
  var SERVICES_DATA = <?= json_encode($contentSections['services'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
  var SOLUTIONS_DATA = <?= json_encode($contentSections['solutions'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
  var CASESTUDIES_DATA = <?= json_encode($contentSections['caseStudies'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
  var PRICING_FAQ_DATA = <?= json_encode($contentSections['pricingFaq'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
  var TEAM_DATA = <?= json_encode($contentSections['team'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
  var VALUES_DATA = <?= json_encode($contentSections['values'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
  var PORTFOLIO_DATA = <?= json_encode($contentSections['portfolio'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
</script>
<script src="<?= e($basePath) ?>/assets/app.js?v=<?= asset_version() ?>"></script>
</body>
</html>
