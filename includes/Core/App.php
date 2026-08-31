<?php
declare(strict_types=1);

namespace Techbiss\Core;

use Techbiss\Repo\SettingsRepo;

/** Application container: configuration, shared services and request context. */
final class App
{
    public const VERSION = '1.0.0';

    private static array $config = [];
    private static string $root = '';
    private static string $basePath = '';
    /** Scheme and host only — never includes the sub-directory. */
    private static string $origin = '';

    /** Per-request CSP nonce, so inline scripts run without 'unsafe-inline'. */
    private static string $nonce = '';
    private static ?SettingsRepo $settings = null;
    private static ?Seo $seo = null;
    private static ?Mailer $mailer = null;
    private static array $old = [];
    private static array $errors = [];
    private static array $flash = [];
    private static string $currentPath = '/';

    public static function boot(string $root): void
    {
        self::$root = rtrim($root, '/');

        $configFile = self::$root . '/config/config.php';
        if (!is_file($configFile)) {
            self::fatalConfig();
        }
        /** @var array<string,mixed> $config */
        $config       = require $configFile;
        self::$config = $config;

        $site = $config['site'] ?? [];
        date_default_timezone_set((string) ($site['timezone'] ?? 'UTC'));

        $debug = (bool) ($site['debug'] ?? false);
        ini_set('display_errors', $debug ? '1' : '0');
        error_reporting($debug ? E_ALL : E_ALL & ~E_DEPRECATED & ~E_NOTICE);
        if (!$debug) {
            $logDir = self::$root . '/storage/logs';
            if (!is_dir($logDir)) {
                @mkdir($logDir, 0775, true);
            }
            ini_set('log_errors', '1');
            ini_set('error_log', $logDir . '/php-error.log');
        }

        // site.url may be given as a bare origin or with the sub-directory
        // included. Split it so the base path is stored exactly once; otherwise
        // it ends up prepended by url(), by siteUrl() and again by the caller.
        $configuredUrl  = rtrim((string) ($site['url'] ?? ''), '/');
        $configuredBase = (string) ($site['base_path'] ?? '');
        $urlPath        = '';

        if ($configuredUrl !== '') {
            $parts  = parse_url($configuredUrl);
            $scheme = $parts['scheme'] ?? 'https';
            $host   = $parts['host'] ?? '';
            if ($host !== '') {
                $port         = isset($parts['port']) ? ':' . (int) $parts['port'] : '';
                self::$origin = $scheme . '://' . $host . $port;
                $urlPath      = rtrim((string) ($parts['path'] ?? ''), '/');
            }
        }
        if (self::$origin === '') {
            self::$origin = self::detectOrigin();
        }

        // Precedence: explicit base_path, then whatever site.url carried, then
        // detection from the running script.
        if ($configuredBase !== '') {
            self::$basePath = '/' . trim($configuredBase, '/');
        } elseif ($urlPath !== '') {
            self::$basePath = '/' . trim($urlPath, '/');
        } else {
            self::$basePath = self::detectBasePath();
        }

        Cache::boot($config['cache'] ?? []);
        Database::boot($config['db'] ?? []);
        Session::start($config['security'] ?? []);
        Auth::boot($config['security'] ?? []);

        self::$settings = new SettingsRepo();
        self::$old      = Session::takeOld();
        self::$errors   = Session::takeErrors();
        self::$flash    = Session::takeFlash();
    }

    public static function config(string $key, mixed $default = null): mixed
    {
        $parts = explode('.', $key);
        $value = self::$config;
        foreach ($parts as $part) {
            if (!is_array($value) || !array_key_exists($part, $value)) {
                return $default;
            }
            $value = $value[$part];
        }
        return $value;
    }

    public static function root(): string
    {
        return self::$root;
    }

    public static function basePath(): string
    {
        return self::$basePath;
    }

    /** Scheme and host, with no sub-directory and no trailing slash. */
    public static function origin(): string
    {
        return self::$origin;
    }

    /** The public root of the site: origin plus sub-directory, exactly once. */
    public static function siteUrl(): string
    {
        return self::$origin . self::$basePath;
    }

    public static function version(): string
    {
        return self::VERSION;
    }

    public static function debug(): bool
    {
        return (bool) self::config('site.debug', false);
    }

    public static function settings(): SettingsRepo
    {
        return self::$settings ??= new SettingsRepo();
    }

    public static function seo(): Seo
    {
        if (self::$seo === null) {
            $s = self::settings();
            self::$seo = new Seo(self::siteUrl(), $s->get('site_name', 'TECHBISS'), $s->get('seo_title_suffix', ' | TECHBISS'));
        }
        return self::$seo;
    }

    public static function mailer(): Mailer
    {
        return self::$mailer ??= new Mailer(self::$config['mail'] ?? [], self::$root . '/storage/logs');
    }

    public static function uploader(): Uploader
    {
        return new Uploader(self::$config['uploads'] ?? [], self::$root);
    }

    public static function old(): array
    {
        return self::$old;
    }

    public static function errors(): array
    {
        return self::$errors;
    }

    public static function flashMessages(): array
    {
        return self::$flash;
    }

    public static function setCurrentPath(string $path): void
    {
        self::$currentPath = $path;
    }

    public static function currentPath(): string
    {
        return self::$currentPath;
    }

    /** Security headers applied to every response. */
    /**
     * A fresh random nonce for this request.
     *
     * Every inline <script> we emit carries it and the Content-Security-Policy
     * names it, so a script tag injected into the page has no nonce and will
     * not run.
     */
    public static function nonce(): string
    {
        if (self::$nonce === '') {
            self::$nonce = base64_encode(random_bytes(16));
        }
        return self::$nonce;
    }

