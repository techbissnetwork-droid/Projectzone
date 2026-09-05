<?php
require_once __DIR__ . '/../includes/db.php';
header('Content-Type: application/json');
require_installed_api();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json(['error' => 'Method not allowed'], 405);
}
require_same_origin();

/**
 * This endpoint does not charge anyone — there is no payment processor
 * wired in (see README). Left open, it handed a working download link for
 * any paid product to any anonymous caller, so it is off unless an admin
 * explicitly turns it on in Settings, and the buy button routes to Contact
 * instead. Wire a real processor (Stripe Checkout + a verified
 * payment_intent.succeeded webhook) before turning this on for real money.
 */
if (get_setting('payments_enabled', 'off') !== 'on') {
    send_json(['error' => 'Online checkout isn\'t available right now — please contact us and we\'ll sort it out directly.'], 503);
}

// Ordering is cheap to script, so bound it per address regardless of who
// is signed in.
if (!rate_limit_hit('purchase:' . client_ip(), 10, 3600)) {
    send_json(['error' => 'Too many orders from this connection. Please try again later.'], 429);
}

$body = json_body();
$productId = trim((string)($body['product_id'] ?? ''));

$pdo = db();
$stmt = $pdo->prepare('SELECT id, name, price, download_path FROM products WHERE id = ?');
$stmt->execute([$productId]);
$product = $stmt->fetch();
if (!$product) {
    send_json(['error' => 'That product could not be found.'], 404);
}
if (!$product['download_path']) {
    send_json(['error' => 'This product isn\'t available for instant purchase yet — please contact us instead.'], 409);
}

// The price is read from the product row, never from the request. The old
// code took a client-supplied "total" and stored it as price_cents, so any
// caller could record a $0 order — or a $1,000,000 one.
$priceCents = (int)round((float)$product['price'] * 100);

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
    $existing = $pdo->prepare('SELECT id FROM customers WHERE email = ?');
    $existing->execute([$email]);
    if ($existing->fetch()) {
        // Guest checkout used to attach the order to whichever account
        // already owned this address and email them a receipt — letting a
        // stranger fill someone else's dashboard with orders they never
        // placed, and send mail from this domain in their name.
        send_json([
            'error' => 'That email already has an account — please sign in first, then come back to complete your order.',
            'needs_login' => true,
        ], 409);
    }
    $pdo->prepare('INSERT INTO customers (name, email) VALUES (?, ?)')->execute([$name, $email]);
    $customerId = (int)$pdo->lastInsertId();
    $customerName = $name;
    $customerEmail = $email;
}

$orderRef = 'TB-' . random_int(100000, 999999);
$downloadToken = bin2hex(random_bytes(24));
$pdo->prepare(
    'INSERT INTO product_orders (order_ref, product_id, customer_id, price_cents, download_token, download_expires_at)
     VALUES (?,?,?,?,?, (NOW() + INTERVAL 30 DAY))'
)->execute([$orderRef, $product['id'], $customerId, $priceCents, $downloadToken]);

$downloadUrl = rtrim(SITE_URL, '/') . '/api/download.php?token=' . $downloadToken;
$mailBody = "Hi " . $customerName . ",\n\n"
    . "Thanks for your order! Here's your download for " . $product['name'] . ":\n\n"
    . $downloadUrl . "\n\n"
    . "Order reference: " . $orderRef . "\n\n"
    . "This link works for 30 days. You can also come back and download it any time from your dashboard — sign in with this email address (" . $customerEmail . ") to get a one-time code, no password needed.";
send_mail($customerEmail, 'Your TECHBISS order — ' . $product['name'], $mailBody);

send_json(['ok' => true, 'order_ref' => $orderRef, 'download_url' => $downloadUrl]);
