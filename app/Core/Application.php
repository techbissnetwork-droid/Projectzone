<?php
declare(strict_types=1);

namespace App\Core;

final class Application
{
    public const VERSION = '1.0.0';

    private Container $container;
    private Router $router;
    private Config $config;
    private array $middleware = [];

    public function __construct(public readonly string $basePath)
    {
        $this->container = Container::instance();
        $this->config = Config::load($this->path('config'), $this->path('storage/installed.php'));
        $this->router = new Router();
        $this->registerServices();
    }

    public function path(string $relative = ''): string
    {
        return rtrim($this->basePath . '/' . ltrim($relative, '/'), '/');
    }

    public function container(): Container
    {
        return $this->container;
    }

    public function router(): Router
    {
        return $this->router;
    }

    public function config(): Config
    {
        return $this->config;
    }

    public function isInstalled(): bool
    {
        return is_file($this->path('storage/installed.php')) && is_file($this->path('storage/install.lock'));
    }

    public function isDebug(): bool
    {
        return (bool) $this->config->get('app.debug', false);
    }

    private function registerServices(): void
    {
        $app = $this;
        $c = $this->container;

        $c->set('app', $this);
        $c->set('config', $this->config);
        $c->set('router', $this->router);

        $c->bind('db', static fn () => new Database($app->config->get('database.connections.' . $app->config->get('database.default', 'sqlite'), [])));
        $c->bind('session', static fn () => new Session($app->config->get('session', [])));
        $c->bind('csrf', static fn (Container $c) => new Csrf($c->get('session')));
        $c->bind('auth', static fn (Container $c) => new Auth($c->get('db'), $c->get('session')));
        $c->bind('cache', static fn () => new Cache($app->path('storage/cache'), (bool) $app->config->get('cache.enabled', true)));
        $c->bind('mailer', static fn () => new Mailer($app->config->get('mail', []), $app->path('storage/logs/mail.log')));
        $c->bind('view', static fn () => new View($app->path('app/Views')));
        $c->bind('seo', static fn () => new Seo(
            $app->config->get('app.url', 'http://localhost'),
            (string) $app->config->get('app.name', 'TECHBISS')
        ));
    }

    public function make(string $service): mixed
    {
        return $this->container->get($service);
    }

    public function middleware(string $name, callable $handler): void
    {
        $this->middleware[$name] = $handler;
    }

    public function handle(Request $request): Response
    {
        // Resolve the runtime URL from the live request unless it was pinned
        // during installation. This keeps the platform portable across
        // domains, sub-directories, staging clones and CDN origins.
        if ((bool) $this->config->get('app.detect_url', true)) {
            $detected = Request::detectBaseUrl($request->server);
            $this->config->set('app.url', $detected);
        }
        $this->config->set('app.base_path', Request::detectBasePath($request->server));

        try {
            if (!$this->isInstalled() && !str_starts_with($request->path, '/install') && !str_starts_with($request->path, '/assets')) {
                return Response::redirect(url('/install'));
            }

            [$status, $result] = $this->router->match($request->method, $request->path);

            if ($status === 'method_not_allowed') {
                return $this->errorResponse($request, 405, 'Method Not Allowed', [
                    'Allow' => implode(', ', $result),
                ]);
            }

            if ($status === 'not_found') {
                return $this->errorResponse($request, 404, 'Page Not Found');
            }

            return $this->runPipeline($request, $result);
        } catch (HttpException $e) {
            return $this->errorResponse($request, $e->getStatusCode(), $e->getMessage());
        } catch (\Throwable $e) {
            $this->logException($e);
            if ($this->isDebug()) {
                throw $e;
            }
            return $this->errorResponse($request, 500, 'Something went wrong on our side.');
        }
    }

    private function runPipeline(Request $request, array $route): Response
    {
        foreach ($route['middleware'] as $name) {
            if (!isset($this->middleware[$name])) {
                continue;
            }
            $result = ($this->middleware[$name])($request, $route);
            if ($result instanceof Response) {
                return $result;
            }
        }

        $handler = $route['handler'];
        $args = $route['args'];

        if (is_array($handler)) {
            [$class, $method] = $handler;
            $controller = new $class($this);
            $response = $controller->{$method}($request, ...array_values($args));
        } else {
            $response = $handler($request, ...array_values($args));
        }

        if (is_string($response)) {
            $response = Response::html($response);
        }
        if (!$response instanceof Response) {
            throw new \RuntimeException('Route handler must return a Response or string.');
        }

        return $response;
    }

    public function errorResponse(Request $request, int $status, string $message, array $headers = []): Response
    {
        if ($request->wantsJson()) {
            return Response::json(['error' => $message, 'status' => $status], $status, $headers);
        }

        /** @var View $view */
        $view = $this->make('view');
        /** @var Seo $seo */
        $seo = $this->make('seo');
        $seo->title(sprintf('%d — %s', $status, $message));
        $seo->description($message);
        $seo->noindex();

        $this->shareViewGlobals($view, $request);

        $html = $view->render('errors.status', [
            'status' => $status,
            'message' => $message,
        ]);

        return Response::html($html, $status, $headers)->cachePrivate();
    }

    public function shareViewGlobals(View $view, Request $request): void
    {
        static $shared = false;
        if ($shared) {
            return;
        }
        $shared = true;

        /** @var Session $session */
        $session = $this->make('session');
        $session->start();

        $view->share('app', $this);
        $view->share('config', $this->config);
        $view->share('request', $request);
        $view->share('seo', $this->make('seo'));
        $view->share('auth', $this->make('auth'));
        $view->share('csrf', $this->make('csrf'));
        $view->share('flash', $session->flashAll());
        $view->share('nav', $this->config->get('navigation', []));
        $view->share('site', $this->config->get('site', []));
    }

    private function logException(\Throwable $e): void
    {
        $file = $this->path('storage/logs/app.log');
        $dir = dirname($file);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        @file_put_contents($file, sprintf(
            "[%s] %s: %s in %s:%d\n%s\n\n",
            gmdate('c'),
            $e::class,
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
            $e->getTraceAsString()
        ), FILE_APPEND | LOCK_EX);
    }
}
