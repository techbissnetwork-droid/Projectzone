<?php /** Standalone layout for the sign-in screen. */ ?>
<!doctype html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e($title ?? 'Sign in') ?> · <?= e($settings->get('site_name', 'TECHBISS')) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" media="print" onload="this.media='all'">
    <link rel="stylesheet" href="<?= e(asset('assets/css/design-system.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('assets/css/site.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('admin/assets/css/admin.css')) ?>">
</head>
<body class="admin">

<?php require \Techbiss\Core\App::root() . '/pages/partials/brand-sprite.php'; ?>
<main class="auth-screen">
    <div class="page-head__bg" aria-hidden="true"><span class="glow"></span><span class="grid-pattern grid-pattern--center"></span></div>
    <?= $content ?>
</main>

<?php foreach ($flash as $item): ?>
<div data-flash="<?= e($item['type']) ?>" data-flash-message="<?= e($item['message']) ?>" hidden></div>
<?php endforeach; ?>

<script nonce="<?= e(\Techbiss\Core\App::nonce()) ?>">window.TECHBISS = { cursor: false, transitions: false };</script>
<script src="<?= e(asset('assets/js/app.js')) ?>" defer></script>
</body>
</html>
