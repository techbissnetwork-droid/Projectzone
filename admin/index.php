<?php
declare(strict_types=1);

/**
 * TECHBISS — admin front controller.
 * Every /admin/* request enters here. Authentication is enforced before any
 * route runs, and each controller action re-checks the specific permission.
 */

require dirname(__DIR__) . '/includes/bootstrap.php';

use Techbiss\Admin\Resources;
use Techbiss\Controllers\Admin\AuthController;
use Techbiss\Controllers\Admin\BlogController;
use Techbiss\Controllers\Admin\CustomerController;
use Techbiss\Controllers\Admin\DashboardController;
use Techbiss\Controllers\Admin\LeadController;
use Techbiss\Controllers\Admin\MediaController;
use Techbiss\Controllers\Admin\NavigationController;
use Techbiss\Controllers\Admin\PackageController;
use Techbiss\Controllers\Admin\PortfolioController;
use Techbiss\Controllers\Admin\ProjectController;
use Techbiss\Controllers\Admin\ProjectOrderController;
use Techbiss\Controllers\Admin\PurchaseController;
use Techbiss\Controllers\Admin\ResourceController;
use Techbiss\Controllers\Admin\SettingsController;
use Techbiss\Controllers\Admin\SystemController;
use Techbiss\Controllers\Admin\UserController;
use Techbiss\Core\App;
use Techbiss\Core\Auth;
use Techbiss\Core\Request;
use Techbiss\Core\Router;
use Techbiss\Core\Session;
use Techbiss\Core\View;

App::sendSecurityHeaders(true);

$request = Request::capture(App::basePath());
App::setCurrentPath($request->path());

$router = new Router();
$path   = $request->path();

// ---------------------------------------------------------------------
// Guest routes
// ---------------------------------------------------------------------
$auth = new AuthController();
$router->get('/admin/login', [$auth, 'showLogin']);
$router->post('/admin/login', [$auth, 'login']);
$router->post('/admin/logout', [$auth, 'logout']);

