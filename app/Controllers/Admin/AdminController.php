<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;

final class AdminController extends Controller
{
    private function nav(): array
    {
        return [
            'Overview' => [
                ['label' => 'Dashboard', 'path' => '/admin', 'icon' => 'grid'],
                ['label' => 'Activity', 'path' => '/admin/activity', 'icon' => 'clock'],
            ],
            'Commerce' => [
                ['label' => 'Products', 'path' => '/admin/products', 'icon' => 'tag'],
                ['label' => 'Orders', 'path' => '/admin/orders', 'icon' => 'cart'],
                ['label' => 'Deployments', 'path' => '/admin/deployments', 'icon' => 'rocket'],
            ],
            'Growth' => [
                ['label' => 'Leads', 'path' => '/admin/leads', 'icon' => 'trend'],
            ],
            'Platform' => [
                ['label' => 'Users', 'path' => '/admin/users', 'icon' => 'users'],
                ['label' => 'Settings', 'path' => '/admin/settings', 'icon' => 'settings'],
            ],
        ];
    }

    private function shell(Request $request, string $view, string $title, string $subtitle, array $data = [], int $status = 200): Response
    {
        $this->seo->title($title . ' — Admin Console', true);
        $this->seo->noindex();

        return $this->render($view, array_merge($data, [
            'request' => $request,
            'portal' => 'admin',
            'sidenav' => $this->nav(),
            'title' => $title,
            'subtitle' => $subtitle,
        ]), $status)->cachePrivate();
    }

    public function dashboard(Request $request): Response
    {
        $stats = $this->cache()->remember('admin.stats', (int) $this->config->get('cache.ttl.stats', 120), function (): array {
            $revenue = (float) $this->db->value("SELECT COALESCE(SUM(total),0) FROM orders WHERE status = 'paid'", [], 0);
            $pending = (float) $this->db->value("SELECT COALESCE(SUM(total),0) FROM orders WHERE status = 'pending'", [], 0);

            return [
                'revenue' => $revenue,
                'pending' => $pending,
                'orders' => (int) $this->db->value('SELECT COUNT(*) FROM orders', [], 0),
                'licenses' => (int) $this->db->value("SELECT COUNT(*) FROM licenses WHERE status = 'active'", [], 0),
                'deployments' => (int) $this->db->value('SELECT COUNT(*) FROM deployments', [], 0),
                'live' => (int) $this->db->value("SELECT COUNT(*) FROM deployments WHERE status = 'live'", [], 0),
                'leads' => (int) $this->db->value("SELECT COUNT(*) FROM leads WHERE status IN ('new','contacted','qualified','proposal')", [], 0),
                'newLeads' => (int) $this->db->value("SELECT COUNT(*) FROM leads WHERE status = 'new'", [], 0),
                'pipeline' => (float) $this->db->value("SELECT COALESCE(SUM(value),0) FROM leads WHERE status != 'lost'", [], 0),
                'users' => (int) $this->db->value("SELECT COUNT(*) FROM users WHERE status = 'active'", [], 0),
                'products' => (int) $this->db->value("SELECT COUNT(*) FROM products WHERE status = 'published'", [], 0),
                'tickets' => (int) $this->db->value("SELECT COUNT(*) FROM tickets WHERE status IN ('open','answered')", [], 0),
            ];
        });

        $topProducts = $this->db->select(
            'SELECT p.name, p.slug, p.sales_count, p.rating, COUNT(oi.id) AS orders,
                    COALESCE(SUM(oi.unit_price),0) AS revenue
             FROM products p LEFT JOIN order_items oi ON oi.product_id = p.id
             GROUP BY p.id ORDER BY revenue DESC, p.sales_count DESC LIMIT 6'
        );

        return $this->shell($request, 'admin.dashboard', 'Platform overview',
            'Revenue, pipeline, deployments and fleet health.', [
                'stats' => $stats,
                'findings' => $this->app->make('audit')->findings(),
                'topProducts' => $topProducts,
                'recentOrders' => $this->db->select('SELECT * FROM orders ORDER BY created_at DESC LIMIT 6'),
                'recentLeads' => $this->db->select('SELECT * FROM leads ORDER BY created_at DESC LIMIT 6'),
                'activity' => $this->db->select(
                    'SELECT a.*, u.name AS user_name FROM activity_log a
                     LEFT JOIN users u ON u.id = a.user_id ORDER BY a.created_at DESC LIMIT 8'
                ),
            ]);
    }

    public function users(Request $request): Response
    {
        $role = $request->str('role');
        $where = $role !== '' ? 'WHERE role = :role' : '';
        $bindings = $role !== '' ? ['role' => $role] : [];

        return $this->shell($request, 'admin.users', 'Users and access',
            'Every account with access to the platform, across all three portals.', [
                'users' => $this->db->select(
                    "SELECT id, name, email, role, status, company, job_title, last_login_at, created_at
                     FROM users {$where} ORDER BY
                     CASE role WHEN 'owner' THEN 0 WHEN 'admin' THEN 1 WHEN 'manager' THEN 2
                               WHEN 'engineer' THEN 3 WHEN 'support' THEN 4 ELSE 5 END, name",
                    $bindings
                ),
                'roleCounts' => $this->countsBy('users', 'role'),
                'activeRole' => $role,
            ]);
    }

