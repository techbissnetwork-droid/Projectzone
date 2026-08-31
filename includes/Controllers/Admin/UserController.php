<?php
declare(strict_types=1);

namespace Techbiss\Controllers\Admin;

use Techbiss\Core\ActivityLog;
use Techbiss\Core\Auth;
use Techbiss\Core\Request;
use Techbiss\Core\Str;
use Techbiss\Core\Validator;
use Techbiss\Repo\AdminRepo;

final class UserController extends BaseAdminController
{
    private AdminRepo $repo;

    public function __construct()
    {
        parent::__construct();
        $this->repo = new AdminRepo();
    }

    // -----------------------------------------------------------------
    // Admin users
    // -----------------------------------------------------------------
    public function index(Request $request): void
    {
        $this->authorize('users.manage');
        $this->view->render('users/index', [
            'title' => 'Admin users',
            'rows'  => $this->repo->all(),
            'roles' => $this->repo->roles(),
        ]);
    }

    public function create(Request $request): void
    {
        $this->authorize('users.manage');
        $this->view->render('users/form', [
            'title' => 'New admin user',
            'row'   => ['is_active' => 1],
            'isNew' => true,
            'roles' => $this->repo->roles(),
        ]);
    }

    public function edit(Request $request, array $params): void
    {
        $this->authorize('users.manage');
        $row = $this->repo->find((int) $params['id']);
        if ($row === null) {
            flash('error', 'User not found.');
            redirect('/admin/users');
        }
        $this->view->render('users/form', [
            'title' => 'Edit ' . $row['name'],
            'row'   => $row,
            'isNew' => false,
            'roles' => $this->repo->roles(),
        ]);
    }

    public function store(Request $request): never
    {
        $this->authorize('users.manage');
        $this->verify($request);
        $this->save($request, null);
    }

    public function update(Request $request, array $params): never
    {
        $this->authorize('users.manage');
        $this->verify($request);
        $this->save($request, (int) $params['id']);
    }

    private function save(Request $request, ?int $id): never
    {
        $roleIds = array_map(static fn ($r) => (string) $r['id'], $this->repo->roles());

        $v = Validator::make($request->all())
            ->required('name', 'Name', 2, 120)
            ->email('email')
            ->in('role_id', $roleIds, 'Role')
            ->optional('job_title', 120)
            ->text('bio', 2000, false, 'Bio')
            ->boolean('is_active');

        $password = (string) $request->post('password', '');
        if ($id === null || $password !== '') {
            $v->password('password', 'Password', $id === null)
              ->matches('password', 'password_confirm');
        }

        if (!$v->fails() && $this->repo->emailTaken((string) $v->get('email'), $id)) {
            $v->addError('email', 'Another admin user already uses that email address.');
        }

        // Never let the last active super admin be demoted or deactivated.
        if ($id !== null) {
            $existing = $this->repo->find($id);
            if ($existing !== null && (string) $existing['role_slug'] === 'super-admin') {
                $stillSuper = (int) $v->get('role_id') === (int) $existing['role_id'];
                $stillActive = (int) $v->get('is_active', 0) === 1;
                if ((!$stillSuper || !$stillActive) && $this->repo->countSuperAdmins($id) === 0) {
                    $v->addError('role_id', 'This is the only active super admin. Promote another user first.');
                }
            }
        }

        if ($v->fails()) {
            $this->fail($request, $v->errors(), $v->firstError(),
                '/admin/users' . ($id === null ? '/create' : '/' . $id . '/edit'));
        }

        $data = [
            'name'      => $v->get('name'),
            'email'     => $v->get('email'),
            'role_id'   => (int) $v->get('role_id'),
            'job_title' => $v->get('job_title', ''),
            'bio'       => $v->get('bio', ''),
            'is_active' => (int) $v->get('is_active', 0),
        ];
        if ($password !== '') {
            $data['password_hash'] = Auth::hash($password);
        }

        if ($id === null) {
            $id = $this->repo->create($data);
            ActivityLog::record('create', 'admins', $id, 'Created admin user: ' . $data['email']);
            $message = 'Admin user created.';
        } else {
            $this->repo->update($id, $data);
            ActivityLog::record('update', 'admins', $id, 'Updated admin user: ' . $data['email']);
            $message = 'Admin user updated.';
        }

        $this->ok($message, '/admin/users');
    }

    public function destroy(Request $request, array $params): never
    {
        $this->authorize('users.manage');
        $this->verify($request);
        $id = (int) $params['id'];

        if ($id === Auth::id()) {
            flash('error', 'You cannot delete your own account.');
            redirect('/admin/users');
        }
        $row = $this->repo->find($id);
        if ($row !== null && (string) $row['role_slug'] === 'super-admin' && $this->repo->countSuperAdmins($id) === 0) {
            flash('error', 'This is the only active super admin and cannot be deleted.');
            redirect('/admin/users');
        }

        $this->repo->delete($id);
        ActivityLog::record('delete', 'admins', $id, 'Deleted admin user: ' . ($row['email'] ?? $id));
        $this->ok('Admin user deleted.', '/admin/users');
    }

    // -----------------------------------------------------------------
    // Profile (own account)
    // -----------------------------------------------------------------
    public function profile(Request $request): void
    {
        $this->authorize('dashboard.view');
        $this->view->render('users/profile', [
            'title'       => 'My profile',
            'row'         => Auth::user(),
            'permissions' => Auth::permissions(),
        ]);
    }

