<?php
require_once __DIR__ . '/../includes/db.php';
require_installed_api();

$token = trim((string)($_GET['token'] ?? ''));
if ($token === '') {
    http_response_code(400);
    exit('Missing download token.');
}

$pdo = db();
$stmt = $pdo->prepare(
    'SELECT o.id, p.name, p.download_path FROM product_orders o
     JOIN products p ON p.id = o.product_id
     WHERE o.download_token = ?'
);
$stmt->execute([$token]);
$order = $stmt->fetch();

if (!$order || !$order['download_path']) {
    http_response_code(404);
    exit('This download link is invalid or has expired.');
}

$path = __DIR__ . '/../' . $order['download_path'];
if (!is_file($path)) {
    http_response_code(404);
    exit('This file is no longer available — please contact us.');
}

$ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
$mime = $ext === 'pdf' ? 'application/pdf' : 'application/zip';
$filename = preg_replace('/[^A-Za-z0-9 _.-]/', '', $order['name']) . '.' . $ext;

header('Content-Type: ' . $mime);
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($path));
readfile($path);
