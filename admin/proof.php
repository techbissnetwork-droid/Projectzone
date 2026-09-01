<?php
declare(strict_types=1);

/** Admin-only viewer for uploaded payment-proof images (stored outside webroot access). */

$config = require __DIR__ . '/_admin.php';

use SignalMasterAi\Auth;
use SignalMasterAi\Database;

Auth::requireLogin();

$stmt = Database::pdo()->prepare('SELECT proof_image FROM payments WHERE id = ?');
$stmt->execute([(int)($_GET['id'] ?? 0)]);
$name = (string)($stmt->fetchColumn() ?: '');

// The stored name is server-generated; re-validate anyway.
if ($name === '' || !preg_match('/^proof-\d+-[a-f0-9]+\.(jpg|png|webp|gif)$/', $name)) {
    http_response_code(404);
    exit('Not found');
}
$path = SMA_UPLOAD_DIR . '/' . $name;
if (!is_file($path)) {
    http_response_code(404);
    exit('File missing');
}
$mime = ['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'gif' => 'image/gif'][pathinfo($name, PATHINFO_EXTENSION)];
header('Content-Type: ' . $mime);
header('Content-Length: ' . (string)filesize($path));
header('X-Content-Type-Options: nosniff');
readfile($path);
