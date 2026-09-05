<?php
require_once __DIR__ . '/../includes/db.php';
header('Content-Type: application/json');
require_installed_api();

$customer = current_customer();
if (!$customer) {
    send_json(['error' => 'Not signed in'], 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json(['error' => 'Method not allowed'], 405);
}

$body = json_body();
$action = (string)($body['action'] ?? '');
$pdo = db();

if ($action === 'request') {
    $newEmail = trim(strtolower((string)($body['new_email'] ?? '')));
    if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
        send_json(['error' => 'Enter a valid email address.'], 400);
    }
    if ($newEmail === strtolower($customer['email'])) {
        send_json(['error' => 'That\'s already your email address.'], 400);
    }
    $dupe = $pdo->prepare('SELECT id FROM customers WHERE email = ?');
    $dupe->execute([$newEmail]);
    if ($dupe->fetch()) {
        send_json(['error' => 'Another account already uses that email address.'], 409);
    }

    try {
        $otp = otp_issue((int)$customer['id'], 'email_change_old', $newEmail);
    } catch (RuntimeException $e) {
        send_json(['error' => $e->getMessage()], 429);
    }
    send_mail(
        $customer['email'],
        'Confirm your TECHBISS email change',
        "Hi " . $customer['name'] . ",\n\nSomeone requested to change the email on your TECHBISS account to $newEmail.\n\n"
        . "Your confirmation code is: " . $otp['code'] . "\n\nIt expires in 10 minutes. If this wasn't you, ignore this email and your account stays as-is."
    );
    send_json(['step' => 'verify_old']);
} elseif ($action === 'verify_old') {
    $code = trim((string)($body['code'] ?? ''));
    $row = otp_verify_code((int)$customer['id'], 'email_change_old', $code);
    if (!$row) {
        send_json(['error' => 'That code is invalid or has expired.'], 401);
    }
    $newEmail = $row['new_email'];

    // Re-check uniqueness in case someone else claimed it while this was pending.
    $dupe = $pdo->prepare('SELECT id FROM customers WHERE email = ?');
    $dupe->execute([$newEmail]);
    if ($dupe->fetch()) {
        send_json(['error' => 'That email address was just claimed by another account — start over with a different one.'], 409);
    }

    try {
        $otp = otp_issue((int)$customer['id'], 'email_change_new', $newEmail);
    } catch (RuntimeException $e) {
        send_json(['error' => $e->getMessage()], 429);
    }
    send_mail(
        $newEmail,
        'Confirm your new TECHBISS email',
        "Your confirmation code to finish moving your TECHBISS account to this email is: " . $otp['code'] . "\n\nIt expires in 10 minutes."
    );
    send_json(['step' => 'verify_new']);
} elseif ($action === 'verify_new') {
    $code = trim((string)($body['code'] ?? ''));
    $row = otp_verify_code((int)$customer['id'], 'email_change_new', $code);
    if (!$row) {
        send_json(['error' => 'That code is invalid or has expired.'], 401);
    }
    $newEmail = $row['new_email'];

    $dupe = $pdo->prepare('SELECT id FROM customers WHERE email = ? AND id != ?');
    $dupe->execute([$newEmail, $customer['id']]);
    if ($dupe->fetch()) {
        send_json(['error' => 'That email address was just claimed by another account — start over with a different one.'], 409);
    }

    $pdo->prepare('UPDATE customers SET email = ? WHERE id = ?')->execute([$newEmail, $customer['id']]);
    send_json(['ok' => true, 'email' => $newEmail]);
} else {
    send_json(['error' => 'Unknown action'], 400);
}
