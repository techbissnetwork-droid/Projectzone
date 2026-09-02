<?php
declare(strict_types=1);
require_once __DIR__ . '/app/bootstrap.php';

/* Drop the identity and rotate the session id, but keep the session itself
   alive so the confirmation survives the redirect. */
log_activity('logout', 'user', (int)($_SESSION['uid'] ?? 0));
$_SESSION = [];
session_regenerate_id(true);
Flash::ok('You have been signed out.');
redirect('login.php');
