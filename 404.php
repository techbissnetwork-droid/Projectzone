<?php
require_once __DIR__ . '/app/bootstrap.php';
require_once APP_DIR . '/partials/sections.php';
http_response_code(404);
$pageTitle = 'Not found — ' . setting('site.name', 'TECHBISS');
$pageDesc  = '';
$activeNav = '';
include APP_DIR . '/partials/head.php';
page_head('404', 'That page', 'is not here.',
    'It may have moved, or the address may have a typo in it. Everything else is still where you left it.',
    [['index.php', 'Home'], [null, 'Not found']],
    [['index.php', 'Back to the site &rarr;'], ['contact.php', 'Tell us what you were after']]);
include APP_DIR . '/partials/footer.php';
