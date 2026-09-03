<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Minimal service container. Keeps boot cost near zero — services are only
 * constructed the first time they are actually resolved.
 */
final class Container
{
    private array $factories = [];
    private array $instances = [];
    private static ?Container $current = null;

    public static function instance(): Container
    {
        return self::$current ??= new self();
    }

    public function bind(string $id, callable $factory): void
    {
        $this->factories[$id] = $factory;
        unset($this->instances[$id]);
    }

    public function set(string $id, mixed $value): void
    {
        $this->instances[$id] = $value;
    }

    public function has(string $id): bool
    {
        return isset($this->instances[$id]) || isset($this->factories[$id]);
    }

    public function get(string $id): mixed
    {
        if (array_key_exists($id, $this->instances)) {
            return $this->instances[$id];
        }
        if (!isset($this->factories[$id])) {
            throw new \RuntimeException("Service [{$id}] is not bound.");
        }
        return $this->instances[$id] = ($this->factories[$id])($this);
    }
}
