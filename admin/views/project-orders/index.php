<?php
/** @var array $rows @var array $summary @var \Techbiss\Core\Paginator $paginator */
use Techbiss\Core\Auth;
use Techbiss\Repo\ProjectOrderRepo;

$query  = array_filter(['q' => $search, 'payment' => $paymentFilter, 'status' => $statusFilter]);
$labels = ProjectOrderRepo::orderStatusLabels();
$statusClass = static fn (string $s): string => match ($s) {
    'delivered' => 'live', 'in_setup' => 'info', 'quoted' => 'warn',
    'cancelled' => 'draft', 'discussing' => 'info', default => 'warn',
};
$payClass = static fn (string $s): string => match ($s) {
    'paid' => 'live', 'pending' => 'warn', 'refunded' => 'info', 'cancelled' => 'draft', default => 'draft',
};
?>
<div class="page-header">
    <div>
        <h1>Project enquiries</h1>
        <p>People asking about a premade project. No price is shown on the site — agree it with them, then record it here.</p>
    </div>
    <?php if (Auth::can('export.manage')): ?>
    <div class="page-header__actions">
        <a class="btn btn--ghost btn--sm" href="<?= e(url('/admin/project-orders/export')) ?>"><?= icon('download') ?>Export CSV</a>
    </div>
    <?php endif; ?>
</div>

<div class="tiles">
    <div class="tile"><span class="tile__label"><?= icon('inbox') ?>New</span>
        <span class="tile__value"><?= (int) $summary['new'] ?></span>
        <span class="tile__meta">Not yet answered</span></div>
    <div class="tile"><span class="tile__label"><?= icon('clock') ?>Quoted, unpaid</span>
        <span class="tile__value"><?= e(money($summary['pipeline'])) ?></span>
        <span class="tile__meta"><?= (int) $summary['pending'] ?> awaiting payment</span></div>
    <div class="tile"><span class="tile__label"><?= icon('money') ?>Confirmed</span>
        <span class="tile__value"><?= e(money($summary['revenue'])) ?></span>
        <span class="tile__meta"><?= (int) $summary['paid'] ?> paid</span></div>
    <div class="tile"><span class="tile__label"><?= icon('check-circle') ?>Delivered</span>
        <span class="tile__value"><?= (int) $summary['delivered'] ?></span>
        <span class="tile__meta"><?= (int) $summary['in_setup'] ?> in setup</span></div>
</div>

<form class="toolbar" method="get" action="<?= e(url('/admin/project-orders')) ?>">
    <div class="input-group toolbar__search">
        <?= icon('search', 'icon icon--lead') ?>
        <label class="sr-only" for="po-q">Search enquiries</label>
        <input class="input" id="po-q" type="search" name="q" value="<?= e($search) ?>" placeholder="Reference, customer or project" data-search-submit>
    </div>
    <label class="sr-only" for="po-pay">Payment status</label>
    <select class="select" id="po-pay" name="payment" data-autosubmit style="max-width:170px">
        <option value="">All payments</option>
        <?php foreach (ProjectOrderRepo::PAYMENT_STATUSES as $s): ?>
        <option value="<?= e($s) ?>" <?= $paymentFilter === $s ? 'selected' : '' ?>><?= e(ucfirst($s)) ?></option>
        <?php endforeach; ?>
    </select>
    <label class="sr-only" for="po-status">Enquiry status</label>
    <select class="select" id="po-status" name="status" data-autosubmit style="max-width:180px">
        <option value="">All statuses</option>
        <?php foreach ($labels as $key => $label): ?>
        <option value="<?= e($key) ?>" <?= $statusFilter === $key ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
    </select>
    <?php if ($query): ?><a class="btn btn--quiet btn--sm" href="<?= e(url('/admin/project-orders')) ?>">Clear</a><?php endif; ?>
</form>

<div class="panel">
    <?php if (!$rows): ?>
        <div class="panel__body">
            <div class="empty-state" style="border:0;background:none">
                <span class="empty-state__icon"><?= icon('inbox') ?></span>
                <h3>No enquiries <?= $query ? 'match those filters' : 'yet' ?></h3>
                <p>Enquiries sent from a premade project page appear here, with the channel the customer asked you to use.</p>
            </div>
        </div>
    <?php else: ?>
    <div class="table-wrap" style="border:0;border-radius:0;background:none">
        <table class="data-table" style="min-width:900px">
            <thead>
                <tr>
                    <th>Reference</th><th>Customer</th><th>Project</th>
                    <th>Reach them on</th><th class="num">Quoted</th>
                    <th>Payment</th><th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                <tr>
                    <td>
                        <a href="<?= e(url('/admin/project-orders/' . (int) $row['id'])) ?>">
                            <span class="cell-title mono-sm" style="color:var(--text)"><?= e($row['reference']) ?></span>
                            <span class="cell-sub"><?= e(format_date($row['ordered_at'])) ?></span>
                        </a>
                    </td>
                    <td>
                        <span class="cell-title"><?= e($row['customer_name']) ?></span>
                        <span class="cell-sub"><?= e($row['business_name'] ?: $row['customer_email']) ?></span>
                    </td>
                    <td><?= e(str_limit($row['project_name'], 34)) ?></td>
                    <td><span class="badge"><?= e(ProjectOrderRepo::contactLabels()[$row['preferred_contact']] ?? 'Email') ?></span></td>
                    <td class="num">
                        <?php if ($row['quoted_amount'] !== null): ?>
                            <strong><?= e(money($row['quoted_amount'])) ?></strong>
                        <?php else: ?><span class="hint">Not quoted</span><?php endif; ?>
                    </td>
                    <td><span class="status-dot status-dot--<?= $payClass((string) $row['payment_status']) ?>"><?= e(ucfirst((string) $row['payment_status'])) ?></span></td>
                    <td><span class="status-dot status-dot--<?= $statusClass((string) $row['order_status']) ?>"><?= e($labels[$row['order_status']] ?? ucfirst((string) $row['order_status'])) ?></span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?= $view->partial('partials/pagination', ['paginator' => $paginator, 'baseUrl' => '/admin/project-orders', 'query' => $query]) ?>
    <?php endif; ?>
</div>
