<?php
/**
 * Icon and manifest links.
 *
 * An administrator can upload a replacement favicon in Settings; when they have,
 * it wins and the bundled set is skipped, so a client's own branding is never
 * shown alongside ours.
 */
$custom = media_url($settings->get('favicon'));
?>
<?php if ($custom !== ''): ?>
    <link rel="icon" href="<?= e($custom) ?>">
    <link rel="apple-touch-icon" href="<?= e($custom) ?>">
<?php else: ?>
    <?php /* SVG first for browsers that take it — it stays sharp at any density. */ ?>
    <link rel="icon" type="image/svg+xml" href="<?= e(asset('assets/images/brand/favicon.svg')) ?>">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= e(asset('assets/images/brand/favicon-32.png')) ?>">
    <link rel="icon" type="image/png" sizes="16x16" href="<?= e(asset('assets/images/brand/favicon-16.png')) ?>">
    <?php /* Bare /favicon.ico is still requested directly by some crawlers and older browsers. */ ?>
    <link rel="shortcut icon" href="<?= e(asset('assets/images/brand/favicon.ico')) ?>">
    <link rel="apple-touch-icon" sizes="180x180" href="<?= e(asset('assets/images/brand/apple-touch-icon.png')) ?>">
    <link rel="manifest" href="<?= e(url('/site.webmanifest')) ?>">
<?php endif; ?>
<link rel="mask-icon" href="<?= e(asset('assets/images/brand/favicon.svg')) ?>" color="#4F8CFF">
<?php /* Saved to a home screen: an app-like title and chrome instead of the browser's own. */ ?>
<meta name="apple-mobile-web-app-title" content="<?= e($settings->get('site_name', 'TECHBISS')) ?>">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">

