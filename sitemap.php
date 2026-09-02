<?php
/** A sitemap built from what is actually published. */
require_once __DIR__ . '/app/bootstrap.php';
require_installed();

header('Content-Type: application/xml; charset=UTF-8');
$base = base_url();

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

$fixed = [
    ['', '1.0'], ['services.php', '0.9'], ['industries.php', '0.8'],
    ['portfolio.php', '0.8'], ['marketplace.php', '0.8'],
    ['pricing.php', '0.9'], ['about.php', '0.6'], ['contact.php', '0.7'],
];
foreach ($fixed as [$path, $priority]) {
    echo "  <url><loc>{$base}/{$path}</loc><priority>{$priority}</priority></url>\n";
}
foreach (public_portfolio() as $p) {
    $loc = $base . '/project.php?slug=' . urlencode($p['slug']);
    echo "  <url><loc>" . htmlspecialchars($loc, ENT_XML1) . "</loc><priority>0.6</priority></url>\n";
}
foreach (active_products() as $p) {
    $loc = $base . '/product.php?slug=' . urlencode($p['slug']);
    echo "  <url><loc>" . htmlspecialchars($loc, ENT_XML1) . "</loc><priority>0.7</priority></url>\n";
}
echo '</urlset>' . "\n";
