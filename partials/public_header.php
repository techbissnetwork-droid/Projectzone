<?php
/** Public site shell. Set $PAGE_TITLE, $META_DESC, optional $BODY_CLASS, $CANONICAL. */
declare(strict_types=1);
if (!class_exists('Settings')) { http_response_code(404); exit('Not found.'); }
$site  = Settings::get('site_name', 'TECHBISS');
$title = isset($PAGE_TITLE) && $PAGE_TITLE !== '' ? $PAGE_TITLE . ' · ' . $site : $site . ' — ' . Settings::get('site_tagline');
$desc  = $META_DESC ?? Settings::get('site_description');
$here  = basename((string)($_SERVER['SCRIPT_NAME'] ?? 'index.php'));
$user  = Auth::user();
$NAVP  = [['services.php', 'Services']];
if (Settings::bool('show_portfolio', true))   { $NAVP[] = ['portfolio.php', 'Work']; }
if (Settings::bool('show_marketplace', true)) { $NAVP[] = ['marketplace.php', 'Marketplace']; }
$NAVP[] = ['contact.php', 'Contact'];
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
<?php if (!empty($OG_IMAGE)): ?><meta property="og:image" content="<?= e($OG_IMAGE) ?>"><?php endif; ?>
<meta name="twitter:card" content="summary_large_image">
<link rel="icon" href="<?= e(asset('assets/img/favicon.svg')) ?>" type="image/svg+xml">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter+Tight:wght@400;500;600;700&family=Inter:wght@400;500;600&display=swap">
<link rel="stylesheet" href="<?= e(asset('assets/css/base.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('assets/css/main.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('assets/css/pages.css')) ?>">
<style>:root{--sig:<?= e(Settings::get('accent_color', '#8FB0FF')) ?>;--ember:<?= e(Settings::get('accent_warm', '#E7BB8D')) ?>}</style>
<script>document.documentElement.classList.replace('no-js','js');</script>
</head>
<body<?= !empty($BODY_CLASS) ? ' class="' . e($BODY_CLASS) . '"' : '' ?>>
<a class="skip-link" href="#main">Skip to content</a>
<div class="grain" aria-hidden="true"></div>
<div class="cursor" aria-hidden="true"><i class="cursor__dot"></i><i class="cursor__ring"><span class="cursor__label"></span></i></div>

<header class="nav" id="nav">
  <div class="nav__inner">
    <a class="brand" href="<?= e(url()) ?>" aria-label="<?= e($site) ?> — home">
      <span class="brand__mark" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><path d="M12 2 21.5 7v10L12 22 2.5 17V7z" stroke="currentColor" stroke-width="1.3" stroke-linejoin="round"/><path d="M12 22V12l9.5-5M12 12 2.5 7" stroke="currentColor" stroke-width="1.3" stroke-linejoin="round" opacity=".55"/></svg></span>
      <span class="brand__word"><?= e($site) ?></span>
    </a>
    <nav class="nav__links" aria-label="Primary">
      <?php foreach ($NAVP as [$f, $t]): ?>
        <a href="<?= e(url($f)) ?>"<?= $here === $f ? ' class="is-current" aria-current="page"' : '' ?>><?= e($t) ?></a>
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
    <?php $n = 1; foreach ($NAVP as [$f, $t]): ?>
      <a href="<?= e(url($f)) ?>"><i><?= sprintf('%02d', $n++) ?></i><span><?= e($t) ?></span></a>
    <?php endforeach; ?>
    <a href="<?= e(url($user ? ($user['role'] === 'admin' ? 'admin/' : 'client/') : 'login.php')) ?>">
      <i><?= sprintf('%02d', $n) ?></i><span><?= $user ? 'My account' : 'Sign in' ?></span></a>
  </nav>
  <div class="menu__foot">
    <a class="btn btn--primary btn--block" href="<?= e(url('contact.php')) ?>">Start a Project <span class="btn__arrow">→</span></a>
    <p><?= e(Settings::get('contact_email')) ?></p>
  </div>
</div>

<main id="main">
