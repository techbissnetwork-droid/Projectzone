<?php
require_once __DIR__ . '/../includes/db.php';
header('Content-Type: application/json');
require_installed_api();

$customer = current_customer();
if (!$customer) {
    send_json(['error' => 'Not signed in'], 401);
}

$pdo = db();
$stmt = $pdo->prepare('SELECT id, name FROM businesses WHERE customer_id = ?');
$stmt->execute([$customer['id']]);
$business = $stmt->fetch();

if (!$business) {
    send_json(['business' => null, 'projects' => []]);
}

$stmt = $pdo->prepare(
    'SELECT id, title, status, progress_pct, domain,
            domain_expires_at, hosting_expires_at, ssl_expires_at, email_expires_at, notes
     FROM projects WHERE business_id = ? ORDER BY created_at DESC'
);
$stmt->execute([$business['id']]);
$projects = $stmt->fetchAll();

send_json(['business' => ['name' => $business['name']], 'projects' => $projects]);
