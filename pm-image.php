<?php
declare(strict_types=1);

/**
 * Streams the image attached to a manual payment method (QR code / payment
 * instructions graphic) - files live outside web access in data/uploads.
 */

$config = require __DIR__ . '/src/bootstrap.php';

use SignalMasterAi\Database;

$stmt = Database::pdo()->prepare('SELECT image FROM payment_methods WHERE id = ? AND enabled = 1');
$stmt->execute([(int)($_GET['id'] ?? 0)]);
$name = (string)($stmt->fetchColumn() ?: '');

if ($name === '' || !preg_match('/^pm-\d+-[a-f0-9]+\.(jpg|png|webp|gif)$/', $name)) {
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
header('Cache-Control: public, max-age=3600');
header('X-Content-Type-Options: nosniff');
readfile($path);
