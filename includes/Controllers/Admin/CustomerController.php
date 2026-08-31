<?php
declare(strict_types=1);

namespace Techbiss\Controllers\Admin;

use Techbiss\Core\ActivityLog;
use Techbiss\Core\Paginator;
use Techbiss\Core\Request;
use Techbiss\Core\Validator;
use Techbiss\Repo\CustomerRepo;
use Techbiss\Repo\IndustryRepo;

final class CustomerController extends BaseAdminController
{
    private CustomerRepo $repo;

    public function __construct()
    {
        parent::__construct();
        $this->repo = new CustomerRepo();
    }

    public function index(Request $request): void
    {
        $this->authorize('customers.manage');
        $page    = max(1, $request->queryInt('page', 1));
        $perPage = $this->perPage($request, 25);
        $result  = $this->repo->paginate($page, $perPage, mb_substr($request->queryString('q'), 0, 80), $request->queryString('status'));

        $this->view->render('customers/index', [
            'title'     => 'Customers',
            'rows'      => $result['items'],
            'paginator' => new Paginator($page, $perPage, $result['total']),
            'search'    => $request->queryString('q'),
            'status'    => $request->queryString('status'),
            'counts'    => [
                'all'      => $this->repo->count(),
                'lead'     => $this->repo->count('status = ?', ['lead']),
                'active'   => $this->repo->count('status = ?', ['active']),
                'inactive' => $this->repo->count('status = ?', ['inactive']),
            ],
        ]);
    }

    public function show(Request $request, array $params): void
    {
        $this->authorize('customers.manage');
        $id  = (int) $params['id'];
        $row = $this->repo->find($id);
        if ($row === null) {
            flash('error', 'Customer not found.');
            redirect('/admin/customers');
        }
        $this->view->render('customers/show', [
            'title'      => (string) $row['name'],
            'row'        => $row,
            'industries' => (new IndustryRepo())->options(),
            'quotes'     => \Techbiss\Core\Database::instance()->all(
                'SELECT * FROM quote_requests WHERE email = ? ORDER BY created_at DESC', [$row['email']]
            ),
        ]);
    }

    public function update(Request $request, array $params): never
    {
        $this->authorize('customers.manage');
        $this->verify($request);
        $id = (int) $params['id'];

        $v = Validator::make($request->all())
            ->required('name', 'Name', 2, 190)
            ->optional('business_name', 190)
            ->email('email')
            ->phone('phone')
            ->optional('country', 80)
            ->optional('city', 120)
            ->url('website')
            ->int('industry_id')
            ->in('status', ['lead', 'active', 'inactive'], 'Status')
            ->text('notes', 5000, false, 'Notes');

        if ($v->fails()) {
            $this->fail($request, $v->errors(), $v->firstError(), '/admin/customers/' . $id);
        }

        $this->repo->update($id, [
            'name'          => $v->get('name'),
            'business_name' => $v->get('business_name', ''),
            'email'         => $v->get('email'),
            'phone'         => $v->get('phone', ''),
            'country'       => $v->get('country', ''),
            'city'          => $v->get('city', ''),
            'website'       => $v->get('website', ''),
            'industry_id'   => $v->get('industry_id') > 0 ? $v->get('industry_id') : null,
            'status'        => $v->get('status'),
            'notes'         => $v->get('notes', ''),
        ]);
        ActivityLog::record('update', 'customers', $id, 'Updated customer: ' . $v->get('name'));
        $this->ok('Customer updated.', '/admin/customers/' . $id);
    }

    public function destroy(Request $request, array $params): never
    {
        $this->authorize('customers.manage');
        $this->verify($request);
        $id  = (int) $params['id'];
        $row = $this->repo->find($id);
        $this->repo->delete($id);
        ActivityLog::record('delete', 'customers', $id, 'Deleted customer: ' . ($row['name'] ?? $id));
        $this->ok('Customer deleted.', '/admin/customers');
    }

    public function export(Request $request): never
    {
        $this->authorize('export.manage');
        ActivityLog::record('export', 'customers', null, 'Exported customers to CSV');
        $this->csv('techbiss-customers-' . date('Y-m-d') . '.csv', $this->repo->export());
    }
}
