<?php
declare(strict_types=1);

namespace App\Core;

final class Response
{
    private array $headers = [];

    public function __construct(
        private string $content = '',
        private int $status = 200,
        array $headers = [],
    ) {
        foreach ($headers as $name => $value) {
            $this->headers[strtolower($name)] = [$name, $value];
        }
    }

    public static function html(string $html, int $status = 200, array $headers = []): self
    {
        return new self($html, $status, $headers + ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    public static function text(string $text, int $status = 200, array $headers = []): self
    {
        return new self($text, $status, $headers + ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    public static function xml(string $xml, int $status = 200, array $headers = []): self
    {
        return new self($xml, $status, $headers + ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    public static function json(array $data, int $status = 200, array $headers = []): self
    {
        $encoded = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return new self($encoded === false ? '{}' : $encoded, $status, $headers + [
            'Content-Type' => 'application/json; charset=UTF-8',
        ]);
    }

    public static function redirect(string $location, int $status = 302): self
    {
        return new self('', $status, ['Location' => $location]);
    }

    public function withHeader(string $name, string $value): self
    {
        $this->headers[strtolower($name)] = [$name, $value];
        return $this;
    }

    /** Public, CDN-cacheable HTML. Used for anonymous marketing pages. */
    public function cachePublic(int $seconds, int $staleWhileRevalidate = 86400): self
    {
        return $this->withHeader(
            'Cache-Control',
            "public, max-age=0, s-maxage={$seconds}, stale-while-revalidate={$staleWhileRevalidate}"
        );
    }

    public function cachePrivate(): self
    {
        return $this->withHeader('Cache-Control', 'private, no-store, max-age=0');
    }

    public function status(): int
    {
        return $this->status;
    }

    public function content(): string
    {
        return $this->content;
    }

    public function headers(): array
    {
        return $this->headers;
    }

    public function send(bool $sendBody = true): void
    {
        if (!headers_sent()) {
            http_response_code($this->status);
            foreach ($this->headers as [$name, $value]) {
                header($name . ': ' . $value, true);
            }
        }
        if ($sendBody) {
            echo $this->content;
        }
    }
}
