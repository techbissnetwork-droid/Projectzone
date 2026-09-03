<?php
/**
 * Public site layout.
 *
 * Critical CSS is inlined, the full stylesheet loads without blocking paint,
 * and the only script is a deferred 4 KB runtime. That combination is what
 * keeps first paint independent of the network round-trip for assets.
 *
 * @var App\Core\Seo $seo
 * @var App\Core\View $view
 * @var App\Core\Request $request
 */
$jsonLd = $seo->jsonLd();
$bodyClass = $bodyClass ?? '';
?>
<!DOCTYPE html>
<html lang="<?= e(config('app.locale', 'en')) ?>" data-theme="dark">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($seo->title()) ?></title>
<?php if ($seo->description() !== ''): ?>
<meta name="description" content="<?= e($seo->description()) ?>">
<?php endif; ?>
<meta name="robots" content="<?= e($seo->robots()) ?>">
<?php if ($seo->canonical() !== ''): ?>
<link rel="canonical" href="<?= e($seo->canonical()) ?>">
<?php endif; ?>
<?php if ($seo->amp() !== null): ?>
<link rel="amphtml" href="<?= e($seo->amp()) ?>">
<?php endif; ?>

<meta property="og:type" content="<?= e($seo->type()) ?>">
<meta property="og:site_name" content="<?= e($seo->siteName()) ?>">
<meta property="og:title" content="<?= e($seo->title()) ?>">
<meta property="og:description" content="<?= e($seo->description()) ?>">
<?php if ($seo->canonical() !== ''): ?>
<meta property="og:url" content="<?= e($seo->canonical()) ?>">
<?php endif; ?>
<?php if ($seo->image() !== ''): ?>
<meta property="og:image" content="<?= e($seo->image()) ?>">
<meta property="og:image:alt" content="<?= e($seo->imageAlt()) ?>">
<meta name="twitter:card" content="summary_large_image">
<?php else: ?>
<meta name="twitter:card" content="summary">
<?php endif; ?>
<meta name="twitter:title" content="<?= e($seo->title()) ?>">
<meta name="twitter:description" content="<?= e($seo->description()) ?>">

<meta name="theme-color" content="#06080d" media="(prefers-color-scheme: dark)">
<meta name="theme-color" content="#fbfcfe" media="(prefers-color-scheme: light)">
<meta name="format-detection" content="telephone=no">

<link rel="icon" href="<?= e(asset('favicon.svg')) ?>" type="image/svg+xml">
<link rel="apple-touch-icon" href="<?= e(asset('apple-touch-icon.png')) ?>">
<link rel="manifest" href="<?= e(url('/manifest.webmanifest')) ?>">
<link rel="alternate" type="application/rss+xml" title="TECHBISS Insights" href="<?= e(url('/feed.xml')) ?>">

<style><?= inline_file('assets/css/critical.css') ?></style>
<link rel="preload" as="style" href="<?= e(asset('assets/css/main.css')) ?>" onload="this.onload=null;this.rel='stylesheet'">
<noscript><link rel="stylesheet" href="<?= e(asset('assets/css/main.css')) ?>"></noscript>

<script>
/* Theme + JS-capability flags, set before first paint to avoid any flash. */
(function(){var d=document.documentElement;d.classList.add('js');try{var t=localStorage.getItem('tb-theme');
if(t==='light'||t==='dark'){d.setAttribute('data-theme',t)}else if(matchMedia('(prefers-color-scheme: light)').matches){d.setAttribute('data-theme','light')}}catch(e){}})();
</script>

<?= $view->section('head') ?>
<?php if ($jsonLd !== ''): ?>
<script type="application/ld+json"><?= $jsonLd ?></script>
<?php endif; ?>
</head>
<body class="<?= e($bodyClass) ?>">
<a class="skip-link" href="#main">Skip to content</a>

<?php $view->partial('partials.header'); ?>

<main id="main">
<?= $view->section('content') ?>
</main>

<?php $view->partial('partials.footer'); ?>
<?php $view->partial('partials.drawer'); ?>
<?= $view->section('after_body') ?>

<script src="<?= e(asset('assets/js/app.js')) ?>" defer></script>
</body>
</html>
