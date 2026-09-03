<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Filesystem cache for rendered fragments and expensive queries. Uses atomic
 * rename on write so a concurrent reader never sees a half-written file.
 */
final class Cache
{
    public function __construct(private string $directory, private bool $enabled = true)
    {
        if ($this->enabled && !is_dir($this->directory)) {
            @mkdir($this->directory, 0775, true);
        }
    }

    private function path(string $key): string
    {
        $hash = hash('xxh128', $key);
        return $this->directory . '/' . substr($hash, 0, 2) . '/' . $hash . '.cache';
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if (!$this->enabled) {
            return $default;
        }
        $file = $this->path($key);
        if (!is_file($file)) {
            return $default;
        }
        $raw = @file_get_contents($file);
        if ($raw === false) {
            return $default;
        }
        $payload = @unserialize($raw, ['allowed_classes' => false]);
        if (!is_array($payload) || !array_key_exists('expires', $payload)) {
            return $default;
        }
        if ($payload['expires'] !== 0 && $payload['expires'] < time()) {
            @unlink($file);
            return $default;
        }
        return $payload['value'];
    }

    public function put(string $key, mixed $value, int $ttl = 3600): void
    {
        if (!$this->enabled) {
            return;
        }
        $file = $this->path($key);
        $dir = dirname($file);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $payload = serialize(['expires' => $ttl > 0 ? time() + $ttl : 0, 'value' => $value]);
        $temp = $file . '.' . bin2hex(random_bytes(4)) . '.tmp';
        if (@file_put_contents($temp, $payload, LOCK_EX) !== false) {
            @rename($temp, $file);
        }
    }

    public function remember(string $key, int $ttl, callable $callback): mixed
    {
        $sentinel = "\0__miss__\0";
        $value = $this->get($key, $sentinel);
        if ($value !== $sentinel) {
            return $value;
        }
        $value = $callback();
        $this->put($key, $value, $ttl);
        return $value;
    }

    public function forget(string $key): void
    {
        @unlink($this->path($key));
    }

    public function flush(): int
    {
        $count = 0;
        foreach (glob($this->directory . '/*/*.cache') ?: [] as $file) {
            if (@unlink($file)) {
                $count++;
            }
        }
        return $count;
    }
}
