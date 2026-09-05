<?php
require_once __DIR__ . '/../includes/db.php';
header('Content-Type: application/json');
require_installed_api();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json(['error' => 'Method not allowed'], 405);
}
require_same_origin();

if (!rate_limit_hit('otpverify:' . client_ip(), 30, 3600)) {
    send_json(['error' => 'Too many attempts. Please wait a few minutes and try again.'], 429);
}

$body = json_body();
$token = trim((string)($body['token'] ?? ''));
$email = trim(strtolower((string)($body['email'] ?? '')));
$code = trim((string)($body['code'] ?? ''));

$customerId = null;

if ($token !== '') {
    $row = otp_verify_token('login', $token);
    if ($row) {
        $customerId = (int)$row['customer_id'];
    }
} elseif ($email !== '' && $code !== '') {
    $stmt = db()->prepare('SELECT id FROM customers WHERE email = ?');
    $stmt->execute([$email]);
    $customer = $stmt->fetch();
    if ($customer && otp_verify_code((int)$customer['id'], 'login', $code)) {
        $customerId = (int)$customer['id'];
    }
} else {
    send_json(['error' => 'Enter the code we sent you.'], 400);
}

if (!$customerId) {
    send_json(['error' => 'That code is invalid or has expired.'], 401);
}

$stmt = db()->prepare('SELECT name, email FROM customers WHERE id = ?');
$stmt->execute([$customerId]);
$customer = $stmt->fetch();

session_regenerate_id(true);
$_SESSION['customer_id'] = $customerId;

send_json(['user' => ['name' => $customer['name'], 'email' => $customer['email']]]);
