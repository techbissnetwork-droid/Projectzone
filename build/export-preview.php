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
    ['/', 'Home', 'Marketing'],
    ['/services', 'Services', 'Marketing'],
    ['/solutions', 'Solutions', 'Marketing'],
    ['/solutions/financial-services', 'Financial Services', 'Marketing'],
    ['/work', 'Work', 'Marketing'],
    ['/work/northwind-settlement-platform', 'Case study', 'Marketing'],
    ['/process', 'Process', 'Marketing'],
    ['/about', 'About', 'Marketing'],
    ['/pricing', 'Pricing', 'Marketing'],
    ['/resources', 'Resources', 'Marketing'],
    ['/resources/core-web-vitals-budget-ci', 'Article', 'Marketing'],
    ['/contact', 'Contact', 'Marketing'],
    ['/marketplace', 'Marketplace', 'Marketplace'],
    ['/marketplace/atlas-corporate-platform', 'Product detail', 'Marketplace'],
    ['/marketplace/installer', 'Advanced Installer', 'Marketplace'],
    ['/marketplace/licensing', 'Licensing', 'Marketplace'],
    ['/marketplace/cart', 'Cart', 'Marketplace'],
    ['/admin/login', 'Admin Login', 'Portals'],
    ['/staff/login', 'Staff Login', 'Portals'],
    ['/client/login', 'Client Login', 'Portals'],
    ['/legal/privacy', 'Privacy', 'Marketing'],
];

// Extra pages are appended only when their route exists, so this script keeps
// working while the platform is still being built out.
$optional = [
    ['/install/step/requirements', 'Installer — Requirements', 'Installer'],
    ['/install/step/environment', 'Installer — Environment', 'Installer'],
    ['/install/step/database', 'Installer — Database', 'Installer'],
    ['/install/step/detection', 'Installer — Existing site', 'Installer'],
    ['/install/step/migration', 'Installer — Migration', 'Installer'],
    ['/install/step/configuration', 'Installer — Configuration', 'Installer'],
    ['/install/step/deploy', 'Installer — Deploy', 'Installer'],
    ['/admin', 'Admin Dashboard', 'Portals'],
    ['/admin/products', 'Admin — Products', 'Portals'],
    ['/admin/orders', 'Admin — Orders', 'Portals'],
    ['/admin/leads', 'Admin — Leads', 'Portals'],
    ['/admin/deployments', 'Admin — Deployments', 'Portals'],
    ['/staff', 'Staff Workspace', 'Portals'],
    ['/staff/pipeline', 'Staff — Pipeline', 'Portals'],
    ['/staff/tickets', 'Staff — Tickets', 'Portals'],
    ['/client', 'Client Dashboard', 'Portals'],
    ['/client/licenses', 'Client — Licences', 'Portals'],
    ['/client/deployments', 'Client — Deployments', 'Portals'],
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
    $html = preg_replace('#<a class="skip-link"[^>]*>.*?</a>#s', '', $html) ?? $html;

    // href="https://preview.techbiss.local/services" -> href="#/services"
    $html = str_replace('href="' . ORIGIN . '/"', 'href="#/"', $html);
    $html = str_replace('href="' . ORIGIN . '/', 'href="#/', $html);
    $html = str_replace('action="' . ORIGIN . '/', 'data-preview-action="#/', $html);
    $html = str_replace(ORIGIN . '/', '#/', $html);
    return $html;
}

$sections = [];
$groups = [];
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
foreach ($all as [$path, $label, $group]) {
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
        'group' => $group,
        'standalone' => $standalone,
        'html' => $content,
    ];
    $groups[$group][] = ['path' => $path, 'label' => $label];
    fwrite(STDOUT, sprintf("  ✓ %-46s %6.1f KB\n", $path, strlen($content) / 1024));
}

$criticalCss = (string) file_get_contents($root . '/public/assets/css/critical.css');
$mainCss = (string) file_get_contents($root . '/public/assets/css/main.css');
$appJs = (string) file_get_contents($root . '/public/assets/js/app.js');

$navHtml = '';
foreach ($groups as $group => $items) {
    $navHtml .= '<div class="pv-group"><span class="pv-group__label">' . htmlspecialchars($group, ENT_QUOTES) . '</span>';
    foreach ($items as $item) {
        $navHtml .= sprintf(
            '<button type="button" class="pv-tab" data-goto="%s">%s</button>',
            htmlspecialchars($item['path'], ENT_QUOTES),
            htmlspecialchars($item['label'], ENT_QUOTES)
        );
    }
    $navHtml .= '</div>';
}

$sectionsHtml = '';
foreach ($sections as $section) {
    $sectionsHtml .= sprintf(
        '<div class="pv-page%s" data-path="%s" hidden>%s</div>',
        $section['standalone'] ? ' pv-page--standalone' : '',
        htmlspecialchars($section['path'], ENT_QUOTES),
        $section['html']
    );
}

$output = $argv[1] ?? ($root . '/build/techbiss-preview.html');

