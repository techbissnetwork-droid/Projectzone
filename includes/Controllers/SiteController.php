<?php
declare(strict_types=1);

namespace Techbiss\Controllers;

use Techbiss\Core\App;
use Techbiss\Core\Csrf;
use Techbiss\Core\Paginator;
use Techbiss\Core\Request;
use Techbiss\Core\Session;
use Techbiss\Core\Str;
use Techbiss\Core\Validator;
use Techbiss\Core\View;
use Techbiss\Repo\BlogRepo;
use Techbiss\Repo\CustomerRepo;
use Techbiss\Repo\FaqRepo;
use Techbiss\Repo\IndustryRepo;
use Techbiss\Repo\LeadRepo;
use Techbiss\Repo\NavigationRepo;
use Techbiss\Repo\PageRepo;
use Techbiss\Repo\PortfolioRepo;
use Techbiss\Repo\ProcessRepo;
use Techbiss\Repo\ProjectRepo;
use Techbiss\Repo\SectionRepo;
use Techbiss\Repo\ServiceRepo;
use Techbiss\Repo\StatRepo;
use Techbiss\Repo\TestimonialRepo;

/**
 * Every public page. Each action gathers data from the repositories and hands
 * it to a template — no SQL and no business rules live in the views.
 */
final class SiteController
{
    private View $view;

    public function __construct()
    {
        $this->view = new View(App::root() . '/pages', App::root() . '/pages/layouts/base.php');

        $settings = App::settings();
        $nav      = new NavigationRepo();

        $this->view->shareMany([
            'settings'    => $settings,
            'seo'         => App::seo(),
            'primaryNav'  => $nav->tree('primary'),
            'footerNav'   => $nav->tree('footer'),
            'legalNav'    => $nav->tree('legal'),
            'socialLinks' => $settings->socialLinks(),
            'flash'       => App::flashMessages(),
            'currentPath' => App::currentPath(),
        ]);
    }

    // =================================================================
    // Home
    // =================================================================
    public function home(Request $request): void
    {
        $sections = (new SectionRepo())->forPage('home');
        $settings = App::settings();

        $seo = App::seo();
        $seo->title($settings->get('seo_default_title', 'TECHBISS — Your Digital Business Starts Here'));
        $seo->description($settings->get('seo_default_description'));
        $seo->canonical(absolute_url('/'));
        $seo->ogImage($settings->get('seo_og_image'));
        $seo->addSchema([
            '@type'       => 'Organization',
            '@id'         => absolute_url('/') . '#organization',
            'name'        => $settings->get('site_name', 'TECHBISS'),
            'url'         => absolute_url('/'),
            'description' => $settings->get('seo_default_description'),
            'slogan'      => $settings->get('tagline'),
            'email'       => $settings->get('contact_email'),
            'telephone'   => $settings->get('contact_phone'),
            'sameAs'      => array_column($settings->socialLinks(), 'url'),
        ]);
        $seo->addSchema([
            '@type'           => 'WebSite',
            '@id'             => absolute_url('/') . '#website',
            'url'             => absolute_url('/'),
            'name'            => $settings->get('site_name', 'TECHBISS'),
            'publisher'       => ['@id' => absolute_url('/') . '#organization'],
            'inLanguage'      => 'en',
        ]);

        $this->view->render('home', [
            'sections'     => $sections,
            'services'     => (new ServiceRepo())->featured(6),
            'projects'     => (new PortfolioRepo())->featured(3),
            'industries'   => (new IndustryRepo())->featured(8),
            'testimonials' => (new TestimonialRepo())->publishedWithProject(3),
            'stats'        => (new StatRepo())->published(4),
            'bodyClass'    => 'page-home',
        ]);
    }

    // =================================================================
    // Services
    // =================================================================
    public function services(Request $request): void
    {
        $repo     = new ServiceRepo();
        $services = $repo->published();

        $seo = App::seo();
        $seo->title('Services — Everything Your Business Needs Online');
        $seo->description('Domain and hosting, business websites, web and mobile applications, business email, branding, e-commerce, SEO, automation and maintenance — delivered by one partner.');
        $seo->canonical(absolute_url('/services'));
        $seo->breadcrumbs([['label' => 'Home', 'url' => '/'], ['label' => 'Services', 'url' => '/services']]);

        foreach ($services as &$s) {
            $s['features'] = $repo->features((int) $s['id']);
        }
        unset($s);

        $this->view->render('services', [
            'services' => $services,
        ]);
    }

    public function serviceDetail(Request $request, array $params): void
    {
        $repo    = new ServiceRepo();
        $service = $repo->publishedBySlug((string) $params['slug']);
        if ($service === null) {
            $this->notFound($request);
            return;
        }

        $seo = App::seo();
        $seo->title($service['seo_title'] !== '' ? $service['seo_title'] : $service['name'] . ' Services');
        $seo->description($service['seo_description'] !== '' ? $service['seo_description'] : $service['short_description']);
        $seo->canonical(absolute_url('/services/' . $service['slug']));
        $seo->ogImage($service['og_image'] !== '' ? $service['og_image'] : $service['image']);
        $seo->breadcrumbs([
            ['label' => 'Home', 'url' => '/'],
            ['label' => 'Services', 'url' => '/services'],
            ['label' => (string) $service['name'], 'url' => '/services/' . $service['slug']],
        ]);
        $seo->addSchema([
            '@type'       => 'Service',
            'name'        => $service['name'],
            'description' => $service['short_description'],
            'provider'    => ['@type' => 'Organization', 'name' => App::settings()->get('site_name', 'TECHBISS')],
            'areaServed'  => App::settings()->get('country') ?: 'Worldwide',
        ]);

        $this->view->render('service-detail', [
            'service'   => $service,
            'features'  => $repo->features((int) $service['id']),
            // Both of these used to ignore the service being viewed: the same
            // three "related" services and the same three featured projects
            // appeared on all ten pages, which is worse than showing neither.
            'related'   => $repo->pairedWith((int) $service['id'], 3),
            'projects'  => (new PortfolioRepo())->forService((int) $service['id'], 3),
            'steps'     => (new ProcessRepo())->published(6),
        ]);
    }

