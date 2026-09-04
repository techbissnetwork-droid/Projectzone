<?php
require_once __DIR__ . '/../includes/db.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json(['error' => 'Method not allowed'], 405);
}

$body = json_body();
$email = trim(strtolower((string)($body['email'] ?? '')));
$password = (string)($body['password'] ?? '');

if ($email === '' || $password === '') {
    send_json(['error' => 'Please enter your email and password.'], 400);
}

// A short delay makes user-enumeration and brute-force timing attacks harder.
usleep(200000);

$stmt = db()->prepare('SELECT id, name, password_hash FROM customers WHERE email = ?');
$stmt->execute([$email]);
$customer = $stmt->fetch();

if (!$customer || !password_verify($password, $customer['password_hash'])) {
    send_json(['error' => 'Invalid email or password.'], 401);
}

session_regenerate_id(true);
$_SESSION['customer_id'] = (int)$customer['id'];

send_json(['user' => ['name' => $customer['name'], 'email' => $email]]);
