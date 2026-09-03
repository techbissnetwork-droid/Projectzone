<?php
declare(strict_types=1);

use App\Controllers\Admin\AdminController;
use App\Controllers\Auth\LoginController;
use App\Controllers\Client\ClientController;
use App\Controllers\ContactController;
use App\Controllers\InstallerController;
use App\Controllers\MarketplaceController;
use App\Controllers\PageController;
use App\Controllers\ResourceController;
use App\Controllers\SeoController;
use App\Controllers\Staff\StaffController;
use App\Core\Application;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;

/** @var Application $app */
$router = $app->router();

/* ---------------------------------------------------------------------------
 | Middleware
 * ------------------------------------------------------------------------ */

$app->middleware('csrf', static function (Request $request) use ($app): ?Response {
    $app->make('session')->start();
    if (!$app->make('csrf')->verify($request)) {
        throw new HttpException(419, 'Your session expired. Please reload the page and try again.');
    }
    return null;
});

$app->middleware('installed', static function () use ($app): ?Response {
    if (!$app->isInstalled()) {
        return Response::redirect(url('/install'));
    }
    return null;
});

// Blocks the installer once the platform is live, unless an unlock file is
// present — the documented way to re-run migration on an existing install.
// The completion page stays reachable for the session that just installed,
// since locking is the last thing a successful install does.
$app->middleware('installer.open', static function (Request $request) use ($app): ?Response {
    if (!$app->isInstalled() || is_file($app->path('storage/install.unlock'))) {
        return null;
    }

    $session = $app->make('session');
    $session->start();
    $state = $session->get('installer', []);
    if ($request->path === '/install/complete' && is_array($state) && ($state['installed'] ?? false)) {
        return null;
    }

    return Response::redirect(url('/marketplace/installer'));
});

foreach (['admin', 'staff', 'client'] as $portal) {
    $app->middleware('portal.' . $portal, static function () use ($app, $portal): ?Response {
        $app->make('session')->start();
        $auth = $app->make('auth');
        if (!$auth->check()) {
            $app->make('session')->flash('intended', $portal);
            return Response::redirect(url('/' . $portal . '/login'));
        }
        if (!$auth->canAccessPortal($portal)) {
            $home = $auth->portal();
            throw HttpException::forbidden(
                $home
                    ? 'Your account belongs to the ' . \App\Core\Auth::PORTALS[$home]['label'] . '.'
                    : 'Your account cannot access this area.'
            );
        }
        return null;
    });
}

/* ---------------------------------------------------------------------------
 | Public marketing site
 * ------------------------------------------------------------------------ */

$router->get('/', [PageController::class, 'home'], ['name' => 'home']);
$router->get('/services', [PageController::class, 'services'], ['name' => 'services']);
$router->get('/solutions', [PageController::class, 'solutions'], ['name' => 'solutions']);
$router->get('/solutions/{slug:[a-z0-9-]+}', [PageController::class, 'solution'], ['name' => 'solution']);
$router->get('/work', [PageController::class, 'work'], ['name' => 'work']);
$router->get('/work/{slug:[a-z0-9-]+}', [PageController::class, 'caseStudy'], ['name' => 'case-study']);
$router->get('/process', [PageController::class, 'process'], ['name' => 'process']);
$router->get('/about', [PageController::class, 'about'], ['name' => 'about']);
$router->get('/pricing', [PageController::class, 'pricing'], ['name' => 'pricing']);

$router->get('/resources', [ResourceController::class, 'index'], ['name' => 'resources']);
$router->get('/resources/{slug:[a-z0-9-]+}', [ResourceController::class, 'show'], ['name' => 'resource']);

$router->get('/contact', [ContactController::class, 'show'], ['name' => 'contact']);
$router->post('/contact', [ContactController::class, 'submit'], ['middleware' => ['csrf']]);
$router->get('/contact/thank-you', [ContactController::class, 'thanks'], ['name' => 'contact.thanks']);
$router->post('/newsletter', [ContactController::class, 'newsletter'], ['middleware' => ['csrf']]);

$router->get('/legal/{slug:privacy|terms|security|accessibility}', [PageController::class, 'legal'], ['name' => 'legal']);

