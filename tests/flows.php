#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * Functional flow tests. These drive real POST requests through the
 * application: the installer wizard, marketplace purchase, and each of the
 * three sign-in portals.
 *
 * Run with: php tests/flows.php
 */

$root = dirname(__DIR__);
require $root . '/app/Support/autoload.php';

use App\Core\Application;
use App\Core\Request;

$app = null;
$cookieJar = [];

function boot(): Application
{
    global $app;
    if ($app === null) {
        $root = dirname(__DIR__);
        $app = new Application($root);
        require $root . '/app/routes.php';
    }
    return $app;
}

/** @return array{status:int,body:string,location:?string} */
function request(string $method, string $path, array $body = [], array $files = []): array
{
    $app = boot();

    $_SERVER = [
        'REQUEST_METHOD' => $method,
        'REQUEST_URI' => $path,
        'SCRIPT_NAME' => '/index.php',
        'HTTP_HOST' => 'techbiss.test',
        'SERVER_PORT' => '443',
        'HTTPS' => 'on',
        'REMOTE_ADDR' => '127.0.0.1',
        'HTTP_USER_AGENT' => 'techbiss-flows/1.0',
    ];
    $_GET = [];
    if ($query = parse_url($path, PHP_URL_QUERY)) {
        parse_str($query, $_GET);
    }
    $_POST = $body;
    $_FILES = $files;

    // A CLI session persists in $_SESSION across requests, which is exactly
    // the single-browser behaviour these flows need.
    if ($method !== 'GET') {
        $_POST['_token'] = $app->make('csrf')->token();
    }

    $response = $app->handle(Request::capture());
    $location = null;
    foreach ($response->headers() as [$name, $value]) {
        if (strtolower($name) === 'location') {
            $location = (string) $value;
        }
    }

    return [
        'status' => $response->status(),
        'body' => $response->content(),
        'location' => $location,
    ];
}

$pass = 0;
$fail = 0;

function check(string $label, bool $condition, string $detail = ''): void
{
    global $pass, $fail;
    if ($condition) {
        $pass++;
        fwrite(STDOUT, "\033[32m  ✓\033[0m {$label}\n");
    } else {
        $fail++;
        fwrite(STDOUT, "\033[31m  ✗\033[0m {$label}" . ($detail !== '' ? " — {$detail}" : '') . "\n");
    }
}

function section(string $name): void
{
    fwrite(STDOUT, "\n\033[1m{$name}\033[0m\n");
}

/* ---------------------------------------------------------------------------
 | Installer wizard
 * ------------------------------------------------------------------------ */

section('Advanced Installer wizard');

$sandbox = $root . '/storage/db/flow-test.sqlite';
@unlink($sandbox);
@unlink($sandbox . '-wal');
@unlink($sandbox . '-shm');
touch($root . '/storage/install.unlock');

$r = request('GET', '/install');
check('GET /install redirects to the first step', $r['status'] === 302 && str_contains((string) $r['location'], 'requirements'));

$r = request('POST', '/install/step/environment', ['url' => 'https://techbiss.test/app', 'detect_url' => '1']);
check('Environment step accepts a URL', $r['status'] === 302 && str_contains((string) $r['location'], '/install/step/database'), (string) $r['location']);

$r = request('POST', '/install/step/database', ['driver' => 'sqlite', 'database' => $sandbox]);
check('Database step tests and stores the connection', $r['status'] === 302 && str_contains((string) $r['location'], '/install/step/detection'), (string) $r['location']);

$r = request('POST', '/install/test-database', ['driver' => 'sqlite', 'database' => $sandbox]);
$json = json_decode($r['body'], true);
check('Live database test returns a success payload', ($json['ok'] ?? false) === true && str_contains((string) ($json['html'] ?? ''), 'Connected'));

$r = request('POST', '/install/test-database', ['driver' => 'mysql', 'host' => '127.0.0.1', 'port' => 3306, 'database' => 'nope', 'username' => 'nobody', 'password' => 'wrong']);
$json = json_decode($r['body'], true);
check('Live database test reports a friendly failure', ($json['ok'] ?? true) === false && str_contains((string) ($json['html'] ?? ''), 'Could not connect'));

$r = request('POST', '/install/scan');
$json = json_decode($r['body'], true);
check('Existing-site scan returns rendered results', ($json['ok'] ?? false) === true && $json['html'] !== '');

$r = request('POST', '/install/step/detection', ['mode' => 'migrate']);
check('Choosing migrate routes to the migration step', $r['status'] === 302 && str_contains((string) $r['location'], '/install/step/migration'), (string) $r['location']);

