<?php
/**
 * Serves a game whose HTML was pasted into the admin panel, so the player can
 * load it into the frame on the home page without the markup bloating every
 * page view.
 *
 * The response is sandboxed by its own Content-Security-Policy: the game runs
 * its scripts but gets an opaque origin, so it cannot reach the site around it.
 * That matches how the built-in games already run (from a blob: URL).
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

/** Nothing to serve → a plain 404, with no hint about what exists. */
function game_not_found(): void
{
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    header('X-Robots-Tag: noindex');
    exit('Game not found.');
}

if (!is_installed()) {
    game_not_found();
}

// While the site is in maintenance mode only a signed-in admin may preview.
if (setting('maintenance_mode', '0') === '1' && !current_user()) {
    game_not_found();
}

$id = (int) ($_GET['g'] ?? 0);
if ($id <= 0) {
    game_not_found();
}

$st = db()->prepare("SELECT * FROM games WHERE id = ? AND is_active = 1");
$st->execute([$id]);
$game = $st->fetch();

if (!$game
    || game_source_key((string) ($game['source'] ?? 'builtin')) !== 'html'
    || trim((string) ($game['html_code'] ?? '')) === '') {
    game_not_found();
}

header('Content-Type: text/html; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-Robots-Tag: noindex, nofollow');
header('Referrer-Policy: no-referrer');
header('Cache-Control: private, max-age=0, must-revalidate');
// The game may script and take pointer lock; it may not act as this site.
header('Content-Security-Policy: sandbox allow-scripts allow-pointer-lock allow-forms allow-modals');

echo (string) $game['html_code'];
