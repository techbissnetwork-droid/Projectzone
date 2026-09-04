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

$body = json_body();
$projectId = (int)($body['project_id'] ?? 0);
$title = trim((string)($body['title'] ?? ''));
$description = trim((string)($body['description'] ?? ''));

if ($title === '') {
    send_json(['error' => 'Please describe what you need.'], 400);
}

$pdo = db();
$stmt = $pdo->prepare(
    'SELECT p.id, p.business_id FROM projects p
     JOIN businesses b ON b.id = p.business_id
     WHERE p.id = ? AND b.customer_id = ?'
);
$stmt->execute([$projectId, $customer['id']]);
$project = $stmt->fetch();
if (!$project) {
    send_json(['error' => 'That project could not be found on your account.'], 404);
}

$openCheck = $pdo->prepare(
    "SELECT id FROM tickets WHERE project_id = ? AND type = 'project_task' AND status != 'Closed'"
);
$openCheck->execute([$projectId]);
if ($openCheck->fetch()) {
    send_json(['error' => 'You already have an open request for this project — we\'ll follow up soon.'], 409);
}

$ticketTitle = mb_substr($title, 0, 255);
$stmt = $pdo->prepare(
    "INSERT INTO tickets (business_id, project_id, title, description, type, priority, status) VALUES (?, ?, ?, ?, 'project_task', ?, ?)"
);
$stmt->execute([$project['business_id'], $projectId, $ticketTitle, $description !== '' ? $description : null, 'Normal', 'Open']);

send_json(['ok' => true]);
