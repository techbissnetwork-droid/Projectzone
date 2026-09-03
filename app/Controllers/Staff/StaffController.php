<?php
declare(strict_types=1);

namespace App\Controllers\Staff;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;

final class StaffController extends Controller
{
    private function nav(): array
    {
        return [
            'Work' => [
                ['label' => 'Today', 'path' => '/staff', 'icon' => 'grid'],
                ['label' => 'My tasks', 'path' => '/staff/tasks', 'icon' => 'check-circle'],
            ],
            'Delivery' => [
                ['label' => 'Projects', 'path' => '/staff/projects', 'icon' => 'layers'],
                ['label' => 'Tickets', 'path' => '/staff/tickets', 'icon' => 'ticket'],
            ],
            'Commercial' => [
                ['label' => 'Pipeline', 'path' => '/staff/pipeline', 'icon' => 'trend'],
            ],
        ];
    }

    private function shell(Request $request, string $view, string $title, string $subtitle, array $data = []): Response
    {
        $this->seo->title($title . ' — Staff Workspace', true);
        $this->seo->noindex();

        return $this->render($view, array_merge($data, [
            'request' => $request,
            'portal' => 'staff',
            'sidenav' => $this->nav(),
            'title' => $title,
            'subtitle' => $subtitle,
        ]))->cachePrivate();
    }

    public function dashboard(Request $request): Response
    {
        $userId = (int) $this->auth->id();
        $today = gmdate('c');

        $myTasks = $this->db->select(
            "SELECT * FROM tasks WHERE assignee_id = ? AND status = 'open' ORDER BY
             CASE priority WHEN 'high' THEN 0 WHEN 'normal' THEN 1 ELSE 2 END, due_at LIMIT 8",
            [$userId]
        );

        return $this->shell($request, 'staff.dashboard', 'Today',
            'What needs you, and where the portfolio stands.', [
                'myTasks' => $myTasks,
                'overdue' => count(array_filter($myTasks, static fn ($t) => $t['due_at'] && $t['due_at'] < $today)),
                'stats' => [
                    'openTasks' => (int) $this->db->value("SELECT COUNT(*) FROM tasks WHERE assignee_id = ? AND status = 'open'", [$userId], 0),
                    'myLeads' => (int) $this->db->value("SELECT COUNT(*) FROM leads WHERE owner_id = ? AND status NOT IN ('won','lost')", [$userId], 0),
                    'openTickets' => (int) $this->db->value("SELECT COUNT(*) FROM tickets WHERE status IN ('open','answered')", [], 0),
                    'activeProjects' => (int) $this->db->value("SELECT COUNT(*) FROM projects WHERE status = 'active'", [], 0),
                ],
                'projects' => $this->db->select(
                    "SELECT p.*, u.name AS client_name, u.company FROM projects p
                     LEFT JOIN users u ON u.id = p.client_id
                     WHERE p.status = 'active' ORDER BY p.due_at LIMIT 5"
                ),
                'tickets' => $this->db->select(
                    "SELECT t.*, u.name AS client_name, u.company FROM tickets t
                     LEFT JOIN users u ON u.id = t.user_id
                     WHERE t.status IN ('open','answered')
                     ORDER BY CASE t.priority WHEN 'high' THEN 0 WHEN 'normal' THEN 1 ELSE 2 END, t.created_at LIMIT 5"
                ),
                'leads' => $this->db->select(
                    "SELECT * FROM leads WHERE owner_id = ? AND status NOT IN ('won','lost')
                     ORDER BY created_at DESC LIMIT 5",
                    [$userId]
                ),
            ]);
    }

    public function tasks(Request $request): Response
    {
        $userId = (int) $this->auth->id();
        $scope = $request->str('scope', 'mine');

        $where = $scope === 'all' ? '1 = 1' : 'assignee_id = :uid';
        $bindings = $scope === 'all' ? [] : ['uid' => $userId];

        return $this->shell($request, 'staff.tasks', 'Tasks',
            $scope === 'all' ? 'Every open task across the team.' : 'Assigned to you, ordered by priority and due date.', [
                'tasks' => $this->db->select(
                    "SELECT t.*, u.name AS assignee_name FROM tasks t
                     LEFT JOIN users u ON u.id = t.assignee_id
                     WHERE {$where}
                     ORDER BY CASE t.status WHEN 'open' THEN 0 ELSE 1 END,
                              CASE t.priority WHEN 'high' THEN 0 WHEN 'normal' THEN 1 ELSE 2 END,
                              t.due_at",
                    $bindings
                ),
                'scope' => $scope,
            ]);
    }

    public function toggleTask(Request $request): Response
    {
        $id = $request->int('id');
        $task = $this->db->first('SELECT id, title, status, assignee_id FROM tasks WHERE id = ?', [$id]);

        if ($task === null) {
            $this->session->flash('error', 'That task no longer exists.');
            return $this->back($request, '/staff/tasks');
        }

        $next = $task['status'] === 'open' ? 'done' : 'open';
        $this->db->update('tasks', ['status' => $next], 'id = :id', ['id' => $id]);
        $this->session->flash('status', '“' . $task['title'] . '” marked ' . $next . '.');
        return $this->back($request, '/staff/tasks');
    }

