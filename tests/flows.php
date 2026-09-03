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


/* ---------------------------------------------------------------------------
 | Portals
 * ------------------------------------------------------------------------ */

function signIn(string $portal, string $email, string $password): array
{
    return request('POST', "/{$portal}/login", ['email' => $email, 'password' => $password]);
}

function signOut(string $portal): void
{
    request('POST', "/{$portal}/logout");
}

section('Portal authentication');

$r = signIn('admin', 'admin@techbiss.com', 'wrong-password');
check('Bad password is rejected', str_contains((string) $r['location'], '/admin/login'));

$r = signIn('admin', 'client@northwind.example', 'ClientDemo!2026');
check('A client credential cannot open the admin portal', str_contains((string) $r['location'], '/admin/login'));

$r = signIn('admin', 'admin@techbiss.com', 'TechbissDemo!2026');
check('Owner signs into the admin console', str_contains((string) $r['location'], '/admin'), (string) $r['location']);

section('Admin console');

foreach ([
    ['/admin', 'Platform overview'],
    ['/admin/users', 'accounts'],
    ['/admin/products', 'Catalogue'],
    ['/admin/orders', 'orders'],
    ['/admin/leads', 'enquiries'],
    ['/admin/deployments', 'Installations'],
    ['/admin/activity', 'Platform activity'],
    ['/admin/settings', 'Runtime'],
] as [$path, $marker]) {
    $r = request('GET', $path);
    check("GET {$path}", $r['status'] === 200 && str_contains($r['body'], $marker), 'status ' . $r['status']);
}

$app = boot();
$lead = $app->make('db')->first('SELECT id, status FROM leads ORDER BY id LIMIT 1');
$r = request('POST', '/admin/leads/status', ['id' => (int) $lead['id'], 'status' => 'qualified']);
$after = $app->make('db')->first('SELECT status FROM leads WHERE id = ?', [(int) $lead['id']]);
check('Admin can move a lead through the pipeline', ($after['status'] ?? '') === 'qualified');

$product = $app->make('db')->first("SELECT id, status FROM products WHERE status = 'published' ORDER BY id LIMIT 1");
request('POST', '/admin/products/status', ['id' => (int) $product['id'], 'status' => 'retired']);
$after = $app->make('db')->first('SELECT status FROM products WHERE id = ?', [(int) $product['id']]);
check('Admin can retire a product', ($after['status'] ?? '') === 'retired');
request('POST', '/admin/products/status', ['id' => (int) $product['id'], 'status' => 'published']);

$owner = $app->make('db')->first("SELECT id FROM users WHERE role = 'owner' LIMIT 1");
$r = request('POST', '/admin/users/status', ['id' => (int) $owner['id'], 'status' => 'suspended']);
$after = $app->make('db')->first('SELECT status FROM users WHERE id = ?', [(int) $owner['id']]);
check('The only owner cannot be suspended', ($after['status'] ?? '') === 'active');

signOut('admin');
$r = request('GET', '/admin');
check('Signing out revokes admin access', $r['status'] === 302 && str_contains((string) $r['location'], '/admin/login'));

section('Staff workspace');

$r = signIn('staff', 'engineer@techbiss.com', 'StaffDemo!2026');
check('Engineer signs into the staff workspace', str_contains((string) $r['location'], '/staff'), (string) $r['location']);

$r = request('GET', '/admin');
check('Staff cannot reach the admin console', $r['status'] === 403, 'status ' . $r['status']);

foreach ([
    ['/staff', 'Today'],
    ['/staff/tasks', 'tasks'],
    ['/staff/projects', 'Milestones'],
    ['/staff/tickets', 'Reply'],
    ['/staff/pipeline', 'Pipeline'],
] as [$path, $marker]) {
    $r = request('GET', $path);
    check("GET {$path}", $r['status'] === 200 && str_contains($r['body'], $marker), 'status ' . $r['status']);
}

$task = $app->make('db')->first("SELECT id, status FROM tasks WHERE status = 'open' ORDER BY id LIMIT 1");
request('POST', '/staff/tasks/toggle', ['id' => (int) $task['id']]);
$after = $app->make('db')->first('SELECT status FROM tasks WHERE id = ?', [(int) $task['id']]);
check('Staff can complete a task', ($after['status'] ?? '') === 'done');

