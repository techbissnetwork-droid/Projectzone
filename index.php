<?php
declare(strict_types=1);

/**
 * TECHBISS — public front controller.
 * Apache rewrites every non-file request here; see .htaccess.
 */

require __DIR__ . '/includes/bootstrap.php';

use Techbiss\Controllers\SiteController;
use Techbiss\Core\App;
use Techbiss\Core\Request;
use Techbiss\Core\Router;

App::sendSecurityHeaders();

$request = Request::capture(App::basePath());
App::setCurrentPath($request->path());

$site   = new SiteController();
$router = new Router();

// Maintenance mode — administrators keep working, visitors see the notice.
if (App::settings()->bool('maintenance_mode', false)
    && !\Techbiss\Core\Auth::check()
    && !in_array($request->path(), ['/robots.txt'], true)
) {
    $site->maintenance();
    exit;
}

// --- Home -------------------------------------------------------------
$router->get('/', [$site, 'home']);

// --- Services ---------------------------------------------------------
$router->get('/services', [$site, 'services']);
$router->get('/services/{slug}', [$site, 'serviceDetail']);

// --- Packages ---------------------------------------------------------
$router->get('/packages', [$site, 'packages']);
$router->get('/packages/{slug}', [$site, 'packageDetail']);
$router->any('/checkout/{slug}', [$site, 'checkout']);

// --- Portfolio --------------------------------------------------------
$router->get('/portfolio', [$site, 'portfolio']);
$router->get('/portfolio/{slug}', [$site, 'portfolioDetail']);

// --- Premade projects -------------------------------------------------
$router->get('/premade-projects', [$site, 'projects']);
$router->post('/premade-projects/{slug}/enquire', [$site, 'projectEnquiry']);
$router->get('/premade-projects/{slug}/apk', [$site, 'projectApk']);
$router->get('/premade-projects/{slug}', [$site, 'projectDetail']);

// --- Industries -------------------------------------------------------
$router->get('/industries', [$site, 'industries']);
$router->get('/industries/{slug}', [$site, 'industryDetail']);

// --- Editorial --------------------------------------------------------
$router->get('/how-it-works', [$site, 'howItWorks']);
$router->get('/testimonials', [$site, 'testimonials']);
$router->get('/faqs', [$site, 'faqs']);
$router->get('/blog', [$site, 'blog']);
$router->get('/blog/{slug}', [$site, 'blogDetail']);

// --- Conversion -------------------------------------------------------
$router->any('/contact', [$site, 'contact']);
$router->any('/quote', [$site, 'quote']);
$router->any('/start', [$site, 'journey']);
$router->get('/thank-you', [$site, 'thankYou']);

// --- Machine-readable -------------------------------------------------
$router->get('/sitemap.xml', [$site, 'sitemap']);
$router->get('/robots.txt', [$site, 'robots']);
$router->get('/site.webmanifest', [$site, 'manifest']);
$router->post('/api/newsletter', [$site, 'newsletter']);

// --- CMS pages, then 404 ---------------------------------------------
$router->fallback(static function (Request $request) use ($site): void {
    $path = trim($request->path(), '/');
    if ($path !== '' && preg_match('#^[a-z0-9-]+$#', $path)) {
        $site->page($request, ['slug' => $path]);
        return;
    }
    $site->notFound($request);
});

try {
    $router->dispatch($request);
} catch (Throwable $e) {
    if (App::debug()) {
        throw $e;
    }
    error_log('[techbiss] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    echo '<!doctype html><meta charset="utf-8"><title>Something went wrong</title>'
        . '<body style="font:16px/1.7 ui-sans-serif,system-ui;background:#06070c;color:#e9ecf6;padding:56px;max-width:44rem;margin:auto">'
        . '<h1 style="font-size:1.6rem">Something went wrong</h1>'
        . '<p style="color:#838da3">We hit an unexpected error. The team has been notified — please try again shortly.</p>'
        . '<p><a href="' . htmlspecialchars(url('/'), ENT_QUOTES) . '" style="color:#4f8cff">Return to the homepage</a></p></body>';
}
