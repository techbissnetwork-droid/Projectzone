<?php
declare(strict_types=1);

namespace Techbiss\Core;

/** Very small file cache used for settings/navigation reads. */
final class Cache
{
    private static bool $enabled = false;
    private static string $dir = '';
    private static int $ttl = 300;
    /** @var array<string,mixed> */
    private static array $memo = [];

    public static function boot(array $cfg): void
    {
        self::$enabled = (bool) ($cfg['enabled'] ?? false);
        self::$dir     = rtrim((string) ($cfg['dir'] ?? ''), '/');
        self::$ttl     = (int) ($cfg['ttl'] ?? 300);
        if (self::$enabled && self::$dir !== '' && !is_dir(self::$dir)) {
            @mkdir(self::$dir, 0775, true);
        }
    }

    public static function remember(string $key, callable $producer, ?int $ttl = null): mixed
    {
        if (array_key_exists($key, self::$memo)) {
            return self::$memo[$key];
        }
        $value = self::get($key);
        if ($value !== null) {
            return self::$memo[$key] = $value;
        }
        $value = $producer();
        self::put($key, $value, $ttl);
        return self::$memo[$key] = $value;
    }

    public static function get(string $key): mixed
    {
        if (!self::$enabled) {
            return null;
        }
        $file = self::path($key);
        if (!is_file($file)) {
            return null;
        }
        $raw = @file_get_contents($file);
        if ($raw === false) {
            return null;
        }
        $data = @unserialize($raw, ['allowed_classes' => false]);
        if (!is_array($data) || !isset($data['expires'], $data['value'])) {
            return null;
        }
        if ($data['expires'] < time()) {
            @unlink($file);
            return null;
        }
        return $data['value'];
    }

    public static function put(string $key, mixed $value, ?int $ttl = null): void
    {
        if (!self::$enabled) {
            return;
        }
        $payload = serialize(['expires' => time() + ($ttl ?? self::$ttl), 'value' => $value]);
        $file    = self::path($key);
        $tmp     = $file . '.' . getmypid() . '.tmp';
        if (@file_put_contents($tmp, $payload, LOCK_EX) !== false) {
            @rename($tmp, $file);
        }
    }

    public static function forget(string $key): void
    {
        unset(self::$memo[$key]);
        if (self::$enabled) {
            @unlink(self::path($key));
        }
    }

    /** Drop the whole cache — called after any admin write. */
    public static function flush(): void
    {
        self::$memo = [];
        if (!self::$enabled || self::$dir === '' || !is_dir(self::$dir)) {
            return;
        }
        foreach (glob(self::$dir . '/*.cache') ?: [] as $file) {
            @unlink($file);
        }
    }

    private static function path(string $key): string
    {
        return self::$dir . '/' . sha1($key) . '.cache';
    }
}
