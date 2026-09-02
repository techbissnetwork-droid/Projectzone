<?php
declare(strict_types=1);
require_once __DIR__ . '/app/bootstrap.php';
Auth::logout();
session_start();
Flash::ok('You have been signed out.');
header('Location: ' . url('login.php'));
exit;
