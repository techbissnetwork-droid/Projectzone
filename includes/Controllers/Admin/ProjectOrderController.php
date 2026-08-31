<?php
declare(strict_types=1);

namespace Techbiss\Controllers\Admin;

use Techbiss\Core\ActivityLog;
use Techbiss\Core\Paginator;
use Techbiss\Core\Request;
use Techbiss\Core\Validator;
use Techbiss\Repo\ProjectOrderRepo;

/**
 * Enquiries about premade projects.
 *
 * The price is agreed in conversation, so the quoted amount is entered here
 * afterwards rather than coming from the website. Payment status is likewise
 * set by hand, once money has actually arrived.
 */
final class ProjectOrderController extends BaseAdminController
{
    private ProjectOrderRepo $repo;

    public function __construct()
    {
        parent::__construct();
        $this->repo = new ProjectOrderRepo();
    }

    public function index(Request $request): void
    {
        $this->authorize('project_orders.manage');
        $page    = max(1, $request->queryInt('page', 1));
        $perPage = $this->perPage($request, 25);
        $result  = $this->repo->paginate(
            $page,
            $perPage,
            mb_substr($request->queryString('q'), 0, 80),
            $request->queryString('payment'),
            $request->queryString('status')
        );

        $this->view->render('project-orders/index', [
            'title'         => 'Project enquiries',
            'rows'          => $result['items'],
            'paginator'     => new Paginator($page, $perPage, $result['total']),
            'summary'       => $this->repo->summary(),
            'search'        => $request->queryString('q'),
            'paymentFilter' => $request->queryString('payment'),
            'statusFilter'  => $request->queryString('status'),
        ]);
    }

    public function show(Request $request, array $params): void
    {
        $this->authorize('project_orders.manage');
        $row = $this->repo->find((int) $params['id']);
        if ($row === null) {
            flash('error', 'Enquiry not found.');
            redirect('/admin/project-orders');
        }
        $this->view->render('project-orders/show', [
            'title' => 'Enquiry ' . $row['reference'],
            'row'   => $row,
        ]);
    }

    public function update(Request $request, array $params): never
    {
        $this->authorize('project_orders.manage');
        $this->verify($request);
        $id = (int) $params['id'];

        $existing = $this->repo->find($id);
        if ($existing === null) {
            flash('error', 'Enquiry not found.');
            redirect('/admin/project-orders');
        }

        $v = Validator::make($request->all())
            ->in('payment_status', ProjectOrderRepo::PAYMENT_STATUSES, 'Payment status')
            ->in('order_status', ProjectOrderRepo::ORDER_STATUSES, 'Order status')
            ->optional('payment_reference', 120)
            ->optional('domain_name', 190)
            ->optional('currency', 6)
            ->text('admin_notes', 5000, false, 'Notes');

        if ($v->fails()) {
            $this->fail($request, $v->errors(), $v->firstError(), '/admin/project-orders/' . $id);
        }

        // Left blank, the quote stays unset rather than becoming a misleading 0.
        $quotedRaw = trim($request->str('quoted_amount'));
        $quoted    = $quotedRaw === '' ? null : round((float) $quotedRaw, 2);

        $data = [
            'payment_status'    => $v->get('payment_status'),
            'order_status'      => $v->get('order_status'),
            'quoted_amount'     => $quoted,
            'currency'          => mb_substr(strtoupper($v->get('currency', '') ?: (string) $existing['currency']), 0, 6),
            'payment_reference' => $v->get('payment_reference', ''),
            'domain_name'       => $v->get('domain_name', ''),
            'admin_notes'       => $v->get('admin_notes', ''),
        ];

        // Delivery is stamped the first time an enquiry is marked delivered, and
        // cleared again if it is moved back — the date always reflects reality.
        if ($data['order_status'] === 'delivered' && (string) $existing['order_status'] !== 'delivered') {
            $data['delivered_at'] = date('Y-m-d H:i:s');
        } elseif ($data['order_status'] !== 'delivered') {
            $data['delivered_at'] = null;
        }

        $this->repo->update($id, $data);
        ActivityLog::record(
            'update',
            'project_orders',
            $id,
            'Updated enquiry ' . $existing['reference'] . ' → ' . $data['payment_status'] . '/' . $data['order_status']
        );
        $this->ok('Enquiry updated.', '/admin/project-orders/' . $id);
    }

    public function destroy(Request $request, array $params): never
    {
        $this->authorize('project_orders.manage');
        $this->verify($request);
        $id  = (int) $params['id'];
        $row = $this->repo->find($id);
        $this->repo->delete($id);
        ActivityLog::record('delete', 'project_orders', $id, 'Deleted enquiry ' . ($row['reference'] ?? $id));
        $this->ok('Enquiry deleted.', '/admin/project-orders');
    }

    public function export(Request $request): never
    {
        $this->authorize('export.manage');
        ActivityLog::record('export', 'project_orders', null, 'Exported project enquiries to CSV');
        $this->csv('techbiss-project-enquiries-' . date('Y-m-d') . '.csv', $this->repo->export());
    }
}
