<?php
declare(strict_types=1);

use App\Core\Application;
use App\Core\Container;

if (!function_exists('app')) {
    function app(?string $service = null): mixed
    {
        $container = Container::instance();
        return $service === null ? $container->get('app') : $container->get($service);
    }
}

if (!function_exists('config')) {
    function config(string $key, mixed $default = null): mixed
    {
        return app('config')->get($key, $default);
    }
}

if (!function_exists('base_url')) {
    function base_url(): string
    {
        return rtrim((string) config('app.url', ''), '/');
    }
}

if (!function_exists('url')) {
    /** Build an application URL that survives sub-directory installs. */
    function url(string $path = '/'): string
    {
        if ($path === '' || $path === '#') {
            return $path === '' ? base_url() . '/' : '#';
        }
        if (preg_match('#^(https?:)?//#', $path) || str_starts_with($path, 'mailto:') || str_starts_with($path, 'tel:')) {
            return $path;
        }
        return base_url() . '/' . ltrim($path, '/');
    }
}

if (!function_exists('asset')) {
    /**
     * Fingerprinted asset URL. The hash is derived from the file's mtime and
     * size so assets can be served with `immutable` far-future caching and
     * still update instantly on deploy.
     */
    function asset(string $path): string
    {
        static $versions = [];
        $path = ltrim($path, '/');
        if (!isset($versions[$path])) {
            $file = app()->path('public/' . $path);
            $versions[$path] = is_file($file)
                ? substr(hash('xxh128', (string) filemtime($file) . '|' . (string) filesize($file)), 0, 10)
                : substr(Application::VERSION, 0, 10);
        }
        return base_url() . '/' . $path . '?v=' . $versions[$path];
    }
}

if (!function_exists('inline_file')) {
    /** Read a build asset for inlining (critical CSS, sprite sheets). */
    function inline_file(string $path): string
    {
        $file = app()->path('public/' . ltrim($path, '/'));
        return is_file($file) ? (string) file_get_contents($file) : '';
    }
}

if (!function_exists('inline_css')) {
    /**
     * Inline a stylesheet, rewriting its root-relative asset URLs.
     *
     * Inlined CSS resolves relative URLs against the *page* URL rather than a
     * stylesheet URL, so `/assets/fonts/x.woff2` would break on a sub-directory
     * install and on any nested route. Rewriting to the absolute base URL keeps
     * font and image references correct everywhere.
     */
    function inline_css(string $path): string
    {
        $css = inline_file($path);
        if ($css === '') {
            return '';
        }
        return str_replace('url("/assets/', 'url("' . base_url() . '/assets/', $css);
    }
}

if (!function_exists('font_preloads')) {
    /** <link rel=preload> tags for the self-hosted faces used above the fold. */
    function font_preloads(): string
    {
        $tags = '';
        foreach (['assets/fonts/manrope-var-latin.woff2', 'assets/fonts/sora-var-latin.woff2'] as $font) {
            $tags .= '<link rel="preload" as="font" type="font/woff2" crossorigin href="'
                . e(base_url() . '/' . $font) . '">' . "\n";
        }
        return $tags;
    }
}

if (!function_exists('e')) {
    function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('attr')) {
    /** Render a conditional attribute list. */
    function attr(array $attributes): string
    {
        $parts = [];
        foreach ($attributes as $name => $value) {
            if ($value === false || $value === null) {
                continue;
            }
            $parts[] = $value === true ? $name : $name . '="' . e($value) . '"';
        }
        return $parts ? ' ' . implode(' ', $parts) : '';
    }
}

if (!function_exists('money')) {
    function money(float|int|string $amount, string $currency = 'USD'): string
    {
        $symbols = ['USD' => '$', 'EUR' => '€', 'GBP' => '£', 'NGN' => '₦', 'AED' => 'AED '];
        $value = (float) $amount;
        $formatted = number_format($value, fmod($value, 1.0) === 0.0 ? 0 : 2);
        return ($symbols[$currency] ?? $currency . ' ') . $formatted;
    }
}

if (!function_exists('str_slug')) {
    function str_slug(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
        return trim($value, '-');
    }
}

if (!function_exists('initials')) {
    function initials(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $letters = array_map(static fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)), array_slice($parts, 0, 2));
        return implode('', $letters) ?: 'T';
    }
}

if (!function_exists('human_date')) {
    function human_date(?string $iso, string $format = 'M j, Y'): string
    {
        if (!$iso) {
            return '—';
        }
        try {
            return (new DateTimeImmutable($iso))->format($format);
        } catch (Throwable) {
            return '—';
        }
    }
}

if (!function_exists('time_ago')) {
    function time_ago(?string $iso): string
    {
        if (!$iso) {
            return '—';
        }
        try {
            $then = (new DateTimeImmutable($iso))->getTimestamp();
        } catch (Throwable) {
            return '—';
        }
        $delta = max(0, time() - $then);
        return match (true) {
            $delta < 60 => 'just now',
            $delta < 3600 => intdiv($delta, 60) . 'm ago',
            $delta < 86400 => intdiv($delta, 3600) . 'h ago',
            $delta < 2592000 => intdiv($delta, 86400) . 'd ago',
            default => human_date($iso),
        };
    }
}

if (!function_exists('old')) {
    function old(string $key, string $default = ''): string
    {
        $flash = app('view')->shared()['flash'] ?? [];
        $values = $flash['old'] ?? [];
        return is_array($values) && isset($values[$key]) && is_scalar($values[$key])
            ? (string) $values[$key]
            : $default;
    }
}

if (!function_exists('error_for')) {
    function error_for(string $key): string
    {
        $flash = app('view')->shared()['flash'] ?? [];
        $errors = $flash['errors'] ?? [];
        return is_array($errors) && isset($errors[$key]) ? (string) $errors[$key] : '';
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return app('csrf')->field();
    }
}

if (!function_exists('is_active')) {
    /** Nav active-state test that also matches nested sections. */
    function is_active(string $path, string $current): bool
    {
        $path = '/' . trim($path, '/');
        $current = '/' . trim($current, '/');
        if ($path === '/') {
            return $current === '/';
        }
        return $current === $path || str_starts_with($current, $path . '/');
    }
}

if (!function_exists('json_attr')) {
    function json_attr(array $data): string
    {
        return e(json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}');
    }
}

if (!function_exists('icon')) {
    function icon(string $name, array $options = []): string
    {
        return App\Support\Icon::render($name, $options);
    }
}

if (!function_exists('art_mockup')) {
    function art_mockup(string $key, string $layout = 'auto', array $options = []): string
    {
        return App\Support\Art::mockup($key, $layout, $options);
    }
}

if (!function_exists('art_tile')) {
    function art_tile(string $key, string $initials = ''): string
    {
        return App\Support\Art::tile($key, $initials);
    }
}
