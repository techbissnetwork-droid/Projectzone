<?php
declare(strict_types=1);

namespace Techbiss\Core;

/**
 * Small regex router. Patterns use {name} placeholders which match a single
 * path segment, or {name:.*} to match the remainder.
 */
final class Router
{
    /** @var array<string,array<int,array{regex:string,keys:array<int,string>,handler:callable}>> */
    private array $routes = [];
    /** @var null|callable */
    private $fallback = null;

    public function get(string $pattern, callable $handler): void
    {
        $this->add('GET', $pattern, $handler);
    }

    public function post(string $pattern, callable $handler): void
    {
        $this->add('POST', $pattern, $handler);
    }

    public function any(string $pattern, callable $handler): void
    {
        $this->add('GET', $pattern, $handler);
        $this->add('POST', $pattern, $handler);
    }

    public function fallback(callable $handler): void
    {
        $this->fallback = $handler;
    }

    private function add(string $method, string $pattern, callable $handler): void
    {
        $keys  = [];
        $regex = preg_replace_callback(
            '/\{([a-zA-Z_][a-zA-Z0-9_]*)(?::([^}]+))?\}/',
            static function (array $m) use (&$keys): string {
                $keys[] = $m[1];
                return '(' . ($m[2] ?? '[^/]+') . ')';
            },
            $pattern
        ) ?? $pattern;

        $this->routes[$method][] = [
            'regex'   => '#^' . $regex . '$#u',
            'keys'    => $keys,
            'handler' => $handler,
        ];
    }

    public function dispatch(Request $request): mixed
    {
        $path   = $request->path();
        $method = $request->method();
        if ($method === 'HEAD') {
            $method = 'GET';
        }

        foreach ($this->routes[$method] ?? [] as $route) {
            if (preg_match($route['regex'], $path, $m)) {
                $params = [];
                foreach ($route['keys'] as $i => $key) {
                    $params[$key] = $m[$i + 1] ?? '';
                }
                return ($route['handler'])($request, $params);
            }
        }

        // Path exists under a different method → 405.
        foreach ($this->routes as $verb => $routes) {
            if ($verb === $method) {
                continue;
            }
            foreach ($routes as $route) {
                if (preg_match($route['regex'], $path)) {
                    http_response_code(405);
                    header('Allow: ' . $verb);
                    echo 'Method Not Allowed';
                    return null;
                }
            }
        }

        if ($this->fallback !== null) {
            return ($this->fallback)($request);
        }

        http_response_code(404);
        echo 'Not Found';
        return null;
    }
}