    public function updateProfile(Request $request): never
    {
        $this->authorize('dashboard.view');
        $this->verify($request);
        $id = Auth::id();

        $v = Validator::make($request->all())
            ->required('name', 'Name', 2, 120)
            ->email('email')
            ->optional('job_title', 120);

        $password = (string) $request->post('password', '');
        if ($password !== '') {
            $current = (string) $request->post('current_password', '');
            $user    = $this->repo->find($id);
            if ($user === null || !password_verify($current, (string) $user['password_hash'])) {
                $v->addError('current_password', 'Your current password is not correct.');
            }
            $v->password('password')->matches('password', 'password_confirm');
        }

        if (!$v->fails() && $this->repo->emailTaken((string) $v->get('email'), $id)) {
            $v->addError('email', 'Another admin user already uses that email address.');
        }

        if ($v->fails()) {
            $this->fail($request, $v->errors(), $v->firstError(), '/admin/profile');
        }

        $data = [
            'name'      => $v->get('name'),
            'email'     => $v->get('email'),
            'job_title' => $v->get('job_title', ''),
        ];
        if ($password !== '') {
            $data['password_hash'] = Auth::hash($password);
        }

        $this->repo->update($id, $data);
        ActivityLog::record('update', 'admins', $id, 'Updated own profile');
        $this->ok('Your profile has been updated.', '/admin/profile');
    }

    // -----------------------------------------------------------------
    // Roles & permissions
    // -----------------------------------------------------------------
    public function roles(Request $request): void
    {
        $this->authorize('roles.manage');
        $roles = $this->repo->roles();
        foreach ($roles as &$role) {
            $role['permissions'] = $this->repo->permissionsForRole((int) $role['id']);
        }
        unset($role);

        $this->view->render('users/roles', [
            'title'       => 'Roles & permissions',
            'roles'       => $roles,
            'permissions' => $this->repo->permissionsGrouped(),
        ]);
    }

    public function editRole(Request $request, array $params): void
    {
        $this->authorize('roles.manage');
        $role = $this->repo->role((int) $params['id']);
        if ($role === null) {
            flash('error', 'Role not found.');
            redirect('/admin/roles');
        }
        $this->view->render('users/role-form', [
            'title'       => 'Edit ' . $role['name'],
            'row'         => $role,
            'isNew'       => false,
            'permissions' => $this->repo->permissionsGrouped(),
            'granted'     => $this->repo->permissionsForRole((int) $role['id']),
        ]);
    }

    public function createRole(Request $request): void
    {
        $this->authorize('roles.manage');
        $this->view->render('users/role-form', [
            'title'       => 'New role',
            'row'         => [],
            'isNew'       => true,
            'permissions' => $this->repo->permissionsGrouped(),
            'granted'     => [],
        ]);
    }

    public function storeRole(Request $request): never
    {
        $this->authorize('roles.manage');
        $this->verify($request);
        $this->saveRole($request, null);
    }

    public function updateRole(Request $request, array $params): never
    {
        $this->authorize('roles.manage');
        $this->verify($request);
        $this->saveRole($request, (int) $params['id']);
    }

    private function saveRole(Request $request, ?int $id): never
    {
        $v = Validator::make($request->all())
            ->required('name', 'Role name', 2, 80)
            ->optional('description', 255);

        if ($v->fails()) {
            $this->fail($request, $v->errors(), $v->firstError(),
                '/admin/roles' . ($id === null ? '/create' : '/' . $id . '/edit'));
        }

        $slug     = Str::slug((string) $v->get('name'));
        $existing = $id !== null ? $this->repo->role($id) : null;

        // The super admin role is the safety net; its permissions stay fixed.
        if ($existing !== null && (string) $existing['slug'] === 'super-admin') {
            $this->repo->updateRole($id, ['description' => $v->get('description', '')]);
            ActivityLog::record('update', 'roles', $id, 'Updated super admin description');
            $this->ok('The Super Admin role always holds every permission; only its description was updated.', '/admin/roles');
        }

        $allowed = array_column($this->repo->permissions(), 'slug');
        $granted = array_values(array_intersect($request->arr('permissions'), $allowed));

        if ($id === null) {
            $suffix = '';
            $n = 1;
            while ($this->repo->roleBySlug($slug . $suffix) !== null) {
                $suffix = '-' . (++$n);
            }
            $id = $this->repo->createRole([
                'name'        => $v->get('name'),
                'slug'        => $slug . $suffix,
                'description' => $v->get('description', ''),
                'is_system'   => 0,
            ]);
            ActivityLog::record('create', 'roles', $id, 'Created role: ' . $v->get('name'));
            $message = 'Role created.';
        } else {
            $this->repo->updateRole($id, [
                'name'        => $v->get('name'),
                'description' => $v->get('description', ''),
            ]);
            ActivityLog::record('update', 'roles', $id, 'Updated role: ' . $v->get('name'));
            $message = 'Role updated.';
        }

        $this->repo->syncRolePermissions($id, $granted);
        $this->ok($message, '/admin/roles');
    }

    public function destroyRole(Request $request, array $params): never
    {
        $this->authorize('roles.manage');
        $this->verify($request);
        $id   = (int) $params['id'];
        $role = $this->repo->role($id);

        if ($role === null) {
            $this->ok('Role already removed.', '/admin/roles');
        }
        if ((int) $role['is_system'] === 1) {
            flash('error', 'Built-in roles cannot be deleted. Edit their permissions instead.');
            redirect('/admin/roles');
        }
        $inUse = \Techbiss\Core\Database::instance()->int('SELECT COUNT(*) FROM admins WHERE role_id = ?', [$id]);
        if ($inUse > 0) {
            flash('error', 'This role is assigned to ' . $inUse . ' user(s). Reassign them before deleting it.');
            redirect('/admin/roles');
        }

        $this->repo->deleteRole($id);
        ActivityLog::record('delete', 'roles', $id, 'Deleted role: ' . $role['name']);
        $this->ok('Role deleted.', '/admin/roles');
    }
}
