<?php
require_once __DIR__ . '/../includes/db.php';
header('Content-Type: application/json');
require_installed_api();

$customer = current_customer();
if (!$customer) {
    send_json(['error' => 'Not signed in'], 401);
}

$pdo = db();

$orderStmt = $pdo->prepare(
    'SELECT o.order_ref, o.download_token, o.created_at, p.name AS product_name
     FROM product_orders o JOIN products p ON p.id = o.product_id
     WHERE o.customer_id = ? ORDER BY o.created_at DESC'
);
$orderStmt->execute([$customer['id']]);
$orders = array_map(function ($o) {
    return [
        'order_ref' => $o['order_ref'],
        'product_name' => $o['product_name'],
        'created_at' => $o['created_at'],
        'download_url' => rtrim(SITE_URL, '/') . '/api/download.php?token=' . $o['download_token'],
    ];
}, $orderStmt->fetchAll());

$stmt = $pdo->prepare('SELECT id, name FROM businesses WHERE customer_id = ? ORDER BY id');
$stmt->execute([$customer['id']]);
$businesses = $stmt->fetchAll();

if (!$businesses) {
    send_json(['businesses' => [], 'orders' => $orders]);
}

$projStmt = $pdo->prepare(
    'SELECT id, title, status, progress_pct, domain,
            domain_expires_at, hosting_expires_at, ssl_expires_at, email_expires_at, notes
     FROM projects WHERE business_id = ? ORDER BY created_at DESC'
);
$openProjectTaskStmt = $pdo->prepare(
    "SELECT id, title, status FROM tickets WHERE project_id = ? AND type = 'project_task' AND status != 'Closed' LIMIT 1"
);
$openNewProjectStmt = $pdo->prepare(
    "SELECT id, title, status FROM tickets WHERE business_id = ? AND type = 'new_project' AND status != 'Closed' LIMIT 1"
);

$result = [];
foreach ($businesses as $b) {
    $projStmt->execute([$b['id']]);
    $projects = $projStmt->fetchAll();
    foreach ($projects as &$p) {
        $openProjectTaskStmt->execute([$p['id']]);
        $p['open_ticket'] = $openProjectTaskStmt->fetch() ?: null;
    }
    unset($p);

    $openNewProjectStmt->execute([$b['id']]);
    $openRequest = $openNewProjectStmt->fetch() ?: null;

    $result[] = [
        'id' => (int)$b['id'],
        'name' => $b['name'],
        'projects' => $projects,
        'open_request_ticket' => $openRequest,
    ];
}

send_json(['businesses' => $result, 'orders' => $orders]);
