<?php
require_once __DIR__ . '/../includes/db.php';
header('Content-Type: application/json');
require_installed_api();

// A plain GET here let any third-party page sign visitors out.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json(['error' => 'Method not allowed'], 405);
}
require_same_origin();

unset($_SESSION['customer_id']);
session_regenerate_id(true);

send_json(['ok' => true]);
