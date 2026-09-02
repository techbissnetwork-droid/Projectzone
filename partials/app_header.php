<?php
/**
 * Shared shell for the admin panel and the client portal.
 * Set before including: $PAGE_TITLE, $AREA ('admin'|'client'), optional $PAGE_ACTIONS (html).
 */
declare(strict_types=1);
if (!class_exists('Settings')) { http_response_code(404); exit('Not found.'); }
$AREA       = $AREA ?? 'client';
$PAGE_TITLE = $PAGE_TITLE ?? 'Dashboard';
$me         = Auth::user() ?? [];
$here       = basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));

$NAV = $AREA === 'admin' ? [
    ['index.php',     'Dashboard',   'grid'],
    ['projects.php',  'Projects',    'layers'],
    ['clients.php',   'Clients',     'users'],
    ['tickets.php',   'Support',     'chat'],
    ['portfolio.php', 'Portfolio',   'star'],
    ['products.php',  'Marketplace', 'cart'],
    ['orders.php',    'Orders',      'receipt'],
    ['services.php',  'Services',    'stack'],
    ['enquiries.php', 'Enquiries',   'inbox'],
    ['settings.php',  'Settings',    'sliders'],
] : [
    ['index.php',    'Dashboard', 'grid'],
    ['projects.php', 'My sites',  'layers'],
    ['tickets.php',  'Support',   'chat'],
    ['orders.php',   'Purchases', 'receipt'],
    ['profile.php',  'Profile',   'user'],
];

function nav_icon(string $k): string
{
    $p = [
        'grid'    => '<path d="M3 3h7v7H3zM14 3h7v7h-7zM3 14h7v7H3zM14 14h7v7h-7z"/>',
        'layers'  => '<path d="M12 3 3 8l9 5 9-5z"/><path d="m3 13 9 5 9-5"/>',
        'users'   => '<circle cx="9" cy="8" r="3.2"/><path d="M3.5 20a5.5 5.5 0 0 1 11 0"/><path d="M16 5.5a3 3 0 0 1 0 5.6M17 20a5.6 5.6 0 0 0-2-4.3"/>',
        'chat'    => '<path d="M21 12a8 8 0 0 1-11.6 7.1L4 21l1.9-5.4A8 8 0 1 1 21 12z"/>',
        'star'    => '<path d="m12 3.5 2.6 5.4 6 .8-4.3 4.2 1 6-5.3-2.9-5.3 2.9 1-6L4.4 9.7l6-.8z"/>',
        'cart'    => '<path d="M4 4h2.2l2 11.2a1.6 1.6 0 0 0 1.6 1.3h8.4a1.6 1.6 0 0 0 1.6-1.2L21.5 8H7"/><circle cx="10.5" cy="20" r="1.4"/><circle cx="18" cy="20" r="1.4"/>',
        'receipt' => '<path d="M6 3h12v18l-3-1.8-3 1.8-3-1.8L6 21z"/><path d="M9.5 8.5h5M9.5 12.5h5"/>',
        'stack'   => '<path d="M4 6h16M4 12h16M4 18h16"/>',
        'inbox'   => '<path d="M3 13h5l1.5 3h5L16 13h5"/><path d="M4.5 5h15l1.5 8v6H3v-6z"/>',
        'sliders' => '<path d="M4 7h16M4 17h16"/><circle cx="9" cy="7" r="2.2"/><circle cx="16" cy="17" r="2.2"/>',
        'user'    => '<circle cx="12" cy="8" r="3.4"/><path d="M5 20a7 7 0 0 1 14 0"/>',
    ];
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
        . ($p[$k] ?? $p['grid']) . '</svg>';
}
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($PAGE_TITLE) ?> · <?= e(Settings::get('site_name', 'TECHBISS')) ?></title>
<link rel="icon" href="<?= e(asset('assets/img/favicon.svg')) ?>" type="image/svg+xml">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter+Tight:wght@500;600;700&family=Inter:wght@400;500;600&display=swap">
<link rel="stylesheet" href="<?= e(asset('assets/css/app.css')) ?>">
<style>:root{--sig:<?= e(Settings::get('accent_color', '#8FB0FF')) ?>;--ember:<?= e(Settings::get('accent_warm', '#E7BB8D')) ?>}</style>
</head>
<body class="app">
<a class="skip" href="#content">Skip to content</a>

<input type="checkbox" id="navToggle" class="navToggle" hidden>
<aside class="side">
  <a class="side__brand" href="<?= e(url()) ?>">
    <span class="side__mark" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><path d="M12 2 21.5 7v10L12 22 2.5 17V7z" stroke="currentColor" stroke-width="1.3" stroke-linejoin="round"/><path d="M12 22V12l9.5-5M12 12 2.5 7" stroke="currentColor" stroke-width="1.3" stroke-linejoin="round" opacity=".55"/></svg></span>
    <span><?= e(Settings::get('site_name', 'TECHBISS')) ?></span>
  </a>
  <p class="side__area"><?= $AREA === 'admin' ? 'Admin panel' : 'Client portal' ?></p>
  <nav class="side__nav" aria-label="<?= $AREA === 'admin' ? 'Admin' : 'Portal' ?>">
    <?php foreach ($NAV as [$file, $text, $icon]): ?>
      <a href="<?= e($file) ?>"<?= $here === $file ? ' class="on" aria-current="page"' : '' ?>>
        <?= nav_icon($icon) ?><span><?= e($text) ?></span>
      </a>
    <?php endforeach; ?>
  </nav>
  <div class="side__foot">
    <?php if ($AREA === 'admin'): ?><a href="<?= e(url()) ?>" target="_blank" rel="noopener">View site ↗</a><?php endif; ?>
    <a href="<?= e(url('logout.php')) ?>">Sign out</a>
  </div>
</aside>

<div class="main">
  <header class="top">
    <label for="navToggle" class="burger" aria-label="Toggle navigation"><span></span><span></span><span></span></label>
    <h1><?= e($PAGE_TITLE) ?></h1>
    <div class="top__actions"><?= $PAGE_ACTIONS ?? '' ?></div>
    <div class="who">
      <span class="who__name"><?= e($me['name'] ?? '') ?></span>
      <span class="who__avatar" aria-hidden="true"><?= e(mb_strtoupper(mb_substr((string)($me['name'] ?? '?'), 0, 1))) ?></span>
    </div>
  </header>

  <main class="content" id="content">
    <?php foreach (Flash::take() as $f): ?>
      <div class="alert <?= $f['type'] === 'ok' ? 'ok' : 'err' ?>" role="status"><?= e($f['message']) ?></div>
    <?php endforeach; ?>