    public function projects(Request $request): Response
    {
        $projects = $this->db->select(
            'SELECT p.*, u.name AS client_name, u.company, l.name AS lead_name FROM projects p
             LEFT JOIN users u ON u.id = p.client_id
             LEFT JOIN users l ON l.id = p.lead_id
             ORDER BY CASE p.health WHEN \'red\' THEN 0 WHEN \'amber\' THEN 1 ELSE 2 END, p.due_at'
        );

        $milestones = [];
        foreach ($projects as $project) {
            $milestones[(int) $project['id']] = $this->db->select(
                'SELECT * FROM project_milestones WHERE project_id = ? ORDER BY position',
                [(int) $project['id']]
            );
        }

        return $this->shell($request, 'staff.projects', 'Projects',
            'Phase, health and milestone status across the portfolio.', [
                'projects' => $projects,
                'milestones' => $milestones,
            ]);
    }

    public function tickets(Request $request): Response
    {
        $status = $request->str('status');
        $where = $status !== '' ? 'WHERE t.status = :status' : '';
        $bindings = $status !== '' ? ['status' => $status] : [];

        $tickets = $this->db->select(
            "SELECT t.*, u.name AS client_name, u.company FROM tickets t
             LEFT JOIN users u ON u.id = t.user_id
             {$where}
             ORDER BY CASE t.status WHEN 'open' THEN 0 WHEN 'answered' THEN 1 ELSE 2 END,
                      CASE t.priority WHEN 'high' THEN 0 WHEN 'normal' THEN 1 ELSE 2 END,
                      t.created_at DESC",
            $bindings
        );

        $replies = [];
        foreach ($tickets as $ticket) {
            $replies[(int) $ticket['id']] = $this->db->select(
                'SELECT * FROM ticket_replies WHERE ticket_id = ? ORDER BY created_at',
                [(int) $ticket['id']]
            );
        }

        $counts = [];
        foreach ($this->db->select('SELECT status, COUNT(*) AS total FROM tickets GROUP BY status') as $row) {
            $counts[(string) $row['status']] = (int) $row['total'];
        }

        return $this->shell($request, 'staff.tickets', 'Support queue',
            'Client tickets, triaged by status age and priority.', [
                'tickets' => $tickets,
                'replies' => $replies,
                'statusCounts' => $counts,
                'activeStatus' => $status,
            ]);
    }

    public function replyTicket(Request $request): Response
    {
        $validator = Validator::make($request->body, [
            'ticket_id' => 'required|integer',
            'body' => 'required|min:5|max:5000',
            'status' => 'required|in:open,answered,resolved',
        ], ['body' => 'Reply']);

        if ($validator->fails()) {
            $this->withInput($request, $validator->errors());
            $this->session->flash('error', 'Write a reply of at least five characters.');
            return $this->back($request, '/staff/tickets');
        }

        $ticketId = (int) $request->int('ticket_id');
        $ticket = $this->db->first('SELECT id, reference, user_id FROM tickets WHERE id = ?', [$ticketId]);
        if ($ticket === null) {
            $this->session->flash('error', 'That ticket no longer exists.');
            return $this->back($request, '/staff/tickets');
        }

        $user = $this->auth->user();
        $now = gmdate('c');

        $this->db->insert('ticket_replies', [
            'ticket_id' => $ticketId,
            'user_id' => (int) $this->auth->id(),
            'author_name' => (string) ($user['name'] ?? 'TECHBISS Support'),
            'body' => (string) $request->input('body'),
            'created_at' => $now,
        ]);

        $this->db->update('tickets', [
            'status' => $request->str('status'),
            'assignee_id' => (int) $this->auth->id(),
            'updated_at' => $now,
        ], 'id = :id', ['id' => $ticketId]);

        $this->session->flash('status', 'Reply posted on ' . $ticket['reference'] . '.');
        return $this->back($request, '/staff/tickets');
    }

    public function pipeline(Request $request): Response
    {
        $stages = ['new', 'contacted', 'qualified', 'proposal', 'won', 'lost'];
        $board = array_fill_keys($stages, []);

        foreach ($this->db->select(
            'SELECT l.*, u.name AS owner_name FROM leads l
             LEFT JOIN users u ON u.id = l.owner_id ORDER BY l.value DESC, l.created_at DESC'
        ) as $lead) {
            $stage = (string) $lead['status'];
            if (isset($board[$stage])) {
                $board[$stage][] = $lead;
            }
        }

        return $this->shell($request, 'staff.pipeline', 'Pipeline',
            'Every enquiry by stage, with owner and value.', [
                'board' => $board,
                'stages' => $stages,
                'topics' => $this->config->get('site.contact_topics', []),
            ]);
    }

    public function updateLeadStatus(Request $request): Response
    {
        return (new \App\Controllers\Admin\AdminController($this->app))
            ->transitionLead($request, '/staff/pipeline');
    }
}