// ---------------------------------------------------------------------
// Everything else requires a signed-in administrator
// ---------------------------------------------------------------------
if (!in_array($path, ['/admin/login', '/admin/logout'], true)) {
    if (!Auth::check()) {
        if ($request->wantsJson()) {
            json_response(['ok' => false, 'message' => 'Your session has expired. Please sign in again.'], 401);
        }
        Session::set('intended_url', $path);
        flash('error', 'Please sign in to continue.');
        redirect('/admin/login');
    }

    $dashboard  = new DashboardController();
    $portfolio  = new PortfolioController();
    $projects   = new ProjectController();
    $prjOrders  = new ProjectOrderController();
    $packages   = new PackageController();
    $purchases  = new PurchaseController();
    $customers  = new CustomerController();
    $leads      = new LeadController();
    $media      = new MediaController();
    $navigation = new NavigationController();
    $settings   = new SettingsController();
    $users      = new UserController();
    $blog       = new BlogController();
    $system     = new SystemController();

    // --- Dashboard ----------------------------------------------------
    $router->get('/admin', [$dashboard, 'index']);
    $router->get('/admin/', [$dashboard, 'index']);

    // --- Portfolio ----------------------------------------------------
    $router->get('/admin/portfolio', [$portfolio, 'index']);
    $router->get('/admin/portfolio/create', [$portfolio, 'create']);
    $router->post('/admin/portfolio', [$portfolio, 'store']);
    $router->post('/admin/portfolio/reorder', [$portfolio, 'reorder']);
    $router->get('/admin/portfolio/{id:\d+}/edit', [$portfolio, 'edit']);
    $router->post('/admin/portfolio/{id:\d+}', [$portfolio, 'update']);
    $router->post('/admin/portfolio/{id:\d+}/delete', [$portfolio, 'destroy']);
    $router->post('/admin/portfolio/{id:\d+}/duplicate', [$portfolio, 'duplicate']);
    $router->post('/admin/portfolio/{id:\d+}/toggle', [$portfolio, 'toggle']);
    $router->post('/admin/portfolio/{id:\d+}/images/reorder', [$portfolio, 'reorderImages']);
    $router->post('/admin/portfolio/{id:\d+}/images/{imageId:\d+}/delete', [$portfolio, 'deleteImage']);

    // Premade projects
    $router->get('/admin/projects', [$projects, 'index']);
    $router->get('/admin/projects/create', [$projects, 'create']);
    $router->post('/admin/projects', [$projects, 'store']);
    $router->post('/admin/projects/reorder', [$projects, 'reorder']);
    $router->get('/admin/projects/{id:\d+}/edit', [$projects, 'edit']);
    $router->post('/admin/projects/{id:\d+}', [$projects, 'update']);
    $router->post('/admin/projects/{id:\d+}/delete', [$projects, 'destroy']);
    $router->post('/admin/projects/{id:\d+}/duplicate', [$projects, 'duplicate']);
    $router->post('/admin/projects/{id:\d+}/toggle', [$projects, 'toggle']);
    $router->post('/admin/projects/{id:\d+}/images/reorder', [$projects, 'reorderImages']);
    $router->post('/admin/projects/{id:\d+}/images/{imageId:\d+}/delete', [$projects, 'deleteImage']);

    // Premade project enquiries
    $router->get('/admin/project-orders', [$prjOrders, 'index']);
    $router->get('/admin/project-orders/export', [$prjOrders, 'export']);
    $router->get('/admin/project-orders/{id:\d+}', [$prjOrders, 'show']);
    $router->post('/admin/project-orders/{id:\d+}', [$prjOrders, 'update']);
    $router->post('/admin/project-orders/{id:\d+}/delete', [$prjOrders, 'destroy']);

    // --- Packages -----------------------------------------------------
    $router->get('/admin/packages', [$packages, 'index']);
    $router->get('/admin/packages/create', [$packages, 'create']);
    $router->post('/admin/packages', [$packages, 'store']);
    $router->post('/admin/packages/reorder', [$packages, 'reorder']);
    $router->get('/admin/packages/{id:\d+}/edit', [$packages, 'edit']);
    $router->post('/admin/packages/{id:\d+}', [$packages, 'update']);
    $router->post('/admin/packages/{id:\d+}/delete', [$packages, 'destroy']);
    $router->post('/admin/packages/{id:\d+}/toggle', [$packages, 'toggle']);

    // --- Purchases & customers ---------------------------------------
    $router->get('/admin/purchases', [$purchases, 'index']);
    $router->get('/admin/purchases/export', [$purchases, 'export']);
    $router->get('/admin/purchases/{id:\d+}', [$purchases, 'show']);
    $router->post('/admin/purchases/{id:\d+}', [$purchases, 'update']);
    $router->post('/admin/purchases/{id:\d+}/extend', [$purchases, 'extend']);
    $router->post('/admin/purchases/{id:\d+}/delete', [$purchases, 'destroy']);

    $router->get('/admin/customers', [$customers, 'index']);
    $router->get('/admin/customers/export', [$customers, 'export']);
    $router->get('/admin/customers/{id:\d+}', [$customers, 'show']);
    $router->post('/admin/customers/{id:\d+}', [$customers, 'update']);
    $router->post('/admin/customers/{id:\d+}/delete', [$customers, 'destroy']);

    // --- Leads --------------------------------------------------------
    $router->get('/admin/messages', [$leads, 'messages']);
    $router->get('/admin/messages/export', [$leads, 'exportMessages']);
    $router->get('/admin/messages/{id:\d+}', [$leads, 'showMessage']);
    $router->post('/admin/messages/{id:\d+}', [$leads, 'updateMessage']);
    $router->post('/admin/messages/{id:\d+}/delete', [$leads, 'destroyMessage']);

    $router->get('/admin/quotes', [$leads, 'quotes']);
    $router->get('/admin/quotes/export', [$leads, 'exportQuotes']);
    $router->get('/admin/quotes/{id:\d+}', [$leads, 'showQuote']);
    $router->post('/admin/quotes/{id:\d+}', [$leads, 'updateQuote']);
    $router->post('/admin/quotes/{id:\d+}/delete', [$leads, 'destroyQuote']);

    $router->get('/admin/subscribers', [$leads, 'subscribers']);
    $router->get('/admin/subscribers/export', [$leads, 'exportSubscribers']);
    $router->post('/admin/subscribers/{id:\d+}', [$leads, 'updateSubscriber']);
    $router->post('/admin/subscribers/{id:\d+}/delete', [$leads, 'destroySubscriber']);

    // --- Blog ---------------------------------------------------------
    $router->get('/admin/blog', [$blog, 'index']);
    $router->get('/admin/blog/create', [$blog, 'create']);
    $router->post('/admin/blog', [$blog, 'store']);
    $router->get('/admin/blog/{id:\d+}/edit', [$blog, 'edit']);
    $router->post('/admin/blog/{id:\d+}', [$blog, 'update']);
    $router->post('/admin/blog/{id:\d+}/delete', [$blog, 'destroy']);
    $router->post('/admin/blog/{id:\d+}/toggle', [$blog, 'toggle']);

    // --- Media --------------------------------------------------------
    $router->get('/admin/media', [$media, 'index']);
    $router->get('/admin/media/browse', [$media, 'browse']);
    $router->post('/admin/media/upload', [$media, 'upload']);
    $router->post('/admin/media/{id:\d+}', [$media, 'update']);
    $router->post('/admin/media/{id:\d+}/delete', [$media, 'destroy']);

    // --- Website ------------------------------------------------------
    $router->get('/admin/navigation', [$navigation, 'index']);
    $router->get('/admin/navigation/create', [$navigation, 'create']);
    $router->post('/admin/navigation', [$navigation, 'store']);
    $router->post('/admin/navigation/reorder', [$navigation, 'reorder']);
    $router->get('/admin/navigation/{id:\d+}/edit', [$navigation, 'edit']);
    $router->post('/admin/navigation/{id:\d+}', [$navigation, 'update']);
    $router->post('/admin/navigation/{id:\d+}/delete', [$navigation, 'destroy']);
    $router->post('/admin/navigation/{id:\d+}/toggle', [$navigation, 'toggle']);

    $router->get('/admin/homepage', [$settings, 'sections']);
    $router->post('/admin/homepage/reorder', [$settings, 'reorderSections']);
    $router->get('/admin/homepage/{id:\d+}/edit', [$settings, 'editSection']);
    $router->post('/admin/homepage/{id:\d+}', [$settings, 'updateSection']);
    $router->post('/admin/homepage/{id:\d+}/toggle', [$settings, 'toggleSection']);

    $router->get('/admin/settings', [$settings, 'index']);
    $router->post('/admin/settings', [$settings, 'update']);

    // --- Users & roles ------------------------------------------------
    $router->get('/admin/users', [$users, 'index']);
    $router->get('/admin/users/create', [$users, 'create']);
    $router->post('/admin/users', [$users, 'store']);
    $router->get('/admin/users/{id:\d+}/edit', [$users, 'edit']);
    $router->post('/admin/users/{id:\d+}', [$users, 'update']);
    $router->post('/admin/users/{id:\d+}/delete', [$users, 'destroy']);

    $router->get('/admin/profile', [$users, 'profile']);
    $router->post('/admin/profile', [$users, 'updateProfile']);

    $router->get('/admin/roles', [$users, 'roles']);
    $router->get('/admin/roles/create', [$users, 'createRole']);
    $router->post('/admin/roles', [$users, 'storeRole']);
    $router->get('/admin/roles/{id:\d+}/edit', [$users, 'editRole']);
    $router->post('/admin/roles/{id:\d+}', [$users, 'updateRole']);
    $router->post('/admin/roles/{id:\d+}/delete', [$users, 'destroyRole']);

    // --- System -------------------------------------------------------
    $router->get('/admin/logs', [$system, 'logs']);
    $router->get('/admin/system', [$system, 'tools']);
    $router->post('/admin/system/cache', [$system, 'clearCache']);
    $router->post('/admin/system/recheck', [$system, 'recheckSecurity']);
    $router->post('/admin/system/migrate', [$system, 'migrate']);
    $router->post('/admin/system/prune-logs', [$system, 'pruneLogs']);
    $router->post('/admin/system/backup', [$system, 'exportDatabase']);

    // --- Declarative resources ---------------------------------------
    foreach (array_keys(Resources::all()) as $key) {
        $make = static fn (): ResourceController => new ResourceController($key);
        $router->get("/admin/$key", static fn (Request $r) => $make()->index($r));
        $router->get("/admin/$key/create", static fn (Request $r) => $make()->create($r));
        $router->post("/admin/$key", static fn (Request $r) => $make()->store($r));
        $router->post("/admin/$key/reorder", static fn (Request $r) => $make()->reorder($r));
        $router->get("/admin/$key/{id:\\d+}/edit", static fn (Request $r, array $p) => $make()->edit($r, $p));
        $router->post("/admin/$key/{id:\\d+}", static fn (Request $r, array $p) => $make()->update($r, $p));
        $router->post("/admin/$key/{id:\\d+}/delete", static fn (Request $r, array $p) => $make()->destroy($r, $p));
        $router->post("/admin/$key/{id:\\d+}/toggle", static fn (Request $r, array $p) => $make()->toggle($r, $p));
    }
}

