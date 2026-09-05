<?php
require_once __DIR__ . '/../includes/db.php';
header('Content-Type: application/json');
require_installed_api();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json(['error' => 'Method not allowed'], 405);
}

$body = json_body();
$name = trim((string)($body['name'] ?? ''));
$email = trim((string)($body['email'] ?? ''));
$company = trim((string)($body['company'] ?? ''));
$need = trim((string)($body['need'] ?? ''));
$message = trim((string)($body['message'] ?? ''));

if ($name === '' || $message === '') {
    send_json(['error' => 'Please fill in your name and a short message.'], 400);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    send_json(['error' => 'Please enter a valid email address.'], 400);
}

$stmt = db()->prepare(
    'INSERT INTO contact_messages (name, email, company, need, message) VALUES (?, ?, ?, ?, ?)'
);
$stmt->execute([$name, $email, $company ?: null, $need ?: null, $message]);

send_json(['ok' => true]);
