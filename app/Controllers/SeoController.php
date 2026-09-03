<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Application;
use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Models\CaseStudy;
use App\Models\Product;
use App\Models\Resource;

final class SeoController extends Controller
{
    /* ------------------------------------------------------------------ */
    /* Machine-readable endpoints                                          */
    /* ------------------------------------------------------------------ */

    public function sitemap(Request $request): Response
    {
        $xml = $this->cache()->remember('sitemap', (int) $this->config->get('cache.ttl.sitemap', 3600), function (): string {
            $entries = [];
            $add = function (string $path, string $changefreq, string $priority, ?string $lastmod = null) use (&$entries): void {
                $entries[] = [
                    'loc' => $this->seo->absolute($path),
                    'changefreq' => $changefreq,
                    'priority' => $priority,
                    'lastmod' => $lastmod,
                ];
            };

            $add('/', 'weekly', '1.0');
            $add('/services', 'monthly', '0.9');
            $add('/solutions', 'monthly', '0.9');
            $add('/work', 'weekly', '0.8');
            $add('/process', 'monthly', '0.7');
            $add('/about', 'monthly', '0.7');
            $add('/pricing', 'monthly', '0.8');
            $add('/resources', 'weekly', '0.8');
            $add('/contact', 'monthly', '0.7');
            $add('/marketplace', 'daily', '0.9');
            $add('/marketplace/installer', 'monthly', '0.8');
            $add('/marketplace/licensing', 'monthly', '0.6');

            foreach ($this->config->get('solutions', []) as $solution) {
                $add('/solutions/' . $solution['slug'], 'monthly', '0.7');
            }
            foreach (array_keys(Product::CATEGORIES) as $category) {
                $add('/marketplace?category=' . $category, 'weekly', '0.6');
            }
            foreach ($this->db->select("SELECT slug, updated_at FROM products WHERE status = 'published'") as $row) {
                $add('/marketplace/' . $row['slug'], 'weekly', '0.7', (string) $row['updated_at']);
            }
            foreach ($this->db->select('SELECT slug, published_at FROM case_studies') as $row) {
                $add('/work/' . $row['slug'], 'monthly', '0.7', (string) $row['published_at']);
            }
            foreach ($this->db->select('SELECT slug, published_at FROM resources') as $row) {
                $add('/resources/' . $row['slug'], 'monthly', '0.6', (string) $row['published_at']);
            }
            foreach (['privacy', 'terms', 'security', 'accessibility'] as $doc) {
                $add('/legal/' . $doc, 'yearly', '0.3');
            }

            $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
                . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
            foreach ($entries as $entry) {
                $xml .= '  <url>' . "\n";
                $xml .= '    <loc>' . htmlspecialchars($entry['loc'], ENT_XML1) . '</loc>' . "\n";
                if ($entry['lastmod']) {
                    $date = date('Y-m-d', strtotime($entry['lastmod']) ?: time());
                    $xml .= '    <lastmod>' . $date . '</lastmod>' . "\n";
                }
                $xml .= '    <changefreq>' . $entry['changefreq'] . '</changefreq>' . "\n";
                $xml .= '    <priority>' . $entry['priority'] . '</priority>' . "\n";
                $xml .= '  </url>' . "\n";
            }
            return $xml . '</urlset>';
        });

        return Response::xml($xml)->cachePublic(3600);
    }

    public function robots(Request $request): Response
    {
        $indexable = (string) $this->db->value(
            "SELECT setting_value FROM settings WHERE setting_key = 'indexable'",
            [],
            '1'
        ) === '1';

        $lines = ['User-agent: *'];
        if ($indexable) {
            $lines[] = 'Allow: /';
            $lines[] = '';
            foreach (['/admin/', '/staff/', '/client/', '/install/', '/api/', '/marketplace/cart', '/marketplace/checkout', '/marketplace/order/'] as $path) {
                $lines[] = 'Disallow: ' . $path;
            }
            $lines[] = 'Disallow: /*?sort=';
            $lines[] = 'Disallow: /*?page=';
        } else {
            $lines[] = 'Disallow: /';
        }

        $lines[] = '';
        $lines[] = 'Sitemap: ' . $this->seo->absolute('/sitemap.xml');

        return Response::text(implode("\n", $lines) . "\n")->cachePublic(3600);
    }

    public function manifest(Request $request): Response
    {
        return Response::json([
            'name' => (string) $this->config->get('app.name', 'TECHBISS') . ' — ' . $this->config->get('app.tagline'),
            'short_name' => (string) $this->config->get('app.name', 'TECHBISS'),
            'description' => (string) $this->config->get('site.brand.positioning'),
            'start_url' => url('/'),
            'scope' => url('/'),
            'display' => 'standalone',
            'background_color' => '#06080d',
            'theme_color' => '#06080d',
            'orientation' => 'portrait-primary',
            'categories' => ['business', 'productivity', 'developer'],
            'icons' => [
                ['src' => asset('favicon.svg'), 'sizes' => 'any', 'type' => 'image/svg+xml', 'purpose' => 'any'],
            ],
            'shortcuts' => [
                ['name' => 'Marketplace', 'url' => url('/marketplace')],
                ['name' => 'Client portal', 'url' => url('/client/login')],
                ['name' => 'Contact', 'url' => url('/contact')],
            ],
        ])->withHeader('Content-Type', 'application/manifest+json; charset=UTF-8')->cachePublic(86400);
    }

