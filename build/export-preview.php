<?php
declare(strict_types=1);

/**
 * Renders the live platform to a single self-contained HTML file.
 *
 * Used to publish a shareable design preview: every stylesheet, script and
 * image is inlined, internal links become hash routes, and the shared header,
 * drawer and footer are emitted once rather than per page.
 *
 * Usage: php build/export-preview.php [output.html]
 */

$root = dirname(__DIR__);
require $root . '/app/Support/autoload.php';

use App\Core\Application;
use App\Core\Request;

const ORIGIN = 'https://preview.techbiss.local';

$pages = [
    ['/', 'Home'],
    ['/services', 'Services'],
    ['/solutions', 'Solutions'],
    ['/work', 'Work'],
    ['/process', 'Process'],
    ['/about', 'About'],
    ['/pricing', 'Pricing'],
    ['/resources', 'Resources'],
    ['/contact', 'Contact'],
    ['/contact/thank-you', 'Thank you'],
    ['/marketplace', 'Marketplace'],
    ['/marketplace/installer', 'Advanced Installer'],
    ['/marketplace/licensing', 'Licensing'],
    ['/marketplace/cart', 'Cart'],
    ['/admin/login', 'Admin sign in'],
    ['/staff/login', 'Staff sign in'],
    ['/client/login', 'Client sign in'],
    ['/legal/privacy', 'Privacy'],
    ['/legal/terms', 'Terms'],
    ['/legal/security', 'Security'],
    ['/legal/accessibility', 'Accessibility'],
];



// Extra pages are appended only when their route exists, so this script keeps
// working while the platform is still being built out.
$optional = [
    ['/install/step/requirements', 'Installer — Requirements'],
    ['/install/step/environment', 'Installer — Environment'],
    ['/install/step/database', 'Installer — Database'],
    ['/install/step/detection', 'Installer — Existing site'],
    ['/install/step/migration', 'Installer — Migration'],
    ['/install/step/configuration', 'Installer — Configuration'],
    ['/install/step/deploy', 'Installer — Deploy'],
    ['/admin', 'Admin Dashboard'],
    ['/admin/products', 'Admin — Products'],
    ['/admin/orders', 'Admin — Orders'],
    ['/admin/leads', 'Admin — Leads'],
    ['/admin/deployments', 'Admin — Deployments'],
    ['/staff', 'Staff Workspace'],
    ['/staff/pipeline', 'Staff — Pipeline'],
    ['/staff/tickets', 'Staff — Tickets'],
    ['/client', 'Client Dashboard'],
    ['/client/licenses', 'Client — Licences'],
    ['/client/deployments', 'Client — Deployments'],
];

$app = new Application($root);
require $root . '/app/routes.php';

/**
 * Sign in so the authenticated portals render with real data. The export runs
 * locally against the development installation; nothing leaves this machine.
 */
function signInAs(Application $app, string $portal, string $email, string $password): bool
{
    $_SERVER = [
        'REQUEST_METHOD' => 'POST', 'REQUEST_URI' => "/{$portal}/login",
        'SCRIPT_NAME' => '/index.php', 'HTTP_HOST' => 'preview.techbiss.local',
        'SERVER_PORT' => '443', 'HTTPS' => 'on', 'REMOTE_ADDR' => '127.0.0.1',
    ];
    $_GET = [];
    $_POST = ['email' => $email, 'password' => $password, '_token' => $app->make('csrf')->token()];
    $response = $app->handle(Request::capture());

    foreach ($response->headers() as [$name, $value]) {
        if (strtolower($name) === 'location' && !str_contains((string) $value, '/login')) {
            return true;
        }
    }
    return false;
}

function signOutOf(Application $app, string $portal): void
{
    $_SERVER = [
        'REQUEST_METHOD' => 'POST', 'REQUEST_URI' => "/{$portal}/logout",
        'SCRIPT_NAME' => '/index.php', 'HTTP_HOST' => 'preview.techbiss.local',
        'SERVER_PORT' => '443', 'HTTPS' => 'on', 'REMOTE_ADDR' => '127.0.0.1',
    ];
    $_GET = [];
    $_POST = ['_token' => $app->make('csrf')->token()];
    $app->handle(Request::capture());
}

