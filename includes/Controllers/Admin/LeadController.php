<?php
declare(strict_types=1);

namespace Techbiss\Controllers\Admin;

use Techbiss\Core\ActivityLog;
use Techbiss\Core\Paginator;
use Techbiss\Core\Request;
use Techbiss\Core\Validator;
use Techbiss\Repo\LeadRepo;

/** Contact messages, quote/journey requests and newsletter subscribers. */
final class LeadController extends BaseAdminController
{
    private LeadRepo $repo;

    public function __construct()
    {
        parent::__construct();
        $this->repo = new LeadRepo();
    }

    // -----------------------------------------------------------------
    // Contact messages
    // -----------------------------------------------------------------
    public function messages(Request $request): void
    {
        $this->authorizeAny(['leads.view', 'leads.manage']);
        $page    = max(1, $request->queryInt('page', 1));
        $perPage = $this->perPage($request, 25);
        $result  = $this->repo->messages($page, $perPage, mb_substr($request->queryString('q'), 0, 80), $request->queryString('status'));

        $db = \Techbiss\Core\Database::instance();
        $this->view->render('leads/messages', [
            'title'     => 'Contact messages',
            'rows'      => $result['items'],
            'paginator' => new Paginator($page, $perPage, $result['total']),
            'search'    => $request->queryString('q'),
            'status'    => $request->queryString('status'),
            'counts'    => [
                'all'      => $db->int('SELECT COUNT(*) FROM contact_messages'),
                'new'      => $db->int("SELECT COUNT(*) FROM contact_messages WHERE status = 'new'"),
                'read'     => $db->int("SELECT COUNT(*) FROM contact_messages WHERE status = 'read'"),
                'replied'  => $db->int("SELECT COUNT(*) FROM contact_messages WHERE status = 'replied'"),
                'archived' => $db->int("SELECT COUNT(*) FROM contact_messages WHERE status = 'archived'"),
                'spam'     => $db->int("SELECT COUNT(*) FROM contact_messages WHERE status = 'spam'"),
            ],
        ]);
    }

    public function showMessage(Request $request, array $params): void
    {
        $this->authorizeAny(['leads.view', 'leads.manage']);
        $id  = (int) $params['id'];
        $row = $this->repo->message($id);
        if ($row === null) {
            flash('error', 'Message not found.');
            redirect('/admin/messages');
        }
        // Opening an unread message marks it read.
        if ((string) $row['status'] === 'new' && \Techbiss\Core\Auth::can('leads.manage')) {
            $this->repo->updateMessage($id, ['status' => 'read']);
            $row['status'] = 'read';
        }
        $this->view->render('leads/message', ['title' => 'Message from ' . $row['name'], 'row' => $row]);
    }

    public function updateMessage(Request $request, array $params): never
    {
        $this->authorize('leads.manage');
        $this->verify($request);
        $id = (int) $params['id'];

        $v = Validator::make($request->all())
            ->in('status', ['new', 'read', 'replied', 'archived', 'spam'], 'Status')
            ->text('admin_notes', 5000, false, 'Notes');

        if ($v->fails()) {
            $this->fail($request, $v->errors(), $v->firstError(), '/admin/messages/' . $id);
        }

        $this->repo->updateMessage($id, ['status' => $v->get('status'), 'admin_notes' => $v->get('admin_notes', '')]);
        ActivityLog::record('update', 'contact_messages', $id, 'Message marked ' . $v->get('status'));
        $this->ok('Message updated.', '/admin/messages/' . $id);
    }

    public function destroyMessage(Request $request, array $params): never
    {
        $this->authorize('leads.manage');
        $this->verify($request);
        $this->repo->deleteMessage((int) $params['id']);
        ActivityLog::record('delete', 'contact_messages', (int) $params['id'], 'Deleted contact message');
        $this->ok('Message deleted.', '/admin/messages');
    }

    public function exportMessages(Request $request): never
    {
        $this->authorize('export.manage');
        ActivityLog::record('export', 'contact_messages', null, 'Exported contact messages');
        $this->csv('techbiss-messages-' . date('Y-m-d') . '.csv', $this->repo->exportMessages($request->queryString('status')));
    }

