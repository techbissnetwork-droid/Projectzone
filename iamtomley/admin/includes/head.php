<?php
/** @var string $title */
require_once __DIR__ . '/../_bootstrap.php';
require_login();
$__page = $__page ?? '';
$__user = current_user();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="robots" content="noindex, nofollow" />
  <title><?= e($title ?? 'Admin') ?> · <?= e(setting('brand_name', 'iamtomley')) ?> Admin</title>
  <link rel="icon" type="image/svg+xml" href="<?= e(url('/favicon/favicon.svg')) ?>" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="<?= e(asset_url('/admin/assets/admin.css')) ?>" />
  <script>
    window.ADMIN = {
      csrf: <?= json_encode(csrf_token()) ?>,
      detectUrl: <?= json_encode(url('/admin/detect-image.php')) ?>,
      base: <?= json_encode(BASE_URL) ?>
    };
  </script>
  <script src="<?= e(asset_url('/admin/assets/admin.js')) ?>" defer></script>
</head>
<body>
<div class="layout">
  <?php
    $nav = [
      'dashboard' => ['index.php',    'Dashboard',    '<path d="M3 13h8V3H3zM13 21h8V11h-8zM13 3v6h8V3zM3 21h8v-6H3z"/>'],
      'settings'  => ['settings.php', 'Site Settings','<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>'],
      'projects'  => ['projects.php', 'Projects',     '<rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>'],
      'stats'     => ['stats.php',    'Stats',        '<line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/>'],
      'games'     => ['games.php',    'Games',        '<rect x="2" y="6" width="20" height="12" rx="2"/><path d="M6 12h4m-2-2v4M15 11h.01M18 13h.01"/>'],
      'seo'       => ['seo.php',      'Search engines','<circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>'],
      'account'   => ['account.php',  'Account',      '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>'],
    ];
  ?>
  <aside class="sidebar">
    <a class="brand" href="<?= e(url('/admin/index.php')) ?>"><span class="dot"></span><?= e(setting('brand_name', 'iamtomley')) ?></a>
    <div class="nav-label">Manage</div>
    <?php foreach ($nav as $key => [$href, $label, $icon]): ?>
      <a class="nav-item <?= $__page === $key ? 'active' : '' ?>" href="<?= e(url('/admin/' . $href)) ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><?= $icon ?></svg><?= e($label) ?>
      </a>
    <?php endforeach; ?>
    <div class="sidebar-foot">
      <a class="nav-item" href="<?= e(url('/index.php')) ?>" target="_blank">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 3h6v6"/><path d="M10 14L21 3"/><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/></svg>View site
      </a>
      <a class="nav-item" href="<?= e(url('/admin/logout.php')) ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>Log out
      </a>
    </div>
  </aside>
  <main class="content">
    <?php if ($f = flash()): ?>
      <div class="alert alert-success"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><polyline points="20 6 9 17 4 12"/></svg><?= e($f) ?></div>
    <?php endif; ?>
    <?php if (using_default_password()): ?>
      <div class="alert alert-warn">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
        You're still using the default password (<strong>admin / admin</strong>). <a href="<?= e(url('/admin/account.php')) ?>">Change it now</a>.
      </div>
    <?php endif; ?>
