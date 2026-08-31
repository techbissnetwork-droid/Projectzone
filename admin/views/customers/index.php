<?php
/** @var array $rows @var array $counts @var \Techbiss\Core\Paginator $paginator */
use Techbiss\Core\Auth;
$query = array_filter(['q' => $search, 'status' => $status]);
?>
<div class="page-header">
    <div>
        <h1>Customers</h1>
        <p>Everyone who has submitted a quote request, journey form or package request, in one list.</p>
    </div>
    <?php if (Auth::can('export.manage')): ?>
    <div class="page-header__actions">
        <a class="btn btn--ghost btn--sm" href="<?= e(url('/admin/customers/export')) ?>"><?= icon('download') ?>Export CSV</a>
    </div>
    <?php endif; ?>
</div>

<div class="tabs">
    <?php foreach (['' => 'All', 'lead' => 'Leads', 'active' => 'Active', 'inactive' => 'Inactive'] as $key => $label): ?>
    <a class="tab<?= $status === $key ? ' is-active' : '' ?>"
       href="<?= e(url('/admin/customers' . query_with(['status' => $key ?: null], ['q' => $search]))) ?>">
        <?= e($label) ?><span class="tab__count"><?= (int) ($counts[$key ?: 'all'] ?? 0) ?></span>
    </a>
    <?php endforeach; ?>
</div>

<form class="toolbar" method="get" action="<?= e(url('/admin/customers')) ?>">
    <div class="input-group toolbar__search">
        <?= icon('search', 'icon icon--lead') ?>
        <label class="sr-only" for="c-q">Search customers</label>
        <input class="input" id="c-q" type="search" name="q" value="<?= e($search) ?>" placeholder="Name, email or business" data-search-submit>
    </div>
    <?php if ($status !== ''): ?><input type="hidden" name="status" value="<?= e($status) ?>"><?php endif; ?>
    <?php if ($query): ?><a class="btn btn--quiet btn--sm" href="<?= e(url('/admin/customers')) ?>">Clear</a><?php endif; ?>
</form>

<div class="panel">
    <?php if (!$rows): ?>
        <div class="panel__body">
            <div class="empty-state" style="border:0;background:none">
                <span class="empty-state__icon"><?= icon('users') ?></span>
                <h3>No customers <?= $query ? 'match those filters' : 'yet' ?></h3>
                <p>A customer record is created automatically the first time someone submits a form with their email address.</p>
            </div>
        </div>
    <?php else: ?>
    <div class="table-wrap" style="border:0;border-radius:0;background:none">
        <table class="data-table" style="min-width:820px">
            <thead>
                <tr><th>Customer</th><th>Business</th><th>Country</th><th>Industry</th>
                    <th class="num">Purchases</th><th class="num">Value</th><th>Status</th><th class="num">Added</th></tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                <tr>
                    <td>
                        <a href="<?= e(url('/admin/customers/' . (int) $row['id'])) ?>">
                            <span class="cell-title"><?= e($row['name']) ?></span>
                            <span class="cell-sub"><?= e($row['email']) ?></span>
                        </a>
                    </td>
                    <td><?= e($row['business_name'] ?: '—') ?></td>
                    <td><span class="hint"><?= e($row['country'] ?: '—') ?></span></td>
                    <td><span class="hint"><?= e($row['industry_name'] ?: '—') ?></span></td>
                    <td class="num"><?= (int) $row['purchase_count'] ?></td>
                    <td class="num"><?= (float) $row['lifetime_value'] > 0 ? e(money($row['lifetime_value'])) : '<span class="hint">—</span>' ?></td>
                    <td>
                        <span class="status-dot status-dot--<?= $row['status'] === 'active' ? 'live' : ($row['status'] === 'lead' ? 'info' : 'draft') ?>">
                            <?= e(ucfirst((string) $row['status'])) ?>
                        </span>
                    </td>
                    <td class="num"><span class="hint"><?= e(time_ago($row['created_at'])) ?></span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?= $view->partial('partials/pagination', ['paginator' => $paginator, 'baseUrl' => '/admin/customers', 'query' => $query]) ?>
    <?php endif; ?>
</div>
