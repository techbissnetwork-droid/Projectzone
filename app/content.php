<?php
/**
 * Reading the editable content. Everything the admin can change comes
 * through here, so a page template never writes SQL.
 */

/** All settings, loaded once per request. */
function settings(): array
{
    static $all = null;
    if ($all === null) {
        $all = [];
        foreach (db_all('SELECT setting_key, value FROM settings') as $row) {
            $all[$row['setting_key']] = $row['value'];
        }
    }
    return $all;
}

/** One setting, with a fallback so a missing row never blanks the page. */
function setting(string $key, string $default = ''): string
{
    $all = settings();
    $v   = $all[$key] ?? null;
    return ($v === null || $v === '') ? $default : $v;
}

function setting_lines(string $key, array $default = []): array
{
    $v = setting($key, '');
    return $v === '' ? $default : lines($v);
}

function setting_set(string $key, string $value): void
{
    $exists = db_one('SELECT id FROM settings WHERE setting_key = ?', [$key]);
    if ($exists) {
        db_update('settings', (int) $exists['id'], ['value' => $value]);
    } else {
        db_insert('settings', [
            'group_name'  => 'global',
            'setting_key' => $key,
            'label'       => $key,
            'value'       => $value,
            'field_type'  => 'text',
        ]);
    }
}

/**
 * A heading where {curly braces} mark the part to highlight.
 * "Take all ten. {One partner.}" → "Take all ten. <mark>One partner.</mark>"
 */
function highlighted(string $text): string
{
    $safe = esc($text);
    return preg_replace('/\{(.+?)\}/s', '<mark>$1</mark>', $safe);
}

/* --- content lists ------------------------------------------------------ */

function active_services(): array
{
    return db_all('SELECT * FROM services WHERE is_active = 1 ORDER BY sort, id');
}

function active_industries(): array
{
    return db_all('SELECT * FROM industries WHERE is_active = 1 ORDER BY sort, id');
}

function active_packages(string $kind = 'build'): array
{
    return db_all(
        'SELECT * FROM packages WHERE is_active = 1 AND kind = ? ORDER BY sort, id',
        [$kind]
    );
}

function active_addons(): array
{
    return db_all('SELECT * FROM addons ORDER BY sort, id');
}

function faqs_for(string $page): array
{
    return db_all('SELECT * FROM faqs WHERE page = ? ORDER BY sort, id', [$page]);
}

function active_testimonials(): array
{
    return db_all('SELECT * FROM testimonials WHERE is_active = 1 ORDER BY sort, id');
}

/** Portfolio items the public may see. Private ones stay in the admin only. */
function public_portfolio(int $limit = 0): array
{
    $sql = "SELECT * FROM portfolio WHERE visibility = 'public'
            ORDER BY is_featured DESC, sort, completed_on DESC, id DESC";
    if ($limit > 0) {
        $sql .= ' LIMIT ' . (int) $limit;
    }
    return db_all($sql);
}

function portfolio_by_slug(string $slug): ?array
{
    return db_one("SELECT * FROM portfolio WHERE slug = ? AND visibility = 'public'", [$slug]);
}

function active_products(int $limit = 0): array
{
    $sql = 'SELECT * FROM products WHERE is_active = 1
            ORDER BY is_featured DESC, sort, id DESC';
    if ($limit > 0) {
        $sql .= ' LIMIT ' . (int) $limit;
    }
    return db_all($sql);
}

function product_by_slug(string $slug): ?array
{
    return db_one('SELECT * FROM products WHERE slug = ? AND is_active = 1', [$slug]);
}

/** What a product actually costs today. */
function product_price(array $p): float
{
    $sale = $p['sale_price'];
    if ($sale !== null && $sale !== '' && (float) $sale > 0 && (float) $sale < (float) $p['price']) {
        return (float) $sale;
    }
    return (float) $p['price'];
}

function product_on_sale(array $p): bool
{
    return product_price($p) < (float) $p['price'];
}

/* --- projects ----------------------------------------------------------- */

function projects_for_user(int $userId): array
{
    return db_all('SELECT * FROM projects WHERE user_id = ? ORDER BY id DESC', [$userId]);
}

/**
 * The four renewal dates on a project, in the order they are shown.
 * Each entry: label, date, state, human.
 */
function project_renewals(array $p): array
{
    $map = [
        ['Domain',  $p['domain_expires_on'],  $p['domain_registrar']],
        ['Hosting', $p['hosting_expires_on'], $p['hosting_provider']],
        ['SSL',     $p['ssl_expires_on'],     $p['ssl_provider']],
        ['Email',   $p['email_expires_on'],   $p['email_provider']],
    ];
    $out = [];
    foreach ($map as [$label, $date, $who]) {
        [$state, $human] = expiry_state($date);
        $out[] = [
            'label'    => $label,
            'date'     => $date,
            'provider' => $who,
            'state'    => $state,
            'human'    => $human,
        ];
    }
    return $out;
}

/** Renewals due within the next N days, across every project. */
function renewals_due(int $days = 45): array
{
    $rows  = db_all('SELECT * FROM projects ORDER BY id DESC');
    $due   = [];
    foreach ($rows as $p) {
        foreach (project_renewals($p) as $r) {
            if ($r['state'] === 'none' || $r['state'] === 'ok') {
                continue;
            }
            $d = days_until($r['date']);
            if ($d !== null && $d <= $days) {
                $due[] = $r + ['project' => $p, 'days' => $d];
            }
        }
    }
    usort($due, fn($a, $b) => $a['days'] <=> $b['days']);
    return $due;
}