$ticket = $app->make('db')->first("SELECT id FROM tickets ORDER BY id LIMIT 1");
$before = (int) $app->make('db')->value('SELECT COUNT(*) FROM ticket_replies WHERE ticket_id = ?', [(int) $ticket['id']], 0);
request('POST', '/staff/tickets/reply', [
    'ticket_id' => (int) $ticket['id'],
    'body' => 'That socket path needs to go in the host field as the full path. Try it and let me know.',
    'status' => 'answered',
]);
$afterCount = (int) $app->make('db')->value('SELECT COUNT(*) FROM ticket_replies WHERE ticket_id = ?', [(int) $ticket['id']], 0);
check('Staff can reply to a ticket', $afterCount === $before + 1);

signOut('staff');

section('Client portal');

$r = signIn('client', 'client@northwind.example', 'ClientDemo!2026');
check('Client signs into the client portal', str_contains((string) $r['location'], '/client'), (string) $r['location']);

$r = request('GET', '/staff');
check('Client cannot reach the staff workspace', $r['status'] === 403, 'status ' . $r['status']);

foreach ([
    ['/client', 'Active projects'],
    ['/client/projects', 'Delivery progress'],
    ['/client/licenses', 'Licence key'],
    ['/client/deployments', 'New deployment'],
    ['/client/invoices', 'Outstanding'],
    ['/client/support', 'Raise a ticket'],
] as [$path, $marker]) {
    $r = request('GET', $path);
    check("GET {$path}", $r['status'] === 200 && str_contains($r['body'], $marker), 'status ' . $r['status']);
}

$clientId = (int) $app->make('db')->value("SELECT id FROM users WHERE email = 'client@northwind.example'", [], 0);
$otherProject = $app->make('db')->first('SELECT id FROM projects WHERE client_id != ? LIMIT 1', [$clientId]);
if ($otherProject !== null) {
    $r = request('GET', '/client/projects/' . (int) $otherProject['id']);
    check('A client cannot read another client\'s project', $r['status'] === 404, 'status ' . $r['status']);
}

$license = $app->make('db')->first('SELECT id, license_key FROM licenses WHERE user_id = ? LIMIT 1', [$clientId]);
$r = request('GET', '/client/downloads/' . $license['license_key']);
check('Client can download their licence file', $r['status'] === 200 && str_contains($r['body'], 'licence_key'));

$r = request('GET', '/client/downloads/TBX-00000-00000-00000-00000');
check('An unknown licence key is refused', $r['status'] === 404);

$before = (int) $app->make('db')->value('SELECT COUNT(*) FROM deployments WHERE user_id = ?', [$clientId], 0);
request('POST', '/client/deployments', [
    'license_id' => (int) $license['id'],
    'site_name' => 'Northwind marketing site',
    'target_url' => 'https://marketing.northwind.example',
    'environment' => 'staging',
    'install_mode' => 'migrate',
    'source_platform' => 'wordpress',
    'database_driver' => 'mysql',
]);
$afterCount = (int) $app->make('db')->value('SELECT COUNT(*) FROM deployments WHERE user_id = ?', [$clientId], 0);
check('Client can create a deployment with an install token', $afterCount === $before + 1);

$before = (int) $app->make('db')->value('SELECT COUNT(*) FROM tickets WHERE user_id = ?', [$clientId], 0);
request('POST', '/client/support', [
    'subject' => 'Question about staging domain registration',
    'category' => 'licensing',
    'priority' => 'normal',
    'body' => 'We would like to register a second staging domain against our extended licence. What is the process?',
]);
$afterCount = (int) $app->make('db')->value('SELECT COUNT(*) FROM tickets WHERE user_id = ?', [$clientId], 0);
check('Client can raise a support ticket', $afterCount === $before + 1);

signOut('client');

/* ---------------------------------------------------------------------------
 | Marketplace purchase
 * ------------------------------------------------------------------------ */

section('Marketplace purchase');

$product = $app->make('db')->first("SELECT id, name, price, extended_price FROM products WHERE status = 'published' ORDER BY id LIMIT 1");

$r = request('POST', '/marketplace/cart/add', ['product_id' => (int) $product['id'], 'tier' => 'extended']);
check('Product added to the cart', str_contains((string) $r['location'], '/marketplace/cart'));

$r = request('GET', '/marketplace/cart');
check('Cart shows the line', str_contains($r['body'], (string) $product['name']));

$r = request('GET', '/marketplace/checkout');
check('Checkout is reachable with a populated cart', $r['status'] === 200 && str_contains($r['body'], 'Order summary'));

