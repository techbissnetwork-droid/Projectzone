<?php
declare(strict_types=1);

namespace Techbiss\Core;

final class Request
{
    private string $method;
    private string $path;
    private array $query;
    private array $post;
    private array $files;
    private array $server;

    private function __construct(string $method, string $path, array $query, array $post, array $files, array $server)
    {
        $this->method = $method;
        $this->path   = $path;
        $this->query  = $query;
        $this->post   = $post;
        $this->files  = $files;
        $this->server = $server;
    }

    public static function capture(string $basePath = ''): self
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        if ($method === 'POST' && isset($_POST['_method'])) {
            $override = strtoupper((string) $_POST['_method']);
            if (in_array($override, ['PUT', 'PATCH', 'DELETE'], true)) {
                $method = $override;
            }
        }

        $uri  = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $path = parse_url($uri, PHP_URL_PATH);
        $path = is_string($path) ? $path : '/';
        $path = rawurldecode($path);

        if ($basePath !== '' && str_starts_with($path, $basePath)) {
            $path = substr($path, strlen($basePath));
        }
        // The router may also receive the path through the rewrite rule.
        if (isset($_GET['__route']) && is_string($_GET['__route']) && $_GET['__route'] !== '') {
            $path = '/' . ltrim($_GET['__route'], '/');
        }
        $path = '/' . trim($path, '/');

        return new self($method, $path, $_GET, $_POST, $_FILES, $_SERVER);
    }

    public function method(): string
    {
        return $this->method;
    }

    public function isPost(): bool
    {
        return $this->method === 'POST';
    }

    public function path(): string
    {
        return $this->path;
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    public function queryString(string $key, string $default = ''): string
    {
        $v = $this->query[$key] ?? $default;
        return is_scalar($v) ? trim((string) $v) : $default;
    }

    public function queryInt(string $key, int $default = 0): int
    {
        $v = $this->query[$key] ?? null;
        return is_numeric($v) ? (int) $v : $default;
    }

    public function allQuery(): array
    {
        return $this->query;
    }

    public function post(string $key, mixed $default = null): mixed
    {
        return $this->post[$key] ?? $default;
    }

    public function str(string $key, string $default = ''): string
    {
        $v = $this->post[$key] ?? $this->query[$key] ?? $default;
        if (is_array($v)) {
            return $default;
        }
        return trim((string) $v);
    }

    public function int(string $key, int $default = 0): int
    {
        $v = $this->post[$key] ?? $this->query[$key] ?? null;
        return is_numeric($v) ? (int) $v : $default;
    }

    public function float(string $key, float $default = 0.0): float
    {
        $v = $this->post[$key] ?? $this->query[$key] ?? null;
        if (is_string($v)) {
            $v = str_replace([',', ' '], '', $v);
        }
        return is_numeric($v) ? (float) $v : $default;
    }

    public function bool(string $key): bool
    {
        $v = $this->post[$key] ?? $this->query[$key] ?? null;
        return in_array($v, ['1', 1, true, 'on', 'true', 'yes'], true);
    }

    /** @return array<int,string> */
    public function arr(string $key): array
    {
        $v = $this->post[$key] ?? $this->query[$key] ?? [];
        if (!is_array($v)) {
            return [];
        }
        $out = [];
        foreach ($v as $item) {
            if (is_scalar($item)) {
                $out[] = trim((string) $item);
            }
        }
        return $out;
    }

    /** @return array<int,array<string,mixed>> repeated form rows, e.g. features[0][title] */
    public function rows(string $key): array
    {
        $v = $this->post[$key] ?? [];
        return is_array($v) ? array_values(array_filter($v, 'is_array')) : [];
    }

    public function all(): array
    {
        return $this->post + $this->query;
    }

    public function file(string $key): ?array
    {
        $f = $this->files[$key] ?? null;
        if (!is_array($f) || ($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        return $f;
    }

    /** @return array<int,array<string,mixed>> normalised multi-file input */
    public function fileList(string $key): array
    {
        $f = $this->files[$key] ?? null;
        if (!is_array($f) || !isset($f['name'])) {
            return [];
        }
        if (!is_array($f['name'])) {
            return ($f['error'] === UPLOAD_ERR_NO_FILE) ? [] : [$f];
        }
        $out = [];
        foreach (array_keys($f['name']) as $i) {
            if (($f['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $out[] = [
                'name'     => $f['name'][$i],
                'type'     => $f['type'][$i],
                'tmp_name' => $f['tmp_name'][$i],
                'error'    => $f['error'][$i],
                'size'     => $f['size'][$i],
            ];
        }
        return $out;
    }

    public function header(string $name): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        $v   = $this->server[$key] ?? null;
        return is_string($v) ? $v : null;
    }

    public function ip(): string
    {
        $ip = $this->server['REMOTE_ADDR'] ?? '0.0.0.0';
        return is_string($ip) ? substr($ip, 0, 45) : '0.0.0.0';
    }

    public function userAgent(): string
    {
        $ua = $this->server['HTTP_USER_AGENT'] ?? '';
        return is_string($ua) ? substr($ua, 0, 255) : '';
    }

    public function isAjax(): bool
    {
        return strtolower((string) $this->header('X-Requested-With')) === 'xmlhttprequest';
    }

    public function wantsJson(): bool
    {
        $accept = (string) $this->header('Accept');
        return $this->isAjax() || str_contains($accept, 'application/json');
    }

    public function referer(): string
    {
        $r = $this->server['HTTP_REFERER'] ?? '';
        return is_string($r) ? $r : '';
    }
}
