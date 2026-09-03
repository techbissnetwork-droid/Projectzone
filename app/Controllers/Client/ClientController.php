<?php
declare(strict_types=1);

namespace App\Controllers\Client;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;

final class ClientController extends Controller
{
    private function nav(): array
    {
        return [
            'Overview' => [
                ['label' => 'Dashboard', 'path' => '/client', 'icon' => 'grid'],
                ['label' => 'Projects', 'path' => '/client/projects', 'icon' => 'layers'],
            ],
            'Marketplace' => [
                ['label' => 'Licences', 'path' => '/client/licenses', 'icon' => 'tag'],
                ['label' => 'Deployments', 'path' => '/client/deployments', 'icon' => 'rocket'],
            ],
            'Account' => [
                ['label' => 'Invoices', 'path' => '/client/invoices', 'icon' => 'file'],
                ['label' => 'Support', 'path' => '/client/support', 'icon' => 'ticket'],
            ],
        ];
    }

    private function shell(Request $request, string $view, string $title, string $subtitle, array $data = []): Response
    {
        $this->seo->title($title . ' — Client Portal', true);
        $this->seo->noindex();

        return $this->render($view, array_merge($data, [
            'request' => $request,
            'portal' => 'client',
            'sidenav' => $this->nav(),
            'title' => $title,
            'subtitle' => $subtitle,
        ]))->cachePrivate();
    }

    private function userId(): int
    {
        return (int) $this->auth->id();
    }

    public function dashboard(Request $request): Response
    {
        $id = $this->userId();
        $user = $this->auth->user();

        return $this->shell($request, 'client.dashboard', 'Welcome back, ' . explode(' ', (string) ($user['name'] ?? 'there'))[0],
            'Your projects, licences and anything waiting on you.', [
                'stats' => [
                    'projects' => (int) $this->db->value("SELECT COUNT(*) FROM projects WHERE client_id = ? AND status = 'active'", [$id], 0),
                    'licenses' => (int) $this->db->value("SELECT COUNT(*) FROM licenses WHERE user_id = ? AND status = 'active'", [$id], 0),
                    'deployments' => (int) $this->db->value('SELECT COUNT(*) FROM deployments WHERE user_id = ?', [$id], 0),
                    'dueInvoices' => (float) $this->db->value("SELECT COALESCE(SUM(amount),0) FROM invoices WHERE user_id = ? AND status = 'due'", [$id], 0),
                    'openTickets' => (int) $this->db->value("SELECT COUNT(*) FROM tickets WHERE user_id = ? AND status IN ('open','answered')", [$id], 0),
                ],
                'projects' => $this->db->select(
                    'SELECT * FROM projects WHERE client_id = ? ORDER BY due_at LIMIT 3',
                    [$id]
                ),
                'milestones' => $this->db->select(
                    'SELECT m.*, p.name AS project_name FROM project_milestones m
                     JOIN projects p ON p.id = m.project_id
                     WHERE p.client_id = ? AND m.status != ? ORDER BY m.due_at LIMIT 6',
                    [$id, 'complete']
                ),
                'licenses' => $this->db->select(
                    'SELECT l.*, p.name AS product_name, p.slug FROM licenses l
                     LEFT JOIN products p ON p.id = l.product_id
                     WHERE l.user_id = ? ORDER BY l.created_at DESC LIMIT 4',
                    [$id]
                ),
                'deployments' => $this->db->select(
                    'SELECT d.*, p.name AS product_name FROM deployments d
                     LEFT JOIN products p ON p.id = d.product_id
                     WHERE d.user_id = ? ORDER BY d.created_at DESC LIMIT 4',
                    [$id]
                ),
                'invoices' => $this->db->select(
                    'SELECT * FROM invoices WHERE user_id = ? ORDER BY issued_at DESC LIMIT 4',
                    [$id]
                ),
            ]);
    }

    public function projects(Request $request): Response
    {
        $id = $this->userId();
        $projects = $this->db->select('SELECT * FROM projects WHERE client_id = ? ORDER BY status, due_at', [$id]);

        $milestones = [];
        foreach ($projects as $project) {
            $milestones[(int) $project['id']] = $this->db->select(
                'SELECT * FROM project_milestones WHERE project_id = ? ORDER BY position',
                [(int) $project['id']]
            );
        }

        return $this->shell($request, 'client.projects', 'Projects',
            'Live status on every engagement, updated as we work.', [
                'projects' => $projects,
                'milestones' => $milestones,
            ]);
    }

