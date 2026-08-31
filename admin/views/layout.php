<?php
/**
 * Admin shell.
 * @var string $content @var array|null $user @var array $flash @var array $badges
 */

$siteName = $settings->get('site_name', 'TECHBISS');
$title    = $title ?? 'Admin';
?><!doctype html>
<html lang="en" data-theme="<?= e($settings->get('theme_mode', 'dark') === 'light' ? 'light' : 'dark') ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e($title) ?> · <?= e($siteName) ?> Admin</title>
    <link rel="icon" type="image/svg+xml" href="<?= e(asset('assets/images/brand/favicon.svg')) ?>">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= e(asset('assets/images/brand/favicon-32.png')) ?>">
    <link rel="apple-touch-icon" sizes="180x180" href="<?= e(asset('assets/images/brand/apple-touch-icon.png')) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" media="print" onload="this.media='all'">
    <link rel="stylesheet" href="<?= e(asset('assets/css/design-system.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('admin/assets/css/admin.css')) ?>">
    <script nonce="<?= e(\Techbiss\Core\App::nonce()) ?>">
    (function () {
        try {
            var s = localStorage.getItem('techbiss-theme');
            if (s === 'light' || s === 'dark') document.documentElement.setAttribute('data-theme', s);
        } catch (e) {}
    })();
    </script>
</head>
<body class="admin">

<?php require \Techbiss\Core\App::root() . '/pages/partials/brand-sprite.php'; ?>
<a class="skip-link" href="#admin-main">Skip to content</a>

<div class="app">
    <?= $view->partial('partials/sidebar') ?>

    <div class="main">
        <header class="topbar">
            <button type="button" class="icon-btn sidebar-toggle" data-sidebar-toggle
                    aria-expanded="false" aria-controls="admin-sidebar" aria-label="Toggle navigation">
                <?= icon('menu') ?>
            </button>
            <span class="topbar__title"><?= e($title) ?></span>

            <div class="topbar__actions">
                <a class="btn btn--quiet btn--sm" href="<?= e(url('/')) ?>" target="_blank" rel="noopener">
                    <?= icon('external') ?><span class="hide-sm">View site</span>
                </a>
                <button type="button" class="theme-toggle" data-theme-toggle aria-label="Switch theme">
                    <?= icon('sun', 'icon icon--sun') ?><?= icon('moon', 'icon icon--moon') ?>
                </button>
                <a class="btn btn--quiet btn--sm" href="<?= e(url('/admin/profile')) ?>" aria-label="Your profile">
                    <span class="avatar avatar--sm"><?= e(initials((string) ($user['name'] ?? '?'))) ?></span>
                </a>
            </div>
        </header>

        <main class="content" id="admin-main" tabindex="-1">
            <?= $content ?>
        </main>
    </div>
</div>

<?php foreach ($flash as $item): ?>
<div data-flash="<?= e($item['type']) ?>" data-flash-message="<?= e($item['message']) ?>" hidden></div>
<?php endforeach; ?>

<script nonce="<?= e(\Techbiss\Core\App::nonce()) ?>">
window.TECHBISS = { cursor: false, transitions: false };
window.TECHBISS_ADMIN = { csrf: <?= ejs(csrf_token()) ?>, base: <?= ejs(url('/')) ?> };
</script>
<script src="<?= e(asset('assets/js/app.js')) ?>" defer></script>
<script src="<?= e(asset('admin/assets/js/admin.js')) ?>" defer></script>
</body>
</html>
