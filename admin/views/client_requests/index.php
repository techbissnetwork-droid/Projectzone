<?php
/** @var array $rows @var array $counts @var \Techbiss\Core\Paginator $paginator */
$query = array_filter(['q' => $search, 'status' => $status]);
$types = ['upgrade' => 'Upgrade', 'update' => 'Update', 'maintenance' => 'Maintenance', 'support' => 'Support', 'other' => 'Other'];
?>
<div class="page-header">
    <div>
        <h1>Client requests</h1>
        <p>Upgrade, update, maintenance and support requests raised from the client portal.</p>
    </div>
</div>

<div class="tabs">
    <?php foreach (['' => 'All', 'new' => 'New', 'in_progress' => 'In progress', 'resolved' => 'Resolved'] as $key => $label): ?>
    <a class="tab<?= $status === $key ? ' is-active' : '' ?>"
       href="<?= e(url('/admin/client-requests' . query_with(['status' => $key ?: null], ['q' => $search]))) ?>">
        <?= e($label) ?><span class="tab__count"><?= (int) ($counts[$key ?: 'all'] ?? 0) ?></span>
    </a>
    <?php endforeach; ?>
</div>

<form class="toolbar" method="get" action="<?= e(url('/admin/client-requests')) ?>">
    <div class="input-group toolbar__search">
        <?= icon('search', 'icon icon--lead') ?>
        <label class="sr-only" for="cr-q">Search requests</label>
        <input class="input" id="cr-q" type="search" name="q" value="<?= e($search) ?>" placeholder="Reference, name or email" data-search-submit>
    </div>
    <?php if ($status !== ''): ?><input type="hidden" name="status" value="<?= e($status) ?>"><?php endif; ?>
    <?php if ($query): ?><a class="btn btn--quiet btn--sm" href="<?= e(url('/admin/client-requests')) ?>">Clear</a><?php endif; ?>
</form>

<div class="panel">
    <?php if (!$rows): ?>
        <div class="panel__body">
            <div class="empty-state" style="border:0;background:none">
                <span class="empty-state__icon"><?= icon('inbox') ?></span>
                <h3>No requests <?= $query ? 'match those filters' : 'yet' ?></h3>
                <p>A client signed into the portal raises these against a project already on file.</p>
            </div>
        </div>
    <?php else: ?>
    <div class="table-wrap" style="border:0;border-radius:0;background:none">
        <table class="data-table" style="min-width:880px">
            <thead><tr><th>Reference</th><th>From</th><th>Type</th><th>Project</th><th>Status</th><th class="num">Received</th></tr></thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                <tr style="<?= $row['status'] === 'new' ? 'background:var(--accent-tint)' : '' ?>">
                    <td>
                        <a href="<?= e(url('/admin/client-requests/' . (int) $row['id'])) ?>">
                            <span class="cell-title" style="color:var(--text)"><?= e($row['reference']) ?></span>
                            <span class="cell-sub"><?= e(format_date($row['created_at'], 'j M')) ?></span>
                        </a>
                    </td>
                    <td>
                        <span class="cell-title"><?= e($row['customer_name']) ?></span>
                        <span class="cell-sub"><?= e($row['customer_email']) ?></span>
                    </td>
                    <td><span class="hint"><?= e($types[$row['request_type']] ?? ucfirst((string) $row['request_type'])) ?></span></td>
                    <td><span class="hint"><?= e($row['project_name'] ?? '—') ?></span></td>
                    <td>
                        <span class="status-dot status-dot--<?= match ($row['status']) { 'new' => 'warn', 'resolved' => 'live', default => 'draft' } ?>">
                            <?= e(ucfirst(str_replace('_', ' ', (string) $row['status']))) ?>
                        </span>
                    </td>
                    <td class="num"><span class="hint"><?= e(time_ago($row['created_at'])) ?></span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?= $view->partial('partials/pagination', ['paginator' => $paginator, 'baseUrl' => '/admin/client-requests', 'query' => $query]) ?>
    <?php endif; ?>
</div>
