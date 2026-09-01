<?php
declare(strict_types=1);

namespace Techbiss\Controllers\Admin;

use Techbiss\Core\ActivityLog;
use Techbiss\Core\App;
use Techbiss\Core\Cache;
use Techbiss\Core\Request;
use Techbiss\Core\Validator;
use Techbiss\Repo\IndustryRepo;
use Techbiss\Repo\PortfolioRepo;
use Techbiss\Repo\ServiceRepo;
use Techbiss\Repo\TaxonomyRepo;

final class PortfolioController extends BaseAdminController
{
    private PortfolioRepo $repo;

    public function __construct()
    {
        parent::__construct();
        $this->repo = new PortfolioRepo();
    }

    public function index(Request $request): void
    {
        $this->authorize('portfolio.manage');
        $this->view->render('portfolio/index', [
            'title'      => 'Portfolio',
            'rows'       => $this->repo->adminList(
                mb_substr($request->queryString('q'), 0, 80),
                $request->queryString('status'),
                $request->queryInt('category')
            ),
            'categories' => (new TaxonomyRepo('portfolio_categories'))->all(),
            'search'     => $request->queryString('q'),
            'status'     => $request->queryString('status'),
            'categoryId' => $request->queryInt('category'),
        ]);
    }

    public function create(Request $request): void
    {
        $this->authorize('portfolio.manage');
        $this->renderForm([
            'accent'       => 'cyan',
            'is_published' => 1,
        ], true);
    }

    public function edit(Request $request, array $params): void
    {
        $this->authorize('portfolio.manage');
        $id  = (int) $params['id'];
        $row = $this->repo->find($id);
        if ($row === null) {
            flash('error', 'Project not found.');
            redirect('/admin/portfolio');
        }
        $this->renderForm($row, false);
    }

    private function renderForm(array $row, bool $isNew): void
    {
        $id = (int) ($row['id'] ?? 0);
        $this->view->render('portfolio/form', [
            'title'        => $isNew ? 'New project' : 'Edit project',
            'row'          => $row,
            'isNew'        => $isNew,
            'categories'   => (new TaxonomyRepo('portfolio_categories'))->all(),
            'industries'   => (new IndustryRepo())->options(),
            'services'     => (new ServiceRepo())->options(),
            'technologies' => (new TaxonomyRepo('portfolio_technologies'))->all(),
            'selectedTech' => $id > 0 ? $this->repo->technologyIds($id) : [],
            'selectedSvc'  => $id > 0 ? $this->repo->serviceIds($id) : [],
            'images'       => $id > 0 ? $this->repo->images($id) : [],
        ]);
    }

    public function store(Request $request): never
    {
        $this->authorize('portfolio.manage');
        $this->verify($request);
        $this->save($request, null);
    }

    public function update(Request $request, array $params): never
    {
        $this->authorize('portfolio.manage');
        $this->verify($request);
        $this->save($request, (int) $params['id']);
    }

    private function save(Request $request, ?int $id): never
    {
        $v = Validator::make($request->all())
            ->required('title', 'Project title', 2, 190)
            ->slug('slug', 'title')
            ->optional('client_name', 190)
            ->email('client_email', 'Client email', false)
            ->phone('client_phone', false)
            ->in('status', ['in_progress', 'completed'], 'Status', false)
            ->text('short_description', 500, false, 'Short description')
            ->html('overview')
            ->html('challenge')
            ->html('solution')
            ->html('results')
            ->url('project_url')
            ->url('android_url')
            ->url('ios_url')
            ->date('project_date')
            ->optional('duration', 60)
            ->int('category_id')
            ->int('industry_id')
            ->optional('seo_title', 190)
            ->text('seo_description', 320, false, 'Meta description')
            ->boolean('is_featured')
            ->boolean('is_published');

        if ($v->fails()) {
            $this->fail($request, $v->errors(), $v->firstError(),
                '/admin/portfolio' . ($id === null ? '/create' : '/' . $id . '/edit'));
        }

        $accent  = in_array($request->str('accent'), ['cyan', 'violet', 'emerald', 'amber', 'rose', 'blue'], true)
            ? $request->str('accent') : 'cyan';
        $mediaOk = static fn (string $p): string
            => ($p !== '' && preg_match('#^(https?://|uploads/)#', $p)) ? mb_substr($p, 0, 500) : '';

        $data = [
            'title'             => $v->get('title'),
            'slug'              => $this->repo->uniqueSlug((string) $v->get('slug'), $id),
            'category_id'       => $v->get('category_id') > 0 ? $v->get('category_id') : null,
            'industry_id'       => $v->get('industry_id') > 0 ? $v->get('industry_id') : null,
            'client_name'       => $v->get('client_name', ''),
            'client_email'      => $v->get('client_email', ''),
            'client_phone'      => $v->get('client_phone', ''),
            'status'            => $v->get('status', '') ?: 'completed',
            'short_description' => $v->get('short_description', ''),
            'overview'          => $v->get('overview', ''),
            'challenge'         => $v->get('challenge', ''),
            'solution'          => $v->get('solution', ''),
            'results'           => $v->get('results', ''),
            'thumbnail'         => $mediaOk($request->str('thumbnail')),
            'hero_image'        => $mediaOk($request->str('hero_image')),
            'project_url'       => $v->get('project_url', ''),
            'android_url'       => $v->get('android_url', ''),
            'ios_url'           => $v->get('ios_url', ''),
            'project_date'      => $v->get('project_date'),
            'duration'          => $v->get('duration', ''),
            'accent'            => $accent,
            'seo_title'         => $v->get('seo_title', ''),
            'seo_description'   => $v->get('seo_description', ''),
            'og_image'          => $mediaOk($request->str('og_image')),
            'is_featured'       => (int) $v->get('is_featured', 0),
            'is_published'      => (int) $v->get('is_published', 0),
        ];

        if ($id === null) {
            $data['sort_order'] = $this->repo->nextSortOrder();
            $id = $this->repo->create($data);
            ActivityLog::record('create', 'portfolio', $id, 'Created project: ' . $data['title']);
            $message = 'Project created.';
        } else {
            $this->repo->updateRow($id, $data);
            ActivityLog::record('update', 'portfolio', $id, 'Updated project: ' . $data['title']);
            $message = 'Project updated.';
        }

        $this->repo->syncTechnologies($id, array_map('intval', $request->arr('technologies')));
        $this->repo->syncServices($id, array_map('intval', $request->arr('services')));

        // Gallery images added through the form's media picker
        foreach ($request->arr('gallery') as $path) {
            if ($path !== '' && preg_match('#^(https?://|uploads/)#', $path)) {
                $existing = array_column($this->repo->images($id), 'path');
                if (!in_array($path, $existing, true)) {
                    $this->repo->addImage($id, $path, (string) $data['title']);
                }
            }
        }

        Cache::flush();
        $this->ok($message, '/admin/portfolio/' . $id . '/edit');
    }

