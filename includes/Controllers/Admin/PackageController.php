<?php
declare(strict_types=1);

namespace Techbiss\Controllers\Admin;

use Techbiss\Core\ActivityLog;
use Techbiss\Core\Cache;
use Techbiss\Core\Request;
use Techbiss\Core\Validator;
use Techbiss\Repo\AddonRepo;
use Techbiss\Repo\PackageRepo;

final class PackageController extends BaseAdminController
{
    private PackageRepo $repo;

    public function __construct()
    {
        parent::__construct();
        $this->repo = new PackageRepo();
    }

    public function index(Request $request): void
    {
        $this->authorize('packages.manage');
        $rows = $this->repo->all();
        foreach ($rows as &$row) {
            $row['pricing']       = PackageRepo::pricing($row);
            $row['feature_count'] = count($this->repo->features((int) $row['id']));
        }
        unset($row);

        $this->view->render('packages/index', ['title' => 'Packages', 'rows' => $rows]);
    }

    public function create(Request $request): void
    {
        $this->authorize('packages.manage');
        $this->renderForm([
            'currency'        => setting('currency', 'USD'),
            'billing_period'  => 'one-time',
            'duration_months' => 12,
            'accent'          => 'cyan',
            'icon'            => 'layers',
            'cta_label'       => 'Get Started',
            'is_published'    => 1,
        ], true);
    }

    public function edit(Request $request, array $params): void
    {
        $this->authorize('packages.manage');
        $row = $this->repo->find((int) $params['id']);
        if ($row === null) {
            flash('error', 'Package not found.');
            redirect('/admin/packages');
        }
        $this->renderForm($row, false);
    }

    private function renderForm(array $row, bool $isNew): void
    {
        $id = (int) ($row['id'] ?? 0);
        $this->view->render('packages/form', [
            'title'           => $isNew ? 'New package' : 'Edit package',
            'row'             => $row,
            'isNew'           => $isNew,
            'features'        => $id > 0 ? $this->repo->features($id) : [],
            'allAddons'       => (new AddonRepo())->all(),
            'selectedAddons'  => $id > 0 ? $this->repo->addonIds($id) : [],
            'pricing'         => $id > 0 ? PackageRepo::pricing($row) : null,
        ]);
    }

    public function store(Request $request): never
    {
        $this->authorize('packages.manage');
        $this->verify($request);
        $this->save($request, null);
    }

    public function update(Request $request, array $params): never
    {
        $this->authorize('packages.manage');
        $this->verify($request);
        $this->save($request, (int) $params['id']);
    }

