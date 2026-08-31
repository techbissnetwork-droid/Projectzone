<?php
declare(strict_types=1);

namespace Techbiss\Controllers\Admin;

use Techbiss\Core\ActivityLog;
use Techbiss\Core\Cache;
use Techbiss\Core\Request;
use Techbiss\Core\Validator;
use Techbiss\Repo\NavigationRepo;

final class NavigationController extends BaseAdminController
{
    private NavigationRepo $repo;

    public function __construct()
    {
        parent::__construct();
        $this->repo = new NavigationRepo();
    }

    private function menu(Request $request): string
    {
        $menu = $request->queryString('menu') ?: $request->str('menu', 'primary');
        return in_array($menu, $this->repo->menus(), true) ? $menu : 'primary';
    }

    public function index(Request $request): void
    {
        $this->authorize('navigation.manage');
        $menu = $this->menu($request);
        $this->view->render('navigation/index', [
            'title' => 'Navigation',
            'menu'  => $menu,
            'menus' => $this->repo->menus(),
            'rows'  => $this->repo->allForMenu($menu),
        ]);
    }

    public function create(Request $request): void
    {
        $this->authorize('navigation.manage');
        $menu = $this->menu($request);
        $this->view->render('navigation/form', [
            'title'   => 'New menu item',
            'row'     => ['menu' => $menu, 'link_type' => 'internal', 'target' => '_self', 'is_active' => 1],
            'isNew'   => true,
            'menus'   => $this->repo->menus(),
            'parents' => $this->repo->parentOptions($menu),
        ]);
    }

    public function edit(Request $request, array $params): void
    {
        $this->authorize('navigation.manage');
        $id  = (int) $params['id'];
        $row = $this->repo->find($id);
        if ($row === null) {
            flash('error', 'Menu item not found.');
            redirect('/admin/navigation');
        }
        $this->view->render('navigation/form', [
            'title'   => 'Edit menu item',
            'row'     => $row,
            'isNew'   => false,
            'menus'   => $this->repo->menus(),
            'parents' => $this->repo->parentOptions((string) $row['menu'], $id),
        ]);
    }

    public function store(Request $request): never
    {
        $this->authorize('navigation.manage');
        $this->verify($request);
        $this->save($request, null);
    }

    public function update(Request $request, array $params): never
    {
        $this->authorize('navigation.manage');
        $this->verify($request);
        $this->save($request, (int) $params['id']);
    }

    private function save(Request $request, ?int $id): never
    {
        $v = Validator::make($request->all())
            ->required('label', 'Label', 1, 120)
            ->in('menu', $this->repo->menus(), 'Menu')
            ->in('link_type', ['internal', 'external'], 'Link type')
            ->optional('description', 190)
            ->in('target', ['_self', '_blank'], 'Target')
            ->int('parent_id')
            ->boolean('is_active')
            ->boolean('is_button');

        $url  = trim($request->str('url'));
        $type = $request->str('link_type', 'internal');
        if ($type === 'external') {
            if ($url !== '' && !preg_match('#^https?://#i', $url)) {
                $url = 'https://' . $url;
            }
            if ($url !== '' && !filter_var($url, FILTER_VALIDATE_URL)) {
                $v->addError('url', 'Please enter a valid external URL.');
            }
        } else {
            // Internal links stay relative and cannot escape the site.
            if ($url !== '') {
                $url = '/' . ltrim(preg_replace('#[^A-Za-z0-9/_\-?=&.]#', '', $url) ?? '', '/');
                if (str_starts_with($url, '//')) {
                    $url = '/' . ltrim($url, '/');
                }
            }
        }

        if ($v->fails()) {
            $this->fail($request, $v->errors(), $v->firstError(),
                '/admin/navigation' . ($id === null ? '/create' : '/' . $id . '/edit'));
        }

        $menu     = (string) $v->get('menu', 'primary');
        $parentId = $v->get('parent_id');
        $parentId = ($parentId !== null && $parentId > 0 && $parentId !== $id) ? $parentId : null;

        $data = [
            'menu'        => $menu,
            'parent_id'   => $parentId,
            'label'       => $v->get('label'),
            'url'         => mb_substr($url, 0, 500),
            'link_type'   => $type,
            'description' => $v->get('description', ''),
            'target'      => $v->get('target', '_self'),
            'is_active'   => (int) $v->get('is_active', 0),
            'is_button'   => (int) $v->get('is_button', 0),
        ];

        if ($id === null) {
            $data['sort_order'] = $this->repo->nextSortForMenu($menu, $parentId);
            $id = $this->repo->create($data);
            ActivityLog::record('create', 'navigation', $id, 'Added menu item: ' . $data['label']);
            $message = 'Menu item added.';
        } else {
            $this->repo->updateRow($id, $data);
            ActivityLog::record('update', 'navigation', $id, 'Updated menu item: ' . $data['label']);
            $message = 'Menu item updated.';
        }

        Cache::flush();
        $this->ok($message, '/admin/navigation?menu=' . urlencode($menu));
    }

    public function destroy(Request $request, array $params): never
    {
        $this->authorize('navigation.manage');
        $this->verify($request);
        $id  = (int) $params['id'];
        $row = $this->repo->find($id);
        $this->repo->deleteRow($id);
        ActivityLog::record('delete', 'navigation', $id, 'Deleted menu item: ' . ($row['label'] ?? $id));
        Cache::flush();
        $this->ok('Menu item deleted.', '/admin/navigation?menu=' . urlencode((string) ($row['menu'] ?? 'primary')));
    }

    public function toggle(Request $request, array $params): never
    {
        $this->authorize('navigation.manage');
        $this->verify($request);
        $value = $this->repo->toggle((int) $params['id'], 'is_active');
        ActivityLog::record('toggle', 'navigation', (int) $params['id'], 'is_active set to ' . $value);
        Cache::flush();
        if ($request->wantsJson()) {
            json_response(['ok' => true, 'value' => $value]);
        }
        $this->back('/admin/navigation');
    }

    public function reorder(Request $request): never
    {
        $this->authorize('navigation.manage');
        $this->verify($request);
        $this->repo->reorder(array_map('intval', $request->arr('order')));
        Cache::flush();
        json_response(['ok' => true, 'message' => 'Menu reordered.']);
    }
}