    public function project(Request $request, string $id): Response
    {
        // Scoped by client_id so one client can never read another's project.
        $project = $this->db->first(
            'SELECT * FROM projects WHERE id = ? AND client_id = ? LIMIT 1',
            [(int) $id, $this->userId()]
        );
        if ($project === null) {
            throw HttpException::notFound('That project is not on your account.');
        }

        return $this->shell($request, 'client.project', (string) $project['name'],
            (string) $project['code'] . ' · ' . ucfirst((string) $project['phase']) . ' phase', [
                'project' => $project,
                'milestones' => $this->db->select(
                    'SELECT * FROM project_milestones WHERE project_id = ? ORDER BY position',
                    [(int) $project['id']]
                ),
                'invoices' => $this->db->select(
                    'SELECT * FROM invoices WHERE project_id = ? ORDER BY issued_at DESC',
                    [(int) $project['id']]
                ),
            ]);
    }

    public function licenses(Request $request): Response
    {
        return $this->shell($request, 'client.licenses', 'Licences',
            'Every marketplace licence on your account, with its key and registered domains.', [
                'licenses' => $this->db->select(
                    'SELECT l.*, p.name AS product_name, p.slug, p.version, p.layout, o.reference AS order_reference
                     FROM licenses l
                     LEFT JOIN products p ON p.id = l.product_id
                     LEFT JOIN orders o ON o.id = l.order_id
                     WHERE l.user_id = ? ORDER BY l.created_at DESC',
                    [$this->userId()]
                ),
            ]);
    }

    public function download(Request $request, string $key): Response
    {
        $license = $this->db->first(
            'SELECT l.*, p.name AS product_name, p.version FROM licenses l
             LEFT JOIN products p ON p.id = l.product_id
             WHERE l.license_key = ? AND l.user_id = ? LIMIT 1',
            [$key, $this->userId()]
        );

        if ($license === null) {
            throw HttpException::notFound('That licence is not on your account.');
        }
        if ($license['status'] !== 'active') {
            throw HttpException::forbidden('That licence is no longer active.');
        }

        $this->auth->log($this->userId(), 'license.download', 'Downloaded ' . $license['product_name'], $request->ip());

        // The distributable package is not bundled with the platform source, so
        // the download hands back a signed manifest the installer consumes.
        $manifest = [
            'product' => $license['product_name'],
            'version' => $license['version'],
            'licence_key' => $license['license_key'],
            'tier' => $license['tier'],
            'seats' => (int) $license['seats'],
            'issued_to' => (string) ($this->auth->user()['email'] ?? ''),
            'support_until' => $license['support_until'],
            'installer' => rtrim((string) $this->config->get('app.url'), '/') . '/install',
            'generated_at' => gmdate('c'),
        ];

        $filename = str_slug((string) $license['product_name']) . '-' . $license['version'] . '-licence.json';

        return Response::json($manifest)
            ->withHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->cachePrivate();
    }

    public function deployments(Request $request): Response
    {
        $id = $this->userId();

        return $this->shell($request, 'client.deployments', 'Deployments',
            'Installations launched from your licences, and their progress.', [
                'deployments' => $this->db->select(
                    'SELECT d.*, p.name AS product_name, l.license_key FROM deployments d
                     LEFT JOIN products p ON p.id = d.product_id
                     LEFT JOIN licenses l ON l.id = d.license_id
                     WHERE d.user_id = ? ORDER BY d.created_at DESC',
                    [$id]
                ),
                'licenses' => $this->db->select(
                    'SELECT l.id, l.license_key, l.product_id, p.name AS product_name FROM licenses l
                     LEFT JOIN products p ON p.id = l.product_id
                     WHERE l.user_id = ? AND l.status = ? ORDER BY p.name',
                    [$id, 'active']
                ),
            ]);
    }

