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
$themeMode  = $settings->get('theme_mode', 'dark') === 'light' ? 'light' : 'dark';
$allowTheme = $settings->bool('allow_theme_toggle', true);
$favicon    = media_url($settings->get('favicon'));
$bodyClass  = $bodyClass ?? '';
$gaId       = $settings->get('google_analytics_id');
?><!doctype html>
<html lang="<?= e($settings->get('locale', 'en')) ?>" data-theme="<?= e($themeMode) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="<?= $themeMode === 'light' ? '#f7f8fb' : '#06070c' ?>">
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

    <?php if ($favicon !== ''): ?>
    <link rel="icon" href="<?= e($favicon) ?>">
    <?php else: ?>
    <link rel="icon" href="data:image/svg+xml,<?= rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32"><rect width="32" height="32" rx="8" fill="#4f8cff"/><text x="16" y="22" font-family="system-ui,sans-serif" font-size="16" font-weight="700" fill="#fff" text-anchor="middle">T</text></svg>') ?>">
    <?php endif; ?>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" media="print" onload="this.media='all'">
    <noscript><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap"></noscript>

    <link rel="stylesheet" href="<?= e(asset('assets/css/design-system.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('assets/css/site.css')) ?>">

    <script>
    // Applied before first paint so a stored theme preference never flashes.
    (function () {
        try {
            var stored = localStorage.getItem('techbiss-theme');
            if (stored === 'light' || stored === 'dark') {
                document.documentElement.setAttribute('data-theme', stored);
            }
        } catch (e) {}
    })();
    </script>
</head>
<body class="<?= e($bodyClass) ?><?= $settings->bool('enable_loader', true) ? ' is-loading' : '' ?>">

<?php if ($settings->bool('enable_loader', true)): ?>
<div class="loader" role="status" aria-live="polite">
    <div class="loader__inner">
        <div class="loader__mark"><?= e($siteName) ?></div>
        <div class="loader__line" aria-hidden="true"></div>
        <div class="loader__caption">Preparing your experience</div>
    </div>
</div>
<?php endif; ?>

<?php if ($settings->bool('enable_transitions', true)): ?>
<div class="page-veil" aria-hidden="true"></div>
<?php endif; ?>

<a class="skip-link" href="#main">Skip to main content</a>

<?= $view->partial('partials/header', ['allowTheme' => $allowTheme]) ?>

<main id="main" class="site-main" tabindex="-1">
    <?= $content ?>
</main>

<?= $view->partial('partials/footer') ?>

<?php foreach ($flash as $item): ?>
<div data-flash="<?= e($item['type']) ?>" data-flash-message="<?= e($item['message']) ?>" hidden></div>
<?php endforeach; ?>

<?php
$whatsapp = preg_replace('/[^0-9]/', '', $settings->get('whatsapp'));
if ($whatsapp !== '' && strlen($whatsapp) >= 8):
?>
<a class="float-action" href="https://wa.me/<?= e($whatsapp) ?>" target="_blank" rel="noopener noreferrer"
   aria-label="Message us on WhatsApp" data-no-transition>
    <?= icon('whatsapp') ?>
</a>
<?php endif; ?>

<script>
window.TECHBISS = {
    base: <?= ejs(url('/')) ?>,
    cursor: <?= $settings->bool('enable_cursor', true) ? 'true' : 'false' ?>,
    transitions: <?= $settings->bool('enable_transitions', true) ? 'true' : 'false' ?>,
    csrf: <?= ejs(csrf_token()) ?>
};
</script>
<script src="<?= e(asset('assets/js/app.js')) ?>" defer></script>

<?php if ($gaId !== '' && preg_match('/^(G|UA|GTM)-[A-Za-z0-9-]+$/', $gaId)): ?>
<script async src="https://www.googletagmanager.com/gtag/js?id=<?= e($gaId) ?>"></script>
<script>
window.dataLayer = window.dataLayer || [];
function gtag(){dataLayer.push(arguments);}
gtag('js', new Date());
gtag('config', <?= ejs($gaId) ?>);
</script>
<?php endif; ?>
</body>
</html>
