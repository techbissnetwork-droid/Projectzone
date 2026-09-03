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

/* Budgets, in bytes, for the gzipped HTML document. */
const BUDGET_HTML_GZIP = 40 * 1024;
const BUDGET_CRITICAL_CSS_GZIP = 9 * 1024;
const BUDGET_DEFERRED_CSS_GZIP = 16 * 1024;
const BUDGET_JS_GZIP = 8 * 1024;
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
$appJs = (string) file_get_contents($root . '/public/assets/js/app.js');

assertBudget('critical.css (inlined, gzipped)', strlen(gzencode($criticalCss, 9) ?: ''), BUDGET_CRITICAL_CSS_GZIP);
assertBudget('main.css (deferred, gzipped)', strlen(gzencode($mainCss, 9) ?: ''), BUDGET_DEFERRED_CSS_GZIP);
assertBudget('app.js (deferred, gzipped)', strlen(gzencode($appJs, 9) ?: ''), BUDGET_JS_GZIP);

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
checkClaim('A noscript fallback stylesheet is present', str_contains($home, '<noscript><link rel="stylesheet"'));
checkClaim('The only script is deferred', substr_count($home, '<script src=') === 1 && str_contains($home, 'defer></script>'));
checkClaim('No external font request', !str_contains($home, 'fonts.googleapis.com') && !str_contains($home, '@font-face'));
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
