<?php /** Standalone layout for the sign-in screen. */ ?>
<!doctype html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e($title ?? 'Sign in') ?> · <?= e($settings->get('site_name', 'TECHBISS')) ?></title>
    <?php /* Always the TECHBISS mark, matching layout.php — this is our tool, not
             the client's site, so a client's uploaded favicon has no business here. */ ?>
    <link rel="icon" type="image/svg+xml" href="<?= e(asset('assets/images/brand/favicon.svg')) ?>">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= e(asset('assets/images/brand/favicon-32.png')) ?>">
    <link rel="apple-touch-icon" sizes="180x180" href="<?= e(asset('assets/images/brand/apple-touch-icon.png')) ?>">
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
