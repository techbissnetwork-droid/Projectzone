<?php
declare(strict_types=1);

namespace Techbiss\Core;

/**
 * Per-page SEO state: title, description, canonical, Open Graph, Twitter cards,
 * schema.org graph and breadcrumbs. Populated by each page controller and
 * rendered once in the layout head.
 */
final class Seo
{
    private string $title = '';
    private string $description = '';
    private string $canonical = '';
    private string $ogImage = '';
    private string $ogType = 'website';
    private bool $noindex = false;
    /** @var array<int,array{label:string,url:string}> */
    private array $breadcrumbs = [];
    /** @var array<int,array<string,mixed>> */
    private array $schemas = [];
    /** @var array<string,string> */
    private array $meta = [];

    public function __construct(private string $siteUrl, private string $siteName, private string $titleSuffix = '')
    {
    }

    public function title(?string $title = null): string
    {
        if ($title !== null) {
            $this->title = trim($title);
        }
        return $this->title;
    }

    public function fullTitle(string $fallback): string
    {
        $t = $this->title !== '' ? $this->title : $fallback;
        if ($this->titleSuffix !== '' && !str_contains($t, trim($this->titleSuffix, ' |-'))) {
            $t .= $this->titleSuffix;
        }
        return mb_substr($t, 0, 120);
    }

    public function description(?string $description = null): string
    {
        if ($description !== null) {
            $this->description = trim(preg_replace('/\s+/u', ' ', strip_tags($description)) ?? '');
        }
        return mb_substr($this->description, 0, 300);
    }

    public function canonical(?string $url = null): string
    {
        if ($url !== null) {
            $this->canonical = str_starts_with($url, 'http') ? $url : rtrim($this->siteUrl, '/') . '/' . ltrim($url, '/');
        }
        return $this->canonical;
    }

    public function ogImage(?string $image = null): string
    {
        if ($image !== null && $image !== '') {
            $this->ogImage = str_starts_with($image, 'http') ? $image : rtrim($this->siteUrl, '/') . '/' . ltrim($image, '/');
        }
        return $this->ogImage;
    }

    public function ogType(string $type): void
    {
        $this->ogType = $type;
    }

    public function noindex(bool $flag = true): void
    {
        $this->noindex = $flag;
    }

    public function isNoindex(): bool
    {
        return $this->noindex;
    }

    public function metaTag(string $name, string $content): void
    {
        if ($content !== '') {
            $this->meta[$name] = $content;
        }
    }

    /** @param array<int,array{label:string,url:string}> $crumbs */
    public function breadcrumbs(array $crumbs): void
    {
        $this->breadcrumbs = $crumbs;
    }

    /** @return array<int,array{label:string,url:string}> */
    public function getBreadcrumbs(): array
    {
        return $this->breadcrumbs;
    }

    public function addSchema(array $schema): void
    {
        $this->schemas[] = $schema;
    }

    public function absolute(string $path): string
    {
        return str_starts_with($path, 'http') ? $path : rtrim($this->siteUrl, '/') . '/' . ltrim($path, '/');
    }

    /** Render every head tag this page needs. */
    public function render(string $fallbackTitle, string $fallbackDescription, string $fallbackImage = ''): string
    {
        $e     = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
        $title = $this->fullTitle($fallbackTitle);
        $desc  = $this->description !== '' ? $this->description() : mb_substr(trim($fallbackDescription), 0, 300);
        $image = $this->ogImage !== '' ? $this->ogImage : ($fallbackImage !== '' ? $this->absolute($fallbackImage) : '');

        $out  = '<title>' . $e($title) . "</title>\n";
        $out .= '    <meta name="description" content="' . $e($desc) . "\">\n";
        if ($this->canonical !== '') {
            $out .= '    <link rel="canonical" href="' . $e($this->canonical) . "\">\n";
        }
        $out .= '    <meta name="robots" content="' . ($this->noindex ? 'noindex, nofollow' : 'index, follow, max-image-preview:large') . "\">\n";

        $out .= '    <meta property="og:type" content="' . $e($this->ogType) . "\">\n";
        $out .= '    <meta property="og:site_name" content="' . $e($this->siteName) . "\">\n";
        $out .= '    <meta property="og:title" content="' . $e($title) . "\">\n";
        $out .= '    <meta property="og:description" content="' . $e($desc) . "\">\n";
        if ($this->canonical !== '') {
            $out .= '    <meta property="og:url" content="' . $e($this->canonical) . "\">\n";
        }
        if ($image !== '') {
            $out .= '    <meta property="og:image" content="' . $e($image) . "\">\n";
            $out .= '    <meta name="twitter:image" content="' . $e($image) . "\">\n";
        }
        $out .= '    <meta name="twitter:card" content="' . ($image !== '' ? 'summary_large_image' : 'summary') . "\">\n";
        $out .= '    <meta name="twitter:title" content="' . $e($title) . "\">\n";
        $out .= '    <meta name="twitter:description" content="' . $e($desc) . "\">\n";

        foreach ($this->meta as $name => $content) {
            $out .= '    <meta name="' . $e($name) . '" content="' . $e($content) . "\">\n";
        }

        $graph = $this->schemas;
        if ($this->breadcrumbs !== []) {
            $items = [];
            foreach ($this->breadcrumbs as $i => $crumb) {
                $items[] = [
                    '@type'    => 'ListItem',
                    'position' => $i + 1,
                    'name'     => $crumb['label'],
                    'item'     => $this->absolute($crumb['url']),
                ];
            }
            $graph[] = ['@type' => 'BreadcrumbList', 'itemListElement' => $items];
        }
        if ($graph !== []) {
            // JSON_HEX_TAG turns every < and > into \u003C / \u003E. Inside a
            // <script> element a raw '<' is enough to trip the HTML tokenizer's
            // script-data-escaped state (via a '<!--' + '<script' sequence in
            // admin-authored content), so the angle brackets never make it out
            // literally. The </ replacement below is a second line of defence.
            $json = json_encode(
                ['@context' => 'https://schema.org', '@graph' => $graph],
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
                | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
            );
            if ($json !== false) {
                $out .= '    <script type="application/ld+json">' . str_replace('</', '<\/', $json) . "</script>\n";
            }
        }
        return $out;
    }
}
