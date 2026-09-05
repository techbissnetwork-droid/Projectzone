<?php
require_once __DIR__ . '/../includes/db.php';
require_installed_api();

$token = trim((string)($_GET['token'] ?? ''));
if ($token === '') {
    http_response_code(400);
    exit('Missing download token.');
}

// Guessing a 48-character token isn't realistic, but the attempt shouldn't
// be free either.
if (!rate_limit_hit('download:' . client_ip(), 60, 3600)) {
    http_response_code(429);
    exit('Too many download attempts. Please try again later.');
}

$pdo = db();
$stmt = $pdo->prepare(
    'SELECT o.id, o.download_expires_at, o.download_count, p.name, p.download_path
     FROM product_orders o
     JOIN products p ON p.id = o.product_id
     WHERE o.download_token = ?'
);
$stmt->execute([$token]);
$order = $stmt->fetch();

if (!$order || !$order['download_path']) {
    http_response_code(404);
    exit('This download link is invalid or has expired.');
}

// The message always claimed links could expire; now they actually do.
// Without this, a link forwarded once — or lifted from an inbox years
// later — stayed a permanent, unlimited, anonymous download of a paid file.
if ($order['download_expires_at'] !== null && strtotime((string)$order['download_expires_at']) < time()) {
    http_response_code(410);
    exit('This download link has expired. Sign in to your dashboard to get a fresh one.');
}
if ((int)$order['download_count'] >= 25) {
    http_response_code(429);
    exit('This download link has been used too many times. Sign in to your dashboard to get a fresh one.');
}

// download_path is written by staff, so this isn't reachable today — but a
// stored value must never be able to address a file outside the products
// folder, whatever future code writes it.
$uploadRoot = realpath(__DIR__ . '/../assets/uploads/products');
$path = realpath(__DIR__ . '/../' . $order['download_path']);
if ($uploadRoot === false || $path === false || !str_starts_with($path, $uploadRoot . DIRECTORY_SEPARATOR)) {
    http_response_code(404);
    exit('This file is no longer available — please contact us.');
}
if (!is_file($path)) {
    http_response_code(404);
    exit('This file is no longer available — please contact us.');
}

$pdo->prepare('UPDATE product_orders SET download_count = download_count + 1 WHERE id = ?')
    ->execute([$order['id']]);

$ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
$mime = $ext === 'pdf' ? 'application/pdf' : 'application/zip';
$filename = preg_replace('/[^A-Za-z0-9 _.-]/', '', $order['name']) . '.' . $ext;

// Any stray output buffered before this point would corrupt the file and
// make Content-Length a lie.
while (ob_get_level() > 0) {
    ob_end_clean();
}

header('Content-Type: ' . $mime);
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($path));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
readfile($path);
