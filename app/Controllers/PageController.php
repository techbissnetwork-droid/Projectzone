<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Models\CaseStudy;
use App\Models\Product;

final class PageController extends Controller
{
    /** Organisation + WebSite nodes shared by every marketing page. */
    private function baseSchema(): void
    {
        $site = $this->config->get('site', []);
        $brand = $site['brand'] ?? [];

        $this->seo->addSchema([
            '@type' => 'Organization',
            '@id' => $this->seo->absolute('/#organization'),
            'name' => $this->config->get('app.name', 'TECHBISS'),
            'legalName' => $this->config->get('app.legal_name'),
            'url' => $this->seo->absolute('/'),
            'description' => $brand['positioning'] ?? '',
            'foundingDate' => (string) ($brand['founded'] ?? ''),
            'email' => $brand['email'] ?? '',
            'telephone' => $brand['phone'] ?? '',
            'sameAs' => array_column($brand['social'] ?? [], 'url'),
            'address' => array_map(static fn (array $o): array => [
                '@type' => 'PostalAddress',
                'addressLocality' => $o['city'],
                'streetAddress' => $o['address'],
            ], $site['offices'] ?? []),
        ]);

        $this->seo->addSchema([
            '@type' => 'WebSite',
            '@id' => $this->seo->absolute('/#website'),
            'url' => $this->seo->absolute('/'),
            'name' => $this->config->get('app.name', 'TECHBISS'),
            'publisher' => ['@id' => $this->seo->absolute('/#organization')],
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => [
                    '@type' => 'EntryPoint',
                    'urlTemplate' => $this->seo->absolute('/marketplace?q={search_term_string}'),
                ],
                'query-input' => 'required name=search_term_string',
            ],
        ]);
    }

    public function home(Request $request): Response
    {
        $this->seo->title('Digital transformation, engineered', true);
        $this->seo->title('TECHBISS — Digital transformation, engineered', true);
        $this->seo->description('TECHBISS designs, builds and operates the digital platforms regulated enterprises depend on. Architecture, delivery and a marketplace of deploy-ready products.');
        $this->seo->canonical('/');
        $this->seo->amp('/amp');
        $this->seo->keywords(['digital transformation', 'platform engineering', 'enterprise software', 'website marketplace']);
        $this->baseSchema();

        $products = new Product($this->db);
        $cases = new CaseStudy($this->db);

        return $this->render('pages.home', [
            'request' => $request,
            'featured' => $products->featured(3),
            'cases' => $cases->featured(3),
        ])->cachePublic((int) $this->config->get('app.page_cache_ttl', 600));
    }

    public function services(Request $request): Response
    {
        $this->seo->title('Services');
        $this->seo->description('Six practices — platform engineering, product design, web and commerce, data and AI, cloud and DevSecOps, growth engineering — staffed by senior engineers who stay on the account.');
        $this->seo->canonical('/services');
        $this->seo->amp('/amp/services');
        $this->seo->breadcrumbs(['Home' => '/', 'Services' => '/services']);
        $this->baseSchema();

        foreach ($this->config->get('site.services', []) as $service) {
            $this->seo->addSchema([
                '@type' => 'Service',
                'name' => $service['name'],
                'description' => $service['lede'],
                'serviceType' => $service['name'],
                'provider' => ['@id' => $this->seo->absolute('/#organization')],
                'areaServed' => ['Americas', 'Europe', 'Middle East', 'Asia Pacific'],
            ]);
        }

        return $this->render('pages.services', ['request' => $request])
            ->cachePublic((int) $this->config->get('app.page_cache_ttl', 600));
    }

    public function solutions(Request $request): Response
    {
        $this->seo->title('Solutions by industry');
        $this->seo->description('Reference architectures and accelerators for financial services, healthcare, retail, logistics, SaaS and the public sector — tuned to each sector’s regulation and scale.');
        $this->seo->canonical('/solutions');
        $this->seo->breadcrumbs(['Home' => '/', 'Solutions' => '/solutions']);
        $this->baseSchema();

        return $this->render('pages.solutions', [
            'request' => $request,
            'solutions' => $this->config->get('solutions', []),
        ])->cachePublic((int) $this->config->get('app.page_cache_ttl', 600));
    }

    public function solution(Request $request, string $slug): Response
    {
        $solutions = $this->config->get('solutions', []);
        $solution = null;
        foreach ($solutions as $candidate) {
            if ($candidate['slug'] === $slug) {
                $solution = $candidate;
                break;
            }
        }
        if ($solution === null) {
            throw HttpException::notFound('That solution does not exist.');
        }

        $this->seo->title($solution['name'] . ' solutions');
        $this->seo->description($solution['summary']);
        $this->seo->canonical('/solutions/' . $slug);
        $this->seo->breadcrumbs(['Home' => '/', 'Solutions' => '/solutions', $solution['name'] => '/solutions/' . $slug]);
        $this->baseSchema();
        $this->seo->addSchema([
            '@type' => 'Service',
            'name' => 'TECHBISS for ' . $solution['name'],
            'description' => $solution['summary'],
            'provider' => ['@id' => $this->seo->absolute('/#organization')],
            'audience' => ['@type' => 'BusinessAudience', 'audienceType' => $solution['name']],
        ]);

        $proof = (new CaseStudy($this->db))->find((string) ($solution['proof'] ?? ''));

        return $this->render('pages.solution', [
            'request' => $request,
            'solution' => $solution,
            'proof' => $proof,
            'others' => array_values(array_filter($solutions, static fn ($s) => $s['slug'] !== $slug)),
        ])->cachePublic((int) $this->config->get('app.page_cache_ttl', 600));
    }

    public function work(Request $request): Response
    {
        $this->seo->title('Work and case studies');
        $this->seo->description('Measured outcomes from platform replacements, storefront replatforms and citizen services — including the numbers, the constraints and what we would do differently.');
        $this->seo->canonical('/work');
        $this->seo->breadcrumbs(['Home' => '/', 'Work' => '/work']);
        $this->baseSchema();

        $cases = new CaseStudy($this->db);
        $industry = $request->str('industry');

        return $this->render('pages.work', [
            'request' => $request,
            'cases' => $cases->all($industry !== '' ? ['industry' => $industry] : []),
            'industries' => $cases->industries(),
            'activeIndustry' => $industry,
        ])->cachePublic((int) $this->config->get('app.page_cache_ttl', 600));
    }

    public function caseStudy(Request $request, string $slug): Response
    {
        $cases = new CaseStudy($this->db);
        $case = $cases->find($slug);
        if ($case === null) {
            throw HttpException::notFound('That case study does not exist.');
        }

        $this->seo->title($case['title']);
        $this->seo->description($case['summary']);
        $this->seo->canonical('/work/' . $slug);
        $this->seo->type('article');
        $this->seo->breadcrumbs(['Home' => '/', 'Work' => '/work', $case['client'] => '/work/' . $slug]);
        $this->baseSchema();
        $this->seo->addSchema([
            '@type' => 'Article',
            'headline' => $case['title'],
            'description' => $case['summary'],
            'datePublished' => $case['published_at'],
            'author' => ['@id' => $this->seo->absolute('/#organization')],
            'publisher' => ['@id' => $this->seo->absolute('/#organization')],
            'about' => $case['industry'],
        ]);

        return $this->render('pages.case-study', [
            'request' => $request,
            'case' => $case,
            'related' => $cases->related($slug, (string) $case['industry'], 2),
        ])->cachePublic((int) $this->config->get('app.page_cache_ttl', 600));
    }

    public function process(Request $request): Response
    {
        $this->seo->title('How we work');
        $this->seo->description('Six phases from align to operate, with the deliverables, durations and quality gates written down — the same process on every engagement.');
        $this->seo->canonical('/process');
        $this->seo->breadcrumbs(['Home' => '/', 'Process' => '/process']);
        $this->baseSchema();

        $steps = $this->config->get('site.process', []);
        $this->seo->addSchema([
            '@type' => 'HowTo',
            'name' => 'The TECHBISS delivery process',
            'description' => 'How a TECHBISS engagement runs from alignment through to operations.',
            'step' => array_map(static fn (array $s, int $i): array => [
                '@type' => 'HowToStep',
                'position' => $i + 1,
                'name' => $s['name'],
                'text' => $s['body'],
            ], $steps, array_keys($steps)),
        ]);

        return $this->render('pages.process', ['request' => $request, 'steps' => $steps])
            ->cachePublic((int) $this->config->get('app.page_cache_ttl', 600));
    }

    public function about(Request $request): Response
    {
        $this->seo->title('About TECHBISS');
        $this->seo->description('A distributed engineering firm operating across four regions under a single delivery standard, with published reliability numbers and security run in-house.');
        $this->seo->canonical('/about');
        $this->seo->breadcrumbs(['Home' => '/', 'Company' => '/about']);
        $this->baseSchema();

        return $this->render('pages.about', ['request' => $request])
            ->cachePublic((int) $this->config->get('app.page_cache_ttl', 600));
    }

    public function pricing(Request $request): Response
    {
        $this->seo->title('Engagement models and pricing');
        $this->seo->description('Fixed-price discovery, fixed-price increments, and marketplace licences. What each model includes, what it costs and when to choose it.');
        $this->seo->canonical('/pricing');
        $this->seo->breadcrumbs(['Home' => '/', 'Pricing' => '/pricing']);
        $this->baseSchema();

        $faqs = $this->config->get('site.faqs', []);
        $this->seo->addSchema([
            '@type' => 'FAQPage',
            'mainEntity' => array_map(static fn (array $f): array => [
                '@type' => 'Question',
                'name' => $f['q'],
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f['a']],
            ], $faqs),
        ]);

        return $this->render('pages.pricing', ['request' => $request, 'faqs' => $faqs])
            ->cachePublic((int) $this->config->get('app.page_cache_ttl', 600));
    }

    public function legal(Request $request, string $slug): Response
    {
        $documents = require $this->app->path('config/legal.php');
        if (!isset($documents[$slug])) {
            throw HttpException::notFound('That document does not exist.');
        }
        $document = $documents[$slug];

        $this->seo->title($document['title']);
        $this->seo->description($document['summary']);
        $this->seo->canonical('/legal/' . $slug);
        $this->seo->breadcrumbs(['Home' => '/', 'Legal' => '/legal/privacy', $document['title'] => '/legal/' . $slug]);

        return $this->render('pages.legal', [
            'request' => $request,
            'document' => $document,
            'documents' => $documents,
            'slug' => $slug,
        ])->cachePublic(3600);
    }
}
