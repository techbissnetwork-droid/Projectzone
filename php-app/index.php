<?php
require_once __DIR__ . '/includes/db.php';
require_installed('install/');

$products = db()->query('SELECT id, name, category AS cat, icon, price, rating, tagline, description AS `desc`, specs_json FROM products ORDER BY sort_order ASC')->fetchAll();
foreach ($products as &$p) {
    $p['price'] = (float)$p['price'];
    $p['rating'] = (float)$p['rating'];
    $p['specs'] = json_decode($p['specs_json'], true) ?: [];
    unset($p['specs_json']);
}
unset($p);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>TECHBISS — Bloom</title>
<meta name="description" content="TECHBISS helps offline businesses get online: websites, apps, domains, hosting, email and everything after launch.">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/style.css">
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
    <div class="splash-hint">click anywhere to skip</div>
  </div>
</div>

<div class="blob-field" id="blobField" aria-hidden="true"></div>
<div class="route-wipe" id="routeWipe" aria-hidden="true">
  <svg viewBox="0 0 200 200"><path id="wipePath" fill="var(--accent-1)" d="M100,20 C150,20 180,60 180,100 C180,150 150,180 100,180 C50,180 20,150 20,100 C20,50 50,20 100,20 Z"/></svg>
</div>

<header class="site-header">
  <div class="container nav-wrap">
    <a href="#/" class="logo" aria-label="TECHBISS home">
      <span class="logo-mark"><svg viewBox="0 0 24 24" fill="none"><g><rect x="3" y="3" width="18" height="18" rx="6" fill="url(#logoGrad)"/><rect x="7.5" y="7.5" width="9" height="2.6" rx="1.3" fill="#fff2ea"/><rect x="10.7" y="7.5" width="2.6" height="9.5" rx="1.3" fill="#fff2ea"/></g></svg></span>
      <b>techbiss</b><span class="logo-badge">bloom</span>
    </a>
    <nav class="nav-links" id="navLinks" aria-label="Primary">
      <div class="nav-blob" id="navBlob"></div>
    </nav>
    <div class="nav-actions">
      <div class="palette-wrap">
        <button class="palette-toggle" id="paletteToggle" aria-label="Choose color theme" aria-haspopup="true" aria-expanded="false">
          <span class="sw-current" id="paletteSwatch"></span>
        </button>
        <div class="palette-pop" id="palettePop" role="menu" aria-label="Color themes">
          <button class="palette-opt" data-palette="" role="menuitemradio" aria-checked="true"><span class="opt-sw" style="--sw1:#ff7a52;--sw2:#ffb648;"></span><span class="opt-name">Bloom</span><svg class="opt-check" viewBox="0 0 24 24" fill="none"><path d="M5 13l4 4L19 7" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
          <button class="palette-opt" data-palette="fresh" role="menuitemradio" aria-checked="false"><span class="opt-sw" style="--sw1:#0ea394;--sw2:#8fd14f;"></span><span class="opt-name">Fresh</span><svg class="opt-check" viewBox="0 0 24 24" fill="none"><path d="M5 13l4 4L19 7" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
          <button class="palette-opt" data-palette="dusk" role="menuitemradio" aria-checked="false"><span class="opt-sw" style="--sw1:#7c5cff;--sw2:#4fc3f7;"></span><span class="opt-name">Dusk</span><svg class="opt-check" viewBox="0 0 24 24" fill="none"><path d="M5 13l4 4L19 7" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
          <button class="palette-opt" data-palette="ember" role="menuitemradio" aria-checked="false"><span class="opt-sw" style="--sw1:#e0457a;--sw2:#f6b93b;"></span><span class="opt-name">Ember</span><svg class="opt-check" viewBox="0 0 24 24" fill="none"><path d="M5 13l4 4L19 7" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
          <button class="palette-opt" data-palette="sunrise" role="menuitemradio" aria-checked="false"><span class="opt-sw" style="--sw1:#ff6a3d;--sw2:#ff4f8b;"></span><span class="opt-name">Sunrise</span><svg class="opt-check" viewBox="0 0 24 24" fill="none"><path d="M5 13l4 4L19 7" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
          <button class="palette-opt" data-palette="lagoon" role="menuitemradio" aria-checked="false"><span class="opt-sw" style="--sw1:#0098c9;--sw2:#2f6fed;"></span><span class="opt-name">Lagoon</span><svg class="opt-check" viewBox="0 0 24 24" fill="none"><path d="M5 13l4 4L19 7" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
          <button class="palette-opt" data-palette="orchid" role="menuitemradio" aria-checked="false"><span class="opt-sw" style="--sw1:#a83df0;--sw2:#6a5cff;"></span><span class="opt-name">Orchid</span><svg class="opt-check" viewBox="0 0 24 24" fill="none"><path d="M5 13l4 4L19 7" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
          <button class="palette-opt" data-palette="citrus" role="menuitemradio" aria-checked="false"><span class="opt-sw" style="--sw1:#7ec242;--sw2:#17a672;"></span><span class="opt-name">Citrus</span><svg class="opt-check" viewBox="0 0 24 24" fill="none"><path d="M5 13l4 4L19 7" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
          <button class="palette-opt" data-palette="slate" role="menuitemradio" aria-checked="false"><span class="opt-sw" style="--sw1:#7a63d1;--sw2:#c9718a;"></span><span class="opt-name">Slate Bloom</span><svg class="opt-check" viewBox="0 0 24 24" fill="none"><path d="M5 13l4 4L19 7" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
          <button class="palette-opt" data-palette="midnight" role="menuitemradio" aria-checked="false"><span class="opt-sw" style="--sw1:#4a3aff;--sw2:#e0389c;"></span><span class="opt-name">Midnight Bloom</span><svg class="opt-check" viewBox="0 0 24 24" fill="none"><path d="M5 13l4 4L19 7" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
          <div class="palette-sep"></div>
          <button class="palette-opt" data-palette="noir" data-forces-dark="1" role="menuitemradio" aria-checked="false"><span class="opt-sw" style="--sw1:#c9a45c;--sw2:#efe6d0;"></span><span class="opt-name">Noir<em>premium black</em></span><svg class="opt-check" viewBox="0 0 24 24" fill="none"><path d="M5 13l4 4L19 7" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
        </div>
      </div>
      <button class="theme-toggle" id="themeToggle" aria-label="Toggle dark mode" aria-pressed="false">
        <span class="knob" id="themeKnob">
          <svg id="themeIcon" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="4.2" stroke="currentColor" stroke-width="2"/><path d="M12 2v2M12 20v2M4.2 4.2l1.4 1.4M18.4 18.4l1.4 1.4M2 12h2M20 12h2M4.2 19.8l1.4-1.4M18.4 5.6l1.4-1.4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
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
  <a href="#/contact" class="dock-item" data-path="/contact">
    <svg viewBox="0 0 24 24" fill="none"><rect x="4" y="5.5" width="16" height="14.5" rx="2.6" stroke="currentColor" stroke-width="1.8"/><path d="M4 10h16M8 3.5v3M16 3.5v3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
    <span>Book a call</span>
  </a>
  <a href="#/login" class="dock-item" data-path="/login">
    <svg viewBox="0 0 24 24" fill="none"><rect x="5" y="10.5" width="14" height="9" rx="3" stroke="currentColor" stroke-width="1.8"/><path d="M8 10.5V8a4 4 0 0 1 8 0v2.5" stroke="currentColor" stroke-width="1.8"/></svg>
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
          <span class="logo-mark"><svg viewBox="0 0 24 24" fill="none"><g><rect x="3" y="3" width="18" height="18" rx="6" fill="url(#logoGrad)"/><rect x="7.5" y="7.5" width="9" height="2.6" rx="1.3" fill="#fff2ea"/><rect x="10.7" y="7.5" width="2.6" height="9.5" rx="1.3" fill="#fff2ea"/></g></svg></span>
          <b>techbiss</b><span class="logo-badge">bloom</span>
        </a>
        <p style="max-width:32ch;">We help offline businesses get online — website or app, domain, hosting, email and everything after launch.</p>
        <div class="flex gap-12" style="margin-top:18px;">
          <a href="#/contact" class="badge">hello@techbiss.com</a>
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
      <span>© 2026 TECHBISS. Concept 5 — Bloom.</span>
      <span>Designed light-first, built for both themes.</span>
    </div>
  </div>
</footer>

<script>
  var PRODUCTS_DATA = <?= json_encode($products, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
</script>
<script src="assets/app.js"></script>
</body>
</html>