    // =================================================================
    // Packages
    // =================================================================
    // =================================================================
    // Request — the one way to ask for work
    // =================================================================

    /**
     * One page: tick what you need, add anything missing, send it.
     *
     * There are no packages and no published prices. The visitor builds a
     * short message here and it is handed to WhatsApp or their email client,
     * where the figure is agreed. A copy is kept so a request is not lost if
     * the message never gets sent, but nothing is paid for here and there is
     * no step between choosing and talking to a person.
     */
    public function request(Request $request): void
    {
        if ($request->isPost()) {
            $this->handleRequest($request);
            return;
        }

        $seo = App::seo();
        $seo->title('Tell Us What You Need');
        $seo->description('Tick what you want built, add anything missing, and send it on WhatsApp or by email. We reply with a price.');
        $seo->canonical(absolute_url('/request'));
        $seo->breadcrumbs([['label' => 'Home', 'url' => '/'], ['label' => 'Request', 'url' => '/request']]);

        $this->renderRequest($request);
    }

    /** @param array<string,mixed> $ready */
    private function renderRequest(Request $request, array $ready = []): void
    {
        $this->view->render('request', [
            'services'  => (new ServiceRepo())->published(),
            'preselect' => Str::slugFilter($request->queryString('service')),
            'ready'     => $ready,
        ]);
    }

    /**
     * Turn the ticked boxes into a sentence and hand it to the chosen channel.
     *
     * The page comes back with the finished message and a link to open it,
     * rather than redirecting straight out: a form submission that redirects
     * to another origin is blocked by the site's own form-action policy, and
     * weakening that policy to save one click is not a trade worth making.
     * Anyone with JavaScript never sees the step — the link opens itself.
     */
    private function handleRequest(Request $request): void
    {
        Csrf::verify($request);

        $names = (new ServiceRepo())->published();
        $bySlug = [];
        foreach ($names as $service) {
            $bySlug[(string) $service['slug']] = (string) $service['name'];
        }

        $picked = [];
        foreach ($request->arr('services') as $slug) {
            if (isset($bySlug[$slug])) {
                $picked[] = $bySlug[$slug];
            }
        }

        $message = self::requestMessage(
            mb_substr($request->str('name'), 0, 120),
            mb_substr($request->str('business_name'), 0, 190),
            $picked,
            mb_substr($request->str('details'), 0, 2000),
            mb_substr($request->str('reply_to'), 0, 190)
        );

        $via = $request->str('via') === 'email' ? 'email' : 'whatsapp';
        $to  = $via === 'email'
            ? email_link('Request from the website', $message)
            : whatsapp_link($message);

        if ($to === '') {
            // The channel is not configured, so there is nowhere to send them.
            flash('error', 'That channel is not set up yet. Please use the contact page.');
            redirect('/contact');
        }

        $this->recordRequest($request, $picked, $via);

        $this->renderRequest($request, [
            'link'    => $to,
            'channel' => $via,
            'message' => $message,
        ]);
    }

    /**
     * Keep a copy of the request.
     *
     * The conversation moves to WhatsApp or email from here, and either can
     * fail to open. A row costs the visitor nothing and means the enquiry is
     * still in the admin if the message never arrives. A failure to write it
     * must never block the redirect — being sent to the chat is the thing the
     * visitor asked for.
     *
     * @param array<int,string> $picked
     */
    private function recordRequest(Request $request, array $picked, string $via): void
    {
        try {
            // One field takes either, because asking for the right one is a
            // question nobody wants at this point. Whichever it is, it is the
            // only way to answer someone whose WhatsApp never opened.
            $replyTo = mb_substr($request->str('reply_to'), 0, 190);
            $isEmail = filter_var($replyTo, FILTER_VALIDATE_EMAIL) !== false;

            (new LeadRepo())->createQuote([
                'source'          => 'quote',
                'name'            => mb_substr($request->str('name'), 0, 190),
                'business_name'   => mb_substr($request->str('business_name'), 0, 190),
                'email'           => $isEmail ? $replyTo : '',
                'phone'           => $isEmail ? '' : $replyTo,
                'country'         => '',
                'website'         => '',
                'business_stage'  => '',
                'services_needed' => implode(', ', $picked),
                'budget_range'    => '',
                'timeline'        => '',
                'project_details' => mb_substr($request->str('details'), 0, 2000),
                'status'          => 'new',
                'priority'        => 'normal',
                'ip_address'      => $request->ip(),
                'user_agent'      => $request->userAgent(),
            ]);
        } catch (\Throwable) {
            // Deliberately silent: the visitor is on their way to the chat.
        }
    }

    /** The message both channels send, so the two never drift apart. */
    public static function requestMessage(string $name, string $business, array $picked, string $details, string $replyTo = ''): string
    {
        $who = trim($name . ($business !== '' ? ' (' . $business . ')' : ''));

        $lines = ['Hi ' . App::settings()->get('site_name', 'TECHBISS') . ','];
        if ($who !== '') {
            $lines[] = 'I am ' . $who . '.';
        }
        if ($picked !== []) {
            $lines[] = 'I need: ' . implode(', ', $picked) . '.';
        }
        if ($details !== '') {
            $lines[] = $details;
        }
        if ($replyTo !== '') {
            $lines[] = 'You can also reach me on ' . $replyTo . '.';
        }
        $lines[] = 'Please send me a price.';

        return implode("\n", $lines);
    }

