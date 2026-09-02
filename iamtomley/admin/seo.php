<?php
/**
 * What a search engine actually sees.
 *
 * Search Console reports problems without showing you the request it made, so
 * this asks your live site the same questions from the server and prints every
 * hop: the address it started at, each redirect, and where it ended up. That
 * turns "many redirect issues" into a list you can act on.
 */
declare(strict_types=1);

$__page = 'seo';
$title = 'Search engines';
require_once __DIR__ . '/_bootstrap.php';
require_login();

/** The robots.txt this site would serve, as text — used for the file we write. */
function robots_text(): string
{
    $base = BASE_URL;
    $out  = "User-agent: *\n";
    $out .= "Allow: {$base}/\n";
    foreach (['/admin/', '/install/', '/includes/', '/data/', '/config.php',
              '/game.php', '/game/'] as $path) {
        $out .= "Disallow: {$base}{$path}\n";
    }
    return $out . "\nSitemap: " . sitemap_url() . "\n";
}

$wrote = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (($_POST['action'] ?? '') === 'write_robots') {
        $file = APP_ROOT . '/robots.txt';
        $wrote = @file_put_contents($file, robots_text()) !== false;
        flash($wrote
            ? 'robots.txt written with your own address and the sitemap line.'
            : 'Could not write robots.txt — the site folder is not writable. Copy the text below into it by hand.');
        header('Location: ' . url('/admin/seo.php'));
        exit;
    }
}

require_once __DIR__ . '/includes/head.php';

$canonical = trim(setting('seo_canonical'));
$hostNow   = current_origin();
$canonHost = $canonical !== '' ? url_origin($canonical) : '';

// What a crawler reaches for. The browser tests these against this same site,
// which avoids asking the server to answer a request it is already busy making.
$targets = [
    'home'     => ['Home page',            site_url('/')],
    'robots'   => ['robots.txt',           site_url('/robots.txt')],
    'sitemap'  => ['Your sitemap',         sitemap_url()],
    'sitemapx' => ['sitemap.xml',          site_url('/sitemap.xml')],
    'indexphp' => ['Old-style /index.php', site_url('/index.php')],
];
?>
<div class="topbar">
  <div>
    <h1 class="page-title">Search engines</h1>
    <div class="page-sub">What Google gets when it asks your site for the things it needs.</div>
  </div>
  <button class="btn btn-primary" type="button" id="seoRun">Run the checks</button>
</div>

<div class="panel">
  <h2>What a crawler asks for</h2>
  <div class="hint" style="margin-bottom:1rem">
    Each address below is requested from your browser, exactly as a crawler would, and the answer is
    reported with any redirect it went through. Nothing is changed.
  </div>
  <div class="table-wrap">
    <table id="seoTable">
      <thead><tr><th>What</th><th>Address</th><th>Answer</th><th>Verdict</th></tr></thead>
      <tbody>
        <?php foreach ($targets as $key => [$label, $u]): ?>
        <tr data-seo="<?= e($key) ?>" data-url="<?= e($u) ?>">
          <td><strong><?= e($label) ?></strong></td>
          <td style="word-break:break-all"><span class="muted" style="font-size:.76rem"><?= e($u) ?></span></td>
          <td class="seo-code muted">—</td>
          <td class="seo-say muted">not checked yet</td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="detect-note" id="seoProgress">Press “Run the checks”.</div>
</div>

<div class="panel">
  <h2>One address, not four</h2>
  <p class="small" style="line-height:1.75">
    A site usually answers on several addresses — with and without <code>www</code>, over
    <code>http</code> and <code>https</code>. Google counts each as a separate page and reports the
    extras as redirects or duplicates. That is the most common cause of a pile of redirect notices,
    and it is fixed at your host: send every version to one address. Then set the same one here so
    every page agrees.
  </p>
  <div class="grid-2" style="margin-top:1rem">
    <div class="field">
      <label>Canonical address set here</label>
      <div class="detect-note <?= $canonical !== '' ? 'ok' : 'warn' ?>">
        <?= $canonical !== '' ? e($canonical) : 'Not set — each page claims whichever address the visitor happened to arrive on.' ?>
      </div>
    </div>
    <div class="field">
      <label>The address you are on now</label>
      <div class="detect-note <?= ($canonHost === '' || $canonHost === $hostNow) ? '' : 'warn' ?>">
        <?= e($hostNow) ?><?= ($canonHost !== '' && $canonHost !== $hostNow)
          ? ' — this does not match the canonical above, which is exactly the split Search Console reports.' : '' ?>
      </div>
    </div>
  </div>
  <div class="hint">Set it under <strong>Site Settings → SEO &amp; footer → Canonical URL</strong>.</div>
</div>

<div class="panel">
  <h2>robots.txt</h2>
  <div class="hint" style="margin-bottom:1rem">
    Where your host rewrites addresses, <code>/robots.txt</code> is generated for you and reacts to
    maintenance mode. Where it does not, a plain file has to exist instead — otherwise that address
    404s, which crawlers treat as a reason to back off. This writes the file with your own address
    and the sitemap line.
  </div>
  <form method="post" action="<?= e(url('/admin/seo.php')) ?>">
    <?= csrf_field() ?><input type="hidden" name="action" value="write_robots">
    <button class="btn btn-primary" type="submit">Write robots.txt</button>
  </form>
  <div class="hint" style="margin-top:1rem">If that cannot write, copy this into <code>robots.txt</code> in your site folder:</div>
  <pre style="white-space:pre-wrap;font-family:var(--mono);font-size:.78rem;background:var(--glass);padding:1rem;border-radius:var(--r-sm);border:1px solid var(--border);margin-top:.5rem"><?= e(robots_text()) ?></pre>
</div>

<?php require __DIR__ . '/includes/foot.php'; ?>