$document = <<<HTML
<title>TECHBISS Platform</title>
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<style>{$criticalCss}</style>
<style>{$mainCss}</style>
<style>
/* Preview chrome — the switcher that stands in for real server routing. */
.pv-bar{position:sticky;top:0;z-index:200;display:flex;align-items:center;gap:.75rem;
  padding:.6rem clamp(.9rem,3vw,1.5rem);background:var(--bg-elev);border-bottom:1px solid var(--line);
  overflow-x:auto;scrollbar-width:none}
.pv-bar::-webkit-scrollbar{display:none}
.pv-brand{display:flex;align-items:center;gap:.5rem;font-size:var(--t--2);font-weight:700;
  letter-spacing:.1em;text-transform:uppercase;color:var(--ink-3);white-space:nowrap;flex:none}
.pv-brand b{width:8px;height:8px;border-radius:50%;background:var(--accent-grad)}
.pv-group{display:flex;align-items:center;gap:.25rem;flex:none;padding-left:.75rem;
  border-left:1px solid var(--line)}
.pv-group__label{font-size:.625rem;letter-spacing:.14em;text-transform:uppercase;color:var(--ink-3);
  margin-right:.25rem;white-space:nowrap}
.pv-tab{flex:none;padding:.35rem .7rem;border-radius:var(--r-full);border:1px solid transparent;
  font-size:var(--t--2);font-weight:540;color:var(--ink-2);white-space:nowrap;
  transition:background var(--d-1),color var(--d-1),border-color var(--d-1)}
.pv-tab[aria-current="true"]{background:var(--accent-soft);border-color:var(--accent-line);color:var(--ink)}
@media (hover:hover){.pv-tab:hover{background:var(--surface-2);color:var(--ink)}}
.pv-page[hidden]{display:none!important}
.pv-shell[hidden]{display:none!important}
.pv-note{position:fixed;left:50%;bottom:1rem;transform:translateX(-50%) translateY(200%);
  z-index:210;padding:.6rem 1rem;border-radius:var(--r-full);background:var(--surface-3);
  border:1px solid var(--line-2);font-size:var(--t--2);color:var(--ink-2);
  transition:transform var(--d-3) var(--ease-expo);pointer-events:none;white-space:nowrap;max-width:90vw}
.pv-note.is-on{transform:translateX(-50%)}
</style>

<nav class="pv-bar" aria-label="Preview pages">
  <span class="pv-brand"><b></b>TECHBISS preview</span>
  {$navHtml}
</nav>

<div class="pv-shell" data-shell>
  {$header}
</div>
<main id="main">{$sectionsHtml}</main>
<div class="pv-shell" data-shell>
  {$footer}
  {$drawer}
</div>
<div class="pv-note" data-note></div>

<script>{$appJs}</script>
<script>
/* Hash router standing in for the platform's server-side routing. */
(function(){
  var pages = Array.prototype.slice.call(document.querySelectorAll('.pv-page'));
  var tabs = Array.prototype.slice.call(document.querySelectorAll('.pv-tab'));
  var shells = Array.prototype.slice.call(document.querySelectorAll('[data-shell]'));
  var note = document.querySelector('[data-note]');
  var noteTimer = null;

  function known(path){ return pages.some(function(p){ return p.dataset.path === path; }); }

  function toast(message){
    if (!note) return;
    note.textContent = message;
    note.classList.add('is-on');
    clearTimeout(noteTimer);
    noteTimer = setTimeout(function(){ note.classList.remove('is-on'); }, 2600);
  }

  function show(path, push){
    if (!known(path)) {
      toast('“' + path + '” is a live route on the real platform — not in this static preview.');
      return;
    }
    var standalone = false;
    pages.forEach(function(page){
      var active = page.dataset.path === path;
      page.hidden = !active;
      if (active) standalone = page.classList.contains('pv-page--standalone');
    });
    shells.forEach(function(shell){ shell.hidden = standalone; });
    tabs.forEach(function(tab){ tab.setAttribute('aria-current', tab.dataset.goto === path ? 'true' : 'false'); });
    if (push !== false && location.hash !== '#' + path) history.replaceState({}, '', '#' + path);
    window.scrollTo(0, 0);
  }

  tabs.forEach(function(tab){
    tab.addEventListener('click', function(){ show(tab.dataset.goto); });
  });

  document.addEventListener('click', function(e){
    var link = e.target.closest('a[href^="#/"]');
    if (!link) return;
    e.preventDefault();
    show(link.getAttribute('href').slice(1));
    var drawer = document.querySelector('[data-drawer]');
    if (drawer) drawer.classList.remove('is-open');
    document.body.style.overflow = '';
  });

  document.addEventListener('submit', function(e){
    e.preventDefault();
    toast('Forms post to the server on the real platform. This preview is static.');
  });

  window.addEventListener('hashchange', function(){
    show(location.hash.slice(1) || '/', false);
  });

  show(location.hash.slice(1) || '/', false);
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