    public function updateUserStatus(Request $request): Response
    {
        $id = $request->int('id');
        $status = $request->str('status');
        if (!in_array($status, ['active', 'suspended'], true)) {
            $this->session->flash('error', 'Unknown account status.');
            return $this->back($request, '/admin/users');
        }

        $user = $this->db->first('SELECT id, name, role FROM users WHERE id = ?', [$id]);
        if ($user === null) {
            $this->session->flash('error', 'That account no longer exists.');
            return $this->back($request, '/admin/users');
        }

        // The platform must never be left without a reachable owner.
        if ($user['role'] === 'owner' && $status === 'suspended') {
            $owners = (int) $this->db->value("SELECT COUNT(*) FROM users WHERE role = 'owner' AND status = 'active'", [], 0);
            if ($owners <= 1) {
                $this->session->flash('error', 'You cannot suspend the only active owner account.');
                return $this->back($request, '/admin/users');
            }
        }
        if ((int) $user['id'] === $this->auth->id() && $status === 'suspended') {
            $this->session->flash('error', 'You cannot suspend your own account.');
            return $this->back($request, '/admin/users');
        }

        $this->db->update('users', ['status' => $status, 'updated_at' => gmdate('c')], 'id = :id', ['id' => $id]);
        $this->app->make('audit')->forget();
        $this->auth->log((int) $this->auth->id(), 'user.status', $user['name'] . ' set to ' . $status, $request->ip());
        $this->session->flash('status', $user['name'] . ' is now ' . $status . '.');
        return $this->back($request, '/admin/users');
    }

    public function products(Request $request): Response
    {
        return $this->shell($request, 'admin.products', 'Marketplace catalogue',
            'Publish, retire and review every product in the marketplace.', [
                'products' => $this->db->select(
                    'SELECT p.*, COUNT(oi.id) AS order_count, COALESCE(SUM(oi.unit_price),0) AS revenue
                     FROM products p LEFT JOIN order_items oi ON oi.product_id = p.id
                     GROUP BY p.id ORDER BY p.featured DESC, revenue DESC'
                ),
                'statusCounts' => $this->countsBy('products', 'status'),
            ]);
    }

    public function updateProductStatus(Request $request): Response
    {
        $id = $request->int('id');
        $status = $request->str('status');
        if (!in_array($status, ['published', 'draft', 'retired'], true)) {
            $this->session->flash('error', 'Unknown product status.');
            return $this->back($request, '/admin/products');
        }

        $product = $this->db->first('SELECT id, name FROM products WHERE id = ?', [$id]);
        if ($product === null) {
            $this->session->flash('error', 'That product no longer exists.');
            return $this->back($request, '/admin/products');
        }

        $this->db->update('products', ['status' => $status, 'updated_at' => gmdate('c')], 'id = :id', ['id' => $id]);
        $this->cache()->flush();
        $this->auth->log((int) $this->auth->id(), 'product.status', $product['name'] . ' set to ' . $status, $request->ip());
        $this->session->flash('status', $product['name'] . ' is now ' . $status . '.');
        return $this->back($request, '/admin/products');
    }

    public function orders(Request $request): Response
    {
        $status = $request->str('status');
        $where = $status !== '' ? 'WHERE o.status = :status' : '';
        $bindings = $status !== '' ? ['status' => $status] : [];

        return $this->shell($request, 'admin.orders', 'Orders',
            'Marketplace transactions, licences issued and payment state.', [
                'orders' => $this->db->select(
                    "SELECT o.*, COUNT(oi.id) AS items,
                            GROUP_CONCAT(oi.product_name) AS product_names
                     FROM orders o LEFT JOIN order_items oi ON oi.order_id = o.id
                     {$where} GROUP BY o.id ORDER BY o.created_at DESC LIMIT 100",
                    $bindings
                ),
                'statusCounts' => $this->countsBy('orders', 'status'),
                'activeStatus' => $status,
                'revenue' => (float) $this->db->value("SELECT COALESCE(SUM(total),0) FROM orders WHERE status = 'paid'", [], 0),
            ]);
    }

    public function leads(Request $request): Response
    {
        $status = $request->str('status');
        $where = $status !== '' ? 'WHERE l.status = :status' : '';
        $bindings = $status !== '' ? ['status' => $status] : [];

        return $this->shell($request, 'admin.leads', 'Pipeline',
            'Enquiries from the contact form, marketplace and referrals.', [
                'leads' => $this->db->select(
                    "SELECT l.*, u.name AS owner_name FROM leads l
                     LEFT JOIN users u ON u.id = l.owner_id
                     {$where} ORDER BY l.created_at DESC LIMIT 100",
                    $bindings
                ),
                'statusCounts' => $this->countsBy('leads', 'status'),
                'activeStatus' => $status,
                'pipelineValue' => (float) $this->db->value("SELECT COALESCE(SUM(value),0) FROM leads WHERE status NOT IN ('lost','won')", [], 0),
                'topics' => $this->config->get('site.contact_topics', []),
            ]);
    }

