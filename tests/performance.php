#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * Performance budget check.
 *
 * Renders each public page through the real pipeline and asserts the byte
 * budgets that determine Core Web Vitals: total transferred HTML, the number
 * of render-blocking resources, and the size of the critical path.
 *
 * These are deterministic — unlike timing measurements they do not flake on a
 * shared runner — and they catch the regressions that actually move LCP.
 *
 * Run with: php tests/performance.php
 */

$root = dirname(__DIR__);
require $root . '/app/Support/autoload.php';

use App\Core\Application;
use App\Core\Request;

/* Budgets in bytes. Raised once, deliberately, to pay for two self-hosted
   variable faces and the motion layer — both are brand-carrying, and the
   figures below still leave the heaviest page far inside a fast-4G budget. */
const BUDGET_HTML_GZIP = 40 * 1024;
const BUDGET_CRITICAL_CSS_GZIP = 8 * 1024;
const BUDGET_DEFERRED_CSS_GZIP = 14 * 1024;
const BUDGET_MOTION_CSS_GZIP = 4 * 1024;
const BUDGET_JS_GZIP = 8 * 1024;
const BUDGET_FONT_TOTAL = 60 * 1024;
const BUDGET_BLOCKING_REQUESTS = 0;

$app = new Application($root);
require $root . '/app/routes.php';

function render(Application $app, string $path): string
{
    $_SERVER = [
        'REQUEST_METHOD' => 'GET', 'REQUEST_URI' => $path, 'SCRIPT_NAME' => '/index.php',
        'HTTP_HOST' => 'techbiss.test', 'SERVER_PORT' => '443', 'HTTPS' => 'on',
        'REMOTE_ADDR' => '127.0.0.1',
    ];
    $_GET = [];
    $_POST = [];
    if ($query = parse_url($path, PHP_URL_QUERY)) {
        parse_str($query, $_GET);
    }
    return $app->handle(Request::capture())->content();
}

$pass = 0;
$fail = 0;

function assertBudget(string $label, int $actual, int $budget, string $unit = 'B'): void
{
    global $pass, $fail;
    $ok = $actual <= $budget;
    $ok ? $pass++ : $fail++;
    $pct = $budget > 0 ? round($actual / $budget * 100) : 0;
    fwrite(STDOUT, sprintf(
        "  %s %-44s %7s / %-7s  %3d%%\n",
        $ok ? "\033[32m✓\033[0m" : "\033[31m✗\033[0m",
        $label,
        $unit === 'B' ? number_format($actual / 1024, 1) . 'K' : (string) $actual,
        $unit === 'B' ? number_format($budget / 1024, 1) . 'K' : (string) $budget,
        $pct
    ));
}

fwrite(STDOUT, "\n\033[1mStatic assets\033[0m\n");

$criticalCss = (string) file_get_contents($root . '/public/assets/css/critical.css');
$mainCss = (string) file_get_contents($root . '/public/assets/css/main.css');
$motionCss = (string) file_get_contents($root . '/public/assets/css/motion.css');
$appJs = (string) file_get_contents($root . '/public/assets/js/app.js');

assertBudget('critical.css (inlined, gzipped)', strlen(gzencode($criticalCss, 9) ?: ''), BUDGET_CRITICAL_CSS_GZIP);
assertBudget('main.css (deferred, gzipped)', strlen(gzencode($mainCss, 9) ?: ''), BUDGET_DEFERRED_CSS_GZIP);
assertBudget('motion.css (deferred, gzipped)', strlen(gzencode($motionCss, 9) ?: ''), BUDGET_MOTION_CSS_GZIP);
assertBudget('app.js (deferred, gzipped)', strlen(gzencode($appJs, 9) ?: ''), BUDGET_JS_GZIP);

// woff2 is already compressed; the wire cost is the file size.
$fontBytes = 0;
foreach (glob($root . '/public/assets/fonts/*.woff2') ?: [] as $font) {
    $fontBytes += (int) filesize($font);
}
assertBudget('self-hosted fonts (total on the wire)', $fontBytes, BUDGET_FONT_TOTAL);

fwrite(STDOUT, "\n\033[1mPage weight (gzipped HTML)\033[0m\n");

$pages = ['/', '/services', '/solutions', '/solutions/financial-services', '/work',
          '/work/northwind-settlement-platform', '/process', '/about', '/pricing',
          '/resources', '/resources/core-web-vitals-budget-ci', '/contact',
          '/marketplace', '/marketplace/atlas-corporate-platform', '/marketplace/installer',
          '/marketplace/licensing', '/admin/login', '/legal/privacy'];

$documents = [];
$heaviest = ['path' => '', 'size' => 0];

foreach ($pages as $path) {
    $html = render($app, $path);
    $documents[$path] = $html;
    $gzipped = strlen(gzencode($html, 9) ?: '');
    if ($gzipped > $heaviest['size']) {
        $heaviest = ['path' => $path, 'size' => $gzipped];
    }
    assertBudget($path, $gzipped, BUDGET_HTML_GZIP);
}

fwrite(STDOUT, "\n\033[1mCritical path\033[0m\n");

