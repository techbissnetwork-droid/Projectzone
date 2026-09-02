<?php
/**
 * Public site layout.
 *
 * @var string $content   rendered page markup
 * @var \Techbiss\Core\Seo $seo
 * @var \Techbiss\Repo\SettingsRepo $settings
 * @var array $primaryNav
 * @var array $flash
 */
$siteName   = $settings->get('site_name', 'TECHBISS');
$bodyClass  = $bodyClass ?? '';
$gaId       = $settings->get('google_analytics_id');
?><!doctype html>
<html lang="<?= e($settings->get('locale', 'en')) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#07080a">
    <?= $seo->render(
        $siteName . ' — ' . $settings->get('tagline', 'Your Digital Business Starts Here.'),
        $settings->get('seo_default_description'),
        $settings->get('seo_og_image')
    ) ?>
    <?php if ($settings->get('google_site_verification') !== ''): ?>
    <meta name="google-site-verification" content="<?= e($settings->get('google_site_verification')) ?>">
    <?php endif; ?>
    <?php if ($settings->get('bing_site_verification') !== ''): ?>
    <meta name="msvalidate.01" content="<?= e($settings->get('bing_site_verification')) ?>">
    <?php endif; ?>

    <?= $view->partial('partials/favicons') ?>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <?php $fontHref = 'https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500..800&family=Inter:wght@400;500;600;700&family=Azeret+Mono:wght@400;500&display=swap'; ?>
    <link rel="stylesheet" href="<?= e($fontHref) ?>" media="print" onload="this.media='all'">
    <noscript><link rel="stylesheet" href="<?= e($fontHref) ?>"></noscript>

    <?php /* Everything below is undone by JavaScript: the loader is removed and
             the reveal animations fade content in. Without JavaScript the
             loading screen would cover the site for good and every section
             would stay at zero opacity, so the page is handed over intact. */ ?>
    <noscript><style>
        .loader, .page-veil { display: none !important; }
        [data-reveal] { opacity: 1 !important; transform: none !important; }
    </style></noscript>

    <link rel="stylesheet" href="<?= e(asset('assets/css/design-system.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('assets/css/site.css')) ?>">
</head>
<body class="<?= e($bodyClass) ?><?= $settings->bool('enable_loader', true) ? ' is-loading' : '' ?>">

<?= $view->partial('partials/brand-sprite') ?>

<?php if ($settings->bool('enable_loader', true)): ?>
<div class="loader" role="status" aria-live="polite">
    <div class="loader__row">
        <span class="loader__mark"><?= e($siteName) ?></span>
        <span class="loader__caption">Loading</span>
    </div>
    <div class="loader__pct" aria-hidden="true">0%</div>
    <div class="loader__line" aria-hidden="true"><span></span></div>
</div>
<?php endif; ?>

<div class="scroll-progress" aria-hidden="true"><span></span></div>

<?php if ($settings->bool('enable_transitions', true)): ?>
<div class="page-veil" aria-hidden="true"></div>
<?php endif; ?>

<a class="skip-link" href="#main">Skip to main content</a>

<?php $minimalChrome = $bodyClass === 'page-home'; ?>
<?php if (!$minimalChrome): ?>
<?= $view->partial('partials/header') ?>
<?php endif; ?>

<main id="main" class="site-main" tabindex="-1">
    <?= $content ?>
</main>

<?php if (!$minimalChrome): ?>
<?= $view->partial('partials/footer') ?>
<?php endif; ?>

<?php /* Live region for toasts. Present from first paint, because a region
   created and filled in the same tick is routinely missed by screen readers. */ ?>
<div class="toast-stack" role="status" aria-live="polite"></div>

<?php foreach ($flash as $item): ?>
<div data-flash="<?= e($item['type']) ?>" data-flash-message="<?= e($item['message']) ?>" hidden></div>
<?php endforeach; ?>

<?php $waFloat = $minimalChrome ? '' : whatsapp_link(); if ($waFloat !== ''): ?>
<a class="float-action" href="<?= e($waFloat) ?>" target="_blank" rel="noopener noreferrer"
   aria-label="Message us on WhatsApp" data-no-transition>
    <?= icon('whatsapp') ?>
</a>
<?php endif; ?>

<script nonce="<?= e(\Techbiss\Core\App::nonce()) ?>">
/* Only what app.js reads. The CSRF token was published into a global here
   and never used — the forms carry it in a hidden field. */
window.TECHBISS = {
    cursor: <?= $settings->bool('enable_cursor', true) ? 'true' : 'false' ?>,
    transitions: <?= $settings->bool('enable_transitions', true) ? 'true' : 'false' ?>,
    ambient: <?= $settings->bool('enable_ambient', true) ? 'true' : 'false' ?>
};
</script>
<script src="<?= e(asset('assets/js/app.js')) ?>" defer></script>

<?php if ($gaId !== '' && preg_match('/^(G|UA|GTM)-[A-Za-z0-9-]+$/', $gaId)): ?>
<script async src="https://www.googletagmanager.com/gtag/js?id=<?= e($gaId) ?>"></script>
<script nonce="<?= e(\Techbiss\Core\App::nonce()) ?>">
window.dataLayer = window.dataLayer || [];
function gtag(){dataLayer.push(arguments);}
gtag('js', new Date());
gtag('config', <?= ejs($gaId) ?>);
</script>
<?php endif; ?>
</body>
</html>
