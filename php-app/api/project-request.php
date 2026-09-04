<?php
require_once __DIR__ . '/../includes/db.php';
header('Content-Type: application/json');
require_installed_api();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json(['error' => 'Method not allowed'], 405);
}

$customer = current_customer();
if (!$customer) {
    send_json(['error' => 'Not signed in'], 401);
}

$pdo = db();
$stmt = $pdo->prepare('SELECT id FROM businesses WHERE customer_id = ? ORDER BY id');
$stmt->execute([$customer['id']]);
$businesses = $stmt->fetchAll();
if (!$businesses) {
    send_json(['error' => 'Your account isn\'t linked to a business yet — please use the Contact page instead.'], 400);
}

$body = json_body();
$title = trim((string)($body['title'] ?? ''));
$description = trim((string)($body['description'] ?? ''));
$requestedId = (int)($body['business_id'] ?? 0);

if (count($businesses) === 1) {
    $businessId = (int)$businesses[0]['id'];
} else {
    $businessId = null;
    foreach ($businesses as $b) {
        if ((int)$b['id'] === $requestedId) {
            $businessId = $requestedId;
            break;
        }
    }
    if ($businessId === null) {
        send_json(['error' => 'Please choose which business this request is for.'], 400);
    }
}

if ($title === '') {
    send_json(['error' => 'Please describe what you\'d like built.'], 400);
}

$ticketTitle = 'New project request: ' . $title;
if ($description !== '') {
    $ticketTitle .= ' — ' . $description;
}
$ticketTitle = mb_substr($ticketTitle, 0, 255);

$stmt = $pdo->prepare('INSERT INTO tickets (business_id, title, priority, status) VALUES (?, ?, ?, ?)');
$stmt->execute([$businessId, $ticketTitle, 'Normal', 'Open']);

send_json(['ok' => true]);