    public function updateLeadStatus(Request $request): Response
    {
        return $this->transitionLead($request, '/admin/leads');
    }

    /** Shared by the admin console and the staff workspace. */
    public function transitionLead(Request $request, string $fallback): Response
    {
        $id = $request->int('id');
        $status = $request->str('status');
        $allowed = ['new', 'contacted', 'qualified', 'proposal', 'won', 'lost'];

        if (!in_array($status, $allowed, true)) {
            $this->session->flash('error', 'Unknown pipeline stage.');
            return $this->back($request, $fallback);
        }

        $lead = $this->db->first('SELECT id, name FROM leads WHERE id = ?', [$id]);
        if ($lead === null) {
            $this->session->flash('error', 'That lead no longer exists.');
            return $this->back($request, $fallback);
        }

        $this->db->update('leads', ['status' => $status, 'updated_at' => gmdate('c')], 'id = :id', ['id' => $id]);
        $this->auth->log((int) $this->auth->id(), 'lead.status', $lead['name'] . ' moved to ' . $status, $request->ip());
        $this->session->flash('status', $lead['name'] . ' moved to ' . $status . '.');
        return $this->back($request, $fallback);
    }

    public function deployments(Request $request): Response
    {
        return $this->shell($request, 'admin.deployments', 'Deployments',
            'Every installation launched from a marketplace licence.', [
                'deployments' => $this->db->select(
                    'SELECT d.*, p.name AS product_name, u.name AS client_name, u.company
                     FROM deployments d
                     LEFT JOIN products p ON p.id = d.product_id
                     LEFT JOIN users u ON u.id = d.user_id
                     ORDER BY d.created_at DESC LIMIT 100'
                ),
                'statusCounts' => $this->countsBy('deployments', 'status'),
            ]);
    }

    public function activity(Request $request): Response
    {
        return $this->shell($request, 'admin.activity', 'Activity log',
            'Authentication, catalogue and pipeline events across the platform.', [
                'entries' => $this->db->select(
                    'SELECT a.*, u.name AS user_name, u.role FROM activity_log a
                     LEFT JOIN users u ON u.id = a.user_id
                     ORDER BY a.created_at DESC LIMIT 150'
                ),
                'attempts' => $this->db->select(
                    'SELECT * FROM login_attempts ORDER BY created_at DESC LIMIT 20'
                ),
            ]);
    }

    public function settings(Request $request): Response
    {
        $rows = $this->db->select('SELECT * FROM settings ORDER BY setting_group, setting_key');
        $grouped = [];
        foreach ($rows as $row) {
            $grouped[(string) $row['setting_group']][] = $row;
        }

        return $this->shell($request, 'admin.settings', 'Settings',
            'Platform configuration applied without a deploy.', [
                'grouped' => $grouped,
                'installed' => $this->app->isInstalled(),
                'detectUrl' => (bool) $this->config->get('app.detect_url', true),
                'currentUrl' => (string) $this->config->get('app.url', ''),
                'driver' => $this->db->driver(),
            ]);
    }

    public function saveSettings(Request $request): Response
    {
        $submitted = $request->arr('settings');
        $known = array_column($this->db->select('SELECT setting_key FROM settings'), 'setting_key');
        $saved = 0;

        foreach ($submitted as $key => $value) {
            if (!in_array($key, $known, true)) {
                continue;
            }
            $this->db->update(
                'settings',
                ['setting_value' => is_scalar($value) ? (string) $value : '', 'updated_at' => gmdate('c')],
                'setting_key = :key',
                ['key' => $key]
            );
            $saved++;
        }

        // Checkboxes that were unticked are absent from the payload entirely.
        foreach (['marketplace_enabled', 'lazy_images', 'amp_enabled', 'indexable'] as $toggle) {
            if (!array_key_exists($toggle, $submitted) && in_array($toggle, $known, true)) {
                $this->db->update('settings', ['setting_value' => '0', 'updated_at' => gmdate('c')], 'setting_key = :key', ['key' => $toggle]);
                $saved++;
            }
        }

        $this->cache()->flush();
        $this->auth->log((int) $this->auth->id(), 'settings.update', "Updated {$saved} settings", $request->ip());
        $this->session->flash('status', "Saved {$saved} settings. Caches were cleared.");
        return $this->redirect('/admin/settings');
    }

    /** @return array<string,int> */
    private function countsBy(string $table, string $column): array
    {
        $rows = $this->db->select("SELECT {$column} AS k, COUNT(*) AS total FROM {$table} GROUP BY {$column}");
        $counts = [];
        foreach ($rows as $row) {
            $counts[(string) $row['k']] = (int) $row['total'];
        }
        return $counts;
    }
}