/**
 * Every detail page the site links to. Capturing all of them means a visitor
 * can click anywhere in the interface without reaching a route that was never
 * exported, which is what makes explanatory preview messaging unnecessary.
 *
 * @return list<array{0:string,1:string}>
 */
function detailPages(Application $app): array
{
    $detail = [];
    foreach ($app->make('db')->select("SELECT slug FROM products WHERE status = 'published'") as $row) {
        $detail[] = ['/marketplace/' . $row['slug'], 'Product'];
        $detail[] = ['/marketplace/preview/' . $row['slug'], 'Preview'];
    }
    foreach ($app->make('db')->select('SELECT slug FROM case_studies') as $row) {
        $detail[] = ['/work/' . $row['slug'], 'Case study'];
    }
    foreach ($app->make('db')->select('SELECT slug FROM resources') as $row) {
        $detail[] = ['/resources/' . $row['slug'], 'Article'];
    }
    foreach ($app->config()->get('solutions', []) as $solution) {
        $detail[] = ['/solutions/' . $solution['slug'], 'Solution'];
    }
    foreach (array_keys(App\Models\Product::CATEGORIES) as $category) {
        $detail[] = ['/marketplace?category=' . $category, 'Category'];
    }
    return $detail;
}

/** Render one path through the real request pipeline. */
function render(Application $app, string $path, ?int &$status = null): string
{
    $_SERVER = [
        'REQUEST_METHOD' => 'GET',
        'REQUEST_URI' => $path,
        'SCRIPT_NAME' => '/index.php',
        'HTTP_HOST' => 'preview.techbiss.local',
        'SERVER_PORT' => '443',
        'HTTPS' => 'on',
        'REMOTE_ADDR' => '127.0.0.1',
        'HTTP_USER_AGENT' => 'techbiss-export/1.0',
    ];
    $_GET = [];
    $_POST = [];
    if ($query = parse_url($path, PHP_URL_QUERY)) {
        parse_str($query, $_GET);
    }

    $response = $app->handle(Request::capture());
    $status = $response->status();
    return $response->content();
}

function between(string $html, string $open, string $close): string
{
    $start = strpos($html, $open);
    if ($start === false) {
        return '';
    }
    $end = strpos($html, $close, $start);
    if ($end === false) {
        return '';
    }
    return substr($html, $start, $end - $start + strlen($close));
}

/** Inner HTML of <main id="main"> … </main>. */
function mainContent(string $html): string
{
    $block = between($html, '<main id="main">', '</main>');
    return $block === '' ? '' : substr($block, strlen('<main id="main">'), -strlen('</main>'));
}

/** Whole <body> contents, for pages that use a standalone layout. */
function bodyContent(string $html): string
{
    $start = strpos($html, '<body');
    if ($start === false) {
        return $html;
    }
    $start = strpos($html, '>', $start);
    $end = strrpos($html, '</body>');
    if ($start === false || $end === false) {
        return $html;
    }
    return substr($html, $start + 1, $end - $start - 1);
}

/** Turn absolute platform URLs into hash routes, and strip the script tag. */
function rewrite(string $html): string
{
    $html = preg_replace('#<script[^>]*src="[^"]*app\.js[^"]*"[^>]*></script>#', '', $html) ?? $html;
    $html = preg_replace('#<link[^>]+rel="preload"[^>]*>#', '', $html) ?? $html;
    $html = preg_replace('#<a class="skip-link"[^>]*>.*?</a>#s', '', $html) ?? $html;

    // href="https://preview.techbiss.local/services" -> href="#/services"
    $html = str_replace('href="' . ORIGIN . '/"', 'href="#/"', $html);
    $html = str_replace('href="' . ORIGIN . '/', 'href="#/', $html);
    $html = str_replace('action="' . ORIGIN . '/', 'data-preview-action="#/', $html);
    $html = str_replace(ORIGIN . '/', '#/', $html);
    return $html;
}

$pages = array_merge($pages, detailPages($app));

$sections = [];
$home = render($app, '/');
$header = rewrite(between($home, '<header class="header"', '</header>'));
$footer = rewrite(between($home, '<footer class="footer">', '</footer>'));
$drawer = rewrite(between($home, '<div class="drawer" id="site-drawer"', '</div>' . "\n"));

