<?php
/**
 * Opening markup for every public page.
 * Expects: $pageTitle, $pageDesc, $activeNav (one of the keys in $navItems).
 */
$navItems = [
    'services'    => ['services.php',    'Services'],
    'industries'  => ['industries.php',  'Industries'],
    'portfolio'   => ['portfolio.php',   'Work'],
    'marketplace' => ['marketplace.php', 'Marketplace'],
    'pricing'     => ['pricing.php',     'Pricing'],
    'about'       => ['about.php',       'About'],
    'contact'     => ['contact.php',     'Contact'],
];
$activeNav = $activeNav ?? '';
$pageTitle = $pageTitle ?? setting('site.name', 'TECHBISS');
$pageDesc  = $pageDesc  ?? setting('site.tagline');
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= esc($pageTitle) ?></title>
<meta name="description" content="<?= esc($pageDesc) ?>">
<meta name="theme-color" content="#0b0b0d">
<meta property="og:title" content="<?= esc($pageTitle) ?>">
<meta property="og:description" content="<?= esc($pageDesc) ?>">
<meta property="og:type" content="website">
<link rel="icon" href="assets/favicon.svg" type="image/svg+xml">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Syne:wght@500;600;700;800&family=Manrope:wght@300;400;500;600&family=Azeret+Mono:wght@400;500&display=swap">
<link rel="stylesheet" href="assets/css/site.css">
</head>
<body>
<a class="skip" href="#main">Skip to content</a>
<canvas id="blobs" aria-hidden="true"></canvas><div class="noise" aria-hidden="true"></div>

<header class="site-head"><div class="wrap nb">
  <a class="logo" href="index.php"><i aria-hidden="true"></i><?= esc(setting('site.name', 'TECHBISS')) ?></a>
  <nav class="nl" aria-label="Main">
<?php foreach ($navItems as $key => [$href, $label]): ?>
    <a href="<?= $href ?>"<?= $key === $activeNav ? ' aria-current="page"' : '' ?>><?= esc($label) ?></a>
<?php endforeach; ?>
  </nav>
  <a class="pill" href="contact.php"><?= esc(setting('nav.cta', 'Talk to us')) ?></a>
  <button class="mb" id="mb" aria-label="Menu" aria-expanded="false" aria-controls="sheet">&#9776;</button>
</div></header>

<nav class="sheet" id="sheet" aria-label="Mobile">
  <a href="index.php"<?= $activeNav === 'home' ? ' aria-current="page"' : '' ?>>Home</a>
<?php foreach ($navItems as $key => [$href, $label]): ?>
  <a href="<?= $href ?>"<?= $key === $activeNav ? ' aria-current="page"' : '' ?>><?= esc($label) ?></a>
<?php endforeach; ?>
  <div class="meta">
    <span><?= esc(setting('site.email')) ?></span>
    <span><?= esc(setting('site.hours')) ?></span>
    <span><a href="client/login.php" style="color:var(--acc)">Client sign-in &rarr;</a></span>
  </div>
</nav>

<main id="main">
