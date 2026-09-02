<?php
/** Public site shell. Set $PAGE_TITLE, $META_DESC, optional $BODY_CLASS, $CANONICAL. */
declare(strict_types=1);
if (!class_exists('Settings')) { http_response_code(404); exit('Not found.'); }
$site  = Settings::get('site_name', 'TECHBISS');
$suffix = Settings::get('meta_suffix', '') !== '' ? Settings::get('meta_suffix') : $site;
$title = isset($PAGE_TITLE) && $PAGE_TITLE !== '' ? $PAGE_TITLE . ' · ' . $suffix : $site . ' — ' . Settings::get('site_tagline');
$desc  = $META_DESC ?? Settings::get('site_description');
$here  = basename((string)($_SERVER['SCRIPT_NAME'] ?? 'index.php'));
$user  = Auth::user();
$NAVP  = Content::nav('header');
if (!$NAVP) {
    $NAVP = [['label' => 'Services', 'href' => url('services.php'), 'new_tab' => false],
             ['label' => 'Contact',  'href' => url('contact.php'),  'new_tab' => false]];
}
$fontD = Settings::get('font_display', 'Inter Tight');
$fontB = Settings::get('font_body', 'Inter');
$families = array_values(array_unique(array_filter([$fontD, $fontB], static fn($f) => $f !== 'system')));
$radius = ['sharp' => ['6px','10px','14px','18px'], 'normal' => ['10px','16px','22px','30px'],
           'round' => ['14px','22px','30px','40px']][Settings::get('radius_scale', 'normal')] ?? ['10px','16px','22px','30px'];
?><!doctype html>
<html lang="en" class="no-js">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($title) ?></title>
<meta name="description" content="<?= e($desc) ?>">
<meta name="theme-color" content="#06070A">
<meta name="color-scheme" content="dark">
<?php if (!empty($CANONICAL)): ?><link rel="canonical" href="<?= e($CANONICAL) ?>"><?php endif; ?>
<meta property="og:type" content="website">
<meta property="og:site_name" content="<?= e($site) ?>">
<meta property="og:title" content="<?= e($title) ?>">
<meta property="og:description" content="<?= e($desc) ?>">
<?php $og = $OG_IMAGE ?? (Settings::get('og_image') !== '' ? url(Settings::get('og_image')) : null);
if ($og): ?><meta property="og:image" content="<?= e($og) ?>"><?php endif; ?>
<meta name="twitter:card" content="summary_large_image">
<?php $fav = Settings::get('favicon_image', ''); ?>
<link rel="icon" href="<?= e($fav !== '' ? url($fav) : asset('assets/img/favicon.svg')) ?>"<?= $fav === '' ? ' type="image/svg+xml"' : '' ?>>
<?php if ($families): ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?<?= implode('&', array_map(
    static fn($f) => 'family=' . str_replace(' ', '+', $f) . ':wght@400;500;600;700', $families)) ?>&display=swap">
<?php endif; ?>
<link rel="stylesheet" href="<?= e(asset('assets/css/base.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('assets/css/main.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('assets/css/pages.css')) ?>">
<style>
:root{
  --sig:<?= e(Settings::get('accent_color', '#8FB0FF')) ?>;
  --ember:<?= e(Settings::get('accent_warm', '#E7BB8D')) ?>;
  --bg:<?= e(Settings::get('bg_base', '#06070A')) ?>;
  --deep:<?= e(Settings::get('bg_base', '#06070A')) ?>;
  --tx:<?= e(Settings::get('text_primary', '#EEF1F6')) ?>;
  --tx-2:<?= e(Settings::get('text_muted', '#98A1B0')) ?>;
  --r-s:<?= e($radius[0]) ?>; --r-m:<?= e($radius[1]) ?>; --r-l:<?= e($radius[2]) ?>; --r-xl:<?= e($radius[3]) ?>;
  --ff-d:<?= $fontD === 'system' ? 'system-ui, sans-serif' : '"' . e($fontD) . '", "Inter", system-ui, sans-serif' ?>;
  --ff-b:<?= $fontB === 'system' ? 'system-ui, sans-serif' : '"' . e($fontB) . '", system-ui, -apple-system, sans-serif' ?>;
}
<?= Settings::get('custom_css') ?>
</style>
<?php if (!Settings::bool('robots_index', true)): ?><meta name="robots" content="noindex, nofollow"><?php endif; ?>
<?= Settings::get('custom_head') ?>
<script>document.documentElement.classList.replace('no-js','js');</script>
</head>
<body<?= !empty($BODY_CLASS) ? ' class="' . e($BODY_CLASS) . '"' : '' ?>>
<a class="skip-link" href="#main">Skip to content</a>
<div class="grain" aria-hidden="true"></div>
<div class="cursor" aria-hidden="true"><i class="cursor__dot"></i><i class="cursor__ring"><span class="cursor__label"></span></i></div>

<header class="nav" id="nav">
  <div class="nav__inner">
    <a class="brand" href="<?= e(url()) ?>" aria-label="<?= e($site) ?> — home">
      <?= Content::logo() ?>
      <?php if (Settings::get('logo_image', '') === ''): ?><span class="brand__word"><?= e($site) ?></span><?php endif; ?>
    </a>
    <nav class="nav__links" aria-label="Primary">
      <?php foreach ($NAVP as $n): ?>
        <a href="<?= e($n['href']) ?>"<?= $n['new_tab'] ? ' target="_blank" rel="noopener"' : '' ?><?=
          str_ends_with($n['href'], '/' . $here) ? ' class="is-current" aria-current="page"' : '' ?>><?= e($n['label']) ?></a>
      <?php endforeach; ?>
      <?php if ($user): ?>
        <a href="<?= e(url($user['role'] === 'admin' ? 'admin/' : 'client/')) ?>"><?= $user['role'] === 'admin' ? 'Admin' : 'My account' ?></a>
      <?php else: ?>
        <a href="<?= e(url('login.php')) ?>">Sign in</a>
      <?php endif; ?>
    </nav>
    <a class="btn btn--sm btn--primary nav__cta magnetic" href="<?= e(url('contact.php')) ?>">Start a Project <span class="btn__arrow">→</span></a>
    <button class="burger" id="burger" aria-expanded="false" aria-controls="menu" aria-label="Open menu"><span></span><span></span></button>
  </div>
  <div class="nav__progress" aria-hidden="true"><i></i></div>
</header>

<div class="menu" id="menu" hidden>
  <div class="menu__bg" aria-hidden="true"></div>
  <nav class="menu__links" aria-label="Mobile">
    <?php $ni = 1; foreach ($NAVP as $n): ?>
      <a href="<?= e($n['href']) ?>"<?= $n['new_tab'] ? ' target="_blank" rel="noopener"' : '' ?>><i><?= sprintf('%02d', $ni++) ?></i><span><?= e($n['label']) ?></span></a>
    <?php endforeach; ?>
    <a href="<?= e(url($user ? ($user['role'] === 'admin' ? 'admin/' : 'client/') : 'login.php')) ?>">
      <i><?= sprintf('%02d', $ni) ?></i><span><?= $user ? 'My account' : 'Sign in' ?></span></a>
  </nav>
  <div class="menu__foot">
    <a class="btn btn--primary btn--block" href="<?= e(url('contact.php')) ?>">Start a Project <span class="btn__arrow">→</span></a>
    <p><?= e(Settings::get('contact_email')) ?></p>
  </div>
</div>

<main id="main">
