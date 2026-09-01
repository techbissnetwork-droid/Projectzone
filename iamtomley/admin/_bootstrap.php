<?php
/** Common admin bootstrap: DB, auth, session. Include at the top of pages. */
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
security_headers();
start_session();

// Force setup before the admin can be used.
if (!is_installed() && is_dir(__DIR__ . '/../install')) {
    header('Location: ' . url('/install/'));
    exit;
}
