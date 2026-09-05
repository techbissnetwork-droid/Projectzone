<?php
require_once __DIR__ . '/../includes/db.php';
header('Content-Type: application/json');
require_installed_api();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json(['error' => 'Method not allowed'], 405);
}
require_same_origin();

$body = json_body();
$email = trim(strtolower((string)($body['email'] ?? '')));
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    send_json(['error' => 'Enter a valid email address.'], 400);
}

// otp_issue() caps codes per account; this caps one source address
// sweeping many accounts, and stops the endpoint being used to send mail
// on someone else's behalf at volume.
if (!rate_limit_hit('otp:ip:' . client_ip(), 15, 3600)) {
    send_json(['error' => 'Too many requests. Please wait a few minutes and try again.'], 429);
}

$stmt = db()->prepare('SELECT id, name FROM customers WHERE email = ?');
$stmt->execute([$email]);
$customer = $stmt->fetch();

if ($customer) {
    try {
        $otp = otp_issue((int)$customer['id'], 'login');
    } catch (RuntimeException $e) {
        send_json(['error' => $e->getMessage()], 429);
    }
    $link = rtrim(SITE_URL, '/') . '/login?token=' . $otp['token'];
    $mailBody = "Hi " . $customer['name'] . ",\n\n"
        . "Your TECHBISS sign-in code is: " . $otp['code'] . "\n\n"
        . "It expires in 10 minutes. Or skip the code and use this link to sign in instantly:\n"
        . $link . "\n\n"
        . "If you didn't request this, you can safely ignore this email.";
    send_mail($email, 'Your TECHBISS sign-in code', $mailBody);
}

// Same response whether or not the email has an account, so we don't
// reveal which addresses are registered.
send_json(['ok' => true]);
