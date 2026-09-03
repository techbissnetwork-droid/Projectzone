<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Plain-PHP template engine: no compilation step, no cache invalidation
 * problems, and output that is already the final HTML byte stream.
 */
final class View
{
    private array $shared = [];
    private array $sections = [];
    private array $stack = [];
    private ?string $layout = null;
    private array $layoutData = [];

    public function __construct(private string $viewPath)
    {
    }

    public function share(string $key, mixed $value): void
    {
        $this->shared[$key] = $value;
    }

    public function shared(): array
    {
        return $this->shared;
    }

    public function exists(string $view): bool
    {
        return is_file($this->resolve($view));
    }

    private function resolve(string $view): string
    {
        $relative = str_replace(['..', '\\'], '', $view);
        $relative = str_replace('.', '/', $relative);
        return rtrim($this->viewPath, '/') . '/' . $relative . '.php';
    }

    /** Render a view, applying its layout if it declared one. */
    public function render(string $view, array $data = []): string
    {
        $previousLayout = $this->layout;
        $this->layout = null;

        $content = $this->renderRaw($view, $data);

        if ($this->layout !== null) {
            $layout = $this->layout;
            $layoutData = $this->layoutData;
            $this->layout = null;
            $this->layoutData = [];
            // A view may either define a `content` section explicitly or simply
            // emit its markup; only fall back to the raw output when it did not.
            if (!isset($this->sections['content'])) {
                $this->sections['content'] = $content;
            }
            $content = $this->renderRaw($layout, array_merge($data, $layoutData));
        }

        $this->layout = $previousLayout;
        return $content;
    }

    public function renderRaw(string $view, array $data = []): string
    {
        $file = $this->resolve($view);
        if (!is_file($file)) {
            throw new \RuntimeException("View [{$view}] not found at {$file}");
        }

        $scope = array_merge($this->shared, $data);
        $scope['view'] = $this;

        $level = ob_get_level();
        ob_start();
        try {
            (static function (string $__file, array $__scope): void {
                extract($__scope, EXTR_SKIP);
                require $__file;
            })($file, $scope);
        } catch (\Throwable $e) {
            while (ob_get_level() > $level) {
                ob_end_clean();
            }
            throw $e;
        }

        return (string) ob_get_clean();
    }

    /** Called from inside a view: $view->extends('layouts.app', [...]) */
    public function extends(string $layout, array $data = []): void
    {
        $this->layout = $layout;
        $this->layoutData = $data;
    }

    public function start(string $section): void
    {
        $this->stack[] = $section;
        ob_start();
    }

    public function stop(): void
    {
        $section = array_pop($this->stack);
        $content = (string) ob_get_clean();
        if ($section !== null) {
            $this->sections[$section] = $content;
        }
    }

    /** Append to a section instead of replacing it (used for per-page JSON-LD). */
    public function push(string $section): void
    {
        $this->stack[] = '+' . $section;
        ob_start();
    }

    public function endpush(): void
    {
        $section = array_pop($this->stack);
        $content = (string) ob_get_clean();
        if ($section !== null) {
            $name = ltrim($section, '+');
            $this->sections[$name] = ($this->sections[$name] ?? '') . $content;
        }
    }

    public function section(string $name, string $default = ''): string
    {
        return $this->sections[$name] ?? $default;
    }

    public function hasSection(string $name): bool
    {
        return isset($this->sections[$name]) && trim($this->sections[$name]) !== '';
    }

    public function setSection(string $name, string $value): void
    {
        $this->sections[$name] = $value;
    }

    /** Include a partial with the current shared scope plus extra data. */
    public function partial(string $view, array $data = []): void
    {
        echo $this->renderRaw($view, $data);
    }

    /** Render a partial for each item — used for card grids. */
    public function each(string $view, iterable $items, string $key = 'item', array $extra = []): void
    {
        foreach ($items as $index => $item) {
            echo $this->renderRaw($view, $extra + [$key => $item, 'loopIndex' => $index]);
        }
    }
}
