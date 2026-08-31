<?php
declare(strict_types=1);

namespace Techbiss\Controllers\Admin;

use Techbiss\Core\ActivityLog;
use Techbiss\Core\Cache;
use Techbiss\Core\Request;
use Techbiss\Core\Validator;
use Techbiss\Repo\IndustryRepo;
use Techbiss\Repo\ProjectRepo;
use Techbiss\Repo\TaxonomyRepo;

/**
 * Premade projects — the catalogue of ready-made builds on offer.
 *
 * There is no price field: each project is priced in conversation over WhatsApp
 * or email, and the agreed figure is recorded on the enquiry, not here.
 */
final class ProjectController extends BaseAdminController
{
    private ProjectRepo $repo;

    public function __construct()
    {
        parent::__construct();
        $this->repo = new ProjectRepo();
    }

    public function index(Request $request): void
    {
        $this->authorize('projects.manage');
        $this->view->render('projects/index', [
            'title'      => 'Premade projects',
            'rows'       => $this->repo->adminList(
                mb_substr($request->queryString('q'), 0, 80),
                $request->queryString('status'),
                $request->queryInt('category')
            ),
            'categories' => (new TaxonomyRepo('project_categories'))->all(),
            'search'     => $request->queryString('q'),
            'status'     => $request->queryString('status'),
            'categoryId' => $request->queryInt('category'),
        ]);
    }

    public function create(Request $request): void
    {
        $this->authorize('projects.manage');
        $this->renderForm([
            'accent'        => 'cyan',
            'cta_label'     => 'Enquire about this',
            'delivery_days' => 7,
            'is_published'  => 1,
        ], true);
    }

    public function edit(Request $request, array $params): void
    {
        $this->authorize('projects.manage');
        $row = $this->repo->find((int) $params['id']);
        if ($row === null) {
            flash('error', 'Project not found.');
            redirect('/admin/projects');
        }
        $this->renderForm($row, false);
    }

    private function renderForm(array $row, bool $isNew): void
    {
        $id = (int) ($row['id'] ?? 0);
        $this->view->render('projects/form', [
            'title'        => $isNew ? 'New premade project' : 'Edit premade project',
            'row'          => $row,
            'isNew'        => $isNew,
            'categories'   => (new TaxonomyRepo('project_categories'))->all(),
            'industries'   => (new IndustryRepo())->options(),
            'technologies' => (new TaxonomyRepo('portfolio_technologies'))->all(),
            'selectedTech' => $id > 0 ? $this->repo->technologyIds($id) : [],
            'features'     => $id > 0 ? $this->repo->features($id) : [],
            'images'       => $id > 0 ? $this->repo->images($id) : [],
        ]);
    }

    public function store(Request $request): never
    {
        $this->authorize('projects.manage');
        $this->verify($request);
        $this->save($request, null);
    }

    public function update(Request $request, array $params): never
    {
        $this->authorize('projects.manage');
        $this->verify($request);
        $this->save($request, (int) $params['id']);
    }

