#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * Request-level smoke test. Boots the real application once per route and
 * asserts the status code, a required content marker and basic HTML health.
 * Run with: php tests/smoke.php
 */

$root = dirname(__DIR__);
require $root . '/app/Support/autoload.php';

use App\Core\Application;
use App\Core\Request;

/** @return array{0:int,1:string} */
function hit(string $path, string $method = 'GET', array $body = []): array
{
    static $booted = false;
    $root = dirname(__DIR__);

    $_SERVER = [
        'REQUEST_METHOD' => $method,
        'REQUEST_URI' => $path,
        'SCRIPT_NAME' => '/index.php',
        'HTTP_HOST' => 'techbiss.test',
        'SERVER_PORT' => '443',
        'HTTPS' => 'on',
        'HTTP_USER_AGENT' => 'techbiss-smoke/1.0',
        'REMOTE_ADDR' => '127.0.0.1',
    ];
    $_GET = [];
    $query = parse_url($path, PHP_URL_QUERY);
    if ($query) {
        parse_str($query, $_GET);
    }
    $_POST = $body;

    /** @var Application $app */
    static $app = null;
    if (!$booted) {
        $app = new Application($root);
        require $root . '/app/routes.php';
        $booted = true;
    }

    $response = $app->handle(Request::capture());
    return [$response->status(), $response->content()];
}

$cases = [
    ['/', 200, 'We build the platforms'],
    ['/services', 200, 'Platform Engineering'],
    ['/solutions', 200, 'Financial Services'],
    ['/solutions/financial-services', 200, 'Strangle the core'],
    ['/solutions/healthcare', 200, 'Interoperate on FHIR'],
    ['/work', 200, 'Northwind'],
    ['/work/northwind-settlement-platform', 200, 'Prove parity before moving traffic'],
    ['/process', 200, 'Align'],
    ['/about', 200, 'Elena Vasquez'],
    ['/pricing', 200, 'Discovery'],
    ['/resources', 200, 'Core Web Vitals'],
    ['/resources/core-web-vitals-budget-ci', 200, 'Put the budget where commits die'],
    ['/contact', 200, 'Start a conversation'],
    ['/legal/privacy', 200, 'Data Protection Officer'],
    ['/legal/terms', 200, 'Marketplace licences'],
    ['/legal/security', 200, 'Reporting a vulnerability'],
    ['/legal/accessibility', 200, 'WCAG'],
    ['/marketplace', 200, 'Marketplace'],
    ['/marketplace/atlas-corporate-platform', 200, 'Atlas Corporate Platform'],
    ['/marketplace/installer', 200, 'Advanced Installer'],
    ['/marketplace/licensing', 200, 'Standard'],
    ['/marketplace/cart', 200, 'cart'],
    ['/marketplace/preview/orbit-agency-theme', 200, 'Orbit'],
    ['/admin/login', 200, 'Admin'],
    ['/staff/login', 200, 'Staff'],
    ['/client/login', 200, 'Client'],
    ['/admin', 302, ''],
    ['/client', 302, ''],
    ['/sitemap.xml', 200, '<urlset'],
    ['/robots.txt', 200, 'Sitemap:'],
    ['/manifest.webmanifest', 200, 'TECHBISS'],
    ['/feed.xml', 200, '<rss'],
    ['/health', 200, 'ok'],
    ['/amp', 200, 'amp-boilerplate'],
    ['/amp/services', 200, 'amp-boilerplate'],
    ['/amp/resources/core-web-vitals-budget-ci', 200, 'amp-boilerplate'],
    ['/api/marketplace/search?q=commerce', 200, 'html'],
    ['/this-page-does-not-exist', 404, 'Page Not Found'],
];

$pass = 0;
$fail = 0;
$failures = [];

foreach ($cases as [$path, $expectedStatus, $marker]) {
    try {
        [$status, $html] = hit($path);
    } catch (Throwable $e) {
        $fail++;
        $failures[] = sprintf("%-52s THREW %s: %s", $path, $e::class, $e->getMessage());
        continue;
    }

    $problems = [];
    if ($status !== $expectedStatus) {
        $problems[] = "status {$status}, expected {$expectedStatus}";
    }
    if ($marker !== '' && !str_contains($html, $marker)) {
        $problems[] = "missing marker \"{$marker}\"";
    }
    if ($status === 200 && str_starts_with(ltrim($html), '<!DOCTYPE html>')) {
        if (substr_count($html, '<html') !== 1) {
            $problems[] = 'malformed html';
        }
        if (str_contains($html, 'Warning:') || str_contains($html, 'Fatal error')) {
            $problems[] = 'php notice in output';
        }
    }

    if ($problems === []) {
        $pass++;
        fwrite(STDOUT, "\033[32m  ✓\033[0m " . $path . "\n");
    } else {
        $fail++;
        $failures[] = sprintf('%-52s %s', $path, implode('; ', $problems));
        fwrite(STDOUT, "\033[31m  ✗\033[0m " . $path . ' — ' . implode('; ', $problems) . "\n");
    }
}

fwrite(STDOUT, "\n" . str_repeat('-', 64) . "\n");
fwrite(STDOUT, sprintf("%d passed, %d failed of %d routes\n", $pass, $fail, count($cases)));

exit($fail === 0 ? 0 : 1);
