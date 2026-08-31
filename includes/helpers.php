<?php
declare(strict_types=1);

use Techbiss\Core\App;

/** Escape for HTML text and attribute contexts. */
function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Encode a value for use inside a <script> block.
 *
 * The HEX flags turn <, >, &, ' and " into \u escapes, so the result cannot
 * close the script element or start an HTML comment. HTML entities are NOT
 * applied: a <script> element's contents are not entity-decoded by the parser,
 * so escaping them here would put a literal &quot; into the JavaScript source.
 */
function ejs(mixed $value): string
{
    return (string) json_encode(
        $value,
        JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
    );
}


/** Site-relative URL, honouring an installation sub-directory. */
function url(string $path = '/'): string
{
    if (preg_match('#^(https?:|mailto:|tel:|//|\#)#i', $path)) {
        return $path;
    }
    $base = App::basePath();
    return $base . '/' . ltrim($path, '/');
}

/**
 * Absolute URL — used for canonicals, Open Graph and the sitemap.
 *
 * Built from the origin rather than siteUrl(), because url() already applies
 * the sub-directory; using siteUrl() here would emit it twice.
 */
function absolute_url(string $path = '/'): string
{
    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }
    return rtrim(App::origin(), '/') . url($path);
}

/** Versioned asset URL so browsers pick up CSS/JS changes immediately. */
function asset(string $path): string
{
    $path = ltrim($path, '/');
    $abs  = App::root() . '/' . $path;
    $ver  = is_file($abs) ? substr((string) filemtime($abs), -6) : App::version();
    return url($path) . '?v=' . $ver;
}

/** Public URL for a stored upload path, or '' when empty. */
function media_url(?string $path): string
{
    $path = trim((string) $path);
    if ($path === '') {
        return '';
    }
    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }
    return url($path);
}

function setting(string $key, string $default = ''): string
{
    return App::settings()->get($key, $default);
}

function setting_bool(string $key, bool $default = false): bool
{
    return App::settings()->bool($key, $default);
}

/**
 * A wa.me link with the message already written, or '' if no number is set.
 *
 * Returning '' rather than a dead link matters: a WhatsApp button that opens
 * nothing is worse than no button, so callers hide the button instead.
 */
function whatsapp_link(string $message = ''): string
{
    $number = preg_replace('/[^0-9]/', '', setting('whatsapp')) ?? '';
    if (strlen($number) < 8) {
        return '';
    }

    return 'https://wa.me/' . $number . ($message === '' ? '' : '?text=' . rawurlencode($message));
}

/** A mailto: link with the subject filled in, or '' if no address is set. */
function email_link(string $subject = '', string $body = ''): string
{
    $address = setting('sales_email') ?: setting('contact_email');
    if (!filter_var($address, FILTER_VALIDATE_EMAIL)) {
        return '';
    }

    $query = array_filter(['subject' => $subject, 'body' => $body], static fn (string $v): bool => $v !== '');

    return 'mailto:' . $address . ($query === [] ? '' : '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986));
}

/** Format a money amount using the configured currency symbol. */
function money(float|int|string|null $amount, ?string $symbol = null, bool $decimals = false): string
{
    $amount = (float) $amount;
    $symbol = $symbol ?? App::settings()->get('currency_symbol', '$');
    $whole  = $decimals || fmod($amount, 1.0) !== 0.0;
    return $symbol . number_format($amount, $whole ? 2 : 0);
}

/** A file size a person can read: 512 KB, 4.2 MB. Never "0.0 MB". */
function human_bytes(int $bytes): string
{
    if ($bytes <= 0) {
        return '';
    }
    if ($bytes < 1024) {
        return $bytes . ' B';
    }
    if ($bytes < 1048576) {
        return round($bytes / 1024) . ' KB';
    }
    return rtrim(rtrim(number_format($bytes / 1048576, 1), '0'), '.') . ' MB';
}

function format_date(?string $date, string $format = 'j M Y'): string
{
    if ($date === null || $date === '' || str_starts_with($date, '0000')) {
        return '';
    }
    $ts = strtotime($date);
    return $ts === false ? '' : date($format, $ts);
}

/** "3 days ago" style relative time for admin lists. */
function time_ago(?string $date): string
{
    $ts = $date ? strtotime($date) : false;
    if ($ts === false) {
        return '';
    }
    $diff = time() - $ts;
    if ($diff < 60) {
        return 'just now';
    }
    foreach ([[31536000, 'year'], [2592000, 'month'], [604800, 'week'], [86400, 'day'], [3600, 'hour'], [60, 'minute']] as [$secs, $label]) {
        if ($diff >= $secs) {
            $n = (int) floor($diff / $secs);
            return $n . ' ' . $label . ($n > 1 ? 's' : '') . ' ago';
        }
    }
    return 'just now';
}

/** Render one of the inline SVG icons from the shared icon set. */
function icon(string $name, string $class = 'icon'): string
{
    return \Techbiss\Core\Icons::svg($name, $class);
}

function csrf_field(): string
{
    return \Techbiss\Core\Csrf::field();
}

function csrf_token(): string
{
    return \Techbiss\Core\Csrf::token();
}

/** Mark a nav link active when it matches the current path. */
function is_active_url(string $path, string $currentPath): bool
{
    $path = '/' . trim(parse_url($path, PHP_URL_PATH) ?? $path, '/');
    if ($path === '/') {
        return $currentPath === '/';
    }
    return $currentPath === $path || str_starts_with($currentPath, $path . '/');
}

/** Split a newline-separated admin field into a list. @return array<int,string> */
function lines_to_list(?string $text): array
{
    $text = trim((string) $text);
    if ($text === '') {
        return [];
    }
    $out = [];
    foreach (preg_split('/\r\n|\r|\n/', $text) ?: [] as $line) {
        $line = trim($line);
        if ($line !== '') {
            $out[] = $line;
        }
    }
    return $out;
}

/** Send a redirect and stop. */
function redirect(string $path, int $status = 302): never
{
    $target = preg_match('#^https?://#i', $path) ? $path : url($path);
    header('Location: ' . $target, true, $status);
    exit;
}

function json_response(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function flash(string $type, string $message): void
{
    \Techbiss\Core\Session::flash($type, $message);
}

function old(string $key, mixed $default = ''): mixed
{
    $old = App::old();
    return $old[$key] ?? $default;
}

function error_for(string $key): string
{
    $errors = App::errors();
    return (string) ($errors[$key] ?? '');
}


/** Build a query string preserving current filters. */
function query_with(array $overrides, array $current = []): string
{
    $merged = array_merge($current, $overrides);
    $merged = array_filter($merged, static fn ($v) => $v !== '' && $v !== null);
    $qs     = http_build_query($merged);
    return $qs === '' ? '' : '?' . $qs;
}

function str_limit(?string $text, int $limit = 120): string
{
    return \Techbiss\Core\Str::excerpt((string) $text, $limit);
}

function initials(string $name): string
{
    return \Techbiss\Core\Str::initials($name);
}
