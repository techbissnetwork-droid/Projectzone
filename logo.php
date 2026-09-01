<?php
declare(strict_types=1);

/**
 * Streams the admin-uploaded site logo (data/uploads is web-blocked, so it is
 * served through this endpoint). Only the exact filename stored in settings
 * is ever readable. Falls back to 404 when no custom logo is set.
 */

require __DIR__ . '/src/bootstrap.php';

use SignalMasterAi\Database;

$name = Database::setting('custom_logo');
if ($name === '' || !preg_match('/^site-logo-[a-f0-9]{12}\.(png|jpg|webp|gif)$/', $name)) {
    http_response_code(404);
    exit;
}
$path = SMA_UPLOAD_DIR . '/' . $name;
if (!is_file($path)) {
    http_response_code(404);
    exit;
}
$types = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'webp' => 'image/webp', 'gif' => 'image/gif'];
$ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
header('Content-Type: ' . ($types[$ext] ?? 'application/octet-stream'));
header('Content-Length: ' . (string)filesize($path));
header('Cache-Control: public, max-age=86400');
header('X-Content-Type-Options: nosniff');
readfile($path);
