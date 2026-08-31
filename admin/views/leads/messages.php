<?php
/** @var array $rows @var array $counts @var \Techbiss\Core\Paginator $paginator */
use Techbiss\Core\Auth;
$query = array_filter(['q' => $search, 'status' => $status]);
?>
<div class="page-header">
    <div>
        <h1>Contact messages</h1>
        <p>Submissions from the contact form, newest first.</p>
    </div>
    <?php if (Auth::can('export.manage')): ?>
    <div class="page-header__actions">
        <a class="btn btn--ghost btn--sm" href="<?= e(url('/admin/messages/export' . query_with(['status' => $status ?: null]))) ?>">
            <?= icon('download') ?>Export CSV
        </a>
    </div>
    <?php endif; ?>
</div>

<div class="tabs">
    <?php foreach (['' => 'All', 'new' => 'New', 'read' => 'Read', 'replied' => 'Replied', 'archived' => 'Archived', 'spam' => 'Spam'] as $key => $label): ?>
    <a class="tab<?= $status === $key ? ' is-active' : '' ?>"
       href="<?= e(url('/admin/messages' . query_with(['status' => $key ?: null], ['q' => $search]))) ?>">
        <?= e($label) ?><span class="tab__count"><?= (int) ($counts[$key ?: 'all'] ?? 0) ?></span>
    </a>
    <?php endforeach; ?>
</div>

<form class="toolbar" method="get" action="<?= e(url('/admin/messages')) ?>">
    <div class="input-group toolbar__search">
        <?= icon('search', 'icon icon--lead') ?>
        <label class="sr-only" for="m-q">Search messages</label>
        <input class="input" id="m-q" type="search" name="q" value="<?= e($search) ?>" placeholder="Name, email or content" data-search-submit>
    </div>
    <?php if ($status !== ''): ?><input type="hidden" name="status" value="<?= e($status) ?>"><?php endif; ?>
    <?php if ($query): ?><a class="btn btn--quiet btn--sm" href="<?= e(url('/admin/messages')) ?>">Clear</a><?php endif; ?>
</form>

<div class="panel">
    <?php if (!$rows): ?>
        <div class="panel__body">
            <div class="empty-state" style="border:0;background:none">
                <span class="empty-state__icon"><?= icon('inbox') ?></span>
                <h3>No messages <?= $query ? 'match those filters' : 'yet' ?></h3>
                <p>Messages sent through the contact page appear here immediately.</p>
            </div>
        </div>
    <?php else: ?>
    <div class="table-wrap" style="border:0;border-radius:0;background:none">
        <table class="data-table" style="min-width:760px">
            <thead><tr><th>From</th><th>Subject</th><th>Country</th><th>Status</th><th class="num">Received</th><th class="actions">Actions</th></tr></thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                <tr style="<?= $row['status'] === 'new' ? 'background:var(--accent-tint)' : '' ?>">
                    <td>
                        <a href="<?= e(url('/admin/messages/' . (int) $row['id'])) ?>">
                            <span class="cell-title"><?= e($row['name']) ?></span>
                            <span class="cell-sub"><?= e($row['company'] ?: $row['email']) ?></span>
                        </a>
                    </td>
                    <td><span class="hint"><?= e(str_limit($row['subject'] ?: $row['message'], 60)) ?></span></td>
                    <td><span class="hint"><?= e($row['country'] ?: '—') ?></span></td>
                    <td>
                        <span class="status-dot status-dot--<?= match ($row['status']) { 'new' => 'warn', 'replied' => 'live', 'spam' => 'danger', default => 'draft' } ?>">
                            <?= e(ucfirst((string) $row['status'])) ?>
                        </span>
                    </td>
                    <td class="num"><span class="hint"><?= e(time_ago($row['created_at'])) ?></span></td>
                    <td class="actions">
                        <div class="row-actions">
                            <a class="icon-btn" title="Open" href="<?= e(url('/admin/messages/' . (int) $row['id'])) ?>"><?= icon('eye') ?></a>
                            <a class="icon-btn" title="Reply by email" href="mailto:<?= e($row['email']) ?>?subject=<?= rawurlencode('Re: ' . ($row['subject'] ?: 'Your enquiry')) ?>"><?= icon('send') ?></a>
                            <?php if (Auth::can('leads.manage')): ?>
                            <form method="post" style="display:inline" action="<?= e(url('/admin/messages/' . (int) $row['id'] . '/delete')) ?>"
                                  data-confirm="Delete this message permanently?">
                                <?= csrf_field() ?>
                                <button class="icon-btn icon-btn--danger" type="submit" title="Delete"><?= icon('trash') ?></button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?= $view->partial('partials/pagination', ['paginator' => $paginator, 'baseUrl' => '/admin/messages', 'query' => $query]) ?>
    <?php endif; ?>
</div>
