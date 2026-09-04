<?php
require_once __DIR__ . '/../includes/db.php';
header('Content-Type: application/json');
require_installed_api();

$customer = current_customer();
if (!$customer) {
    send_json(['error' => 'Not signed in'], 401);
}

$pdo = db();
$stmt = $pdo->prepare('SELECT id, name FROM businesses WHERE customer_id = ? ORDER BY id');
$stmt->execute([$customer['id']]);
$businesses = $stmt->fetchAll();

if (!$businesses) {
    send_json(['businesses' => []]);
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

send_json(['businesses' => $result]);
