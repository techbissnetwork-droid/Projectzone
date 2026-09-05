<?php
require_once __DIR__ . '/../includes/db.php';
header('Content-Type: application/json');
require_installed_api();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json(['error' => 'Method not allowed'], 405);
}

$body = json_body();
$email = trim(strtolower((string)($body['email'] ?? '')));

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    send_json(['error' => 'Please enter a valid email address.'], 400);
}

$stmt = db()->prepare('INSERT IGNORE INTO newsletter_subscribers (email) VALUES (?)');
$stmt->execute([$email]);

send_json(['ok' => true]);
