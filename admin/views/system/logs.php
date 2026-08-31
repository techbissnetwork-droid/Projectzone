<?php
/** @var array $rows @var array $actions @var \Techbiss\Core\Paginator $paginator */
$query = array_filter(['q' => $search, 'action' => $action]);
?>
<div class="page-header">
    <div>
        <h1>Activity log</h1>
        <p>Every administrative action, with who did it and from where.</p>
    </div>
</div>

<form class="toolbar" method="get" action="<?= e(url('/admin/logs')) ?>">
    <div class="input-group toolbar__search">
        <?= icon('search', 'icon icon--lead') ?>
        <label class="sr-only" for="l-q">Search log</label>
        <input class="input" id="l-q" type="search" name="q" value="<?= e($search) ?>" placeholder="User, description or entity" data-search-submit>
    </div>
    <label class="sr-only" for="l-action">Action</label>
    <select class="select" id="l-action" name="action" data-autosubmit style="max-width:170px">
        <option value="">All actions</option>
        <?php foreach ($actions as $a): ?>
        <option value="<?= e($a) ?>" <?= $action === $a ? 'selected' : '' ?>><?= e(ucfirst($a)) ?></option>
        <?php endforeach; ?>
    </select>
    <?php if ($query): ?><a class="btn btn--quiet btn--sm" href="<?= e(url('/admin/logs')) ?>">Clear</a><?php endif; ?>
</form>

<div class="panel">
    <?php if (!$rows): ?>
        <div class="panel__body">
            <div class="empty-state" style="border:0;background:none">
                <span class="empty-state__icon"><?= icon('clock') ?></span>
                <h3>Nothing logged <?= $query ? 'for those filters' : 'yet' ?></h3>
                <p>Actions are recorded here as soon as administrators start making changes.</p>
            </div>
        </div>
    <?php else: ?>
    <div class="table-wrap" style="border:0;border-radius:0;background:none">
        <table class="data-table" style="min-width:760px">
            <thead><tr><th>Action</th><th>Description</th><th>User</th><th>IP</th><th class="num">When</th></tr></thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                <tr>
                    <td>
                        <span class="badge badge--<?= match ($row['action']) {
                            'delete' => 'danger', 'create' => 'success', 'login', 'logout' => 'info', default => 'accent',
                        } ?>"><?= e($row['action']) ?></span>
                    </td>
                    <td>
                        <span class="cell-title" style="font-weight:400"><?= e($row['description']) ?></span>
                        <?php if ($row['entity_type'] !== ''): ?>
                        <span class="cell-sub mono-sm"><?= e($row['entity_type']) ?><?= $row['entity_id'] ? ' #' . (int) $row['entity_id'] : '' ?></span>
                        <?php endif; ?>
                    </td>
                    <td><span class="hint"><?= e($row['admin_name'] ?: 'System') ?></span></td>
                    <td><span class="mono-sm"><?= e($row['ip_address'] ?: '—') ?></span></td>
                    <td class="num"><span class="hint" title="<?= e($row['created_at']) ?>"><?= e(time_ago($row['created_at'])) ?></span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?= $view->partial('partials/pagination', ['paginator' => $paginator, 'baseUrl' => '/admin/logs', 'query' => $query]) ?>
    <?php endif; ?>
</div>
