<?php
declare(strict_types=1);

namespace Techbiss\Controllers\Admin;

use Techbiss\Core\ActivityLog;
use Techbiss\Core\App;
use Techbiss\Core\Paginator;
use Techbiss\Core\Request;
use Techbiss\Core\Validator;
use Techbiss\Repo\PortalRepo;

/** Upgrade/update/maintenance/support requests raised from the client portal. */
final class ClientRequestController extends BaseAdminController
{
    private PortalRepo $repo;

    public function __construct()
    {
        parent::__construct();
        $this->repo = new PortalRepo();
    }

    public function index(Request $request): void
    {
        $this->authorize('client_requests.manage');
        $page    = max(1, $request->queryInt('page', 1));
        $perPage = $this->perPage($request, 25);
        $result  = $this->repo->paginate($page, $perPage, mb_substr($request->queryString('q'), 0, 80), $request->queryString('status'));

        $db = \Techbiss\Core\Database::instance();
        $this->view->render('client_requests/index', [
            'title'     => 'Client requests',
            'rows'      => $result['items'],
            'paginator' => new Paginator($page, $perPage, $result['total']),
            'search'    => $request->queryString('q'),
            'status'    => $request->queryString('status'),
            'counts'    => [
                'all'         => $db->int('SELECT COUNT(*) FROM client_requests'),
                'new'         => $db->int("SELECT COUNT(*) FROM client_requests WHERE status = 'new'"),
                'in_progress' => $db->int("SELECT COUNT(*) FROM client_requests WHERE status = 'in_progress'"),
                'resolved'    => $db->int("SELECT COUNT(*) FROM client_requests WHERE status = 'resolved'"),
            ],
        ]);
    }

    public function show(Request $request, array $params): void
    {
        $this->authorize('client_requests.manage');
        $row = $this->repo->find((int) $params['id']);
        if ($row === null) {
            flash('error', 'Request not found.');
            redirect('/admin/client-requests');
        }
        $this->view->render('client_requests/show', ['title' => 'Request ' . $row['reference'], 'row' => $row]);
    }

    public function update(Request $request, array $params): never
    {
        $this->authorize('client_requests.manage');
        $this->verify($request);
        $id = (int) $params['id'];

        $v = Validator::make($request->all())
            ->in('status', ['new', 'in_progress', 'resolved'], 'Status')
            ->text('admin_notes', 5000, false, 'Notes');

        if ($v->fails()) {
            $this->fail($request, $v->errors(), $v->firstError(), '/admin/client-requests/' . $id);
        }

        $this->repo->updateRow($id, ['status' => $v->get('status'), 'admin_notes' => $v->get('admin_notes', '')]);
        ActivityLog::record('update', 'client_requests', $id, 'Request marked ' . $v->get('status'));
        $this->ok('Request updated.', '/admin/client-requests/' . $id);
    }

    public function destroy(Request $request, array $params): never
    {
        $this->authorize('client_requests.manage');
        $this->verify($request);
        $this->repo->deleteRow((int) $params['id']);
        ActivityLog::record('delete', 'client_requests', (int) $params['id'], 'Deleted client request');
        $this->ok('Request deleted.', '/admin/client-requests');
    }

    public function reply(Request $request, array $params): never
    {
        $this->authorize('client_requests.manage');
        $this->verify($request);
        $id  = (int) $params['id'];
        $row = $this->repo->find($id);
        if ($row === null) {
            flash('error', 'Request not found.');
            redirect('/admin/client-requests');
        }

        $v = Validator::make($request->all())
            ->required('subject', 'Subject', 2, 180)
            ->text('message', 5000, true, 'Message');
        if ($v->fails()) {
            $this->fail($request, $v->errors(), $v->firstError(), '/admin/client-requests/' . $id);
        }

        $sender = App::settings()->get('sales_email') ?: App::settings()->get('contact_email');
        $sent   = App::mailer()->send((string) $row['customer_email'], $v->get('subject'), $v->get('message'), $sender);
        if (!$sent) {
            $this->fail($request, [], 'The message could not be sent. Check the mail settings and try again.', '/admin/client-requests/' . $id);
        }

        ActivityLog::record('email', 'client_requests', $id, 'Emailed client about request ' . $row['reference']);
        $this->ok('Email sent to ' . $row['customer_email'] . '.', '/admin/client-requests/' . $id);
    }
}
