<?php
/** @var array $rows @var array $counts @var \Techbiss\Core\Paginator $paginator */
use Techbiss\Core\Auth;
$query = array_filter(['q' => $search, 'status' => $status, 'source' => $source]);
?>
<div class="page-header">
    <div>
        <h1>Quote requests</h1>
        <p>Everything submitted through the quote form and the Start Your Digital Journey wizard.</p>
    </div>
    <?php if (Auth::can('export.manage')): ?>
    <div class="page-header__actions">
        <a class="btn btn--ghost btn--sm" href="<?= e(url('/admin/quotes/export' . query_with(['status' => $status ?: null]))) ?>">
            <?= icon('download') ?>Export CSV
        </a>
    </div>
    <?php endif; ?>
</div>

<div class="tabs">
    <?php foreach (['' => 'All', 'new' => 'New', 'reviewing' => 'Reviewing', 'quoted' => 'Quoted', 'won' => 'Won', 'lost' => 'Lost'] as $key => $label): ?>
    <a class="tab<?= $status === $key ? ' is-active' : '' ?>"
       href="<?= e(url('/admin/quotes' . query_with(['status' => $key ?: null], ['q' => $search, 'source' => $source]))) ?>">
        <?= e($label) ?><span class="tab__count"><?= (int) ($counts[$key ?: 'all'] ?? 0) ?></span>
    </a>
    <?php endforeach; ?>
</div>

<form class="toolbar" method="get" action="<?= e(url('/admin/quotes')) ?>">
    <div class="input-group toolbar__search">
        <?= icon('search', 'icon icon--lead') ?>
        <label class="sr-only" for="q-q">Search requests</label>
        <input class="input" id="q-q" type="search" name="q" value="<?= e($search) ?>" placeholder="Reference, name, email or business" data-search-submit>
    </div>
    <label class="sr-only" for="q-source">Source</label>
    <select class="select" id="q-source" name="source" data-autosubmit style="max-width:170px">
        <option value="">All sources</option>
        <option value="quote" <?= $source === 'quote' ? 'selected' : '' ?>>Quote form</option>
        <option value="journey" <?= $source === 'journey' ? 'selected' : '' ?>>Digital journey</option>
        <option value="package" <?= $source === 'package' ? 'selected' : '' ?>>Package enquiry</option>
    </select>
    <?php if ($status !== ''): ?><input type="hidden" name="status" value="<?= e($status) ?>"><?php endif; ?>
    <?php if ($query): ?><a class="btn btn--quiet btn--sm" href="<?= e(url('/admin/quotes')) ?>">Clear</a><?php endif; ?>
</form>

<div class="panel">
    <?php if (!$rows): ?>
        <div class="panel__body">
            <div class="empty-state" style="border:0;background:none">
                <span class="empty-state__icon"><?= icon('send') ?></span>
                <h3>No requests <?= $query ? 'match those filters' : 'yet' ?></h3>
                <p>Quote and journey submissions arrive here with a reference number the customer also receives.</p>
            </div>
        </div>
    <?php else: ?>
    <div class="table-wrap" style="border:0;border-radius:0;background:none">
        <table class="data-table" style="min-width:880px">
            <thead><tr><th>Reference</th><th>From</th><th>Needs</th><th>Budget</th><th>Priority</th><th>Status</th><th class="num">Received</th></tr></thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                <tr style="<?= $row['status'] === 'new' ? 'background:var(--accent-tint)' : '' ?>">
                    <td>
                        <a href="<?= e(url('/admin/quotes/' . (int) $row['id'])) ?>">
                            <span class="cell-title mono-sm" style="color:var(--text)"><?= e($row['reference']) ?></span>
                            <span class="cell-sub"><?= e(ucfirst((string) $row['source'])) ?></span>
                        </a>
                    </td>
                    <td>
                        <span class="cell-title"><?= e($row['name']) ?></span>
                        <span class="cell-sub"><?= e($row['business_name'] ?: $row['email']) ?></span>
                    </td>
                    <td><span class="hint"><?= e(str_limit($row['services_needed'] ?: ($row['package_name'] ?? '—'), 44)) ?></span></td>
                    <td><span class="hint"><?= e($row['budget_range'] ?: '—') ?></span></td>
                    <td>
                        <span class="status-dot status-dot--<?= $row['priority'] === 'high' ? 'danger' : ($row['priority'] === 'low' ? 'draft' : 'info') ?>">
                            <?= e(ucfirst((string) $row['priority'])) ?>
                        </span>
                    </td>
                    <td>
                        <span class="status-dot status-dot--<?= match ($row['status']) { 'new' => 'warn', 'won' => 'live', 'lost' => 'danger', default => 'draft' } ?>">
                            <?= e(ucfirst((string) $row['status'])) ?>
                        </span>
                    </td>
                    <td class="num"><span class="hint"><?= e(time_ago($row['created_at'])) ?></span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?= $view->partial('partials/pagination', ['paginator' => $paginator, 'baseUrl' => '/admin/quotes', 'query' => $query]) ?>
    <?php endif; ?>
</div>
