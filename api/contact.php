<?php
require_once __DIR__ . '/../includes/db.php';
header('Content-Type: application/json');
require_installed_api();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json(['error' => 'Method not allowed'], 405);
}
require_same_origin();

$body = json_body();

// Bots fill in every field they can see. This one is hidden from people,
// so anything in it is automated — answer 200 so the bot believes it
// worked and doesn't retry, but save nothing.
if (trim((string)($body['website'] ?? '')) !== '') {
    send_json(['ok' => true]);
}

if (!rate_limit_hit('contact:' . client_ip(), 5, 3600)) {
    send_json(['error' => 'Thanks — we\'ve already got your message. Please give us a little time to reply.'], 429);
}

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

/**
 * Until now this table was written and never read: no admin screen, no
 * notification. Every enquiry from the site's primary call to action was
 * silently buried. Staff can now read them at /admin/messages.php, and
 * this mail means nobody has to remember to go looking.
 */
$notify = trim(get_setting('contact_notify_email', '')) ?: trim(get_setting('contact_email', ''));
if ($notify !== '' && filter_var($notify, FILTER_VALIDATE_EMAIL)) {
    $siteName = get_setting('site_name', 'TECHBISS');
    send_mail(
        $notify,
        'New enquiry from ' . $name . ' — ' . $siteName,
        "New message from the {$siteName} contact form.\n\n"
        . "Name:    {$name}\n"
        . "Email:   {$email}\n"
        . ($company !== '' ? "Company: {$company}\n" : '')
        . ($need !== '' ? "Needs:   {$need}\n" : '')
        . "\n{$message}\n\n"
        . "Read and manage every enquiry at " . rtrim(SITE_URL, '/') . "/admin/messages.php"
    );
}

send_json(['ok' => true]);