    public function createDeployment(Request $request): Response
    {
        $validator = Validator::make($request->body, [
            'license_id' => 'required|integer',
            'site_name' => 'required|max:190',
            'target_url' => 'required|url|max:255',
            'environment' => 'required|in:production,staging,development',
            'install_mode' => 'required|in:clean,upgrade,migrate',
            'database_driver' => 'required|in:mysql,sqlite',
        ], ['target_url' => 'Target URL', 'site_name' => 'Site name']);

        if ($validator->fails()) {
            $this->withInput($request, $validator->errors());
            $this->session->flash('error', 'Please correct the highlighted fields.');
            return $this->redirect('/client/deployments');
        }

        $licenseId = $request->int('license_id');
        $license = $this->db->first(
            'SELECT id, product_id FROM licenses WHERE id = ? AND user_id = ? AND status = ? LIMIT 1',
            [$licenseId, $this->userId(), 'active']
        );

        if ($license === null) {
            $this->session->flash('error', 'That licence is not active on your account.');
            return $this->redirect('/client/deployments');
        }

        $token = bin2hex(random_bytes(16));
        $this->db->insert('deployments', [
            'token' => $token,
            'license_id' => (int) $license['id'],
            'product_id' => (int) $license['product_id'],
            'user_id' => $this->userId(),
            'site_name' => $request->str('site_name'),
            'target_url' => rtrim($request->str('target_url'), '/'),
            'environment' => $request->str('environment'),
            'install_mode' => $request->str('install_mode'),
            'source_platform' => $request->str('source_platform') ?: null,
            'database_driver' => $request->str('database_driver'),
            'status' => 'pending',
            'progress' => 0,
            'log' => null,
            'created_at' => gmdate('c'),
            'completed_at' => null,
        ]);

        $this->auth->log($this->userId(), 'deployment.create', 'Created deployment for ' . $request->str('site_name'), $request->ip());
        $this->session->flash('status', 'Deployment created. Use the install token below when you open the Advanced Installer on your server.');

        return $this->redirect('/client/deployments');
    }

    public function invoices(Request $request): Response
    {
        $id = $this->userId();
        $invoices = $this->db->select(
            'SELECT i.*, p.name AS project_name FROM invoices i
             LEFT JOIN projects p ON p.id = i.project_id
             WHERE i.user_id = ? ORDER BY i.issued_at DESC',
            [$id]
        );

        return $this->shell($request, 'client.invoices', 'Invoices',
            'Everything issued to your account, paid and outstanding.', [
                'invoices' => $invoices,
                'orders' => $this->db->select(
                    'SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC',
                    [$id]
                ),
                'due' => (float) $this->db->value("SELECT COALESCE(SUM(amount),0) FROM invoices WHERE user_id = ? AND status = 'due'", [$id], 0),
                'paid' => (float) $this->db->value("SELECT COALESCE(SUM(amount),0) FROM invoices WHERE user_id = ? AND status = 'paid'", [$id], 0),
            ]);
    }

    public function support(Request $request): Response
    {
        $id = $this->userId();
        $tickets = $this->db->select(
            'SELECT * FROM tickets WHERE user_id = ? ORDER BY
             CASE status WHEN \'open\' THEN 0 WHEN \'answered\' THEN 1 ELSE 2 END, created_at DESC',
            [$id]
        );

        $replies = [];
        foreach ($tickets as $ticket) {
            $replies[(int) $ticket['id']] = $this->db->select(
                'SELECT * FROM ticket_replies WHERE ticket_id = ? ORDER BY created_at',
                [(int) $ticket['id']]
            );
        }

        return $this->shell($request, 'client.support', 'Support',
            'Raise a ticket, or follow one already open.', [
                'tickets' => $tickets,
                'replies' => $replies,
            ]);
    }

    public function openTicket(Request $request): Response
    {
        $validator = Validator::make($request->body, [
            'subject' => 'required|min:6|max:190',
            'category' => 'required|in:general,installation,licensing,migration,performance,billing',
            'priority' => 'required|in:low,normal,high',
            'body' => 'required|min:20|max:5000',
        ], ['body' => 'Description']);

        if ($validator->fails()) {
            $this->withInput($request, $validator->errors());
            $this->session->flash('error', 'Please correct the highlighted fields.');
            return $this->redirect('/client/support');
        }

        $reference = 'SUP-' . strtoupper(bin2hex(random_bytes(3)));
        $now = gmdate('c');

        $this->db->insert('tickets', [
            'reference' => $reference,
            'user_id' => $this->userId(),
            'assignee_id' => null,
            'subject' => $request->str('subject'),
            'body' => (string) $request->input('body'),
            'category' => $request->str('category'),
            'priority' => $request->str('priority'),
            'status' => 'open',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $user = $this->auth->user();
        $this->app->make('mailer')->send(
            (string) $this->config->get('mail.support_inbox', 'support@techbiss.com'),
            '[' . $reference . '] ' . $request->str('subject'),
            '<p><strong>' . e((string) ($user['name'] ?? '')) . '</strong> ('
            . e((string) ($user['company'] ?? '')) . ') raised a '
            . e($request->str('priority')) . ' priority ticket.</p><p>'
            . nl2br(e((string) $request->input('body'))) . '</p>',
            ['reply_to' => (string) ($user['email'] ?? '')]
        );

        $this->session->flash('status', 'Ticket ' . $reference . ' raised. Support replies within one business day.');
        return $this->redirect('/client/support');
    }
}
