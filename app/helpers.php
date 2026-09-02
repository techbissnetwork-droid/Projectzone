<?php
declare(strict_types=1);

function base_path(string $rel = ''): string
{
    $root = dirname(__DIR__);
    return $rel === '' ? $root : $root . '/' . ltrim($rel, '/');
}

/** Absolute URL for a path inside the app, derived from the request. */
function url(string $path = ''): string
{
    static $base = null;
    if ($base === null) {
        $cfg  = $GLOBALS['TB_CONFIG']['app']['url'] ?? '';
        $base = rtrim((string)$cfg, '/');
        if ($base === '') {
            $https  = (($_SERVER['HTTPS'] ?? '') !== '' && $_SERVER['HTTPS'] !== 'off')
                   || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
            $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $script = str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/')));
            foreach (['/admin', '/client', '/install'] as $sub) {
                if (str_ends_with($script, $sub)) {
                    $script = substr($script, 0, -strlen($sub));
                }
            }
            $base = ($https ? 'https://' : 'http://') . $host . rtrim($script, '/');
        }
    }
    return $base . ($path === '' ? '/' : '/' . ltrim($path, '/'));
}

function asset(string $path): string
{
    return url($path);
}

function e(?string $v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Escaped, with newlines preserved. */
function enl(?string $v): string
{
    return nl2br(e($v), false);
}

function now(): string
{
    return date('Y-m-d H:i:s');
}

function client_ip(): string
{
    return substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
}

function redirect(string $path): never
{
    header('Location: ' . (preg_match('~^https?://~', $path) ? $path : url($path)));
    exit;
}

function old(string $key, mixed $fallback = ''): string
{
    return (string)($_POST[$key] ?? $fallback);
}

function slugify(string $text, string $fallback = 'item'): string
{
    $t = preg_replace('/[^\p{L}\p{Nd}]+/u', '-', $text) ?? '';
    $t = trim(mb_strtolower($t), '-');
    $t = preg_replace('/-{2,}/', '-', $t) ?? '';
    return $t !== '' ? mb_substr($t, 0, 160) : $fallback;
}

/** A slug that is unique within a table, ignoring $ignoreId when editing. */
function unique_slug(string $table, string $base, ?int $ignoreId = null): string
{
    $slug = slugify($base);
    $i = 1;
    while (true) {
        $sql = 'SELECT COUNT(*) FROM ' . $table . ' WHERE slug = :s';
        $p   = ['s' => $slug];
        if ($ignoreId !== null) {
            $sql .= ' AND id <> :id';
            $p['id'] = $ignoreId;
        }
        if ((int)Database::value($sql, $p, 0) === 0) {
            return $slug;
        }
        $slug = slugify($base) . '-' . (++$i);
    }
}

function reference(string $prefix): string
{
    return strtoupper($prefix) . '-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
}

function money(float|string|null $amount, ?string $symbol = null): string
{
    $symbol ??= Settings::get('currency_symbol', 'Rs');
    return $symbol . ' ' . number_format((float)$amount, 2);
}

function fdate(?string $date, string $format = 'j M Y'): string
{
    if (!$date || str_starts_with($date, '0000')) {
        return '—';
    }
    $ts = strtotime($date);
    return $ts ? date($format, $ts) : '—';
}

function ftime(?string $dt): string
{
    return fdate($dt, 'j M Y, g:ia');
}

/** Whole days until a date. Negative when it has already passed. */
function days_until(?string $date): ?int
{
    if (!$date) {
        return null;
    }
    $ts = strtotime($date . ' 00:00:00');
    if ($ts === false) {
        return null;
    }
    return (int)floor(($ts - strtotime('today')) / 86400);
}

/** ok | warn | danger | expired — how urgently a renewal needs attention. */
function expiry_state(?string $date): string
{
    $d = days_until($date);
    if ($d === null)  return 'none';
    if ($d < 0)       return 'expired';
    if ($d <= 14)     return 'danger';
    if ($d <= (int)Settings::get('expiry_warn_days', '45')) return 'warn';
    return 'ok';
}

function expiry_label(?string $date): string
{
    $d = days_until($date);
    if ($d === null)  return 'Not set';
    if ($d < 0)       return 'Expired ' . abs($d) . ' ' . ($d === -1 ? 'day' : 'days') . ' ago';
    if ($d === 0)     return 'Expires today';
    return 'In ' . $d . ' ' . ($d === 1 ? 'day' : 'days');
}

/** Trim to a word boundary. */
function excerpt(?string $text, int $chars = 160): string
{
    $t = trim(preg_replace('/\s+/', ' ', strip_tags((string)$text)) ?? '');
    if (mb_strlen($t) <= $chars) {
        return $t;
    }
    $cut = mb_substr($t, 0, $chars);
    $sp  = mb_strrpos($cut, ' ');
    return rtrim($sp ? mb_substr($cut, 0, $sp) : $cut, ' ,.;:') . '…';
}

/** Split a textarea of one-per-line values into a clean list. */
function lines(?string $text): array
{
    if (!$text) {
        return [];
    }
    $out = array_map('trim', preg_split('/\r\n|\r|\n/', $text) ?: []);
    return array_values(array_filter($out, static fn($l) => $l !== ''));
}

/** Split a comma separated field into a clean list. */
function csv_list(?string $text): array
{
    if (!$text) {
        return [];
    }
    $out = array_map('trim', explode(',', $text));
    return array_values(array_filter($out, static fn($l) => $l !== ''));
}

/** Is this request a form submission? */
function post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function is_email(string $v): bool
{
    return (bool)filter_var($v, FILTER_VALIDATE_EMAIL);
}

function log_activity(string $action, ?string $entity = null, ?int $entityId = null, ?string $detail = null): void
{
    try {
        Database::insert('activity_log', [
            'user_id'    => $_SESSION['uid'] ?? null,
            'action'     => $action,
            'entity'     => $entity,
            'entity_id'  => $entityId,
            'detail'     => $detail === null ? null : mb_substr($detail, 0, 400),
            'ip'         => client_ip(),
            'created_at' => now(),
        ]);
    } catch (Throwable) {
        // Logging must never break the request.
    }
}

/** Status label + tone, shared by admin and portal badges. */
function status_tone(string $status): string
{
    return match ($status) {
        'live', 'paid', 'delivered', 'resolved', 'active', 'public' => 'ok',
        'in_progress', 'answered', 'open', 'new'                    => 'info',
        'planning', 'pending', 'on_hold', 'private'                 => 'warn',
        'closed', 'cancelled', 'suspended'                          => 'muted',
        default                                                     => 'muted',
    };
}

function label(string $value): string
{
    return ucwords(str_replace('_', ' ', $value));
}
