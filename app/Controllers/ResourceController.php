<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Models\Resource;

final class ResourceController extends Controller
{
    public function index(Request $request): Response
    {
        $this->seo->title('Insights and playbooks');
        $this->seo->description('Field notes, playbooks and essays on platform architecture, performance, delivery and design — written by the people doing the work.');
        $this->seo->canonical('/resources');
        $this->seo->breadcrumbs(['Home' => '/', 'Resources' => '/resources']);

        $resources = new Resource($this->db);
        $filters = array_filter([
            'topic' => $request->str('topic'),
            'type' => $request->str('type'),
        ]);

        $items = $resources->all($filters, 24);

        $this->seo->addSchema([
            '@type' => 'CollectionPage',
            'name' => 'TECHBISS Insights',
            'url' => $this->seo->absolute('/resources'),
            'hasPart' => array_map(fn (array $r): array => [
                '@type' => 'Article',
                'headline' => $r['title'],
                'url' => $this->seo->absolute('/resources/' . $r['slug']),
                'datePublished' => $r['published_at'],
            ], array_slice($items, 0, 10)),
        ]);

        return $this->render('pages.resources', [
            'request' => $request,
            'items' => $items,
            'topics' => $resources->topics(),
            'types' => $resources->types(),
            'filters' => $filters,
            'total' => $resources->count($filters),
        ])->cachePublic((int) $this->config->get('app.page_cache_ttl', 600));
    }

    public function show(Request $request, string $slug): Response
    {
        $resources = new Resource($this->db);
        $item = $resources->find($slug);
        if ($item === null) {
            throw HttpException::notFound('That article does not exist.');
        }

        $this->seo->title($item['title']);
        $this->seo->description($item['excerpt']);
        $this->seo->canonical('/resources/' . $slug);
        $this->seo->amp('/amp/resources/' . $slug);
        $this->seo->type('article');
        $this->seo->breadcrumbs(['Home' => '/', 'Resources' => '/resources', $item['title'] => '/resources/' . $slug]);
        $this->seo->addSchema([
            '@type' => 'Article',
            'headline' => $item['title'],
            'description' => $item['excerpt'],
            'datePublished' => $item['published_at'],
            'dateModified' => $item['published_at'],
            'wordCount' => str_word_count(strip_tags((string) $item['body'])),
            'timeRequired' => 'PT' . (int) $item['read_minutes'] . 'M',
            'author' => ['@type' => 'Person', 'name' => $item['author'], 'jobTitle' => $item['author_role']],
            'publisher' => [
                '@type' => 'Organization',
                'name' => $this->config->get('app.name', 'TECHBISS'),
                'url' => $this->seo->absolute('/'),
            ],
            'mainEntityOfPage' => $this->seo->absolute('/resources/' . $slug),
        ]);

        return $this->render('pages.resource', [
            'request' => $request,
            'item' => $item,
            'related' => $resources->related($slug, (string) $item['topic'], 3),
        ])->cachePublic((int) $this->config->get('app.page_cache_ttl', 600));
    }
}
