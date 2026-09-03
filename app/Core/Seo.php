<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Per-request SEO state: title, description, canonical, robots directives,
 * Open Graph / Twitter cards, hreflang, AMP pairing and JSON-LD graph.
 */
final class Seo
{
    private string $title = '';
    private string $titleTemplate = '%s — TECHBISS';
    private string $description = '';
    private string $canonical = '';
    private string $robots = 'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1';
    private string $image = '';
    private string $imageAlt = '';
    private string $type = 'website';
    private ?string $ampUrl = null;
    private ?string $canonicalOfAmp = null;
    private array $keywords = [];
    private array $graph = [];
    private array $breadcrumbs = [];

    public function __construct(private string $baseUrl, private string $siteName = 'TECHBISS')
    {
    }

    public function baseUrl(): string
    {
        return $this->baseUrl;
    }

    public function absolute(string $path): string
    {
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }
        return rtrim($this->baseUrl, '/') . '/' . ltrim($path, '/');
    }

    public function title(?string $title = null, bool $raw = false): string
    {
        if ($title !== null) {
            $this->title = $raw ? $title : sprintf($this->titleTemplate, $title);
        }
        return $this->title !== '' ? $this->title : $this->siteName;
    }

    public function description(?string $description = null): string
    {
        if ($description !== null) {
            // 158 characters keeps the description inside what search
            // engines actually render, across both desktop and mobile.
            $this->description = self::clamp($description, 158);
        }
        return $this->description;
    }

    public function canonical(?string $path = null): string
    {
        if ($path !== null) {
            $this->canonical = $this->absolute($path);
        }
        return $this->canonical;
    }

    public function robots(?string $robots = null): string
    {
        if ($robots !== null) {
            $this->robots = $robots;
        }
        return $this->robots;
    }

    public function noindex(): void
    {
        $this->robots = 'noindex, nofollow';
    }

    public function image(?string $path = null, string $alt = ''): string
    {
        if ($path !== null) {
            $this->image = $this->absolute($path);
            $this->imageAlt = $alt;
        }
        return $this->image;
    }

    public function imageAlt(): string
    {
        return $this->imageAlt;
    }

    public function type(?string $type = null): string
    {
        if ($type !== null) {
            $this->type = $type;
        }
        return $this->type;
    }

    public function keywords(array $keywords = []): array
    {
        if ($keywords !== []) {
            $this->keywords = $keywords;
        }
        return $this->keywords;
    }

    public function amp(?string $path = null): ?string
    {
        if ($path !== null) {
            $this->ampUrl = $this->absolute($path);
        }
        return $this->ampUrl;
    }

    /** Mark this response as the AMP variant of a canonical HTML page. */
    public function isAmpFor(string $canonicalPath): void
    {
        $this->canonicalOfAmp = $this->absolute($canonicalPath);
        $this->canonical = $this->canonicalOfAmp;
        $this->ampUrl = null;
    }

    public function breadcrumbs(array $crumbs = []): array
    {
        if ($crumbs !== []) {
            $this->breadcrumbs = $crumbs;
        }
        return $this->breadcrumbs;
    }

    public function addSchema(array $node): void
    {
        $this->graph[] = $node;
    }

    public function siteName(): string
    {
        return $this->siteName;
    }

    /** The complete JSON-LD @graph for this page. */
    public function schemaGraph(): array
    {
        $graph = $this->graph;

        if ($this->breadcrumbs !== []) {
            $items = [];
            $position = 1;
            foreach ($this->breadcrumbs as $label => $path) {
                $items[] = [
                    '@type' => 'ListItem',
                    'position' => $position++,
                    'name' => $label,
                    'item' => $this->absolute((string) $path),
                ];
            }
            $graph[] = ['@type' => 'BreadcrumbList', 'itemListElement' => $items];
        }

        return $graph;
    }

    public function jsonLd(): string
    {
        $graph = $this->schemaGraph();
        if ($graph === []) {
            return '';
        }
        $payload = ['@context' => 'https://schema.org', '@graph' => $graph];
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return $json === false ? '' : $json;
    }

    public static function clamp(string $text, int $length): string
    {
        $text = trim(preg_replace('/\s+/', ' ', strip_tags($text)) ?? '');
        if (mb_strlen($text) <= $length) {
            return $text;
        }
        $cut = mb_substr($text, 0, $length);
        $lastSpace = mb_strrpos($cut, ' ');
        return rtrim($lastSpace ? mb_substr($cut, 0, $lastSpace) : $cut, " ,.;:-") . '…';
    }
}
