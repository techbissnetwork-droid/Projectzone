<?php
/** Small helpers used by both the public site and the admin area. */

/** Escape for HTML output. Every dynamic value on a page goes through this. */
function e(?string $v): string
{
    return htmlspecialchars($v ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function config(?string $key = null)
{
    static $config = null;
    if ($config === null) {
        $file = __DIR__ . '/config.php';
        if (!is_file($file)) {
            $file = __DIR__ . '/config.sample.php';
        }
        $config = require $file;
    }
    return $key === null ? $config : ($config[$key] ?? null);
}

function base_url(string $path = ''): string
{
    $base = rtrim((string) config('base_url'), '/');
    return $base . '/' . ltrim($path, '/');
}

function redirect(string $path): never
{
    header('Location: ' . (str_starts_with($path, 'http') ? $path : base_url($path)));
    exit;
}

/* ---------------------------------------------------------------- CSRF --- */

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

/**
 * Verify a POSTed token. Kills the request rather than returning false.
 *
 * Both sides must be non-empty before they are compared: with a missing
 * session and a missing field, hash_equals('', '') is true, which would let a
 * request carrying no token at all through.
 */
function csrf_check(): void
{
    $sent    = $_POST['_csrf'] ?? '';
    $session = $_SESSION['csrf'] ?? '';

    if (!is_string($sent) || $sent === '' || $session === '' || !hash_equals($session, $sent)) {
        http_response_code(419);
        exit('Session expired. Go back, reload the page and try again.');
    }
}

/* --------------------------------------------------------------- flash --- */

function flash(?string $message = null, string $type = 'ok'): ?array
{
    if ($message !== null) {
        $_SESSION['flash'] = ['message' => $message, 'type' => $type];
        return null;
    }
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

/* ------------------------------------------------------------- content --- */

/**
 * Editable string, looked up by key. Every key is seeded at install time, so
 * the default is only a safety net for a key added later in code.
 */
function txt(string $key, string $default = ''): string
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        foreach (all('SELECT ckey, cvalue FROM content') as $r) {
            $cache[$r['ckey']] = $r['cvalue'];
        }
    }
    $v = $cache[$key] ?? null;
    return ($v === null || $v === '') ? $default : $v;
}

/** Same as txt() but the value is written straight into HTML. */
function etxt(string $key, string $default = ''): void
{
    echo e(txt($key, $default));
}

/** Split a textarea field into a clean list of lines. */
function lines(?string $text): array
{
    if ($text === null || trim($text) === '') {
        return [];
    }
    $out = [];
    foreach (preg_split('/\R/', $text) as $line) {
        $line = trim($line);
        if ($line !== '') {
            $out[] = $line;
        }
    }
    return $out;
}

/**
 * Panel rows are authored as "label | value" per line, so the admin can edit
 * the little terminal panels without touching markup.
 */
function panel_rows(?string $text): array
{
    $out = [];
    foreach (lines($text) as $line) {
        $parts = array_map('trim', explode('|', $line, 2));
        $out[] = ['label' => $parts[0], 'value' => $parts[1] ?? ''];
    }
    return $out;
}

/** Initials for a testimonial avatar when none are given. */
function initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name));
    $out = '';
    foreach (array_slice($parts, 0, 2) as $p) {
        $out .= mb_strtoupper(mb_substr($p, 0, 1));
    }
    return $out ?: '?';
}

function current_page(): string
{
    return basename($_SERVER['SCRIPT_NAME'] ?? 'index.php');
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function post(string $key, string $default = ''): string
{
    $v = $_POST[$key] ?? $default;
    return is_string($v) ? trim($v) : $default;
}

function client_ip(): string
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
}

function now(): string
{
    return date('Y-m-d H:i:s');
}

/**
 * Statement lines are authored with the accented phrase in braces, so the
 * admin can move the highlight without writing HTML:
 *   "No sales script. {Tell us what you run} and what's missing."
 */
function statement_html(string $text): string
{
    $safe = e($text);
    return preg_replace('/\{(.+?)\}/u', '<span class="accent">$1</span>', $safe);
}

/** Turn blank-line-separated text into paragraphs. */
function paragraphs_html(string $text, string $class = ''): string
{
    $out = '';
    $attr = $class === '' ? '' : ' class="' . e($class) . '"';
    foreach (preg_split('/\R{2,}/', trim($text)) as $p) {
        $p = trim($p);
        if ($p !== '') {
            $out .= '<p' . $attr . '>' . nl2br(e($p)) . '</p>';
        }
    }
    return $out;
}

/** Bento/card size class suffix, validated against what the CSS provides. */
function size_class(?string $size): string
{
    return in_array($size, ['a', 'b', 'c'], true) ? $size : 'c';
}