    /**
     * Send the security headers.
     *
     * Emitted from PHP rather than left to .htaccess, so they still apply on a
     * server where AllowOverride is off or which is not Apache at all.
     *
     * @param bool $isAdmin the admin embeds nothing third-party, so its policy is tighter
     */
    public static function sendSecurityHeaders(bool $isAdmin = false): void
    {
        if (headers_sent()) {
            return;
        }

        $script  = ["'self'", "'nonce-" . self::nonce() . "'"];
        $connect = ["'self'"];
        $img     = ["'self'", 'data:', 'blob:'];
        $style   = ["'self'", "'unsafe-inline'", 'https://fonts.googleapis.com'];
        $font    = ["'self'", 'https://fonts.gstatic.com', 'data:'];

        // Analytics is named only when an ID is actually configured, so a site
        // without it does not advertise a Google origin it never contacts.
        if (!$isAdmin && self::settings()->get('google_analytics_id') !== '') {
            $script[]  = 'https://www.googletagmanager.com';
            $connect[] = 'https://www.googletagmanager.com';
            $connect[] = 'https://www.google-analytics.com';
            $img[]     = 'https://www.google-analytics.com';
        }
        // Media can legitimately be stored on another host, so images stay open.
        $img[] = 'https:';

        header('Content-Security-Policy: ' . implode('; ', [
            "default-src 'self'",
            'script-src ' . implode(' ', $script),
            'style-src ' . implode(' ', $style),
            'img-src ' . implode(' ', $img),
            'font-src ' . implode(' ', $font),
            'connect-src ' . implode(' ', $connect),
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'self'",
            "frame-src 'none'",
        ]));
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: geolocation=(), microphone=(), camera=(), interest-cohort=()');
        header_remove('X-Powered-By');
        if ($isAdmin) {
            header('X-Robots-Tag: noindex, nofollow');
            header('Cache-Control: no-store, no-cache, must-revalidate, private');
        }
    }

    /**
     * Scheme and host for this request, honouring a reverse proxy when one is
     * in front. Used when config/config.php leaves site.url empty.
     */
    public static function detectOrigin(): string
    {
        $https = (($_SERVER['HTTPS'] ?? '') !== '' && strtolower((string) $_SERVER['HTTPS']) !== 'off')
            || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443
            || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https'
            || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_SSL'] ?? '')) === 'on';

        $host = (string) ($_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost');
        // A forwarded header can carry a list; the first entry is the client-facing host.
        $host = trim(explode(',', $host)[0]);
        $host = preg_replace('/[^A-Za-z0-9:._\-\[\]]/', '', $host) ?: 'localhost';

        return ($https ? 'https://' : 'http://') . $host;
    }

    /**
     * The sub-directory the application is served from, derived from the
     * running script rather than configuration — so /, /techbiss and
     * /clients/techbiss all work with the same config file.
     */
    public static function detectBasePath(): string
    {
        $script = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
        if ($script === '') {
            return '';
        }
        $dir = str_replace('\\', '/', dirname($script));
        // The admin front controller sits one level deeper than the site root.
        if (str_ends_with($dir, '/admin')) {
            $dir = substr($dir, 0, -6);
        }
        $dir = rtrim($dir, '/');
        return ($dir === '' || $dir === '.') ? '' : $dir;
    }

    private static function fatalConfig(): never
    {
        http_response_code(503);
        header('Content-Type: text/html; charset=utf-8');
        // If the installer is still present, send them straight to it — but never
        // from a request that is already aimed at the installer. Apache serves
        // this front controller as the 404 handler, so a mis-configured rewrite
        // would otherwise bounce /install -> index.php -> /install forever.
        $installer = self::$root . '/install.php';
        $path      = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
        $atInstaller = (bool) preg_match('#/install(\.php)?/?$#', $path);
        if (is_file($installer) && !$atInstaller) {
            $base = self::detectBasePath();
            header('Location: ' . self::detectOrigin() . $base . '/install.php', true, 302);
            exit;
        }
        if ($atInstaller) {
            echo '<!doctype html><meta charset="utf-8"><title>Setup could not start</title>'
                . '<body style="font:16px/1.7 ui-sans-serif,system-ui;background:#0a0c12;color:#e6e9f2;padding:56px;max-width:46rem;margin:auto">'
                . '<h1 style="font-size:1.6rem;margin:0 0 .5rem">Setup could not start</h1>'
                . '<p style="color:#9aa3b8">The web server did not run <code>install.php</code>; it handed the request to the '
                . 'front controller instead. That usually means <code>mod_rewrite</code> is off, or the shipped '
                . '<code>.htaccess</code> is being ignored because <code>AllowOverride</code> is set to <code>None</code>.</p>'
                . '<p style="color:#9aa3b8">Ask your host to enable both, or run the installer from the command line:<br>'
                . '<code>php install.php</code> (with no arguments it prints the options)</p>'
                . '</body>';
            exit;
        }

        echo '<!doctype html><meta charset="utf-8"><title>Configuration required</title>'
            . '<body style="font:16px/1.7 ui-sans-serif,system-ui;background:#0a0c12;color:#e6e9f2;padding:56px;max-width:46rem;margin:auto">'
            . '<h1 style="font-size:1.6rem;margin:0 0 .5rem">Configuration required</h1>'
            . '<p style="color:#9aa3b8">TECHBISS cannot start because <code>config/config.php</code> is missing.</p>'
            . '<p style="color:#9aa3b8">Copy <code>config/config.sample.php</code> to <code>config/config.php</code> and '
            . 'fill in your database credentials, or restore <code>install.php</code> and reload this page '
            . 'to run the setup wizard again.</p>'
            . '</body>';
        exit;
    }
}
