<?php
require_once __DIR__ . '/../includes/db.php';
header('Content-Type: application/json');
require_installed_api();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json(['error' => 'Method not allowed'], 405);
}

$body = json_body();
$productId = trim((string)($body['product_id'] ?? ''));
$total = max(0, (float)($body['total'] ?? 0));

$pdo = db();
$stmt = $pdo->prepare('SELECT id, name, download_path FROM products WHERE id = ?');
$stmt->execute([$productId]);
$product = $stmt->fetch();
if (!$product) {
    send_json(['error' => 'That product could not be found.'], 404);
}
if (!$product['download_path']) {
    send_json(['error' => 'This product isn\'t available for instant purchase yet — please contact us instead.'], 409);
}

$customer = current_customer();
if ($customer) {
    $customerId = (int)$customer['id'];
    $customerName = $customer['name'];
    $customerEmail = $customer['email'];
} else {
    $name = trim((string)($body['name'] ?? ''));
    $email = trim(strtolower((string)($body['email'] ?? '')));
    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        send_json(['error' => 'Enter a valid name and email address.'], 400);
    }
    $existing = $pdo->prepare('SELECT id, name FROM customers WHERE email = ?');
    $existing->execute([$email]);
    $row = $existing->fetch();
    if ($row) {
        $customerId = (int)$row['id'];
        $customerName = $row['name'];
    } else {
        $pdo->prepare('INSERT INTO customers (name, email) VALUES (?, ?)')->execute([$name, $email]);
        $customerId = (int)$pdo->lastInsertId();
        $customerName = $name;
    }
    $customerEmail = $email;
}

$orderRef = 'TB-' . random_int(100000, 999999);
$downloadToken = bin2hex(random_bytes(24));
$pdo->prepare('INSERT INTO product_orders (order_ref, product_id, customer_id, price_cents, download_token) VALUES (?,?,?,?,?)')
    ->execute([$orderRef, $product['id'], $customerId, (int)round($total * 100), $downloadToken]);

$downloadUrl = rtrim(SITE_URL, '/') . '/api/download.php?token=' . $downloadToken;
$mailBody = "Hi " . $customerName . ",\n\n"
    . "Thanks for your order! Here's your download for " . $product['name'] . ":\n\n"
    . $downloadUrl . "\n\n"
    . "Order reference: " . $orderRef . "\n\n"
    . "You can also come back and download it any time from your dashboard — sign in with this email address (" . $customerEmail . ") to get a one-time code, no password needed.";
send_mail($customerEmail, 'Your TECHBISS order — ' . $product['name'], $mailBody);

send_json(['ok' => true, 'order_ref' => $orderRef, 'download_url' => $downloadUrl]);
