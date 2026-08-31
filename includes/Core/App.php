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
    private static string $siteUrl = '';
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

        self::$basePath = rtrim((string) ($site['base_path'] ?? ''), '/');
        self::$siteUrl  = rtrim((string) ($site['url'] ?? ''), '/');
        if (self::$siteUrl === '') {
            $https  = (($_SERVER['HTTPS'] ?? '') !== '' && $_SERVER['HTTPS'] !== 'off')
                || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
            $host   = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
            $host   = preg_replace('/[^A-Za-z0-9:.\-]/', '', $host) ?: 'localhost';
            self::$siteUrl = ($https ? 'https://' : 'http://') . $host;
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

    public static function siteUrl(): string
    {
        return self::$siteUrl . self::$basePath;
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
    public static function sendSecurityHeaders(bool $isAdmin = false): void
    {
        if (headers_sent()) {
            return;
        }
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

    private static function fatalConfig(): never
    {
        http_response_code(503);
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><meta charset="utf-8"><title>Configuration required</title>'
            . '<body style="font:16px/1.7 ui-sans-serif,system-ui;background:#0a0c12;color:#e6e9f2;padding:56px;max-width:46rem;margin:auto">'
            . '<h1 style="font-size:1.6rem;margin:0 0 .5rem">Configuration required</h1>'
            . '<p style="color:#9aa3b8">TECHBISS cannot start because <code>config/config.php</code> is missing.</p>'
            . '<p style="color:#9aa3b8">Copy <code>config/config.sample.php</code> to <code>config/config.php</code>, '
            . 'fill in your database credentials, then open <code>/database/install.php</code> to create the tables.</p>'
            . '</body>';
        exit;
    }
}