    /**
     * A way to reach a past client again — the address is on file precisely
     * so a conversation can restart without having to go dig it up.
     */
    public function emailForm(Request $request, array $params): void
    {
        $this->authorize('portfolio.manage');
        $id  = (int) $params['id'];
        $row = $this->repo->find($id);
        if ($row === null || (string) $row['client_email'] === '') {
            flash('error', 'That project has no client email on file.');
            redirect('/admin/portfolio');
        }
        $this->view->render('portfolio/email', ['row' => $row, 'title' => 'Email ' . ($row['client_name'] ?: 'client')]);
    }

    public function sendEmail(Request $request, array $params): never
    {
        $this->authorize('portfolio.manage');
        $this->verify($request);
        $id  = (int) $params['id'];
        $row = $this->repo->find($id);
        if ($row === null || (string) $row['client_email'] === '') {
            flash('error', 'That project has no client email on file.');
            redirect('/admin/portfolio');
        }

        $v = Validator::make($request->all())
            ->required('subject', 'Subject', 2, 180)
            ->text('message', 5000, true, 'Message');
        if ($v->fails()) {
            $this->fail($request, $v->errors(), $v->firstError(), '/admin/portfolio/' . $id . '/email');
        }

        $sender  = App::settings()->get('sales_email') ?: App::settings()->get('contact_email');
        $sent    = App::mailer()->send((string) $row['client_email'], $v->get('subject'), $v->get('message'), $sender);
        if (!$sent) {
            $this->fail($request, [], 'The message could not be sent. Check the mail settings and try again.', '/admin/portfolio/' . $id . '/email');
        }

        ActivityLog::record('email', 'portfolio', $id, 'Emailed client about: ' . $row['title']);
        $this->ok('Email sent to ' . $row['client_email'] . '.', '/admin/portfolio/' . $id . '/edit');
    }

    public function destroy(Request $request, array $params): never
    {
        $this->authorize('portfolio.manage');
        $this->verify($request);
        $id  = (int) $params['id'];
        $row = $this->repo->find($id);
        $this->repo->deleteRow($id);
        ActivityLog::record('delete', 'portfolio', $id, 'Deleted project: ' . ($row['title'] ?? $id));
        $this->ok('Project deleted.', '/admin/portfolio');
    }

    public function duplicate(Request $request, array $params): never
    {
        $this->authorize('portfolio.manage');
        $this->verify($request);
        $newId = $this->repo->duplicate((int) $params['id']);
        if ($newId === null) {
            flash('error', 'That project could not be duplicated.');
            redirect('/admin/portfolio');
        }
        ActivityLog::record('duplicate', 'portfolio', $newId, 'Duplicated project #' . (int) $params['id']);
        $this->ok('Project duplicated as a draft.', '/admin/portfolio/' . $newId . '/edit');
    }

    public function toggle(Request $request, array $params): never
    {
        $this->authorize('portfolio.manage');
        $this->verify($request);
        $column = $request->str('column', 'is_published');
        $value  = $this->repo->toggle((int) $params['id'], $column);
        ActivityLog::record('toggle', 'portfolio', (int) $params['id'], $column . ' set to ' . $value);
        if ($request->wantsJson()) {
            json_response(['ok' => true, 'value' => $value]);
        }
        $this->back('/admin/portfolio');
    }

    public function reorder(Request $request): never
    {
        $this->authorize('portfolio.manage');
        $this->verify($request);
        $this->repo->reorder(array_map('intval', $request->arr('order')));
        json_response(['ok' => true, 'message' => 'Projects reordered.']);
    }

    public function deleteImage(Request $request, array $params): never
    {
        $this->authorize('portfolio.manage');
        $this->verify($request);
        $this->repo->deleteImage((int) $params['imageId'], (int) $params['id']);
        if ($request->wantsJson()) {
            json_response(['ok' => true, 'message' => 'Image removed.']);
        }
        $this->ok('Image removed from the project.', '/admin/portfolio/' . (int) $params['id'] . '/edit');
    }

    public function reorderImages(Request $request, array $params): never
    {
        $this->authorize('portfolio.manage');
        $this->verify($request);
        $this->repo->reorderImages((int) $params['id'], array_map('intval', $request->arr('order')));
        json_response(['ok' => true, 'message' => 'Gallery reordered.']);
    }
}
