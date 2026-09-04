<?php
require_once __DIR__ . '/../includes/db.php';
header('Content-Type: application/json');

$customer = current_customer();
if (!$customer) {
    send_json(['user' => null], 401);
}

send_json(['user' => ['name' => $customer['name'], 'email' => $customer['email']]]);