    private function save(Request $request, ?int $id): never
    {
        $v = Validator::make($request->all())
            ->required('name', 'Project name', 2, 190)
            ->slug('slug', 'name')
            ->optional('tagline', 255)
            ->text('short_description', 500, false, 'Short description')
            ->html('description')
            ->html('whats_included')
            ->text('customisation_note', 500, false, 'Customisation note')
            ->url('demo_url')
            ->url('demo_admin_url')
            ->optional('demo_username', 120)
            ->optional('demo_password', 120)
            ->optional('demo_note', 255)
            ->optional('licence', 80)
            ->optional('revisions', 60)
            ->optional('badge', 40)
            ->optional('cta_label', 60)
            ->int('category_id')
            ->int('industry_id')
            ->int('delivery_days')
            ->int('support_months')
            ->int('page_count')
            ->optional('seo_title', 190)
            ->text('seo_description', 320, false, 'Meta description')
            ->boolean('is_featured')
            ->boolean('is_published');

        if ($v->fails()) {
            $this->fail($request, $v->errors(), $v->firstError(),
                '/admin/projects' . ($id === null ? '/create' : '/' . $id . '/edit'));
        }

        $accent  = in_array($request->str('accent'), ['cyan', 'violet', 'emerald', 'amber', 'rose', 'blue'], true)
            ? $request->str('accent') : 'cyan';
        $mediaOk = static fn (string $p): string
            => ($p !== '' && preg_match('#^(https?://|uploads/)#', $p)) ? mb_substr($p, 0, 500) : '';

        $data = [
            'name'               => $v->get('name'),
            'slug'               => $this->repo->uniqueSlug((string) $v->get('slug'), $id),
            'tagline'            => $v->get('tagline', ''),
            'short_description'  => $v->get('short_description', ''),
            'description'        => $v->get('description', ''),
            'whats_included'     => $v->get('whats_included', ''),
            'customisation_note' => $v->get('customisation_note', ''),
            'category_id'        => $v->get('category_id') > 0 ? $v->get('category_id') : null,
            'industry_id'        => $v->get('industry_id') > 0 ? $v->get('industry_id') : null,
            'demo_url'           => $v->get('demo_url', ''),
            'demo_admin_url'     => $v->get('demo_admin_url', ''),
            'demo_username'      => $v->get('demo_username', ''),
            'demo_password'      => $v->get('demo_password', ''),
            'demo_note'          => $v->get('demo_note', ''),
            'thumbnail'          => $mediaOk($request->str('thumbnail')),
            'hero_image'         => $mediaOk($request->str('hero_image')),
            'licence'            => $v->get('licence', ''),
            'delivery_days'      => max(0, (int) $v->get('delivery_days', 0)),
            'revisions'          => $v->get('revisions', ''),
            'support_months'     => max(0, (int) $v->get('support_months', 0)),
            'page_count'         => max(0, (int) $v->get('page_count', 0)),
            'badge'              => $v->get('badge', ''),
            'cta_label'          => $v->get('cta_label', '') ?: 'Enquire about this',
            'accent'             => $accent,
            'seo_title'          => $v->get('seo_title', ''),
            'seo_description'    => $v->get('seo_description', ''),
            'og_image'           => $mediaOk($request->str('og_image')),
            'is_featured'        => (int) $v->get('is_featured', 0),
            'is_published'       => (int) $v->get('is_published', 0),
        ];

        if ($id === null) {
            $data['sort_order'] = $this->repo->nextSortOrder();
            $id = $this->repo->create($data);
            ActivityLog::record('create', 'premade_projects', $id, 'Created premade project: ' . $data['name']);
            $message = 'Premade project created.';
        } else {
            $this->repo->updateRow($id, $data);
            ActivityLog::record('update', 'premade_projects', $id, 'Updated premade project: ' . $data['name']);
            $message = 'Premade project updated.';
        }

        $this->repo->replaceFeatures($id, $request->rows('features'));
        $this->repo->syncTechnologies($id, array_map('intval', $request->arr('technologies')));

        foreach ($request->arr('gallery') as $path) {
            if ($path !== '' && preg_match('#^(https?://|uploads/)#', $path)) {
                $existing = array_column($this->repo->images($id), 'path');
                if (!in_array($path, $existing, true)) {
                    $this->repo->addImage($id, $path, (string) $data['name']);
                }
            }
        }

        Cache::flush();
        $this->ok($message, '/admin/projects/' . $id . '/edit');
    }

    public function destroy(Request $request, array $params): never
    {
        $this->authorize('projects.manage');
        $this->verify($request);
        $id  = (int) $params['id'];
        $row = $this->repo->find($id);
        $this->repo->deleteRow($id);
        ActivityLog::record('delete', 'premade_projects', $id, 'Deleted premade project: ' . ($row['name'] ?? $id));
        $this->ok('Premade project deleted.', '/admin/projects');
    }

    public function duplicate(Request $request, array $params): never
    {
        $this->authorize('projects.manage');
        $this->verify($request);
        $newId = $this->repo->duplicate((int) $params['id']);
        if ($newId === null) {
            flash('error', 'That project could not be duplicated.');
            redirect('/admin/projects');
        }
        ActivityLog::record('duplicate', 'premade_projects', $newId, 'Duplicated premade project #' . (int) $params['id']);
        $this->ok('Project duplicated as a draft.', '/admin/projects/' . $newId . '/edit');
    }

    public function toggle(Request $request, array $params): never
    {
        $this->authorize('projects.manage');
        $this->verify($request);
        $column = $request->str('column', 'is_published');
        $value  = $this->repo->toggle((int) $params['id'], $column);
        ActivityLog::record('toggle', 'premade_projects', (int) $params['id'], $column . ' set to ' . $value);
        if ($request->wantsJson()) {
            json_response(['ok' => true, 'value' => $value]);
        }
        $this->back('/admin/projects');
    }

    public function reorder(Request $request): never
    {
        $this->authorize('projects.manage');
        $this->verify($request);
        $this->repo->reorder(array_map('intval', $request->arr('order')));
        json_response(['ok' => true, 'message' => 'Projects reordered.']);
    }

    public function deleteImage(Request $request, array $params): never
    {
        $this->authorize('projects.manage');
        $this->verify($request);
        $this->repo->deleteImage((int) $params['imageId'], (int) $params['id']);
        if ($request->wantsJson()) {
            json_response(['ok' => true, 'message' => 'Image removed.']);
        }
        $this->ok('Image removed from the project.', '/admin/projects/' . (int) $params['id'] . '/edit');
    }

    public function reorderImages(Request $request, array $params): never
    {
        $this->authorize('projects.manage');
        $this->verify($request);
        $this->repo->reorderImages((int) $params['id'], array_map('intval', $request->arr('order')));
        json_response(['ok' => true, 'message' => 'Gallery reordered.']);
    }
}