    // =================================================================
    // Portfolio
    // =================================================================
    public function portfolio(Request $request): void
    {
        $repo    = new PortfolioRepo();
        $perPage = max(3, App::settings()->int('items_per_page', 9));
        $page    = max(1, $request->queryInt('page', 1));
        $cat     = Str::slugFilter($request->queryString('category'));
        $ind     = Str::slugFilter($request->queryString('industry'));
        $search  = mb_substr($request->queryString('q'), 0, 80);

        $result    = $repo->paginate($page, $perPage, $cat, $ind, $search);
        $paginator = new Paginator($page, $perPage, $result['total']);

        $seo = App::seo();
        $seo->title('Our Work — Selected Projects & Case Studies');
        $seo->description('Websites, web applications, mobile apps, commerce platforms and brand systems built by TECHBISS.');
        $seo->canonical(absolute_url('/portfolio'));
        $seo->breadcrumbs([['label' => 'Home', 'url' => '/'], ['label' => 'Work', 'url' => '/portfolio']]);
        if ($page > 1) {
            $seo->noindex(true);
        }

        $this->view->render('portfolio', [
            'projects'   => $result['items'],
            'paginator'  => $paginator,
            'categories' => $repo->activeCategories(),
            'industries' => (new IndustryRepo())->options(),
            'activeCat'  => $cat,
            'activeInd'  => $ind,
            'search'     => $search,
        ]);
    }

    public function portfolioDetail(Request $request, array $params): void
    {
        $repo    = new PortfolioRepo();
        $project = $repo->publishedBySlug((string) $params['slug']);
        if ($project === null) {
            $this->notFound($request);
            return;
        }
        $repo->incrementViews((int) $project['id']);

        $seo = App::seo();
        $seo->title($project['seo_title'] !== '' ? $project['seo_title'] : $project['title'] . ' — Case Study');
        $seo->description($project['seo_description'] !== '' ? $project['seo_description'] : $project['short_description']);
        $seo->canonical(absolute_url('/portfolio/' . $project['slug']));
        $seo->ogImage($project['og_image'] !== '' ? $project['og_image'] : ($project['hero_image'] !== '' ? $project['hero_image'] : $project['thumbnail']));
        $seo->ogType('article');
        $seo->breadcrumbs([
            ['label' => 'Home', 'url' => '/'],
            ['label' => 'Work', 'url' => '/portfolio'],
            ['label' => (string) $project['title'], 'url' => '/portfolio/' . $project['slug']],
        ]);
        $seo->addSchema([
            '@type'       => 'CreativeWork',
            'name'        => $project['title'],
            'description' => $project['short_description'],
            'creator'     => ['@type' => 'Organization', 'name' => App::settings()->get('site_name', 'TECHBISS')],
        ]);

        $this->view->render('portfolio-detail', [
            'project'      => $project,
            'images'       => $repo->images((int) $project['id']),
            'technologies' => $repo->technologyNames((int) $project['id']),
            'servicesUsed' => (new ServiceRepo())->forPortfolio((int) $project['id']),
            'related'      => $repo->related($project, 3),
            'testimonial'  => $this->testimonialForProject((int) $project['id']),
        ]);
    }

    private function testimonialForProject(int $projectId): ?array
    {
        foreach ((new TestimonialRepo())->publishedWithProject() as $t) {
            if ((int) ($t['portfolio_id'] ?? 0) === $projectId) {
                return $t;
            }
        }
        return null;
    }

    // =================================================================
    // Industries
    // =================================================================
    public function industries(Request $request): void
    {
        $seo = App::seo();
        $seo->title('Industries — Built Around How Your Sector Works');
        $seo->description('Restaurants, hotels, retail, education, healthcare, real estate and more. Digital foundations designed around the way each sector actually operates.');
        $seo->canonical(absolute_url('/industries'));
        $seo->breadcrumbs([['label' => 'Home', 'url' => '/'], ['label' => 'Industries', 'url' => '/industries']]);

        $this->view->render('industries', [
            'industries' => (new IndustryRepo())->published(),
        ]);
    }

    public function industryDetail(Request $request, array $params): void
    {
        $repo     = new IndustryRepo();
        $industry = $repo->publishedBySlug((string) $params['slug']);
        if ($industry === null) {
            $this->notFound($request);
            return;
        }

        $seo = App::seo();
        $seo->title($industry['seo_title'] !== '' ? $industry['seo_title'] : 'Digital Solutions for ' . $industry['name']);
        $seo->description($industry['seo_description'] !== '' ? $industry['seo_description'] : $industry['short_description']);
        $seo->canonical(absolute_url('/industries/' . $industry['slug']));
        $seo->ogImage($industry['og_image'] !== '' ? $industry['og_image'] : $industry['image']);
        $seo->breadcrumbs([
            ['label' => 'Home', 'url' => '/'],
            ['label' => 'Industries', 'url' => '/industries'],
            ['label' => (string) $industry['name'], 'url' => '/industries/' . $industry['slug']],
        ]);

        $portfolio = new PortfolioRepo();
        $projects  = $portfolio->paginate(1, 3, '', (string) $industry['slug']);

        $this->view->render('industry-detail', [
            'industry' => $industry,
            'services' => (new ServiceRepo())->forIndustry((int) $industry['id']),
            'projects' => $projects['items'],
            'others'   => array_slice(array_filter($repo->published(), static fn ($i) => (int) $i['id'] !== (int) $industry['id']), 0, 6),
        ]);
    }