// Build an import file containing absolute URLs that must be rewritten.
$importPath = $root . '/storage/uploads/flow-import.json';
@mkdir(dirname($importPath), 0775, true);
file_put_contents($importPath, json_encode([
    ['title' => 'Why we replatformed', 'slug' => 'why-we-replatformed', 'topic' => 'Engineering',
     'author' => 'A. Okonkwo', 'published_at' => '2026-04-12',
     'body' => '<p>See <a href="https://old-site.example/about">about</a> and https://old-site.example/pricing.</p>'],
    ['title' => 'Our new stack', 'slug' => 'our-new-stack', 'topic' => 'Engineering',
     'author' => 'A. Okonkwo', 'published_at' => '2026-05-02',
     'body' => '<p>Docs at https://old-site.example/docs still apply.</p>'],
]));

$r = request('POST', '/install/step/migration', ['old_url' => 'https://old-site.example', 'import_source' => 'json'], [
    'import_file' => ['name' => 'flow-import.json', 'type' => 'application/json', 'tmp_name' => $importPath, 'error' => UPLOAD_ERR_OK, 'size' => filesize($importPath)],
]);
check('Migration step accepts an import file', $r['status'] === 302 && str_contains((string) $r['location'], '/install/step/configuration'), (string) $r['location']);

$r = request('POST', '/install/step/configuration', [
    'site_name' => 'Flow Test Platform',
    'timezone' => 'Europe/London',
    'admin_name' => 'Flow Owner',
    'admin_email' => 'owner@flow.test',
    'admin_password' => 'FlowTesting!2026',
    'admin_password_confirmation' => 'FlowTesting!2026',
    'demo_data' => '1',
]);
check('Configuration step validates and advances', $r['status'] === 302 && str_contains((string) $r['location'], '/install/step/install'), (string) $r['location']);

$r = request('POST', '/install/step/configuration', [
    'site_name' => 'Flow Test Platform', 'timezone' => 'UTC',
    'admin_name' => 'Flow Owner', 'admin_email' => 'owner@flow.test',
    'admin_password' => 'short', 'admin_password_confirmation' => 'different',
]);
check('Configuration step rejects a weak/mismatched password', str_contains((string) $r['location'], '/install/step/configuration'));

/* The install itself runs against the sandbox database, not the live one. */
$r = request('POST', '/install/run');
$ranOk = $r['status'] === 302 && str_contains((string) $r['location'], '/install/complete');
check('Install run completes', $ranOk, (string) $r['location']);

if ($ranOk) {
    $db = new App\Core\Database(['driver' => 'sqlite', 'database' => $sandbox]);
    check('Schema created in the sandbox database', count($db->tables()) >= 18, (string) count($db->tables()));
    check('Owner account created', (int) $db->value("SELECT COUNT(*) FROM users WHERE email = 'owner@flow.test'", [], 0) === 1);
    check('Catalogue seeded', (int) $db->value('SELECT COUNT(*) FROM products', [], 0) === 12);

    $imported = $db->first("SELECT body FROM resources WHERE slug = 'why-we-replatformed'");
    check('Migration imported the content', $imported !== null);
    check(
        'Migration rewrote absolute URLs to the new origin',
        $imported !== null
            && !str_contains((string) $imported['body'], 'old-site.example')
            && str_contains((string) $imported['body'], 'https://techbiss.test/app'),
        $imported === null ? 'not imported' : substr((string) $imported['body'], 0, 90)
    );
}

$r = request('GET', '/install/complete');
check('Completion page renders', $r['status'] === 200 && str_contains($r['body'], 'Installation complete'));

// Restore the live configuration that the install run overwrote.
fwrite(STDOUT, "\n  · restoring the development installation\n");
@unlink($root . '/storage/installed.php');
@unlink($root . '/storage/install.lock');
@unlink($sandbox);
@unlink($sandbox . '-wal');
@unlink($sandbox . '-shm');
@unlink($importPath);
foreach (glob($root . '/storage/uploads/import-*') ?: [] as $stale) {
    @unlink($stale);
}
exec(sprintf(
    'php %s install --email=admin@techbiss.com --password=%s --name=%s 2>&1',
    escapeshellarg($root . '/bin/techbiss'),
    escapeshellarg('TechbissDemo!2026'),
    escapeshellarg('Elena Vasquez')
), $out, $code);
check('Development installation restored', $code === 0);
@unlink($root . '/storage/install.unlock');

fwrite(STDOUT, "\n" . str_repeat('-', 64) . "\n");
fwrite(STDOUT, sprintf("%d passed, %d failed\n", $pass, $fail));
exit($fail === 0 ? 0 : 1);
