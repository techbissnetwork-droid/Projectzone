<?php
declare(strict_types=1);

namespace Techbiss\Core;

/** Renders plain PHP templates inside a layout. */
final class View
{
    /** @var array<string,mixed> shared with every template */
    private array $shared = [];

    public function __construct(private string $viewPath, private string $layoutPath)
    {
    }

    public function share(string $key, mixed $value): void
    {
        $this->shared[$key] = $value;
    }

    /** @param array<string,mixed> $data */
    public function shareMany(array $data): void
    {
        $this->shared = array_merge($this->shared, $data);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->shared[$key] ?? $default;
    }

    /** Render a partial and return the markup. */
    public function partial(string $template, array $data = []): string
    {
        return $this->capture($this->resolve($template), array_merge($this->shared, $data));
    }

    /** Render a template inside the layout and echo the result. */
    public function render(string $template, array $data = [], ?string $layout = null): void
    {
        $data    = array_merge($this->shared, $data);
        $content = $this->capture($this->resolve($template), $data);
        $layout  = $layout ?? $this->layoutPath;
        if ($layout === '') {
            echo $content;
            return;
        }
        $data['content'] = $content;
        echo $this->capture($layout, $data);
    }

    private function resolve(string $template): string
    {
        if (!preg_match('#^[a-z0-9/_-]+$#i', $template)) {
            throw new \InvalidArgumentException('Invalid template name.');
        }
        return rtrim($this->viewPath, '/') . '/' . $template . '.php';
    }

    private function capture(string $file, array $data): string
    {
        if (!is_file($file)) {
            throw new \RuntimeException('Template not found: ' . basename($file));
        }
        $view = $this;
        extract($data, EXTR_SKIP);
        ob_start();
        try {
            include $file;
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        return (string) ob_get_clean();
    }
}
