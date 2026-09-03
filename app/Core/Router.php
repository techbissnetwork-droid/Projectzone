<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Compact regex router with named routes, per-route middleware and a static
 * "fast map" for literal paths so the common case is an O(1) array lookup.
 */
final class Router
{
    private array $static = [];   // [METHOD][path] => route
    private array $dynamic = [];  // [METHOD][] => route
    private array $named = [];
    private array $groupStack = [];

    public function group(array $attributes, callable $callback): void
    {
        $this->groupStack[] = $attributes;
        $callback($this);
        array_pop($this->groupStack);
    }

    public function get(string $path, array|callable $handler, array $options = []): self
    {
        return $this->map(['GET', 'HEAD'], $path, $handler, $options);
    }

    public function post(string $path, array|callable $handler, array $options = []): self
    {
        return $this->map(['POST'], $path, $handler, $options);
    }

    public function any(array $methods, string $path, array|callable $handler, array $options = []): self
    {
        return $this->map($methods, $path, $handler, $options);
    }

    private function map(array $methods, string $path, array|callable $handler, array $options): self
    {
        [$prefix, $middleware] = $this->groupContext();
        $path = '/' . trim($prefix . '/' . trim($path, '/'), '/');
        $path = $path === '//' ? '/' : $path;

        $route = [
            'methods' => $methods,
            'path' => $path,
            'handler' => $handler,
            'middleware' => array_values(array_unique([...$middleware, ...($options['middleware'] ?? [])])),
            'name' => $options['name'] ?? null,
            'regex' => null,
            'params' => [],
        ];

        if (str_contains($path, '{')) {
            [$route['regex'], $route['params']] = $this->compile($path);
            foreach ($methods as $method) {
                $this->dynamic[$method][] = $route;
            }
        } else {
            foreach ($methods as $method) {
                $this->static[$method][$path] = $route;
            }
        }

        if ($route['name'] !== null) {
            $this->named[$route['name']] = $path;
        }

        return $this;
    }

    private function groupContext(): array
    {
        $prefix = '';
        $middleware = [];
        foreach ($this->groupStack as $group) {
            if (isset($group['prefix'])) {
                $prefix .= '/' . trim($group['prefix'], '/');
            }
            foreach ((array) ($group['middleware'] ?? []) as $m) {
                $middleware[] = $m;
            }
        }
        return [$prefix, $middleware];
    }

    /** Compile "/marketplace/{slug}" into a regex plus its parameter names. */
    private function compile(string $path): array
    {
        $params = [];
        $regex = preg_replace_callback(
            '/\{([a-zA-Z_][a-zA-Z0-9_]*)(?::([^}]+))?\}/',
            function (array $m) use (&$params): string {
                $params[] = $m[1];
                return '(' . ($m[2] ?? '[^/]+') . ')';
            },
            $path
        );
        return ['#^' . $regex . '$#u', $params];
    }

    /**
     * @return array{0:string,1:array<string,mixed>|array<int,string>}
     *         [status, route|allowedMethods]
     */
    public function match(string $method, string $path): array
    {
        if (isset($this->static[$method][$path])) {
            return ['found', $this->static[$method][$path] + ['args' => []]];
        }

        foreach ($this->dynamic[$method] ?? [] as $route) {
            if (preg_match($route['regex'], $path, $matches)) {
                array_shift($matches);
                $args = array_combine($route['params'], $matches) ?: [];
                return ['found', $route + ['args' => $args]];
            }
        }

        $allowed = [];
        foreach (array_keys($this->static + $this->dynamic) as $candidate) {
            if ($candidate === $method) {
                continue;
            }
            if (isset($this->static[$candidate][$path])) {
                $allowed[] = $candidate;
                continue;
            }
            foreach ($this->dynamic[$candidate] ?? [] as $route) {
                if (preg_match($route['regex'], $path)) {
                    $allowed[] = $candidate;
                    break;
                }
            }
        }

        return $allowed ? ['method_not_allowed', array_values(array_unique($allowed))] : ['not_found', []];
    }

    public function routeExists(string $name): bool
    {
        return isset($this->named[$name]);
    }

    public function pathFor(string $name, array $params = []): string
    {
        $path = $this->named[$name] ?? '/';
        foreach ($params as $key => $value) {
            $path = str_replace(['{' . $key . '}'], rawurlencode((string) $value), $path);
        }
        return preg_replace('/\{[^}]+\}/', '', $path) ?: '/';
    }

    /** Every literal GET path — used to build sitemap.xml. */
    public function staticGetPaths(): array
    {
        return array_keys($this->static['GET'] ?? []);
    }
}
