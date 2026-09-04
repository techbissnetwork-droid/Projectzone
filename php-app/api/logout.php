<?php
require_once __DIR__ . '/../includes/db.php';
header('Content-Type: application/json');

unset($_SESSION['customer_id']);
session_regenerate_id(true);

send_json(['ok' => true]);
