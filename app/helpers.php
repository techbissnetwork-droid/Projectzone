<?php
/** Small helpers used everywhere. Kept deliberately boring. */

/** Escape for HTML output. Use it on every value that came from a human. */
function esc(?string $v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Escape and turn newlines into paragraphs. */
function esc_para(?string $v): string
{
    $parts = preg_split('/\n\s*\n/', trim((string) $v));
    $out   = '';
    foreach ($parts as $p) {
        if ($p === '') {
            continue;
        }
        $out .= '<p>' . nl2br(esc($p)) . '</p>';
    }
    return $out;
}

/** Split a textarea of one-per-line values into an array. */
function lines(?string $v): array
{
    $out = [];
    foreach (preg_split('/\r\n|\r|\n/', (string) $v) as $line) {
        $line = trim($line);
        if ($line !== '') {
            $out[] = $line;
        }
    }
    return $out;
}

function redirect(string $to): void
{
    header('Location: ' . $to);
    exit;
}

function post(string $key, $default = ''): string
{
    return isset($_POST[$key]) && is_scalar($_POST[$key]) ? trim((string) $_POST[$key]) : (string) $default;
}

function get(string $key, $default = ''): string
{
    return isset($_GET[$key]) && is_scalar($_GET[$key]) ? trim((string) $_GET[$key]) : (string) $default;
}

function post_int(string $key, int $default = 0): int
{
    return isset($_POST[$key]) ? (int) $_POST[$key] : $default;
}

function get_int(string $key, int $default = 0): int
{
    return isset($_GET[$key]) ? (int) $_GET[$key] : $default;
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

/* --- CSRF ------------------------------------------------------------- */

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_token" value="' . esc(csrf_token()) . '">';
}

/** Call at the top of every POST handler. */
function csrf_check(): void
{
    $sent = $_POST['_token'] ?? '';
    if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
        http_response_code(419);
        exit('Your session expired. Go back, reload the page and try again.');
    }
}

/* --- flash messages ---------------------------------------------------- */

function flash(string $message, string $type = 'ok'): void
{
    $_SESSION['flash'][] = ['message' => $message, 'type' => $type];
}

function flash_take(): array
{
    $all = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $all;
}

/* --- formatting -------------------------------------------------------- */

function money($amount, string $symbol = '$'): string
{
    if ($amount === null || $amount === '') {
        return 'Quoted';
    }
    if (!is_numeric($amount)) {
        return (string) $amount;
    }
    $n = (float) $amount;
    return $symbol . number_format($n, fmod($n, 1) == 0 ? 0 : 2);
}

function date_human(?string $date): string
{
    if (!$date) {
        return '—';
    }
    $ts = strtotime($date);
    return $ts ? date('j M Y', $ts) : '—';
}

function datetime_human(?string $date): string
{
    if (!$date) {
        return '—';
    }
    $ts = strtotime($date);
    return $ts ? date('j M Y, H:i', $ts) : '—';
}

/** Whole days until a date. Negative when it has already passed. */
function days_until(?string $date): ?int
{
    if (!$date) {
        return null;
    }
    $ts = strtotime($date);
    if (!$ts) {
        return null;
    }
    return (int) floor(($ts - strtotime('today')) / 86400);
}

/**
 * Renewal state for a date, used for the coloured pills on projects.
 * Returns [state, label] where state is ok | soon | urgent | expired | none.
 */
function expiry_state(?string $date): array
{
    $d = days_until($date);
    if ($d === null) {
        return ['none', 'Not set'];
    }
    if ($d < 0) {
        return ['expired', abs($d) . ' days ago'];
    }
    if ($d <= 14) {
        return ['urgent', 'in ' . $d . ' days'];
    }
    if ($d <= 45) {
        return ['soon', 'in ' . $d . ' days'];
    }
    return ['ok', 'in ' . $d . ' days'];
}

function slugify(string $text): string
{
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    return trim((string) $text, '-') ?: 'item';
}

