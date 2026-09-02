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
  padding: 28px clamp(20px, 5vw, 34px); font-size: 14.5px; line-height: 1.8; color: var(--text); }
.page-body p { margin: 0 0 18px; }
.page-body p:last-child { margin-bottom: 0; }
.page-body .clause { display: flex; gap: 14px; align-items: flex-start; margin: 0 0 18px; }
.page-body .clause:last-child { margin-bottom: 0; }
.page-body .clause-n { flex: none; width: 26px; height: 26px; border-radius: 50%;
  background: var(--accent-soft); color: var(--accent); font: 700 12px/26px var(--font, inherit);
  text-align: center; margin-top: 1px; }
.page-body .clause p { margin: 0; }
.page-body strong { color: var(--accent); }
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

<?php $paragraphs = preg_split('/\n{2,}/', trim($content)) ?: []; ?>
<div class="page-wrap">
  <h1><?= sma_e($title) ?></h1>
  <div class="page-body">
    <?php foreach ($paragraphs as $para):
        $para = trim($para);
        if ($para === '') { continue; }
        // "1. ..." clauses (Terms of Use is written this way) get a number
        // badge instead of just sitting in the text - the numbering is
        // already the content's own structure, this just makes it visible
        // at a glance instead of requiring a full read to find clause 4.
        if (preg_match('/^(\d{1,2})\.\s+(.+)$/s', $para, $m)): ?>
    <div class="clause"><span class="clause-n"><?= sma_e($m[1]) ?></span><p><?= nl2br(sma_e($m[2])) ?></p></div>
    <?php // "Label: rest of the sentence" (Privacy/Risk use this) - bolding
          // the label turns a paragraph you have to read to categorise into
          // one you can scan. Kept narrow (short, single line, no sentence-
          // ending punctuation before the colon) so it never fires on a
          // colon that just happens to appear inside ordinary prose.
          elseif (preg_match('/^([A-Z][A-Za-z0-9 ()\/&\'-]{2,38}):\s+(.+)$/s', $para, $m2)): ?>
    <p><strong><?= sma_e($m2[1]) ?>:</strong> <?= nl2br(sma_e($m2[2])) ?></p>
    <?php else: ?>
    <p><?= nl2br(sma_e($para)) ?></p>
    <?php endif; endforeach; ?>
  </div>
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
