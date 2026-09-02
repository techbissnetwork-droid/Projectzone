<?php
/** Expects: $title, $desc, and optionally $withLoader / $withHero. */
$withLoader = $withLoader ?? false;
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title><?= e($title) ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="description" content="<?= e($desc) ?>">
<meta name="theme-color" content="#07080A">
<link rel="icon" href="<?= e(base_url('assets/favicon.svg')) ?>" type="image/svg+xml">
<meta property="og:title" content="<?= e($title) ?>">
<meta property="og:description" content="<?= e($desc) ?>">
<meta property="og:type" content="website">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Unbounded:wght@500;700;800;900&family=Manrope:wght@400;500;600;700&family=Azeret+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(base_url('assets/css/style.css')) ?>">
<script>document.documentElement.className+=" js";</script>
</head>
<body>

<a class="skip" href="#main">Skip to content</a>

<?php if ($withLoader): ?>
<div class="loader" id="loader">
  <div class="loader__row"><span><?= e(txt('site.name', 'TECHBISS')) ?></span><span id="loaderStatus">LOADING ASSETS</span></div>
  <div class="loader__pct" id="loaderPct">0%</div>
  <div class="loader__bar"><i id="loaderBar"></i></div>
</div>
<?php endif; ?>

<svg width="0" height="0" style="position:absolute" aria-hidden="true">
  <filter id="grainFilter"><feTurbulence type="fractalNoise" baseFrequency="0.85" numOctaves="2" stitchTiles="stitch"/></filter>
</svg>
<div class="grain" aria-hidden="true" style="filter:url(#grainFilter)"></div>
<div class="cursor" id="cursor" aria-hidden="true"></div>
<div class="progress" aria-hidden="true"><i id="progressFill"></i></div>
