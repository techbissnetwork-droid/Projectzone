<?php
/** @var array $rows @var \Techbiss\Core\Paginator $paginator */
use Techbiss\Core\Auth;
$query = array_filter(['q' => $search, 'status' => $status]);
?>
<div class="page-header">
    <div>
        <h1>Newsletter subscribers</h1>
        <p><?= $paginator->total ?> record<?= $paginator->total === 1 ? '' : 's' ?> collected through the site footer.</p>
    </div>
    <?php if (Auth::can('export.manage')): ?>
    <div class="page-header__actions">
        <a class="btn btn--ghost btn--sm" href="<?= e(url('/admin/subscribers/export')) ?>"><?= icon('download') ?>Export CSV</a>
    </div>
    <?php endif; ?>
</div>

<form class="toolbar" method="get" action="<?= e(url('/admin/subscribers')) ?>">
    <div class="input-group toolbar__search">
        <?= icon('search', 'icon icon--lead') ?>
        <label class="sr-only" for="s-q">Search subscribers</label>
        <input class="input" id="s-q" type="search" name="q" value="<?= e($search) ?>" placeholder="Email or name" data-search-submit>
    </div>
    <label class="sr-only" for="s-status">Status</label>
    <select class="select" id="s-status" name="status" data-autosubmit style="max-width:170px">
        <option value="">All</option>
        <option value="subscribed" <?= $status === 'subscribed' ? 'selected' : '' ?>>Subscribed</option>
        <option value="unsubscribed" <?= $status === 'unsubscribed' ? 'selected' : '' ?>>Unsubscribed</option>
    </select>
    <?php if ($query): ?><a class="btn btn--quiet btn--sm" href="<?= e(url('/admin/subscribers')) ?>">Clear</a><?php endif; ?>
</form>

<div class="panel">
    <?php if (!$rows): ?>
        <div class="panel__body">
            <div class="empty-state" style="border:0;background:none">
                <span class="empty-state__icon"><?= icon('mail') ?></span>
                <h3>No subscribers <?= $query ? 'match those filters' : 'yet' ?></h3>
                <p>The newsletter form in the site footer adds people here.</p>
            </div>
        </div>
    <?php else: ?>
    <div class="table-wrap" style="border:0;border-radius:0;background:none">
        <table class="data-table" style="min-width:640px">
            <thead><tr><th>Email</th><th>Name</th><th>Source</th><th>Status</th><th class="num">Subscribed</th><th class="actions">Actions</th></tr></thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                <tr>
                    <td><span class="cell-title"><?= e($row['email']) ?></span></td>
                    <td><?= e($row['name'] ?: '—') ?></td>
                    <td><span class="badge"><?= e($row['source']) ?></span></td>
                    <td>
                        <span class="status-dot status-dot--<?= $row['status'] === 'subscribed' ? 'live' : 'draft' ?>">
                            <?= e(ucfirst((string) $row['status'])) ?>
                        </span>
                    </td>
                    <td class="num"><span class="hint"><?= e(format_date($row['created_at'])) ?></span></td>
                    <td class="actions">
                        <?php if (Auth::can('leads.manage')): ?>
                        <div class="row-actions">
                            <form method="post" style="display:inline" action="<?= e(url('/admin/subscribers/' . (int) $row['id'])) ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="status" value="<?= $row['status'] === 'subscribed' ? 'unsubscribed' : 'subscribed' ?>">
                                <button class="icon-btn" type="submit" title="<?= $row['status'] === 'subscribed' ? 'Mark unsubscribed' : 'Mark subscribed' ?>" aria-label="<?= $row['status'] === 'subscribed' ? 'Mark unsubscribed' : 'Mark subscribed' ?>">
                                    <?= icon($row['status'] === 'subscribed' ? 'x-circle' : 'check-circle') ?>
                                </button>
                            </form>
                            <form method="post" style="display:inline" action="<?= e(url('/admin/subscribers/' . (int) $row['id'] . '/delete')) ?>"
                                  data-confirm="Remove this subscriber permanently?">
                                <?= csrf_field() ?>
                                <button class="icon-btn icon-btn--danger" type="submit" title="Delete" aria-label="Delete"><?= icon('trash') ?></button>
                            </form>
                        </div>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?= $view->partial('partials/pagination', ['paginator' => $paginator, 'baseUrl' => '/admin/subscribers', 'query' => $query]) ?>
    <?php endif; ?>
</div>