// The drawer's closing tag is ambiguous by string search, so take it from the
// dedicated partial render instead.
$drawer = '';
$drawerStart = strpos($home, '<div class="drawer" id="site-drawer"');
if ($drawerStart !== false) {
    $depth = 0;
    $length = strlen($home);
    for ($i = $drawerStart; $i < $length; $i++) {
        if (substr($home, $i, 4) === '<div') {
            $depth++;
        } elseif (substr($home, $i, 6) === '</div>') {
            $depth--;
            if ($depth === 0) {
                $drawer = rewrite(substr($home, $drawerStart, $i + 6 - $drawerStart));
                break;
            }
        }
    }
}

$portalCredentials = [
    'admin' => ['admin@techbiss.com', getenv('TECHBISS_ADMIN_PASSWORD') ?: 'TechbissDemo!2026'],
    'staff' => ['engineer@techbiss.com', 'StaffDemo!2026'],
    'client' => ['client@northwind.example', 'ClientDemo!2026'],
];

$all = $pages;
foreach ($optional as $candidate) {
    $path = $candidate[0];

    // Sign into the portal a page belongs to before probing it.
    foreach ($portalCredentials as $portal => [$email, $password]) {
        if (str_starts_with($path, '/' . $portal)) {
            signInAs($app, $portal, $email, $password);
            break;
        }
    }

    $status = null;
    render($app, $path, $status);
    if ($status === 200) {
        $all[] = $candidate;
    }
}

// Probing left a portal session open; the capture loop below assumes it starts
// signed out so the login pages render as a visitor sees them.
foreach (array_keys($portalCredentials) as $portal) {
    signOutOf($app, $portal);
}

/** Which portal must be signed in for this path, if any. */
function portalFor(string $path, array $credentials): ?string
{
    if (str_contains($path, '/login') || str_contains($path, '/forgot-password')) {
        return null;
    }
    foreach (array_keys($credentials) as $portal) {
        if (str_starts_with($path, '/' . $portal)) {
            return $portal;
        }
    }
    return null;
}

$activePortal = null;
foreach ($all as [$path, $label]) {
    // Reconcile the session with what this page needs: a login page must be
    // captured signed out, a dashboard signed into its own portal.
    $needed = portalFor($path, $portalCredentials);
    if ($needed !== $activePortal) {
        if ($activePortal !== null) {
            signOutOf($app, $activePortal);
            $activePortal = null;
        }
        if ($needed !== null) {
            [$email, $password] = $portalCredentials[$needed];
            if (signInAs($app, $needed, $email, $password)) {
                $activePortal = $needed;
            }
        }
    }

    $status = null;
    $html = render($app, $path, $status);
    if ($status !== 200) {
        fwrite(STDERR, "skip {$path} (status {$status})\n");
        continue;
    }

    $standalone = !str_contains($html, '<main id="main">')
        || str_contains($html, 'class="auth ')
        || str_contains($html, 'class="install"')
        || str_contains($html, 'class="shell"');

    $content = $standalone ? bodyContent($html) : mainContent($html);
    $content = rewrite($content);

    // Strip the deferred script tag that bodyContent may have carried through.
    $content = preg_replace('#<script[^>]*></script>#', '', $content) ?? $content;

    $sections[] = [
        'path' => $path,
        'label' => $label,
        'standalone' => $standalone,
        'html' => $content,
    ];
    fwrite(STDOUT, sprintf("  ✓ %-46s %6.1f KB\n", $path, strlen($content) / 1024));
}

$criticalCss = (string) file_get_contents($root . '/public/assets/css/critical.css');
$mainCss = (string) file_get_contents($root . '/public/assets/css/main.css');
$motionCss = (string) file_get_contents($root . '/public/assets/css/motion.css');
$appJs = (string) file_get_contents($root . '/public/assets/js/app.js');

/**
 * Inline the self-hosted faces as data URIs.
 *
 * The export is a single file served from an origin that has no /assets path,
 * so a relative font reference would silently fall back to a system stack and
 * lose the platform's typographic identity.
 */
