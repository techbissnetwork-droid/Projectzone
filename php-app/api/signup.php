<?php
require_once __DIR__ . '/../includes/db.php';
header('Content-Type: application/json');
require_installed_api();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json(['error' => 'Method not allowed'], 405);
}

$body = json_body();
$name = trim((string)($body['name'] ?? ''));
$email = trim(strtolower((string)($body['email'] ?? '')));
$password = (string)($body['password'] ?? '');

if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    send_json(['error' => 'Please enter your name and a valid email address.'], 400);
}
if (strlen($password) < 8) {
    send_json(['error' => 'Password must be at least 8 characters.'], 400);
}

$pdo = db();
$exists = $pdo->prepare('SELECT id FROM customers WHERE email = ?');
$exists->execute([$email]);
if ($exists->fetch()) {
    send_json(['error' => 'An account with that email already exists.'], 400);
}

$stmt = $pdo->prepare('INSERT INTO customers (name, email, password_hash) VALUES (?, ?, ?)');
$stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);
$newId = (int)$pdo->lastInsertId();

session_regenerate_id(true);
$_SESSION['customer_id'] = $newId;

send_json(['user' => ['name' => $name, 'email' => $email]]);
