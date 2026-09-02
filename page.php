<?php
declare(strict_types=1);
require_once __DIR__ . '/app/bootstrap.php';

$slug = (string)($_GET['slug'] ?? '');
$page = Content::page($slug);

if (!$page) {
    http_response_code(404);
    $PAGE_TITLE = 'Page not found';
    require __DIR__ . '/partials/public_header.php';
    echo '<section class="pagehead" data-theme="deep"><div class="shell">'
       . '<h1 class="pagehead__title">Page not found.</h1>'
       . '<p class="pagehead__lede">That address does not exist, or the page is not published yet. '
       . '<a class="link" href="' . e(url()) . '">Back to the home page →</a></p></div></section>';
    require __DIR__ . '/partials/public_footer.php';
    exit;
}

$PAGE_TITLE = $page['meta_title'] ?: $page['title'];
$META_DESC  = $page['meta_desc'] ?: excerpt($page['subtitle'] ?: $page['body'], 158);
$CANONICAL  = url('page.php?slug=' . urlencode($page['slug']));
require __DIR__ . '/partials/public_header.php';
?>
<?php if ($page['hero_style'] !== 'plain'): ?>
  <section class="pagehead<?= $page['hero_style'] === 'large' ? ' pagehead--lg' : '' ?>" data-theme="deep">
    <div class="shell">
      <?php if ($page['eyebrow']): ?><p class="eyebrow reveal"><?= e($page['eyebrow']) ?></p><?php endif; ?>
      <h1 class="pagehead__title reveal"><?= e($page['title']) ?></h1>
      <?php if ($page['subtitle']): ?><p class="pagehead__lede reveal"><?= e($page['subtitle']) ?></p><?php endif; ?>
    </div>
  </section>
<?php endif; ?>

<article class="prose">
  <div class="shell prose__inner">
    <?php if ($page['hero_style'] === 'plain'): ?>
      <h1 class="prose__h1 reveal"><?= e($page['title']) ?></h1>
      <?php if ($page['subtitle']): ?><p class="prose__lede reveal"><?= e($page['subtitle']) ?></p><?php endif; ?>
    <?php endif; ?>

    <?php
    /* Plain text in, structured HTML out. Walks the body line by line so a
       heading, a list and a paragraph can sit in the same block:
         "Something:"  on its own short line  → heading
         lines beginning "- " or "* "         → list, consecutive ones grouped
         anything else                        → paragraph, blank line ends it */
    $out    = [];
    $para   = [];
    $bullet = [];
    $flushPara = static function () use (&$para, &$out): void {
        if ($para) { $out[] = ['p', implode("\n", $para)]; $para = []; }
    };
    $flushList = static function () use (&$bullet, &$out): void {
        if ($bullet) { $out[] = ['ul', $bullet]; $bullet = []; }
    };
    foreach (preg_split('/\r\n|\r|\n/', (string)$page['body']) ?: [] as $raw) {
        $line = rtrim($raw);
        if (trim($line) === '') { $flushList(); $flushPara(); continue; }
        if (preg_match('/^\s*[-*]\s+(.*)$/u', $line, $m)) {
            $flushPara();
            $bullet[] = trim($m[1]);
            continue;
        }
        $flushList();
        $t = trim($line);
        if (!$para && mb_strlen($t) < 90 && str_ends_with($t, ':')) {
            $out[] = ['h2', rtrim($t, ':')];
            continue;
        }
        $para[] = $t;
    }
    $flushList();
    $flushPara();

    foreach ($out as [$type, $value]):
        if ($type === 'h2'): ?>
            <h2 class="prose__h2 reveal"><?= e($value) ?></h2>
        <?php elseif ($type === 'ul'): ?>
            <ul class="prose__list reveal">
              <?php foreach ($value as $li): ?><li><?= e($li) ?></li><?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p class="reveal"><?= enl($value) ?></p>
        <?php endif;
    endforeach;
    ?>
  </div>
</article>

<?php if ((int)$page['show_cta'] === 1) { require __DIR__ . '/partials/section_cta.php'; } ?>
<?php require __DIR__ . '/partials/public_footer.php'; ?>