    public function feed(Request $request): Response
    {
        $items = (new Resource($this->db))->all([], 20);
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">' . "\n"
            . '<channel>' . "\n"
            . '  <title>TECHBISS Insights</title>' . "\n"
            . '  <link>' . htmlspecialchars($this->seo->absolute('/resources'), ENT_XML1) . '</link>' . "\n"
            . '  <description>Field notes and playbooks on platform architecture, performance and delivery.</description>' . "\n"
            . '  <language>en</language>' . "\n"
            . '  <atom:link href="' . htmlspecialchars($this->seo->absolute('/feed.xml'), ENT_XML1) . '" rel="self" type="application/rss+xml"/>' . "\n";

        foreach ($items as $item) {
            $link = $this->seo->absolute('/resources/' . $item['slug']);
            $xml .= '  <item>' . "\n"
                . '    <title>' . htmlspecialchars((string) $item['title'], ENT_XML1) . '</title>' . "\n"
                . '    <link>' . htmlspecialchars($link, ENT_XML1) . '</link>' . "\n"
                . '    <guid isPermaLink="true">' . htmlspecialchars($link, ENT_XML1) . '</guid>' . "\n"
                . '    <description>' . htmlspecialchars((string) $item['excerpt'], ENT_XML1) . '</description>' . "\n"
                . '    <pubDate>' . date(DATE_RSS, strtotime((string) $item['published_at']) ?: time()) . '</pubDate>' . "\n"
                . '    <category>' . htmlspecialchars((string) $item['topic'], ENT_XML1) . '</category>' . "\n"
                . '  </item>' . "\n";
        }

        return Response::xml($xml . '</channel>' . "\n" . '</rss>')
            ->withHeader('Content-Type', 'application/rss+xml; charset=UTF-8')
            ->cachePublic(1800);
    }

    public function health(Request $request): Response
    {
        $database = 'ok';
        $tables = 0;
        try {
            $this->db->pdo();
            $tables = count($this->db->tables());
        } catch (\Throwable $e) {
            $database = 'error';
        }

        $healthy = $database === 'ok' && $tables > 0;

        return Response::json([
            'status' => $healthy ? 'ok' : 'degraded',
            'version' => Application::VERSION,
            'installed' => $this->app->isInstalled(),
            'database' => ['status' => $database, 'driver' => $this->db->driver(), 'tables' => $tables],
            'cache' => is_writable($this->app->path('storage/cache')) ? 'ok' : 'read-only',
            'time' => gmdate('c'),
        ], $healthy ? 200 : 503)->cachePrivate();
    }

    /* ------------------------------------------------------------------ */
    /* AMP variants                                                        */
    /* ------------------------------------------------------------------ */

    public function ampHome(Request $request): Response
    {
        $this->seo->title('TECHBISS — Digital transformation, engineered', true);
        $this->seo->description('TECHBISS designs, builds and operates the digital platforms regulated enterprises depend on.');
        $this->seo->isAmpFor('/');

        return $this->renderAmp('amp.home', [
            'request' => $request,
            'featured' => (new Product($this->db))->featured(3),
            'cases' => (new CaseStudy($this->db))->featured(2),
        ]);
    }

    public function ampServices(Request $request): Response
    {
        $this->seo->title('Services — TECHBISS', true);
        $this->seo->description('Six practices staffed by senior engineers who stay on the account.');
        $this->seo->isAmpFor('/services');

        return $this->renderAmp('amp.services', ['request' => $request]);
    }

    public function ampContact(Request $request): Response
    {
        $this->seo->title('Contact TECHBISS', true);
        $this->seo->description('Talk to an architect. We reply to every qualified enquiry within one business day.');
        $this->seo->isAmpFor('/contact');

        return $this->renderAmp('amp.contact', ['request' => $request]);
    }

    public function ampResource(Request $request, string $slug): Response
    {
        $item = (new Resource($this->db))->find($slug);
        if ($item === null) {
            throw HttpException::notFound('That article does not exist.');
        }

        $this->seo->title($item['title'] . ' — TECHBISS', true);
        $this->seo->description($item['excerpt']);
        $this->seo->isAmpFor('/resources/' . $slug);
        $this->seo->addSchema([
            '@type' => 'Article',
            'headline' => $item['title'],
            'description' => $item['excerpt'],
            'datePublished' => $item['published_at'],
            'author' => ['@type' => 'Person', 'name' => $item['author']],
            'publisher' => ['@type' => 'Organization', 'name' => 'TECHBISS'],
            'mainEntityOfPage' => $this->seo->absolute('/resources/' . $slug),
        ]);

        return $this->renderAmp('amp.resource', ['request' => $request, 'item' => $item]);
    }

    public function ampProduct(Request $request, string $slug): Response
    {
        $product = (new Product($this->db))->find($slug);
        if ($product === null) {
            throw HttpException::notFound('That product is not in the catalogue.');
        }

        $this->seo->title($product['name'] . ' — TECHBISS Marketplace', true);
        $this->seo->description($product['tagline']);
        $this->seo->isAmpFor('/marketplace/' . $slug);
        $this->seo->addSchema([
            '@type' => 'Product',
            'name' => $product['name'],
            'description' => $product['tagline'],
            'brand' => ['@type' => 'Brand', 'name' => 'TECHBISS'],
            'offers' => [
                '@type' => 'Offer',
                'price' => (float) $product['price'],
                'priceCurrency' => $product['currency'],
                'availability' => 'https://schema.org/InStock',
                'url' => $this->seo->absolute('/marketplace/' . $slug),
            ],
        ]);

        return $this->renderAmp('amp.product', ['request' => $request, 'product' => $product]);
    }

    /** AMP responses skip the standard layout entirely — no external CSS or JS. */
    private function renderAmp(string $view, array $data): Response
    {
        $this->app->shareViewGlobals($this->view, $data['request']);
        return Response::html($this->view->render($view, $data))
            ->cachePublic((int) $this->config->get('app.page_cache_ttl', 600));
    }
}
