<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Immutable-ish request wrapper with reliable base-path and scheme detection.
 * The detection logic here is shared with the Advanced Installer so a site
 * installed in a sub-directory, behind a proxy or on a CDN edge still builds
 * correct absolute URLs.
 */
final class Request
{
    private function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $query,
        public readonly array $body,
        public readonly array $files,
        public readonly array $server,
        public readonly array $cookies,
    ) {
    }

    public static function capture(): self
    {
        $server = $_SERVER;
        $method = strtoupper($server['REQUEST_METHOD'] ?? 'GET');

        // Allow method override for HTML forms (PUT/PATCH/DELETE).
        if ($method === 'POST' && isset($_POST['_method'])) {
            $override = strtoupper((string) $_POST['_method']);
            if (in_array($override, ['PUT', 'PATCH', 'DELETE'], true)) {
                $method = $override;
            }
        }

        $uri = (string) ($server['REQUEST_URI'] ?? '/');
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $path = '/' . trim(rawurldecode($path), '/');

        $base = self::detectBasePath($server);
        if ($base !== '' && str_starts_with($path, $base)) {
            $path = '/' . ltrim(substr($path, strlen($base)), '/');
        }
        $path = $path === '' ? '/' : $path;

        return new self($method, $path, $_GET, $_POST, $_FILES, $server, $_COOKIE);
    }

    /** Build a synthetic request (used by the CLI tools and tests). */
    public static function create(string $method, string $path, array $body = [], array $query = []): self
    {
        return new self(strtoupper($method), '/' . trim($path, '/'), $query, $body, [], $_SERVER, []);
    }

    /** Sub-directory the front controller is mounted under, e.g. "/techbiss". */
    public static function detectBasePath(array $server): string
    {
        $scriptName = (string) ($server['SCRIPT_NAME'] ?? '');
        $base = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');
        if ($base === '.' || $base === '/') {
            return '';
        }
        return $base;
    }

    public static function detectScheme(array $server): string
    {
        $forwarded = strtolower((string) ($server['HTTP_X_FORWARDED_PROTO'] ?? ''));
        if ($forwarded !== '') {
            return str_contains($forwarded, 'https') ? 'https' : 'http';
        }
        if (strtolower((string) ($server['HTTP_X_FORWARDED_SSL'] ?? '')) === 'on') {
            return 'https';
        }
        if (!empty($server['HTTPS']) && strtolower((string) $server['HTTPS']) !== 'off') {
            return 'https';
        }
        if ((int) ($server['SERVER_PORT'] ?? 80) === 443) {
            return 'https';
        }
        return 'http';
    }

    public static function detectHost(array $server): string
    {
        $host = (string) ($server['HTTP_X_FORWARDED_HOST'] ?? $server['HTTP_HOST'] ?? $server['SERVER_NAME'] ?? 'localhost');
        $host = trim(explode(',', $host)[0]);
        // Strip anything that cannot legally appear in an authority component.
        return preg_replace('/[^A-Za-z0-9\.\-:\[\]]/', '', $host) ?: 'localhost';
    }

    /** Fully-qualified origin + base path, e.g. https://techbiss.com/app */
    public static function detectBaseUrl(array $server): string
    {
        return self::detectScheme($server) . '://' . self::detectHost($server) . self::detectBasePath($server);
    }

    public function isMethod(string $method): bool
    {
        return $this->method === strtoupper($method);
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $this->query[$key] ?? $default;
    }

    public function str(string $key, string $default = ''): string
    {
        $value = $this->input($key, $default);
        return is_scalar($value) ? trim((string) $value) : $default;
    }

    public function int(string $key, int $default = 0): int
    {
        $value = $this->input($key, $default);
        return is_numeric($value) ? (int) $value : $default;
    }

    public function boolean(string $key): bool
    {
        return in_array($this->input($key), ['1', 1, true, 'true', 'on', 'yes'], true);
    }

    public function arr(string $key): array
    {
        $value = $this->input($key, []);
        return is_array($value) ? $value : [];
    }

    public function wantsJson(): bool
    {
        $accept = (string) ($this->server['HTTP_ACCEPT'] ?? '');
        return str_contains($accept, 'application/json')
            || strtolower((string) ($this->server['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';
    }

    public function ip(): string
    {
        foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $key) {
            $value = (string) ($this->server[$key] ?? '');
            if ($value === '') {
                continue;
            }
            $candidate = trim(explode(',', $value)[0]);
            if (filter_var($candidate, FILTER_VALIDATE_IP)) {
                return $candidate;
            }
        }
        return '0.0.0.0';
    }

    public function userAgent(): string
    {
        return substr((string) ($this->server['HTTP_USER_AGENT'] ?? ''), 0, 500);
    }

    public function fullPath(): string
    {
        return $this->path . ($this->query ? '?' . http_build_query($this->query) : '');
    }
}