/* ---------------------------------------------------------------------------
 | Marketplace
 * ------------------------------------------------------------------------ */

$router->group(['prefix' => '/marketplace'], static function (Router $r): void {
    $r->get('/', [MarketplaceController::class, 'index'], ['name' => 'marketplace']);
    $r->get('/installer', [MarketplaceController::class, 'installerOverview'], ['name' => 'marketplace.installer']);
    $r->get('/licensing', [MarketplaceController::class, 'licensing'], ['name' => 'marketplace.licensing']);
    $r->get('/cart', [MarketplaceController::class, 'cart'], ['name' => 'marketplace.cart']);
    $r->post('/cart/add', [MarketplaceController::class, 'addToCart'], ['middleware' => ['csrf']]);
    $r->post('/cart/remove', [MarketplaceController::class, 'removeFromCart'], ['middleware' => ['csrf']]);
    $r->get('/checkout', [MarketplaceController::class, 'checkout'], ['name' => 'marketplace.checkout']);
    $r->post('/checkout', [MarketplaceController::class, 'placeOrder'], ['middleware' => ['csrf']]);
    $r->get('/order/{reference:[A-Z0-9\-]+}', [MarketplaceController::class, 'orderConfirmation'], ['name' => 'marketplace.order']);
    $r->get('/preview/{slug:[a-z0-9-]+}', [MarketplaceController::class, 'preview'], ['name' => 'marketplace.preview']);
    $r->get('/{slug:[a-z0-9-]+}', [MarketplaceController::class, 'show'], ['name' => 'product']);
});

/* ---------------------------------------------------------------------------
 | Advanced Installer
 * ------------------------------------------------------------------------ */

$router->group(['prefix' => '/install', 'middleware' => ['installer.open']], static function (Router $r): void {
    $r->get('/', [InstallerController::class, 'index'], ['name' => 'install']);
    $r->get('/step/{step:[a-z-]+}', [InstallerController::class, 'step'], ['name' => 'install.step']);
    $r->post('/step/{step:[a-z-]+}', [InstallerController::class, 'save'], ['middleware' => ['csrf']]);
    $r->post('/test-database', [InstallerController::class, 'testDatabase'], ['middleware' => ['csrf']]);
    $r->post('/scan', [InstallerController::class, 'scan'], ['middleware' => ['csrf']]);
    $r->post('/run', [InstallerController::class, 'run'], ['middleware' => ['csrf']]);
    $r->get('/complete', [InstallerController::class, 'complete'], ['name' => 'install.complete']);
});

/* ---------------------------------------------------------------------------
 | Portals — Admin, Staff, Client
 * ------------------------------------------------------------------------ */

foreach (['admin', 'staff', 'client'] as $portal) {
    $router->group(['prefix' => '/' . $portal], static function (Router $r) use ($portal): void {
        $r->get('/login', [LoginController::class, 'show' . ucfirst($portal)], ['name' => $portal . '.login']);
        $r->post('/login', [LoginController::class, 'authenticate' . ucfirst($portal)], ['middleware' => ['csrf']]);
        $r->post('/logout', [LoginController::class, 'logout'], ['middleware' => ['csrf']]);
        $r->get('/forgot-password', [LoginController::class, 'forgot'], ['name' => $portal . '.forgot']);
        $r->post('/forgot-password', [LoginController::class, 'sendReset'], ['middleware' => ['csrf']]);
    });
}

$router->group(['prefix' => '/admin', 'middleware' => ['portal.admin']], static function (Router $r): void {
    $r->get('/', [AdminController::class, 'dashboard'], ['name' => 'admin.dashboard']);
    $r->get('/users', [AdminController::class, 'users'], ['name' => 'admin.users']);
    $r->post('/users/status', [AdminController::class, 'updateUserStatus'], ['middleware' => ['csrf']]);
    $r->get('/products', [AdminController::class, 'products'], ['name' => 'admin.products']);
    $r->post('/products/status', [AdminController::class, 'updateProductStatus'], ['middleware' => ['csrf']]);
    $r->get('/orders', [AdminController::class, 'orders'], ['name' => 'admin.orders']);
    $r->get('/leads', [AdminController::class, 'leads'], ['name' => 'admin.leads']);
    $r->post('/leads/status', [AdminController::class, 'updateLeadStatus'], ['middleware' => ['csrf']]);
    $r->get('/deployments', [AdminController::class, 'deployments'], ['name' => 'admin.deployments']);
    $r->get('/settings', [AdminController::class, 'settings'], ['name' => 'admin.settings']);
    $r->post('/settings', [AdminController::class, 'saveSettings'], ['middleware' => ['csrf']]);
    $r->get('/activity', [AdminController::class, 'activity'], ['name' => 'admin.activity']);
});