    // =================================================================
    // Editorial
    // =================================================================
    public function howItWorks(Request $request): void
    {
        $seo = App::seo();
        $seo->title('How It Works — Six Stages From Offline to Online');
        $seo->description('Tell us about your business, choose a setup, build the foundation, design the presence, launch, then grow. A defined process with a schedule you see before we start.');
        $seo->canonical(absolute_url('/how-it-works'));
        $seo->breadcrumbs([['label' => 'Home', 'url' => '/'], ['label' => 'How It Works', 'url' => '/how-it-works']]);

        $this->view->render('how-it-works', [
            'steps' => (new ProcessRepo())->published(),
        ]);
    }

    public function testimonials(Request $request): void
    {
        $seo = App::seo();
        $seo->title('Testimonials — What Clients Say');
        $seo->description('Feedback from businesses TECHBISS has taken from offline operations to a professional digital presence.');
        $seo->canonical(absolute_url('/testimonials'));
        $seo->breadcrumbs([['label' => 'Home', 'url' => '/'], ['label' => 'Testimonials', 'url' => '/testimonials']]);

        $this->view->render('testimonials', [
            'testimonials' => (new TestimonialRepo())->publishedWithProject(),
        ]);
    }

    public function faqs(Request $request): void
    {
        $grouped = (new FaqRepo())->grouped();

        $seo = App::seo();
        $seo->title('Frequently Asked Questions');
        $seo->description('Answers about getting started, pricing, ownership, technical support, SEO and working with TECHBISS.');
        $seo->canonical(absolute_url('/faqs'));
        $seo->breadcrumbs([['label' => 'Home', 'url' => '/'], ['label' => 'FAQs', 'url' => '/faqs']]);

        $entities = [];
        foreach ($grouped as $items) {
            foreach ($items as $faq) {
                $entities[] = [
                    '@type'          => 'Question',
                    'name'           => $faq['question'],
                    'acceptedAnswer' => ['@type' => 'Answer', 'text' => strip_tags((string) $faq['answer'])],
                ];
            }
        }
        if ($entities !== []) {
            $seo->addSchema(['@type' => 'FAQPage', 'mainEntity' => $entities]);
        }

        $this->view->render('faqs', ['grouped' => $grouped]);
    }

    // =================================================================
    // Blog
    // =================================================================
    public function blog(Request $request): void
    {
        $repo    = new BlogRepo();
        $perPage = max(3, App::settings()->int('items_per_page', 9));
        $page    = max(1, $request->queryInt('page', 1));
        $cat     = Str::slugFilter($request->queryString('category'));
        $tag     = Str::slugFilter($request->queryString('tag'));
        $search  = mb_substr($request->queryString('q'), 0, 80);

        $result    = $repo->paginate($page, $perPage, $cat, $tag, $search);
        $paginator = new Paginator($page, $perPage, $result['total']);

        $seo = App::seo();
        $seo->title('Blog — Guides on Taking a Business Digital');
        $seo->description('Practical writing on domains, hosting, websites, business email, SEO, e-commerce and automation for businesses moving online.');
        $seo->canonical(absolute_url('/blog'));
        $seo->breadcrumbs([['label' => 'Home', 'url' => '/'], ['label' => 'Blog', 'url' => '/blog']]);
        if ($page > 1) {
            $seo->noindex(true);
        }

        $this->view->render('blog', [
            'posts'      => $result['items'],
            'paginator'  => $paginator,
            'categories' => $repo->activeCategories(),
            'activeCat'  => $cat,
            'activeTag'  => $tag,
            'search'     => $search,
        ]);
    }

    public function blogDetail(Request $request, array $params): void
    {
        $repo = new BlogRepo();
        $post = $repo->publishedBySlug((string) $params['slug']);
        if ($post === null) {
            $this->notFound($request);
            return;
        }
        $repo->incrementViews((int) $post['id']);

        $seo = App::seo();
        $seo->title($post['seo_title'] !== '' ? $post['seo_title'] : (string) $post['title']);
        $seo->description($post['seo_description'] !== '' ? $post['seo_description'] : (string) $post['excerpt']);
        $seo->canonical(absolute_url('/blog/' . $post['slug']));
        $seo->ogImage($post['og_image'] !== '' ? $post['og_image'] : (string) $post['featured_image']);
        $seo->ogType('article');
        $seo->breadcrumbs([
            ['label' => 'Home', 'url' => '/'],
            ['label' => 'Blog', 'url' => '/blog'],
            ['label' => (string) $post['title'], 'url' => '/blog/' . $post['slug']],
        ]);
        $seo->addSchema([
            '@type'         => 'BlogPosting',
            'headline'      => $post['title'],
            'description'   => $post['excerpt'],
            'datePublished' => $post['published_at'] ? date('c', (int) strtotime((string) $post['published_at'])) : null,
            'dateModified'  => date('c', (int) strtotime((string) $post['updated_at'])),
            'author'        => ['@type' => 'Person', 'name' => $post['author_name'] ?: App::settings()->get('site_name', 'TECHBISS')],
            'publisher'     => ['@type' => 'Organization', 'name' => App::settings()->get('site_name', 'TECHBISS')],
            'mainEntityOfPage' => absolute_url('/blog/' . $post['slug']),
        ]);

        $this->view->render('blog-detail', [
            'post'    => $post,
            'related' => $repo->related($post, 3),
        ]);
    }

