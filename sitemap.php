<?php
declare(strict_types=1);

/**
 * The site's own map, for search engines.
 *
 * There was none. Every public page here is reachable only from another page's
 * navigation, and the chart pages - one per coin, which is most of what this
 * site actually publishes - are reachable only through a picker that is
 * JavaScript. A crawler that never runs that picker sees one chart page and
 * assumes the rest do not exist.
 *
 * Generated rather than a file on disk, because the list is not static: coins
 * are added and removed, tiers change what is public, and a stale sitemap
 * claiming pages that now 404 is worse than none. Served from PHP so it is
 * always current, and mapped to /sitemap.xml by .htaccess for the hosts that
 * read it - both spellings work, and the robots.txt line names the one that
 * always resolves.
 *
 * ONLY PUBLIC PAGES GO IN. A sitemap is a set of claims about what a stranger
 * can read. Listing a page that answers "please log in" wastes the crawl and
 * teaches the crawler to trust the file less.
 */

$config = require __DIR__ . '/src/bootstrap.php';

use SignalMasterAi\Database;
use SignalMasterAi\Master;
use SignalMasterAi\Request;
use SignalMasterAi\Visibility;

header('Content-Type: application/xml; charset=utf-8');
// Worth caching: it is a database read per request otherwise, and the answer
// changes when coins do, not when visitors arrive.
header('Cache-Control: public, max-age=3600');

// The same base the canonical tags are built from - see View::indexBase().
// Built from site_url, the sitemap published http:// while the pages
// declared https://, and every entry contradicted the page it pointed at.
$base  = \SignalMasterAi\View::indexBase();
// The same test the canonical tag uses - and it has to be the same, or the
// sitemap publishes addresses the server does not serve. See View::cleanUrls().
$clean = \SignalMasterAi\View::cleanUrls();
$ext   = $clean ? '' : '.php';

/** @var array<int,array{0:string,1:string,2:string}> [path, changefreq, priority] */
$urls = [['', 'daily', '1.0']];

// A page only goes in when the feature behind it is actually switched on.
if (Master::master('signals')) {
    $urls[] = ['charts' . $ext,  'hourly', '0.9'];
    // The market scanner was its own page here, gated the same as everything
    // else that hides behind a tier. That page is gone - the "Live setups"
    // sheet on charts.php took over everything it did - so there is no
    // second URL for the scanner feature to list any more.
}
if (Database::setting('performance_page_enabled', '1') === '1') {
    $urls[] = ['performance' . $ext, 'daily', '0.8'];
}
$urls[] = ['status' . $ext, 'daily', '0.3'];
// The legal pages are NOT listed.
//
// page.php sends `noindex` on purpose - the text is boilerplate that appears
// on a thousand sites - and a sitemap is a request to index. Submitting a URL
// and then telling the crawler not to index it is a contradiction, and Search
// Console reports it as one: "Excluded by 'noindex' tag", once per page, for
// as long as both halves stay. The pages are still linked from every footer,
// so they are reachable and their links still carry weight; they are simply
// not being asked for.

// One entry per public coin on its own default timeframe.
//
// Public means public: a coin behind a tier is a page a crawler cannot read,
// so listing it would be a promise the site does not keep. The timeframe is
// pinned so this does not become one URL per coin per frame - that is the
// same content sliced, and it is how a small site talks itself into ten
// thousand near-duplicate pages.
if (Master::master('signals')) {
    try {
        $defaultTf = Database::setting('default_interval', '1h');
        $rows = Database::pdo()->query(
            "SELECT symbol FROM symbols WHERE enabled = 1 AND tier = 'public'"
            . Visibility::sql('charts', 'symbols') . ' ORDER BY symbol'
        )->fetchAll(PDO::FETCH_COLUMN);
        foreach ($rows as $sym) {
            $tfs = Visibility::tfsFor((string)$sym, [$defaultTf]);
            $tf  = $tfs[0] ?? $defaultTf;
            // LIST THE URL THE PAGE ITSELF DECLARES, or the two contradict.
            //
            // This published ?symbol=X&tf=1h while the page canonicalises the
            // default frame away to ?symbol=X - so every coin was submitted
            // under one spelling and self-declared under another, and a
            // sitemap entry whose page points somewhere else is the definition
            // of the duplicate a crawler reports. The frame is still pinned:
            // where a coin's first frame is NOT the site default, that frame is
            // genuinely different content and keeps its own URL.
            $q = '?symbol=' . rawurlencode((string)$sym)
               . ($tf === $defaultTf ? '' : '&tf=' . rawurlencode($tf));
            $urls[] = ['charts' . $ext . $q, 'hourly', '0.6'];
        }
    } catch (\Throwable $e) {
        // A sitemap missing its coin pages is still a valid sitemap.
    }
}

$today = gmdate('Y-m-d');
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($urls as [$path, $freq, $prio]) {
    $loc = $base . '/' . $path;
    echo "  <url>\n"
       . '    <loc>' . htmlspecialchars($loc, ENT_XML1 | ENT_QUOTES, 'UTF-8') . "</loc>\n"
       . '    <lastmod>' . $today . "</lastmod>\n"
       . '    <changefreq>' . $freq . "</changefreq>\n"
       . '    <priority>' . $prio . "</priority>\n"
       . "  </url>\n";
}
echo '</urlset>' . "\n";