/** Make a slug unique within a table. */
function unique_slug(string $table, string $slug, int $ignoreId = 0): string
{
    $base = $slug;
    $i    = 1;
    while (db_count("SELECT COUNT(*) FROM {$table} WHERE slug = ? AND id <> ?", [$slug, $ignoreId])) {
        $slug = $base . '-' . (++$i);
    }
    return $slug;
}

function excerpt(?string $text, int $words = 26): string
{
    $t     = trim(preg_replace('/\s+/', ' ', strip_tags((string) $text)));
    $parts = explode(' ', $t);
    if (count($parts) <= $words) {
        return $t;
    }
    return implode(' ', array_slice($parts, 0, $words)) . '…';
}

function now(): string
{
    return date('Y-m-d H:i:s');
}

/** A short human-readable reference, e.g. TB-7F3K92. */
function reference(string $prefix = 'TB'): string
{
    return $prefix . '-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
}

function base_url(): string
{
    $cfg = config();
    if (!empty($cfg['base_url'])) {
        return rtrim($cfg['base_url'], '/');
    }
    $https  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $script = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
    $script = rtrim(preg_replace('#/(admin|client)$#', '', $script), '/');
    return ($https ? 'https://' : 'http://') . $host . $script;
}

/** Handle one uploaded image. Returns a web path, or null. */
function handle_upload(string $field, ?string &$error = null): ?string
{
    if (empty($_FILES[$field]) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    $file = $_FILES[$field];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error = 'That file did not upload. It may be larger than the server allows.';
        return null;
    }
    if ($file['size'] > 4 * 1024 * 1024) {
        $error = 'That image is over 4MB. Please resize it first.';
        return null;
    }
    $info = @getimagesize($file['tmp_name']);
    $allowed = [
        IMAGETYPE_JPEG => 'jpg',
        IMAGETYPE_PNG  => 'png',
        IMAGETYPE_GIF  => 'gif',
        IMAGETYPE_WEBP => 'webp',
    ];
    if (!$info || !isset($allowed[$info[2]])) {
        $error = 'Only JPG, PNG, GIF and WebP images can be uploaded.';
        return null;
    }
    $dir = dirname(__DIR__) . '/storage/uploads';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $name = date('Ymd') . '-' . bin2hex(random_bytes(6)) . '.' . $allowed[$info[2]];
    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) {
        $error = 'The image could not be saved. Check permissions on storage/uploads.';
        return null;
    }
    return 'storage/uploads/' . $name;
}

function delete_upload(?string $path): void
{
    if (!$path || !str_starts_with($path, 'storage/uploads/')) {
        return;
    }
    $full = dirname(__DIR__) . '/' . $path;
    if (is_file($full)) {
        @unlink($full);
    }
}

/** Path from the site root, correct whether we are in /, /admin or /client. */
function url(string $path = ''): string
{
    return base_url() . '/' . ltrim($path, '/');
}

function valid_email(string $email): bool
{
    return (bool) filter_var($email, FILTER_VALIDATE_EMAIL);
}

/**
 * A coloured pill for a status value. Shared by the admin area and the client
 * portal, so both show the same word in the same colour.
 */
function status_pill(string $status): string
{
    $map = [
        'new'         => 'acc',  'open'        => 'acc',
        'answered'    => 'soon', 'in_progress' => 'soon',
        'closed'      => 'ok',   'done'        => 'ok',
        'active'      => 'ok',   'paid'        => 'ok',   'delivered' => 'ok',
        'building'    => 'soon', 'paused'      => 'soon', 'quoted'    => 'soon',
        'read'        => '',
        'won'         => 'ok',   'lost'        => 'bad',  'cancelled' => 'bad',
        'ended'       => 'bad',  'suspended'   => 'bad',
    ];
    $cls = $map[$status] ?? '';
    return '<span class="pill ' . $cls . '">' . esc(ucwords(str_replace('_', ' ', $status))) . '</span>';
}