    // =================================================================
    // Contact
    // =================================================================
    public function contact(Request $request): void
    {
        if ($request->isPost()) {
            $this->handleContact($request);
            return;
        }

        $seo = App::seo();
        $seo->title('Contact TECHBISS');
        $seo->description('Talk to the team about taking your business digital. Tell us where you are today and we will come back with a clear next step.');
        $seo->canonical(absolute_url('/contact'));
        $seo->breadcrumbs([['label' => 'Home', 'url' => '/'], ['label' => 'Contact', 'url' => '/contact']]);
        $seo->addSchema([
            '@type'    => 'ContactPage',
            'name'     => 'Contact TECHBISS',
            'url'      => absolute_url('/contact'),
        ]);

        $this->view->render('contact', [
            'countries' => self::countries(),
            'faqs'      => (new FaqRepo())->publishedFlat(5),
        ]);
    }

    private function handleContact(Request $request): void
    {
        Csrf::verify($request);
        $leads = new LeadRepo();

        if ($leads->recentSubmissionCount($request->ip()) >= 5) {
            $this->formFail($request, '/contact', 'You have sent several messages recently. Please wait a few minutes before sending another.', []);
            return;
        }

        $v = Validator::make($request->all())
            ->honeypot()
            ->required('name', 'Your name', 2, 120)
            ->optional('company', 190)
            ->email('email')
            ->phone('phone')
            ->in('country', self::countries(), 'Country', false)
            ->optional('subject', 190)
            ->text('message', 5000, true, 'Message');

        if ($v->fails()) {
            $this->formFail($request, '/contact', $v->firstError(), $v->errors());
            return;
        }

        $id = $leads->createMessage([
            'name'       => $v->get('name'),
            'company'    => $v->get('company', ''),
            'email'      => $v->get('email'),
            'phone'      => $v->get('phone', ''),
            'country'    => $v->get('country', ''),
            'subject'    => $v->get('subject', '') ?: 'Website enquiry',
            'message'    => $v->get('message'),
            'status'     => 'new',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        $this->notify(
            'New contact message #' . $id,
            sprintf(
                "Name: %s\nCompany: %s\nEmail: %s\nPhone: %s\nCountry: %s\n\n%s",
                $v->get('name'),
                $v->get('company', '') ?: '—',
                $v->get('email'),
                $v->get('phone', '') ?: '—',
                $v->get('country', '') ?: '—',
                $v->get('message')
            ),
            (string) $v->get('email')
        );

        $this->formSuccess(
            $request,
            '/contact?sent=1',
            'Thank you — your message has been received. We usually reply within one business day.'
        );
    }

    // =================================================================
    // Quote request
    // =================================================================
    // =================================================================
    // Premade projects
    // =================================================================

    /**
     * Catalogue of ready-made builds.
     *
     * No price is shown anywhere: each one is priced in conversation, so the
     * cards carry what a buyer actually needs to judge it — a live demo, what
     * is included, and how long setup takes.
     */
    public function projects(Request $request): void
    {
        $repo    = new ProjectRepo();
        $perPage = max(3, App::settings()->int('items_per_page', 9));
        $page    = max(1, $request->queryInt('page', 1));
        $cat     = Str::slugFilter($request->queryString('category'));
        $ind     = Str::slugFilter($request->queryString('industry'));
        $search  = mb_substr($request->queryString('q'), 0, 80);
        $sort    = $request->queryString('sort');
        if (!in_array($sort, ProjectRepo::sortKeys(), true)) {
            $sort = 'featured';
        }

        $result    = $repo->paginate($page, $perPage, $cat, $ind, $search, $sort);
        $paginator = new Paginator($page, $perPage, $result['total']);

        $seo = App::seo();
        $seo->title('Ready Projects — Launch-Ready Websites');
        $seo->description('Working builds you can see live, then have set up on your own domain. Ask about any of them and we agree the price with you directly.');
        $seo->canonical(absolute_url('/premade-projects'));
        $seo->breadcrumbs([
            ['label' => 'Home', 'url' => '/'],
            ['label' => 'Ready Projects', 'url' => '/premade-projects'],
        ]);
        if ($page > 1) {
            $seo->noindex(true);
        }

        $this->view->render('premade-projects', [
            'projects'   => $result['items'],
            'paginator'  => $paginator,
            'categories' => $repo->activeCategories(),
            'industries' => (new IndustryRepo())->options(),
            'activeCat'  => $cat,
            'activeInd'  => $ind,
            'activeSort' => $sort,
            'search'     => $search,
        ]);
    }

    public function projectDetail(Request $request, array $params): void
    {
        $repo    = new ProjectRepo();
        $project = $repo->publishedBySlug((string) $params['slug']);
        if ($project === null) {
            $this->notFound($request);
            return;
        }
        $repo->incrementViews((int) $project['id']);

        $seo = App::seo();
        $seo->title($project['seo_title'] !== '' ? $project['seo_title'] : $project['name'] . ' — Premade Project');
        $seo->description($project['seo_description'] !== '' ? $project['seo_description'] : $project['short_description']);
        $seo->canonical(absolute_url('/premade-projects/' . $project['slug']));
        $seo->ogImage($project['og_image'] !== '' ? $project['og_image'] : ($project['hero_image'] !== '' ? $project['hero_image'] : $project['thumbnail']));
        $seo->breadcrumbs([
            ['label' => 'Home', 'url' => '/'],
            ['label' => 'Ready Projects', 'url' => '/premade-projects'],
            ['label' => (string) $project['name'], 'url' => '/premade-projects/' . $project['slug']],
        ]);
        // Described as a creative work rather than a Product: with no price and
        // no checkout, an Offer would be a claim we cannot back.
        $seo->addSchema([
            '@type'       => 'CreativeWork',
            'name'        => $project['name'],
            'description' => $project['short_description'],
            'creator'     => ['@type' => 'Organization', 'name' => App::settings()->get('site_name', 'TECHBISS')],
        ]);

        $this->view->render('premade-project-detail', [
            'project'   => $project,
            'related'   => $repo->related($project, 3),
            'countries' => self::countries(),
            'sent'      => $request->queryString('sent') === '1',
            'ready'     => [],
        ]);
    }

    /**
     * Hand over the APK for a project.
     *
     * Served through here rather than as a bare link so it always arrives as a
     * download with the right type and a sensible filename, and so the stored
     * path is checked to be a real file inside uploads/ before anything is
     * read — a value that escapes that directory is simply a 404.
     */
    public function projectApk(Request $request, array $params): void
    {
        $project = (new ProjectRepo())->publishedBySlug((string) $params['slug']);
        if ($project === null) {
            $this->notFound($request);
            return;
        }

        // An externally hosted build is a redirect; we never proxy it.
        $external = trim((string) $project['apk_external_url']);
        if ((string) $project['apk_path'] === '' && $external !== '') {
            redirect($external);
        }

        $stored = ltrim((string) $project['apk_path'], '/');
        if ($stored === '') {
            $this->notFound($request);
            return;
        }

        $uploads = realpath(App::root() . '/uploads');
        $real    = realpath(App::root() . '/' . $stored);
        if ($uploads === false || $real === false
            || !str_starts_with($real, $uploads . DIRECTORY_SEPARATOR)
            || !is_file($real)) {
            $this->notFound($request);
            return;
        }

        $name = Str::slug((string) $project['name']);
        if ((string) $project['apk_version'] !== '') {
            $name .= '-' . Str::slug((string) $project['apk_version']);
        }

        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: application/vnd.android.package-archive');
        header('Content-Disposition: attachment; filename="' . $name . '.apk"');
        header('Content-Length: ' . (string) filesize($real));
        header('X-Content-Type-Options: nosniff');
        header('Content-Security-Policy: default-src \'none\'; sandbox');
        header('Cache-Control: public, max-age=3600');
        readfile($real);
        exit;
    }

    /**
     * Record an enquiry about a premade project.
     *
     * Nothing is priced or charged here. The enquiry reaches the admin with the
     * customer's preferred channel so the conversation can start where they
     * asked for it.
     */
    public function projectEnquiry(Request $request, array $params): void
    {
        $repo    = new ProjectRepo();
        $project = $repo->publishedBySlug((string) $params['slug']);
        if ($project === null) {
            $this->notFound($request);
            return;
        }
        if (!$request->isPost()) {
            redirect('/premade-projects/' . $project['slug']);
        }

        Csrf::verify($request);
        $leads  = new LeadRepo();
        $target = '/premade-projects/' . $project['slug'];

        if ($leads->recentSubmissionCount($request->ip()) >= 5) {
            $this->formFail($request, $target, 'You have submitted several requests recently. Please wait a few minutes.', []);
            return;
        }

        $channels = array_keys(\Techbiss\Repo\ProjectOrderRepo::contactLabels());

        $v = Validator::make($request->all())
            ->honeypot()
            ->required('name', 'Your name', 2, 120)
            ->optional('business_name', 190)
            ->email('email')
            ->phone('phone', true)
            ->in('country', self::countries(), 'Country', false)
            ->in('preferred_contact', $channels, 'Preferred contact', false)
            ->optional('domain_name', 190)
            ->text('requirements', 3000, false, 'What you need');

        if ($v->fails()) {
            $this->formFail($request, $target, $v->firstError(), $v->errors());
            return;
        }

        $customerId = (new CustomerRepo())->upsert([
            'name'          => $v->get('name'),
            'business_name' => $v->get('business_name', ''),
            'email'         => $v->get('email'),
            'phone'         => $v->get('phone', ''),
            'country'       => $v->get('country', ''),
        ]);

        $reference = $leads->nextReference('TBR', 'project_orders');

        (new \Techbiss\Repo\ProjectOrderRepo())->create([
            'reference'         => $reference,
            'customer_id'       => $customerId,
            'project_id'        => (int) $project['id'],
            'project_name'      => (string) $project['name'],
            'preferred_contact' => $v->get('preferred_contact', '') ?: 'whatsapp',
            'currency'          => App::settings()->get('currency', 'USD'),
            'quoted_amount'     => null,
            'payment_status'    => 'pending',
            'order_status'      => 'new',
            'domain_name'       => $v->get('domain_name', ''),
            'business_details'  => $v->get('business_name', ''),
            'requirements'      => $v->get('requirements', ''),
            'ordered_at'        => date('Y-m-d H:i:s'),
            'ip_address'        => $request->ip(),
        ]);

        $this->notify(
            'Premade project enquiry ' . $reference,
            sprintf(
                "Reference: %s\nProject: %s\nName: %s (%s)\nBusiness: %s\nPhone: %s\nCountry: %s\nPreferred contact: %s\nDomain: %s\n\nWhat they need:\n%s",
                $reference,
                $project['name'],
                $v->get('name'),
                $v->get('email'),
                $v->get('business_name', '') ?: '—',
                $v->get('phone', '') ?: '—',
                $v->get('country', '') ?: '—',
                $v->get('preferred_contact', '') ?: 'whatsapp',
                $v->get('domain_name', '') ?: '—',
                $v->get('requirements', '') ?: '—'
            ),
            (string) $v->get('email')
        );

        // The enquiry is saved and the admin notified either way. When WhatsApp
        // was the chosen channel, hand the visitor straight into the chat with
        // the message pre-filled, the same way /request does — rather than
        // making them wait for a reply to a form that already knows what to say.
        if ($v->get('preferred_contact', '') === 'whatsapp') {
            $message = self::projectOrderMessage(
                $reference,
                (string) $project['name'],
                $v->get('name'),
                $v->get('business_name', ''),
                $v->get('domain_name', ''),
                $v->get('requirements', '')
            );
            $wa = whatsapp_link($message);
            if ($wa !== '') {
                $this->view->render('premade-project-detail', [
                    'project'   => $project,
                    'related'   => $repo->related($project, 3),
                    'countries' => self::countries(),
                    'sent'      => false,
                    'ready'     => ['link' => $wa, 'channel' => 'whatsapp', 'message' => $message],
                ]);
                return;
            }
        }

        $this->formSuccess($request, $target . '?sent=1', 'Thanks — we have your enquiry and will be in touch shortly.');
    }

    /** The WhatsApp message for a premade-project order, so the chat opens already saying what was asked for. */
    public static function projectOrderMessage(string $reference, string $project, string $name, string $business, string $domain, string $requirements): string
    {
        $who = trim($name . ($business !== '' ? ' (' . $business . ')' : ''));

        $lines = ['Hi ' . App::settings()->get('site_name', 'TECHBISS') . ','];
        $lines[] = 'I would like to order "' . $project . '" (Ref: ' . $reference . ').';
        if ($who !== '') {
            $lines[] = 'I am ' . $who . '.';
        }
        if ($domain !== '') {
            $lines[] = 'Domain: ' . $domain . '.';
        }
        if ($requirements !== '') {
            $lines[] = $requirements;
        }
        $lines[] = 'Please confirm the price and how we get started.';

        return implode("\n", $lines);
    }

    // =================================================================
    // Package checkout / request
    // =================================================================
    public function page(Request $request, array $params): void
    {
        $slug = Str::slug((string) ($params['slug'] ?? ''));
        $page = (new PageRepo())->publishedBySlug($slug);
        if ($page === null) {
            $this->notFound($request);
            return;
        }

        $seo = App::seo();
        $seo->title($page['seo_title'] !== '' ? $page['seo_title'] : (string) $page['title']);
        $seo->description($page['seo_description'] !== '' ? $page['seo_description'] : (string) $page['subtitle']);
        $seo->canonical(absolute_url('/' . $page['slug']));
        $seo->ogImage((string) $page['og_image']);
        if ((int) $page['noindex'] === 1) {
            $seo->noindex(true);
        }
        $seo->breadcrumbs([
            ['label' => 'Home', 'url' => '/'],
            ['label' => (string) $page['title'], 'url' => '/' . $page['slug']],
        ]);

        // These two pages used to borrow the same three sections — the stats,
        // the service grid and the six-step process — so the bottom two thirds
        // of each was identical to the other, and to /services and
        // /how-it-works. Each now carries only what it is the page for.
        $extra = [];
        if ($slug === 'about') {
            $extra = ['stats' => (new StatRepo())->published(4)];
        } elseif ($slug === 'why-techbiss') {
            $extra = ['testimonials' => (new TestimonialRepo())->publishedWithProject(3)];
        }

        $template = (string) $page['template'] === 'legal' ? 'page-legal' : 'page';
        $this->view->render($template, array_merge(['page' => $page], $extra));
    }

    // =================================================================
    // Newsletter (async)
    // =================================================================
    public function newsletter(Request $request): void
    {
        Csrf::verify($request);

        $v = Validator::make($request->all())
            ->honeypot()
            ->email('email')
            ->optional('name', 190);

        if ($v->fails()) {
            json_response(['ok' => false, 'message' => $v->firstError(), 'errors' => $v->errors()], 422);
        }

        $result = (new LeadRepo())->subscribe(
            (string) $v->get('email'),
            (string) $v->get('name', ''),
            mb_substr($request->str('source', 'footer'), 0, 60),
            $request->ip()
        );

        $message = $result['status'] === 'already'
            ? 'You are already subscribed — thank you.'
            : 'Thank you. You are subscribed to occasional updates.';

        json_response(['ok' => true, 'message' => $message]);
    }

    // =================================================================
    // Sitemap & robots
    // =================================================================
    public function sitemap(Request $request): void
    {
        header('Content-Type: application/xml; charset=utf-8');

        $urls = [
            ['loc' => absolute_url('/'), 'priority' => '1.0', 'changefreq' => 'weekly'],
            ['loc' => absolute_url('/services'), 'priority' => '0.9', 'changefreq' => 'monthly'],
            ['loc' => absolute_url('/request'), 'priority' => '0.9', 'changefreq' => 'yearly'],
            ['loc' => absolute_url('/portfolio'), 'priority' => '0.8', 'changefreq' => 'weekly'],
            ['loc' => absolute_url('/premade-projects'), 'priority' => '0.9', 'changefreq' => 'weekly'],
            ['loc' => absolute_url('/industries'), 'priority' => '0.8', 'changefreq' => 'monthly'],
            ['loc' => absolute_url('/how-it-works'), 'priority' => '0.7', 'changefreq' => 'yearly'],
            ['loc' => absolute_url('/blog'), 'priority' => '0.8', 'changefreq' => 'weekly'],
            ['loc' => absolute_url('/testimonials'), 'priority' => '0.6', 'changefreq' => 'monthly'],
            ['loc' => absolute_url('/faqs'), 'priority' => '0.6', 'changefreq' => 'monthly'],
            ['loc' => absolute_url('/contact'), 'priority' => '0.7', 'changefreq' => 'yearly'],
        ];

        $add = static function (array $rows, string $prefix, string $priority) use (&$urls): void {
            foreach ($rows as $row) {
                $urls[] = [
                    'loc'      => absolute_url($prefix . '/' . $row['slug']),
                    'lastmod'  => $row['updated_at'] ? date('Y-m-d', (int) strtotime((string) $row['updated_at'])) : null,
                    'priority' => $priority,
                    'changefreq' => 'monthly',
                ];
            }
        };

        $add((new ServiceRepo())->forSitemap(), '/services', '0.8');
        $add((new PortfolioRepo())->forSitemap(), '/portfolio', '0.7');
        $add((new ProjectRepo())->forSitemap(), '/premade-projects', '0.8');
        $add((new IndustryRepo())->forSitemap(), '/industries', '0.7');
        $add((new BlogRepo())->forSitemap(), '/blog', '0.6');

        foreach ((new PageRepo())->forSitemap() as $row) {
            $urls[] = [
                'loc'      => absolute_url('/' . $row['slug']),
                'lastmod'  => $row['updated_at'] ? date('Y-m-d', (int) strtotime((string) $row['updated_at'])) : null,
                'priority' => '0.5',
                'changefreq' => 'yearly',
            ];
        }

        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($urls as $u) {
            echo '  <url>' . "\n";
            echo '    <loc>' . e($u['loc']) . '</loc>' . "\n";
            if (!empty($u['lastmod'])) {
                echo '    <lastmod>' . e($u['lastmod']) . '</lastmod>' . "\n";
            }
            echo '    <changefreq>' . e($u['changefreq']) . '</changefreq>' . "\n";
            echo '    <priority>' . e($u['priority']) . '</priority>' . "\n";
            echo '  </url>' . "\n";
        }
        echo '</urlset>';
    }

    /**
     * Web app manifest.
     *
     * Generated rather than shipped as a static file so the icon paths follow
     * the install's base path and the name follows the configured site name.
     */
    public function manifest(Request $request): void
    {
        header('Content-Type: application/manifest+json; charset=utf-8');
        $settings = App::settings();
        $name     = $settings->get('site_name');
        if ($name === '') {
            $name = 'TECHBISS';
        }
        $short = mb_substr($name, 0, 12);

        $manifest = [
            'name'             => $name,
            'short_name'       => $short,
            'description'      => $settings->get('tagline'),
            'start_url'        => url('/'),
            'scope'            => url('/'),
            'display'          => 'standalone',
            'theme_color'      => '#06070c',
            'background_color' => '#06070c',
            'icons'            => [
                [
                    'src'   => asset('assets/images/brand/icon-192.png'),
                    'sizes' => '192x192',
                    'type'  => 'image/png',
                ],
                [
                    'src'   => asset('assets/images/brand/icon-512.png'),
                    'sizes' => '512x512',
                    'type'  => 'image/png',
                ],
                [
                    'src'     => asset('assets/images/brand/icon-maskable-512.png'),
                    'sizes'   => '512x512',
                    'type'    => 'image/png',
                    'purpose' => 'maskable',
                ],
            ],
        ];

        echo json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    public function robots(Request $request): void
    {
        header('Content-Type: text/plain; charset=utf-8');
        $lines = [
            'User-agent: *',
            'Disallow: /admin/',
            'Disallow: /api/',
            'Disallow: /config/',
            'Disallow: /includes/',
            'Disallow: /database/',
            'Disallow: /storage/',
            'Allow: /',
            '',
            'Sitemap: ' . absolute_url('/sitemap.xml'),
        ];
        $extra = App::settings()->get('robots_extra');
        if ($extra !== '') {
            $lines[] = '';
            $lines[] = trim(strip_tags($extra));
        }
        echo implode("\n", $lines) . "\n";
    }

    // =================================================================
    // 404
    // =================================================================
    public function notFound(Request $request): void
    {
        http_response_code(404);
        $seo = App::seo();
        $seo->title('Page Not Found');
        $seo->description('The page you are looking for does not exist or has moved.');
        $seo->noindex(true);

        $this->view->render('404', [
            'services' => (new ServiceRepo())->featured(3),
            'projects' => (new PortfolioRepo())->featured(2),
        ]);
    }

    public function maintenance(): void
    {
        http_response_code(503);
        header('Retry-After: 3600');
        $settings = App::settings();
        $this->view->render('maintenance', [
            'message' => $settings->get('maintenance_message', 'We will be back shortly.'),
        ], App::root() . '/pages/layouts/bare.php');
    }

    // =================================================================
    // Helpers
    // =================================================================
    private function formFail(Request $request, string $redirectTo, string $message, array $errors): void
    {
        if ($request->wantsJson()) {
            json_response(['ok' => false, 'message' => $message, 'errors' => $errors], 422);
        }
        Session::flashInput($request->all());
        Session::flashErrors($errors);
        flash('error', $message);
        redirect($redirectTo);
    }

    private function formSuccess(Request $request, string $redirectTo, string $message): void
    {
        if ($request->wantsJson()) {
            json_response(['ok' => true, 'message' => $message, 'redirect' => url($redirectTo)]);
        }
        flash('success', $message);
        redirect($redirectTo);
    }

    private function notify(string $subject, string $body, string $replyTo = ''): void
    {
        $settings = App::settings();
        if (!$settings->bool('notify_new_lead', true)) {
            return;
        }
        $to = $settings->get('notification_email') ?: $settings->get('contact_email');
        if ($to === '') {
            return;
        }
        App::mailer()->send($to, $subject, $body, $replyTo);
    }

    /** @return array<int,string> */
    public static function countries(): array
    {
        static $list = null;
        if ($list !== null) {
            return $list;
        }
        $file = App::root() . '/includes/data/countries.php';
        $list = is_file($file) ? (array) require $file : [];
        return $list;
    }




}
