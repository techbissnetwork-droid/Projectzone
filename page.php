<?php
declare(strict_types=1);

/**
 * Legal pages: Terms of Use, Privacy Policy, Risk Disclosure.
 * Content is plain text edited in Admin > Settings > Legal & trust
 * (sensible defaults are seeded); rendered safely with paragraphs preserved.
 */

$config = require __DIR__ . '/src/bootstrap.php';

// The shared navigation shows the signed-in member's account, so the session
// has to be open before it renders - otherwise a logged-in reader who opens
// the terms page is told to log in again.
\SignalMasterAi\MemberAuth::start();

$pages = [
    'terms'   => ['Terms of Use', 'terms_content'],
    'privacy' => ['Privacy Policy', 'privacy_content'],
    'risk'    => ['Risk Disclosure', 'risk_content'],
];
// Cast before the lookup - PHP 8 throws a TypeError reading an array offset
// with an array key, and ?p[]=x is a one-character request away from anyone
// probing query params, on a page linked from every footer on the site.
$p = (string)($_GET['p'] ?? 'terms');
if (!isset($pages[$p])) {
    http_response_code(404);
    $p = 'terms';
}
[$title, $key] = $pages[$p];
$content = sma_setting($key);
$siteName = sma_setting('site_name', $config['app_name']);
$hex = fn(string $k, string $d) => preg_match('/^#[0-9a-f]{6}$/i', $v = sma_setting($k, $d)) ? $v : $d;
$accent = $hex('accent_color', \SignalMasterAi\View::BRAND_DEFAULTS['accent_color']);
?>
<?php
// Through the shared head, like every other public page.
//
// These are the terms, the privacy notice and the risk disclosure - the three
// pages a visitor checks before deciding whether to trust a trading site, and
// the three most likely to be linked from somewhere else. They hand-wrote a
// head with no canonical and no share preview, so a link to the risk
// disclosure showed as a bare URL.
//
// Still noindex: the text is boilerplate that appears on a thousand sites and
// there is nothing to be gained by competing for it. "follow" though - the
// links out of these pages lead back into the site, and that is worth having.
$pageCss = <<<'CSS'
.page-wrap { max-width: 760px; margin: 30px auto; padding: 0 18px; }
.page-wrap h1 { font-size: clamp(22px, 4vw, 30px); margin-bottom: 6px; }
.page-updated { color: var(--muted); font-size: 12px; margin-bottom: 18px; }
.page-body { background: var(--surface); border: 1px solid var(--border); border-radius: 14px;
  padding: 24px; font-size: 14px; line-height: 1.85; color: var(--text); white-space: pre-wrap; }
.page-nav { margin-top: 18px; font-size: 13px; }
.page-nav a { color: var(--accent); text-decoration: none; margin-right: 14px; }
CSS;
\SignalMasterAi\View::head(
    $title,
    // head() trims this to a search-snippet length on its own, at a whole
    // word rather than mid-sentence - no need to pre-cut it here.
    trim(preg_replace('/\s+/', ' ', $content)),
    // Which page this is lives in the query string, so the canonical has to
    // carry it - without this all three legal pages claim the same address.
    ['noindex' => true, 'style' => $pageCss, 'query' => ['p' => $p]]
);
?>
<?php \SignalMasterAi\View::topbar(''); ?>

<div class="page-wrap">
  <h1><?= sma_e($title) ?></h1>
  <div class="page-body"><?= sma_e($content) ?></div>
  <p class="page-nav">
    <a href="page.php?p=terms">Terms of Use</a>
    <a href="page.php?p=privacy">Privacy Policy</a>
    <a href="page.php?p=risk">Risk Disclosure</a>
    <a href="performance.php">Track record</a>
  </p>
</div>

<footer class="footer">
  <p><?= sma_e(sma_setting('site_notice')) ?></p>
</footer>
<script src="assets/ui.js?v=<?= @filemtime(__DIR__ . '/assets/ui.js') ?: 1 ?>" defer></script>
</body>
</html>