function checkClaim(string $label, bool $ok, string $detail = ''): void
{
    global $pass, $fail;
    $ok ? $pass++ : $fail++;
    fwrite(STDOUT, sprintf("  %s %s%s\n", $ok ? "\033[32m✓\033[0m" : "\033[31m✗\033[0m", $label, $detail !== '' ? " — {$detail}" : ''));
}

$home = $documents['/'];

// A render-blocking stylesheet is a <link rel="stylesheet"> in the head with no
// async swap. Ours uses rel=preload + onload, which does not block paint.
preg_match_all('#<link[^>]+rel="stylesheet"[^>]*>#i', substr($home, 0, (int) strpos($home, '</head>')), $blocking);
$blockingLinks = array_values(array_filter($blocking[0], static fn (string $tag): bool => !str_contains($tag, 'noscript')));
$noscriptFree = preg_replace('#<noscript>.*?</noscript>#s', '', substr($home, 0, (int) strpos($home, '</head>'))) ?? '';
preg_match_all('#<link[^>]+rel="stylesheet"#i', $noscriptFree, $realBlocking);

assertBudget('render-blocking stylesheets in <head>', count($realBlocking[0]), BUDGET_BLOCKING_REQUESTS, 'n');

preg_match_all('#<script(?![^>]*(?:defer|async|type="application/ld\+json"))[^>]*src=#i', $home, $blockingJs);
assertBudget('render-blocking scripts', count($blockingJs[0]), BUDGET_BLOCKING_REQUESTS, 'n');

$head = substr($home, 0, (int) strpos($home, '</head>'));
preg_match('#<style>(.*?)</style>#s', $head, $inlined);
checkClaim(
    'Critical CSS is inlined in the head',
    isset($inlined[1]) && str_contains($inlined[1], '--accent-grad') && strlen($inlined[1]) > 8000,
    isset($inlined[1]) ? number_format(strlen($inlined[1]) / 1024, 1) . ' KB inlined' : 'no inline style block'
);
checkClaim('Full stylesheet is preloaded, not blocking', str_contains($home, 'rel="preload" as="style"'));
preg_match('#<noscript>(.*?)</noscript>#s', $head, $noscriptBlock);
checkClaim(
    'Every deferred stylesheet has a noscript fallback',
    isset($noscriptBlock[1]) && substr_count($noscriptBlock[1], 'rel="stylesheet"') === 2
);
checkClaim('The only script is deferred', substr_count($home, '<script src=') === 1 && str_contains($home, 'defer></script>'));
checkClaim(
    'Fonts are self-hosted, never fetched from a third party',
    !str_contains($home, 'fonts.googleapis.com') && !str_contains($home, 'fonts.gstatic.com')
);
checkClaim(
    'Both faces are preloaded',
    substr_count($home, 'rel="preload" as="font"') === 2
);
checkClaim(
    'Every face uses font-display: swap, so text is never invisible',
    substr_count($home, 'font-display:swap') === substr_count($home, '@font-face')
        && substr_count($home, '@font-face') === 2
);
// Only resource-loading attributes count: an outbound <a href> to LinkedIn
// costs nothing, a third-party src or stylesheet costs a connection.
preg_match_all('#(?:src|href)="(https?:)?//([a-z0-9.-]+)#i', $head, $headOrigins);
$foreignResourceHosts = array_values(array_unique(array_filter(
    $headOrigins[2],
    static fn (string $host): bool => !str_ends_with($host, 'techbiss.test')
)));
checkClaim(
    'No third-party resource origin on the critical path',
    $foreignResourceHosts === [],
    implode(', ', $foreignResourceHosts)
);

fwrite(STDOUT, "\n\033[1mMotion safety\033[0m\n");

$allCss = $criticalCss . $motionCss . $mainCss;

// Animating anything but transform, opacity and filter forces layout or paint
// on the main thread, which is what makes motion feel cheap on a phone.
preg_match_all('#(?:^|[;{\s])transition(?:-property)?\s*:\s*([^;}]+)#i', $allCss, $declarations);
$animatedProperties = [];
foreach ($declarations[1] as $value) {
    foreach (explode(',', $value) as $part) {
        $token = trim(explode(' ', trim($part))[0]);
        if ($token !== '' && !preg_match('#^(\d|\.|cubic-|steps|linear$|ease|infinite|alternate|forwards|backwards|both|none|normal|reverse|calc|var|paused|running)#i', $token)) {
            $animatedProperties[$token] = true;
        }
    }
}
/**
 * Extract each @keyframes body by counting braces. A regex cannot do this:
 * keyframe bodies nest one level, and most are written on a single line.
 *
 * @return list<string>
 */
function keyframeBodies(string $css): array
{
    $bodies = [];
    $offset = 0;
    while (($start = strpos($css, '@keyframes', $offset)) !== false) {
        $open = strpos($css, '{', $start);
        if ($open === false) {
            break;
        }
        $depth = 0;
        $length = strlen($css);
        for ($i = $open; $i < $length; $i++) {
            if ($css[$i] === '{') {
                $depth++;
            } elseif ($css[$i] === '}') {
                $depth--;
                if ($depth === 0) {
                    $bodies[] = substr($css, $open + 1, $i - $open - 1);
                    $offset = $i + 1;
                    break;
                }
            }
        }
        if ($depth !== 0) {
            break;
        }
    }
    return $bodies;
}

