<?php
/** @var array $rows @var array $summary @var \Techbiss\Core\Paginator $paginator */
use Techbiss\Core\Auth;
$query = array_filter(['q' => $search, 'payment' => $paymentFilter, 'status' => $statusFilter]);
$statusClass = static fn (string $s): string => match ($s) {
    'active' => 'live', 'expiring' => 'warn', 'expired' => 'danger', 'cancelled' => 'draft', default => 'info',
};
$payClass = static fn (string $s): string => match ($s) {
    'paid' => 'live', 'pending' => 'warn', 'refunded' => 'info', 'cancelled' => 'draft', default => 'draft',
};
?>
<div class="page-header">
    <div>
        <h1>Package purchases</h1>
        <p>Every request recorded through the website. Payment is confirmed here manually — nothing is charged automatically.</p>
    </div>
    <?php if (Auth::can('export.manage')): ?>
    <div class="page-header__actions">
        <a class="btn btn--ghost btn--sm" href="<?= e(url('/admin/purchases/export')) ?>"><?= icon('download') ?>Export CSV</a>
    </div>
    <?php endif; ?>
</div>

<div class="tiles">
    <div class="tile"><span class="tile__label"><?= icon('money') ?>Confirmed</span>
        <span class="tile__value"><?= e(money($summary['revenue'])) ?></span>
        <span class="tile__meta"><?= (int) $summary['paid'] ?> paid</span></div>
    <div class="tile"><span class="tile__label"><?= icon('clock') ?>Awaiting payment</span>
        <span class="tile__value"><?= e(money($summary['pipeline'])) ?></span>
        <span class="tile__meta"><?= (int) $summary['pending'] ?> pending</span></div>
    <div class="tile"><span class="tile__label"><?= icon('check-circle') ?>Active</span>
        <span class="tile__value"><?= (int) $summary['active'] ?></span>
        <span class="tile__meta"><?= (int) $summary['expiring'] ?> expiring soon</span></div>
    <div class="tile"><span class="tile__label"><?= icon('alert') ?>Expired</span>
        <span class="tile__value"><?= (int) $summary['expired'] ?></span>
        <span class="tile__meta">Needing renewal</span></div>
</div>

<form class="toolbar" method="get" action="<?= e(url('/admin/purchases')) ?>">
    <div class="input-group toolbar__search">
        <?= icon('search', 'icon icon--lead') ?>
        <label class="sr-only" for="pu-q">Search purchases</label>
        <input class="input" id="pu-q" type="search" name="q" value="<?= e($search) ?>" placeholder="Reference, customer or package" data-search-submit>
    </div>
    <label class="sr-only" for="pu-pay">Payment status</label>
    <select class="select" id="pu-pay" name="payment" data-autosubmit style="max-width:170px">
        <option value="">All payments</option>
        <?php foreach (\Techbiss\Repo\PurchaseRepo::PAYMENT_STATUSES as $s): ?>
        <option value="<?= e($s) ?>" <?= $paymentFilter === $s ? 'selected' : '' ?>><?= e(ucfirst($s)) ?></option>
        <?php endforeach; ?>
    </select>
    <label class="sr-only" for="pu-status">Package status</label>
    <select class="select" id="pu-status" name="status" data-autosubmit style="max-width:170px">
        <option value="">All statuses</option>
        <?php foreach (\Techbiss\Repo\PurchaseRepo::PACKAGE_STATUSES as $s): ?>
        <option value="<?= e($s) ?>" <?= $statusFilter === $s ? 'selected' : '' ?>><?= e(ucfirst($s)) ?></option>
        <?php endforeach; ?>
    </select>
    <?php if ($query): ?><a class="btn btn--quiet btn--sm" href="<?= e(url('/admin/purchases')) ?>">Clear</a><?php endif; ?>
</form>

<div class="panel">
    <?php if (!$rows): ?>
        <div class="panel__body">
            <div class="empty-state" style="border:0;background:none">
                <span class="empty-state__icon"><?= icon('money') ?></span>
                <h3>No package requests <?= $query ? 'match those filters' : 'yet' ?></h3>
                <p>Requests submitted through the package checkout appear here with their full pricing breakdown.</p>
            </div>
        </div>
    <?php else: ?>
    <div class="table-wrap" style="border:0;border-radius:0;background:none">
        <table class="data-table" style="min-width:900px">
            <thead>
                <tr>
                    <th>Reference</th><th>Customer</th><th>Package</th>
                    <th class="num">Total</th><th class="num">Saved</th>
                    <th>Payment</th><th>Status</th><th class="num">Expires</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                <tr>
                    <td>
                        <a href="<?= e(url('/admin/purchases/' . (int) $row['id'])) ?>">
                            <span class="cell-title mono-sm" style="color:var(--text)"><?= e($row['reference']) ?></span>
                            <span class="cell-sub"><?= e(format_date($row['purchased_at'])) ?></span>
                        </a>
                    </td>
                    <td>
                        <span class="cell-title"><?= e($row['customer_name']) ?></span>
                        <span class="cell-sub"><?= e($row['business_name'] ?: $row['customer_email']) ?></span>
                    </td>
                    <td><?= e($row['package_name']) ?></td>
                    <td class="num"><strong><?= e(money($row['total_amount'])) ?></strong></td>
                    <td class="num">
                        <?php if ((float) $row['discount_amount'] > 0): ?>
                            <span class="status-dot status-dot--live"><?= e(money($row['discount_amount'])) ?></span>
                        <?php else: ?><span class="hint">—</span><?php endif; ?>
                    </td>
                    <td><span class="status-dot status-dot--<?= $payClass((string) $row['payment_status']) ?>"><?= e(ucfirst((string) $row['payment_status'])) ?></span></td>
                    <td><span class="status-dot status-dot--<?= $statusClass((string) $row['package_status']) ?>"><?= e(ucfirst((string) $row['package_status'])) ?></span></td>
                    <td class="num"><span class="hint"><?= $row['expires_at'] ? e(format_date($row['expires_at'])) : '—' ?></span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?= $view->partial('partials/pagination', ['paginator' => $paginator, 'baseUrl' => '/admin/purchases', 'query' => $query]) ?>
    <?php endif; ?>
</div>