$router->fallback(static function (Request $request): void {
    http_response_code(404);
    if ($request->wantsJson()) {
        json_response(['ok' => false, 'message' => 'Not found.'], 404);
    }
    $view = new View(App::root() . '/admin/views', App::root() . '/admin/views/layout.php');
    $view->shareMany([
        'settings'    => App::settings(),
        'user'        => Auth::user(),
        'flash'       => App::flashMessages(),
        'currentPath' => App::currentPath(),
        'badges'      => ['messages' => 0, 'quotes' => 0, 'purchases' => 0],
    ]);
    $view->render('404', ['title' => 'Page not found']);
});

try {
    $router->dispatch($request);
} catch (Throwable $e) {
    if (App::debug()) {
        throw $e;
    }
    error_log('[techbiss-admin] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    echo '<!doctype html><meta charset="utf-8"><title>Admin error</title>'
        . '<body style="font:16px/1.7 ui-sans-serif,system-ui;background:#06070c;color:#e9ecf6;padding:56px;max-width:44rem;margin:auto">'
        . '<h1 style="font-size:1.5rem">Something went wrong</h1>'
        . '<p style="color:#838da3">The action could not be completed. Details have been written to the error log.</p>'
        . '<p><a href="' . htmlspecialchars(url('/admin'), ENT_QUOTES) . '" style="color:#4f8cff">Back to the dashboard</a></p></body>';
}