$r = request('POST', '/marketplace/checkout', [
    'name' => 'Test Buyer', 'email' => 'buyer@example.test', 'company' => 'Example Ltd',
    'country' => 'United Kingdom', 'payment_method' => 'card',
]);
check('Checkout rejects an unaccepted licence term', str_contains((string) $r['location'], '/marketplace/checkout'));

$r = request('POST', '/marketplace/checkout', [
    'name' => 'Test Buyer', 'email' => 'buyer@example.test', 'company' => 'Example Ltd',
    'country' => 'United Kingdom', 'payment_method' => 'card', 'terms' => '1',
]);
$placed = str_contains((string) $r['location'], '/marketplace/order/');
check('Order is placed', $placed, (string) $r['location']);

if ($placed) {
    $reference = substr((string) $r['location'], strrpos((string) $r['location'], '/') + 1);
    $order = $app->make('db')->first('SELECT * FROM orders WHERE reference = ?', [$reference]);
    check('Order recorded at the extended price',
        $order !== null && abs((float) $order['total'] - (float) $product['extended_price']) < 0.01,
        $order === null ? 'no order' : (string) $order['total']);
    $licenses = $app->make('db')->select('SELECT * FROM licenses WHERE order_id = ?', [(int) $order['id']]);
    check('Licence key issued for the order', count($licenses) === 1 && str_starts_with((string) $licenses[0]['license_key'], 'TBX-'));
    check('Extended licence carries five seats', (int) ($licenses[0]['seats'] ?? 0) === 5);

    $r = request('GET', '/marketplace/order/' . $reference);
    check('Order confirmation renders the licence key', $r['status'] === 200 && str_contains($r['body'], (string) $licenses[0]['license_key']));

    $r = request('GET', '/marketplace/cart');
    check('Cart is emptied after checkout', str_contains($r['body'], 'Your cart is empty'));
}

/* ---------------------------------------------------------------------------
 | Contact form
 * ------------------------------------------------------------------------ */

section('Contact form');

$before = (int) $app->make('db')->value('SELECT COUNT(*) FROM leads', [], 0);
$r = request('POST', '/contact', [
    'name' => 'Jamie Fields', 'email' => 'jamie@example.test', 'company' => 'Example Ltd',
    'topic' => 'new-project', 'budget' => '150k-500k', 'timeline' => 'This quarter',
    'message' => 'We need to replace our order management platform across four European markets.',
    'consent' => '1', 't' => time() - 30,
]);
$afterCount = (int) $app->make('db')->value('SELECT COUNT(*) FROM leads', [], 0);
check('Valid enquiry creates a lead', $afterCount === $before + 1 && str_contains((string) $r['location'], 'thank-you'));

$before = $afterCount;
$r = request('POST', '/contact', [
    'name' => 'Bot', 'email' => 'bot@example.test', 'topic' => 'new-project',
    'message' => 'Buy cheap things at this link, definitely not spam at all here.',
    'consent' => '1', 't' => time(), 'company_website' => 'http://spam.example',
]);
$afterCount = (int) $app->make('db')->value('SELECT COUNT(*) FROM leads', [], 0);
check('Honeypot submission is silently discarded', $afterCount === $before && str_contains((string) $r['location'], 'thank-you'));

$before = $afterCount;
request('POST', '/contact', ['name' => 'X', 'email' => 'not-an-email', 'topic' => 'new-project', 'message' => 'too short', 'consent' => '1', 't' => time() - 30]);
$afterCount = (int) $app->make('db')->value('SELECT COUNT(*) FROM leads', [], 0);
check('Invalid enquiry is rejected', $afterCount === $before);

section('CSRF protection');

$_POST = ['name' => 'No Token', 'email' => 'x@example.test', 'topic' => 'new-project',
          'message' => 'This request deliberately carries no CSRF token at all here.', 'consent' => '1'];
$_SERVER = ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/contact', 'SCRIPT_NAME' => '/index.php',
            'HTTP_HOST' => 'techbiss.test', 'HTTPS' => 'on', 'SERVER_PORT' => '443', 'REMOTE_ADDR' => '127.0.0.1'];
$_GET = [];
$response = boot()->handle(Request::capture());
check('A POST without a CSRF token is refused', $response->status() === 419, 'status ' . $response->status());


fwrite(STDOUT, "\n" . str_repeat('-', 64) . "\n");
fwrite(STDOUT, sprintf("%d passed, %d failed\n", $pass, $fail));
exit($fail === 0 ? 0 : 1);
