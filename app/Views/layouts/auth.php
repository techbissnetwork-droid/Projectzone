<?php
/** @var App\Core\Seo $seo @var App\Core\View $view */
?>
<!DOCTYPE html>
<html lang="<?= e(config('app.locale', 'en')) ?>" data-theme="dark">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($seo->title()) ?></title>
<meta name="description" content="<?= e($seo->description()) ?>">
<meta name="robots" content="noindex, nofollow">
<meta name="theme-color" content="#06080d">
<link rel="icon" href="<?= e(asset('favicon.svg')) ?>" type="image/svg+xml">
<style><?= inline_file('assets/css/critical.css') ?></style>
<link rel="preload" as="style" href="<?= e(asset('assets/css/main.css')) ?>" onload="this.onload=null;this.rel='stylesheet'">
<noscript><link rel="stylesheet" href="<?= e(asset('assets/css/main.css')) ?>"></noscript>
<script>(function(){var d=document.documentElement;d.classList.add('js');try{var t=localStorage.getItem('tb-theme');if(t==='light'||t==='dark'){d.setAttribute('data-theme',t)}}catch(e){}})();</script>
</head>
<body>
<a class="skip-link" href="#main">Skip to content</a>
<main id="main"><?= $view->section('content') ?></main>
<script src="<?= e(asset('assets/js/app.js')) ?>" defer></script>
</body>
</html>
