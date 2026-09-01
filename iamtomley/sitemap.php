<?php
/**
 * sitemap.xml — served from PHP so it is always current.
 *
 * The site is one page, so the sitemap is short by nature. What makes it worth
 * submitting is the image extension: every project logo on the page is listed
 * against it, which is how those images get indexed at all.
 *
 * Apache maps /sitemap.xml here (see .htaccess). Without mod_rewrite the file
 * is still reachable at /sitemap.php.
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';

header('Content-Type: application/xml; charset=UTF-8');
header('X-Robots-Tag: noindex');

/** Nothing to index yet — reply with a valid but empty sitemap. */
function empty_sitemap(): void
{
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
       . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"></urlset>' . "\n";
    exit;
}

if (!is_installed()) {
    empty_sitemap();
}

// A site that is switched off should not be advertising URLs.
if (setting('maintenance_mode', '0') === '1' || setting('seo_noindex', '0') === '1') {
    empty_sitemap();
}

$home = canonical_url();
$when = content_updated_at();

// Every project picture that lives on this site (not ones linked elsewhere).
$images = [];
foreach (projects_active() as $p) {
    $img = trim((string) ($p['image'] ?? ''));
    if ($img === '' || !str_starts_with($img, '/')) { continue; }
    $images[] = ['loc' => site_url($img), 'title' => (string) $p['title']];
}

echo '<?xml version="1.0" encoding="UTF-8"?>', "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"', "\n";
echo '        xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">', "\n";
echo '  <url>', "\n";
echo '    <loc>', e($home), '</loc>', "\n";
if ($when > 0) {
    echo '    <lastmod>', e(gmdate('Y-m-d\TH:i:s\Z', $when)), '</lastmod>', "\n";
}
echo '    <changefreq>weekly</changefreq>', "\n";
echo '    <priority>1.0</priority>', "\n";
foreach ($images as $img) {
    echo '    <image:image>', "\n";
    echo '      <image:loc>', e($img['loc']), '</image:loc>', "\n";
    echo '      <image:title>', e($img['title']), '</image:title>', "\n";
    echo '    </image:image>', "\n";
}
echo '  </url>', "\n";
echo '</urlset>', "\n";
