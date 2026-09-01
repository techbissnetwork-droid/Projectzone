<?php
/**
 * Admin-only endpoint behind the "Detect" buttons: give it a website address
 * and it finds that site's logo / preview image, saves a copy in uploads/ and
 * returns the path for the form to fill in.
 */
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../includes/imagefetch.php';

header('Content-Type: application/json; charset=UTF-8');
header('X-Robots-Tag: noindex');

/** Reply with JSON and stop. */
function reply(array $data, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

if (!current_user()) {
    reply(['ok' => false, 'error' => 'Your session has expired — please sign in again.'], 401);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    reply(['ok' => false, 'error' => 'Method not allowed.'], 405);
}
if (!isset($_POST['csrf'], $_SESSION['csrf']) || !hash_equals((string) $_SESSION['csrf'], (string) $_POST['csrf'])) {
    reply(['ok' => false, 'error' => 'This page has been open a while — reload it and try again.'], 419);
}

$url    = trim((string) ($_POST['url'] ?? ''));
$prefix = slugify((string) ($_POST['prefix'] ?? 'site'), 24) ?: 'site';

$result = detect_site_info($url, $prefix);
if (!$result['ok']) {
    reply(['ok' => false, 'error' => $result['error']]);
}

reply([
    'ok'          => true,
    'title'       => $result['title'],                  // the site's own name
    'description' => $result['description'],            // its own summary
    'path'        => $result['path'],                   // what to store on the record
    'src'         => $result['path'] === '' ? '' : media($result['path']),
    'source'      => $result['source'],                 // where the image came from
    'kind'        => $result['kind'],                   // "Open Graph image", "Favicon", …
    'note'        => $result['error'],                  // e.g. no image, but text found
]);