    private function save(Request $request, ?int $id): never
    {
        $v = Validator::make($request->all())
            ->required('name', 'Package name', 2, 120)
            ->slug('slug', 'name')
            ->optional('tagline', 255)
            ->text('short_description', 500, false, 'Short description')
            ->html('description')
            ->optional('best_for', 255)
            ->optional('currency', 6)
            ->decimal('regular_price', 'Regular price', 0, 99999999.99, false)
            ->decimal('prepaid_price', 'Prepaid price')
            ->in('billing_period', ['one-time', 'monthly', 'yearly', 'project'], 'Billing period')
            ->int('duration_months', 1, 240, 'Duration')
            ->optional('badge', 40)
            ->optional('cta_label', 60)
            ->optional('seo_title', 190)
            ->text('seo_description', 320, false, 'Meta description')
            ->boolean('is_custom_quote')
            ->boolean('is_featured')
            ->boolean('is_published');

        $regular = (float) $v->get('regular_price', 0);
        $prepaid = $v->get('prepaid_price');
        $custom  = (int) $v->get('is_custom_quote', 0) === 1;

        // Refuse to store a "discount" that is not one. A prepaid price at or
        // above the regular price would render as a fake saving on the site.
        if (!$custom && $prepaid !== null && $prepaid > 0 && $prepaid >= $regular && $regular > 0) {
            $v->addError('prepaid_price', 'The prepaid price must be lower than the regular price, or left empty.');
        }
        if (!$custom && $regular <= 0) {
            $v->addError('regular_price', 'Enter a regular price, or mark this package as a custom quote.');
        }

        if ($v->fails()) {
            $this->fail($request, $v->errors(), $v->firstError(),
                '/admin/packages' . ($id === null ? '/create' : '/' . $id . '/edit'));
        }

        $mediaOk = static fn (string $p): string
            => ($p !== '' && preg_match('#^(https?://|uploads/)#', $p)) ? mb_substr($p, 0, 500) : '';

        $data = [
            'name'              => $v->get('name'),
            'slug'              => $this->repo->uniqueSlug((string) $v->get('slug'), $id),
            'tagline'           => $v->get('tagline', ''),
            'short_description' => $v->get('short_description', ''),
            'description'       => $v->get('description', ''),
            'best_for'          => $v->get('best_for', ''),
            'currency'          => strtoupper((string) ($v->get('currency', '') ?: 'USD')),
            'regular_price'     => $custom ? 0 : $regular,
            'prepaid_price'     => $custom ? null : $prepaid,
            'billing_period'    => $v->get('billing_period', 'one-time'),
            'duration_months'   => max(1, (int) ($v->get('duration_months') ?? 12)),
            'badge'             => $v->get('badge', ''),
            'cta_label'         => $v->get('cta_label', '') ?: 'Get Started',
            'icon'              => \Techbiss\Core\Icons::has($request->str('icon')) ? $request->str('icon') : 'layers',
            'image'             => $mediaOk($request->str('image')),
            'accent'            => in_array($request->str('accent'), ['cyan', 'violet', 'emerald', 'amber', 'rose', 'blue'], true) ? $request->str('accent') : 'cyan',
            'is_custom_quote'   => $custom ? 1 : 0,
            'seo_title'         => $v->get('seo_title', ''),
            'seo_description'   => $v->get('seo_description', ''),
            'og_image'          => $mediaOk($request->str('og_image')),
            'is_featured'       => (int) $v->get('is_featured', 0),
            'is_published'      => (int) $v->get('is_published', 0),
        ];

        if ($id === null) {
            $data['sort_order'] = $this->repo->nextSortOrder();
            $id = $this->repo->create($data);
            ActivityLog::record('create', 'packages', $id, 'Created package: ' . $data['name']);
            $message = 'Package created.';
        } else {
            $this->repo->updateRow($id, $data);
            ActivityLog::record('update', 'packages', $id, 'Updated package: ' . $data['name']);
            $message = 'Package updated.';
        }

        $this->repo->replaceFeatures($id, $request->rows('features'));
        $this->repo->syncAddons($id, array_map('intval', $request->arr('addons')));
        Cache::flush();

        $this->ok($message, '/admin/packages/' . $id . '/edit');
    }

    public function destroy(Request $request, array $params): never
    {
        $this->authorize('packages.manage');
        $this->verify($request);
        $id  = (int) $params['id'];
        $row = $this->repo->find($id);

        $inUse = \Techbiss\Core\Database::instance()->int('SELECT COUNT(*) FROM package_purchases WHERE package_id = ?', [$id]);
        if ($inUse > 0) {
            flash('error', 'This package has ' . $inUse . ' purchase record(s) attached. Unpublish it instead of deleting, so the history stays intact.');
            redirect('/admin/packages');
        }

        $this->repo->deleteRow($id);
        ActivityLog::record('delete', 'packages', $id, 'Deleted package: ' . ($row['name'] ?? $id));
        $this->ok('Package deleted.', '/admin/packages');
    }

    public function toggle(Request $request, array $params): never
    {
        $this->authorize('packages.manage');
        $this->verify($request);
        $column = $request->str('column', 'is_published');
        $value  = $this->repo->toggle((int) $params['id'], $column);
        ActivityLog::record('toggle', 'packages', (int) $params['id'], $column . ' set to ' . $value);
        if ($request->wantsJson()) {
            json_response(['ok' => true, 'value' => $value]);
        }
        $this->back('/admin/packages');
    }

    public function reorder(Request $request): never
    {
        $this->authorize('packages.manage');
        $this->verify($request);
        $this->repo->reorder(array_map('intval', $request->arr('order')));
        json_response(['ok' => true, 'message' => 'Packages reordered.']);
    }
}
