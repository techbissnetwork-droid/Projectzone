<?php
declare(strict_types=1);

namespace Techbiss\Controllers\Admin;

use Techbiss\Core\ActivityLog;
use Techbiss\Core\Paginator;
use Techbiss\Core\Request;
use Techbiss\Core\Validator;
use Techbiss\Repo\PurchaseRepo;

/** Prepaid package purchases: lifecycle, payment status and manual extensions. */
final class PurchaseController extends BaseAdminController
{
    private PurchaseRepo $repo;

    public function __construct()
    {
        parent::__construct();
        $this->repo = new PurchaseRepo();
    }

    public function index(Request $request): void
    {
        $this->authorize('purchases.manage');
        $this->repo->refreshStatuses();

        $page    = max(1, $request->queryInt('page', 1));
        $perPage = $this->perPage($request, 25);
        $result  = $this->repo->paginate(
            $page,
            $perPage,
            mb_substr($request->queryString('q'), 0, 80),
            $request->queryString('payment'),
            $request->queryString('status')
        );

        $this->view->render('purchases/index', [
            'title'         => 'Purchases',
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
        $this->authorize('purchases.manage');
        $row = $this->repo->find((int) $params['id']);
        if ($row === null) {
            flash('error', 'Purchase not found.');
            redirect('/admin/purchases');
        }
        $this->view->render('purchases/show', ['title' => 'Purchase ' . $row['reference'], 'row' => $row]);
    }

    public function update(Request $request, array $params): never
    {
        $this->authorize('purchases.manage');
        $this->verify($request);
        $id = (int) $params['id'];

        $existing = $this->repo->find($id);
        if ($existing === null) {
            flash('error', 'Purchase not found.');
            redirect('/admin/purchases');
        }

        $v = Validator::make($request->all())
            ->in('payment_status', PurchaseRepo::PAYMENT_STATUSES, 'Payment status')
            ->in('package_status', PurchaseRepo::PACKAGE_STATUSES, 'Package status')
            ->in('renewal_status', PurchaseRepo::RENEWAL_STATUSES, 'Renewal status')
            ->date('starts_at')
            ->date('expires_at')
            ->optional('payment_reference', 120)
            ->text('admin_notes', 5000, false, 'Notes');

        if ($v->fails()) {
            $this->fail($request, $v->errors(), $v->firstError(), '/admin/purchases/' . $id);
        }

        $data = [
            'payment_status'    => $v->get('payment_status'),
            'package_status'    => $v->get('package_status'),
            'renewal_status'    => $v->get('renewal_status'),
            'starts_at'         => $v->get('starts_at'),
            'expires_at'        => $v->get('expires_at'),
            'payment_reference' => $v->get('payment_reference', ''),
            'admin_notes'       => $v->get('admin_notes', ''),
        ];

        // Marking a purchase paid activates it and sets its term, once.
        if ($data['payment_status'] === 'paid' && (string) $existing['payment_status'] !== 'paid') {
            if ($data['starts_at'] === null) {
                $data['starts_at'] = date('Y-m-d');
            }
            if ($data['expires_at'] === null) {
                $months = max(1, (int) $existing['duration_months']);
                $data['expires_at'] = \Techbiss\Core\Dates::addMonths((string) $data['starts_at'], $months);
            }
            if ($data['package_status'] === 'pending') {
                $data['package_status'] = 'active';
            }
        }

        $this->repo->update($id, $data);
        ActivityLog::record('update', 'purchases', $id, 'Updated purchase ' . $existing['reference'] . ' → ' . $data['payment_status'] . '/' . $data['package_status']);
        $this->ok('Purchase updated.', '/admin/purchases/' . $id);
    }

    public function extend(Request $request, array $params): never
    {
        $this->authorize('purchases.manage');
        $this->verify($request);
        $id     = (int) $params['id'];
        $months = max(1, min(120, $request->int('months', 12)));

        if (!$this->repo->extend($id, $months)) {
            flash('error', 'That purchase could not be extended.');
            redirect('/admin/purchases/' . $id);
        }
        ActivityLog::record('extend', 'purchases', $id, 'Extended validity by ' . $months . ' months');
        $this->ok('Validity extended by ' . $months . ' month' . ($months === 1 ? '' : 's') . '.', '/admin/purchases/' . $id);
    }

    public function destroy(Request $request, array $params): never
    {
        $this->authorize('purchases.manage');
        $this->verify($request);
        $id  = (int) $params['id'];
        $row = $this->repo->find($id);
        $this->repo->delete($id);
        ActivityLog::record('delete', 'purchases', $id, 'Deleted purchase ' . ($row['reference'] ?? $id));
        $this->ok('Purchase record deleted.', '/admin/purchases');
    }

    public function export(Request $request): never
    {
        $this->authorize('export.manage');
        ActivityLog::record('export', 'purchases', null, 'Exported purchases to CSV');
        $this->csv('techbiss-purchases-' . date('Y-m-d') . '.csv', $this->repo->export());
    }
}
