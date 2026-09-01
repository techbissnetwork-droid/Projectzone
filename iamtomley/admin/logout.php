<?php
require_once __DIR__ . '/_bootstrap.php';
logout();
header('Location: ' . url('/admin/login.php'));
exit;