    // -----------------------------------------------------------------
    // Quote & journey requests
    // -----------------------------------------------------------------
    public function quotes(Request $request): void
    {
        $this->authorizeAny(['leads.view', 'leads.manage']);
        $page    = max(1, $request->queryInt('page', 1));
        $perPage = $this->perPage($request, 25);
        $result  = $this->repo->quotes(
            $page,
            $perPage,
            mb_substr($request->queryString('q'), 0, 80),
            $request->queryString('status'),
            $request->queryString('source')
        );

        $db = \Techbiss\Core\Database::instance();
        $this->view->render('leads/quotes', [
            'title'     => 'Quote requests',
            'rows'      => $result['items'],
            'paginator' => new Paginator($page, $perPage, $result['total']),
            'search'    => $request->queryString('q'),
            'status'    => $request->queryString('status'),
            'source'    => $request->queryString('source'),
            'counts'    => [
                'all'       => $db->int('SELECT COUNT(*) FROM quote_requests'),
                'new'       => $db->int("SELECT COUNT(*) FROM quote_requests WHERE status = 'new'"),
                'reviewing' => $db->int("SELECT COUNT(*) FROM quote_requests WHERE status = 'reviewing'"),
                'quoted'    => $db->int("SELECT COUNT(*) FROM quote_requests WHERE status = 'quoted'"),
                'won'       => $db->int("SELECT COUNT(*) FROM quote_requests WHERE status = 'won'"),
                'lost'      => $db->int("SELECT COUNT(*) FROM quote_requests WHERE status = 'lost'"),
            ],
        ]);
    }

    public function showQuote(Request $request, array $params): void
    {
        $this->authorizeAny(['leads.view', 'leads.manage']);
        $row = $this->repo->quote((int) $params['id']);
        if ($row === null) {
            flash('error', 'Request not found.');
            redirect('/admin/quotes');
        }
        $this->view->render('leads/quote', ['title' => 'Request ' . $row['reference'], 'row' => $row]);
    }

    public function updateQuote(Request $request, array $params): never
    {
        $this->authorize('leads.manage');
        $this->verify($request);
        $id = (int) $params['id'];

        $v = Validator::make($request->all())
            ->in('status', ['new', 'reviewing', 'quoted', 'won', 'lost', 'archived'], 'Status')
            ->in('priority', ['low', 'normal', 'high'], 'Priority')
            ->decimal('estimated_value', 'Estimated value')
            ->text('admin_notes', 5000, false, 'Notes');

        if ($v->fails()) {
            $this->fail($request, $v->errors(), $v->firstError(), '/admin/quotes/' . $id);
        }

        $this->repo->updateQuote($id, [
            'status'          => $v->get('status'),
            'priority'        => $v->get('priority'),
            'estimated_value' => $v->get('estimated_value'),
            'admin_notes'     => $v->get('admin_notes', ''),
        ]);
        ActivityLog::record('update', 'quote_requests', $id, 'Request marked ' . $v->get('status'));
        $this->ok('Request updated.', '/admin/quotes/' . $id);
    }

    public function destroyQuote(Request $request, array $params): never
    {
        $this->authorize('leads.manage');
        $this->verify($request);
        $this->repo->deleteQuote((int) $params['id']);
        ActivityLog::record('delete', 'quote_requests', (int) $params['id'], 'Deleted quote request');
        $this->ok('Request deleted.', '/admin/quotes');
    }

    public function exportQuotes(Request $request): never
    {
        $this->authorize('export.manage');
        ActivityLog::record('export', 'quote_requests', null, 'Exported quote requests');
        $this->csv('techbiss-quotes-' . date('Y-m-d') . '.csv', $this->repo->exportQuotes($request->queryString('status')));
    }

    // -----------------------------------------------------------------
    // Newsletter subscribers
    // -----------------------------------------------------------------
    public function subscribers(Request $request): void
    {
        $this->authorizeAny(['leads.view', 'leads.manage']);
        $page    = max(1, $request->queryInt('page', 1));
        $perPage = $this->perPage($request, 50);
        $result  = $this->repo->subscribers($page, $perPage, mb_substr($request->queryString('q'), 0, 80), $request->queryString('status'));

        $this->view->render('leads/subscribers', [
            'title'     => 'Newsletter subscribers',
            'rows'      => $result['items'],
            'paginator' => new Paginator($page, $perPage, $result['total']),
            'search'    => $request->queryString('q'),
            'status'    => $request->queryString('status'),
        ]);
    }

    public function updateSubscriber(Request $request, array $params): never
    {
        $this->authorize('leads.manage');
        $this->verify($request);
        $status = $request->str('status') === 'unsubscribed' ? 'unsubscribed' : 'subscribed';
        $this->repo->setSubscriberStatus((int) $params['id'], $status);
        ActivityLog::record('update', 'newsletter_subscribers', (int) $params['id'], 'Subscriber marked ' . $status);
        $this->back('/admin/subscribers');
    }

    public function destroySubscriber(Request $request, array $params): never
    {
        $this->authorize('leads.manage');
        $this->verify($request);
        $this->repo->deleteSubscriber((int) $params['id']);
        ActivityLog::record('delete', 'newsletter_subscribers', (int) $params['id'], 'Deleted subscriber');
        $this->ok('Subscriber removed.', '/admin/subscribers');
    }

    public function exportSubscribers(Request $request): never
    {
        $this->authorize('export.manage');
        ActivityLog::record('export', 'newsletter_subscribers', null, 'Exported subscribers');
        $this->csv('techbiss-subscribers-' . date('Y-m-d') . '.csv', $this->repo->exportSubscribers());
    }
}