foreach (['sora-var-latin', 'manrope-var-latin'] as $face) {
    $file = $root . '/public/assets/fonts/' . $face . '.woff2';
    if (!is_file($file)) {
        continue;
    }
    $dataUri = 'data:font/woff2;base64,' . base64_encode((string) file_get_contents($file));
    $criticalCss = str_replace('/assets/fonts/' . $face . '.woff2', $dataUri, $criticalCss);
}

$sectionsHtml = '';
foreach ($sections as $section) {
    $sectionsHtml .= sprintf(
        '<div class="tb-page" data-path="%s" data-standalone="%s" hidden>%s</div>',
        htmlspecialchars($section['path'], ENT_QUOTES),
        $section['standalone'] ? '1' : '0',
        $section['html']
    );
}

$output = $argv[1] ?? ($root . '/build/techbiss-preview.html');

$document = <<<HTML
<title>TECHBISS Platform</title>
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<style>{$criticalCss}</style>
<style>{$mainCss}</style>
<style>{$motionCss}</style>
<style>.tb-page[hidden]{display:none!important}.tb-shell[hidden]{display:none!important}</style>

<div class="scroll-rail" data-scroll-rail aria-hidden="true"></div>
<div class="grain" aria-hidden="true"></div>

<div class="tb-shell" data-shell>
  {$header}
</div>
<main id="main">{$sectionsHtml}</main>
<div class="tb-shell" data-shell>
  {$footer}
  {$drawer}
</div>

<script>{$appJs}</script>
<script>
/* Resolves the site's own links against the exported pages. Anchors and query
   strings fall back to their base path, and an unmatched path walks up its
   segments, so every link in the interface lands somewhere real. */
(function(){
  var pages = Array.prototype.slice.call(document.querySelectorAll('.tb-page'));
  var shells = Array.prototype.slice.call(document.querySelectorAll('[data-shell]'));
  var known = {};
  pages.forEach(function(page){ known[page.dataset.path] = page; });

  function resolve(path){
    if (known[path]) return path;
    var bare = path.split('#')[0].split('?')[0];
    if (known[bare]) return bare;
    var parts = bare.replace(/\/+$/, '').split('/');
    while (parts.length > 1) {
      parts.pop();
      var candidate = parts.join('/') || '/';
      if (known[candidate]) return candidate;
    }
    return '/';
  }

  function show(path, anchor){
    var target = resolve(path);
    var standalone = false;
    pages.forEach(function(page){
      var active = page.dataset.path === target;
      page.hidden = !active;
      if (active) standalone = page.dataset.standalone === '1';
    });
    shells.forEach(function(shell){ shell.hidden = standalone; });
    if (location.hash !== '#' + path) history.replaceState({}, '', '#' + path);

    if (anchor) {
      var el = document.getElementById(anchor);
      if (el) { el.scrollIntoView({ block: 'start' }); return; }
    }
    window.scrollTo(0, 0);
  }

  document.addEventListener('click', function(e){
    var link = e.target.closest('a[href^="#/"]');
    if (!link) return;
    e.preventDefault();
    var href = link.getAttribute('href').slice(1);
    show(href, href.split('#')[1] || null);
    var drawer = document.querySelector('[data-drawer]');
    if (drawer) drawer.classList.remove('is-open');
    document.body.style.overflow = '';
  });

  // Same-page anchors inside an exported section.
  document.addEventListener('click', function(e){
    var link = e.target.closest('a[href^="#"]:not([href^="#/"])');
    if (!link) return;
    var id = link.getAttribute('href').slice(1);
    var el = id && document.getElementById(id);
    if (el) { e.preventDefault(); el.scrollIntoView({ block: 'start' }); }
  });

  // Forms need a server; keep them inert rather than navigating away.
  document.addEventListener('submit', function(e){ e.preventDefault(); });

  window.addEventListener('hashchange', function(){
    var h = location.hash.slice(1) || '/';
    show(h, h.split('#')[1] || null);
  });

  var initial = location.hash.slice(1) || '/';
  show(initial, initial.split('#')[1] || null);
})();
</script>
HTML;

file_put_contents($output, $document);
fwrite(STDOUT, sprintf(
    "\nWrote %s — %d pages, %.1f KB\n",
    $output,
    count($sections),
    strlen($document) / 1024
));