$router->group(['prefix' => '/staff', 'middleware' => ['portal.staff']], static function (Router $r): void {
    $r->get('/', [StaffController::class, 'dashboard'], ['name' => 'staff.dashboard']);
    $r->get('/pipeline', [StaffController::class, 'pipeline'], ['name' => 'staff.pipeline']);
    $r->post('/pipeline/status', [StaffController::class, 'updateLeadStatus'], ['middleware' => ['csrf']]);
    $r->get('/projects', [StaffController::class, 'projects'], ['name' => 'staff.projects']);
    $r->get('/tickets', [StaffController::class, 'tickets'], ['name' => 'staff.tickets']);
    $r->post('/tickets/reply', [StaffController::class, 'replyTicket'], ['middleware' => ['csrf']]);
    $r->get('/tasks', [StaffController::class, 'tasks'], ['name' => 'staff.tasks']);
    $r->post('/tasks/toggle', [StaffController::class, 'toggleTask'], ['middleware' => ['csrf']]);
});

$router->group(['prefix' => '/client', 'middleware' => ['portal.client']], static function (Router $r): void {
    $r->get('/', [ClientController::class, 'dashboard'], ['name' => 'client.dashboard']);
    $r->get('/projects', [ClientController::class, 'projects'], ['name' => 'client.projects']);
    $r->get('/projects/{id:\d+}', [ClientController::class, 'project'], ['name' => 'client.project']);
    $r->get('/licenses', [ClientController::class, 'licenses'], ['name' => 'client.licenses']);
    $r->get('/downloads/{key:[A-Za-z0-9\-]+}', [ClientController::class, 'download'], ['name' => 'client.download']);
    $r->get('/deployments', [ClientController::class, 'deployments'], ['name' => 'client.deployments']);
    $r->post('/deployments', [ClientController::class, 'createDeployment'], ['middleware' => ['csrf']]);
    $r->get('/invoices', [ClientController::class, 'invoices'], ['name' => 'client.invoices']);
    $r->get('/support', [ClientController::class, 'support'], ['name' => 'client.support']);
    $r->post('/support', [ClientController::class, 'openTicket'], ['middleware' => ['csrf']]);
});

/* ---------------------------------------------------------------------------
 | AMP variants
 * ------------------------------------------------------------------------ */

$router->get('/amp', [SeoController::class, 'ampHome'], ['name' => 'amp.home']);
$router->get('/amp/services', [SeoController::class, 'ampServices'], ['name' => 'amp.services']);
$router->get('/amp/contact', [SeoController::class, 'ampContact'], ['name' => 'amp.contact']);
$router->get('/amp/resources/{slug:[a-z0-9-]+}', [SeoController::class, 'ampResource'], ['name' => 'amp.resource']);
$router->get('/amp/marketplace/{slug:[a-z0-9-]+}', [SeoController::class, 'ampProduct'], ['name' => 'amp.product']);

/* ---------------------------------------------------------------------------
 | Machine-readable endpoints
 * ------------------------------------------------------------------------ */

$router->get('/sitemap.xml', [SeoController::class, 'sitemap']);
$router->get('/robots.txt', [SeoController::class, 'robots']);
$router->get('/manifest.webmanifest', [SeoController::class, 'manifest']);
$router->get('/feed.xml', [SeoController::class, 'feed']);
$router->get('/health', [SeoController::class, 'health']);
$router->get('/api/marketplace/search', [MarketplaceController::class, 'search']);
