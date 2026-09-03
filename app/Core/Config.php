<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Configuration store. Values are merged from config/*.php and, once the
 * platform has been installed, from storage/installed.php written by the
 * Advanced Installer.
 */
final class Config
{
    private array $items = [];

    public function __construct(array $items = [])
    {
        $this->items = $items;
    }

    public static function load(string $configDir, ?string $installedFile = null): self
    {
        $items = [];
        foreach (glob(rtrim($configDir, '/') . '/*.php') ?: [] as $file) {
            $items[basename($file, '.php')] = require $file;
        }
        if ($installedFile !== null && is_file($installedFile)) {
            $installed = require $installedFile;
            if (is_array($installed)) {
                $items = self::mergeDeep($items, $installed);
            }
        }
        return new self($items);
    }

    public static function mergeDeep(array $base, array $overrides): array
    {
        foreach ($overrides as $key => $value) {
            if (is_array($value) && isset($base[$key]) && is_array($base[$key])) {
                $base[$key] = self::mergeDeep($base[$key], $value);
                continue;
            }
            $base[$key] = $value;
        }
        return $base;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $segments = explode('.', $key);
        $value = $this->items;
        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }
        return $value;
    }

    public function set(string $key, mixed $value): void
    {
        $segments = explode('.', $key);
        $ref = &$this->items;
        foreach ($segments as $segment) {
            if (!isset($ref[$segment]) || !is_array($ref[$segment])) {
                $ref[$segment] = [];
            }
            $ref = &$ref[$segment];
        }
        $ref = $value;
    }

    public function all(): array
    {
        return $this->items;
    }
}
