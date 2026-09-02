<?php
/** Minimal layout for maintenance and standalone screens. */
$siteName = $settings->get('site_name', 'TECHBISS');
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($siteName) ?></title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="stylesheet" href="<?= e(asset('assets/css/design-system.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('assets/css/site.css')) ?>">
</head>
<body>
<main class="section" style="min-height:100dvh;display:grid;place-items:center">
    <?= $content ?>
</main>
</body>
</html>