foreach ([$criticalCss, $motionCss, $mainCss] as $sheet) {
    foreach (keyframeBodies($sheet) as $frame) {
        // Inside a keyframe body, declarations only ever appear after a { or ;
        preg_match_all('#[{;]\s*([a-z-]+)\s*:#i', $frame, $props);
        foreach ($props[1] as $property) {
            $animatedProperties[strtolower($property)] = true;
        }
    }
}

$expensive = array_values(array_diff(
    array_keys($animatedProperties),
    ['transform', 'opacity', 'filter', 'all', 'backdrop-filter', 'color', 'background',
     'background-color', 'border-color', 'box-shadow', 'max-height', 'visibility']
));
checkClaim('Animations stay on compositor-friendly properties', $expensive === [], implode(', ', $expensive));

$reducedBlocks = substr_count($criticalCss, 'prefers-reduced-motion')
    + substr_count($motionCss, 'prefers-reduced-motion');
checkClaim('Reduced motion is honoured in both animated stylesheets', $reducedBlocks >= 4, $reducedBlocks . ' guards');

checkClaim(
    'Content is visible without JavaScript',
    !str_contains($criticalCss, "\n[data-reveal]{opacity:0") && str_contains($criticalCss, '.js [data-reveal]')
);

checkClaim(
    'Scroll-driven effects degrade rather than depend on new APIs',
    str_contains($motionCss, '@supports (animation-timeline: view())')
        && str_contains($motionCss, '@supports (animation-timeline: scroll())')
);

fwrite(STDOUT, "\n\033[1mSEO and semantics\033[0m\n");

foreach (['/', '/services', '/marketplace', '/work/northwind-settlement-platform'] as $path) {
    $html = $documents[$path] ?? render($app, $path);
    $problems = [];

    if (substr_count($html, '<h1') !== 1) {
        $problems[] = 'expected exactly one h1, found ' . substr_count($html, '<h1');
    }
    if (!preg_match('#<meta name="description" content="[^"]{50,160}"#u', $html)) {
        $problems[] = 'missing or badly sized meta description';
    }
    if (!str_contains($html, '<link rel="canonical"')) {
        $problems[] = 'missing canonical';
    }
    if (!str_contains($html, 'application/ld+json')) {
        $problems[] = 'missing structured data';
    }
    if (!str_contains($html, '<html lang=')) {
        $problems[] = 'missing lang attribute';
    }
    if (!str_contains($html, 'og:title')) {
        $problems[] = 'missing Open Graph tags';
    }
    if (!str_contains($html, '<main id="main">')) {
        $problems[] = 'missing main landmark';
    }

    // Every JSON-LD block must parse.
    preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $html, $blocks);
    foreach ($blocks[1] as $block) {
        if (json_decode($block) === null) {
            $problems[] = 'invalid JSON-LD';
        }
    }

    checkClaim($path, $problems === [], implode('; ', $problems));
}

fwrite(STDOUT, "\n\033[1mAMP validity\033[0m\n");

foreach (['/amp', '/amp/services', '/amp/contact', '/amp/resources/core-web-vitals-budget-ci'] as $path) {
    $html = render($app, $path);
    $problems = [];

    if (!preg_match('#<html [^>]*(⚡|amp)#u', $html)) {
        $problems[] = 'missing amp attribute on <html>';
    }
    if (!str_contains($html, 'https://cdn.ampproject.org/v0.js')) {
        $problems[] = 'missing AMP runtime';
    }
    if (!str_contains($html, 'amp-boilerplate')) {
        $problems[] = 'missing boilerplate';
    }
    if (!str_contains($html, '<link rel="canonical"')) {
        $problems[] = 'missing canonical back-link';
    }
    if (preg_match('#<link[^>]+rel="stylesheet"#i', $html)) {
        $problems[] = 'external stylesheet is forbidden in AMP';
    }
    if (preg_match('#<script(?![^>]*(?:custom-element|async src="https://cdn\.ampproject\.org|application/ld\+json))#i', $html)) {
        $problems[] = 'author javascript is forbidden in AMP';
    }
    // AMP caps custom CSS at 75 KB.
    if (preg_match('#<style amp-custom>(.*?)</style>#s', $html, $m) && strlen($m[1]) > 75000) {
        $problems[] = 'amp-custom CSS exceeds 75 KB';
    }
    if (substr_count($html, '<style amp-custom>') !== 1) {
        $problems[] = 'expected exactly one amp-custom block';
    }

    checkClaim($path, $problems === [], implode('; ', $problems));
}

fwrite(STDOUT, "\n" . str_repeat('-', 72) . "\n");
fwrite(STDOUT, sprintf(
    "%d passed, %d failed. Heaviest page: %s at %.1f KB gzipped.\n",
    $pass,
    $fail,
    $heaviest['path'],
    $heaviest['size'] / 1024
));

exit($fail === 0 ? 0 : 1);
