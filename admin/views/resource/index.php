<?php
/** Generic resource list. @var array $resource @var array $rows @var \Techbiss\Core\Paginator $paginator */
$key       = $resourceKey;
$orderable = !empty($resource['orderable']);
$query     = array_filter(['q' => $search, 'status' => $status]);
?>
<div class="page-header">
    <div>
        <h1><?= e($resource['plural']) ?></h1>
        <p><?= $paginator->total ?> record<?= $paginator->total === 1 ? '' : 's' ?><?= $orderable ? ' · drag the handle to reorder' : '' ?></p>
    </div>
    <div class="page-header__actions">
        <a class="btn btn--primary btn--sm" href="<?= e(url('/admin/' . $key . '/create')) ?>">
            <?= icon('plus') ?>New <?= e(strtolower($resource['singular'])) ?>
        </a>
    </div>
</div>

<?php if (!empty($resource['notice'])): ?>
<div class="notice notice--accent mb-4"><?= icon('info') ?><span><?= e($resource['notice']) ?></span></div>
<?php endif; ?>

<form class="toolbar" method="get" action="<?= e(url('/admin/' . $key)) ?>">
    <?php if (!empty($resource['searchable'])): ?>
    <div class="input-group toolbar__search">
        <?= icon('search', 'icon icon--lead') ?>
        <label class="sr-only" for="res-search">Search <?= e(strtolower($resource['plural'])) ?></label>
        <input class="input" id="res-search" type="search" name="q" value="<?= e($search) ?>"
               placeholder="Search <?= e(strtolower($resource['plural'])) ?>" data-search-submit>
    </div>
    <?php endif; ?>

    <label class="sr-only" for="res-status">Status</label>
    <select class="select" id="res-status" name="status" data-autosubmit style="max-width:150px">
        <option value="">All statuses</option>
        <option value="published" <?= $status === 'published' ? 'selected' : '' ?>>Published</option>
        <option value="draft" <?= $status === 'draft' ? 'selected' : '' ?>>Draft</option>
    </select>

    <?php if ($search !== '' || $status !== ''): ?>
    <a class="btn btn--quiet btn--sm" href="<?= e(url('/admin/' . $key)) ?>">Clear</a>
    <?php endif; ?>
</form>

<div class="panel">
    <?php if (!$rows): ?>
        <div class="panel__body">
            <div class="empty-state" style="border:0;background:none">
                <span class="empty-state__icon"><?= icon($resource['icon']) ?></span>
                <h3>No <?= e(strtolower($resource['plural'])) ?> <?= $search !== '' || $status !== '' ? 'match those filters' : 'yet' ?></h3>
                <p>
                    <?php if ($search !== '' || $status !== ''): ?>
                        Try a different search, or clear the filters.
                    <?php else: ?>
                        Create the first one and it will appear on the website immediately.
                    <?php endif; ?>
                </p>
                <a class="btn btn--primary mt-4" href="<?= e(url('/admin/' . $key . '/create')) ?>">
                    <?= icon('plus') ?>New <?= e(strtolower($resource['singular'])) ?>
                </a>
            </div>
        </div>
    <?php else: ?>
        <div class="table-wrap" style="border:0;border-radius:0;background:none">
            <table class="data-table" style="min-width:640px">
                <thead>
                    <tr>
                        <?php if ($orderable): ?><th scope="col" aria-label="Reorder"></th><?php endif; ?>
                        <?php foreach ($resource['columns'] as $col): ?>
                        <th scope="col" class="<?= ($col['type'] ?? '') === 'money' ? 'num' : '' ?>"><?= e($col['label']) ?></th>
                        <?php endforeach; ?>
                        <th scope="col" class="actions">Actions</th>
                    </tr>
                </thead>
                <tbody data-sortable="<?= $orderable ? e(url('/admin/' . $key . '/reorder')) : '' ?>" data-sortable-item="tr[data-id]">
                    <?php foreach ($rows as $row): ?>
                    <tr data-id="<?= (int) $row['id'] ?>">
                        <?php if ($orderable): ?>
                        <td class="drag-handle" data-drag-handle title="Drag to reorder"><?= icon('drag') ?></td>
                        <?php endif; ?>

                        <?php foreach ($resource['columns'] as $col):
                            $value = $row[$col['key']] ?? '';
                            $type  = $col['type'] ?? 'text'; ?>
                        <td class="<?= $type === 'money' ? 'num' : '' ?>">
                            <?php if (!empty($col['primary'])): ?>
                                <a href="<?= e(url('/admin/' . $key . '/' . (int) $row['id'] . '/edit')) ?>">
                                    <span class="cell-title"><?= e(str_limit((string) $value, (int) ($col['truncate'] ?? 70))) ?></span>
                                </a>
                                <?php if (!empty($col['sub']) && !empty($row[$col['sub']])): ?>
                                <span class="cell-sub"><?= e(str_limit((string) $row[$col['sub']], 70)) ?></span>
                                <?php endif; ?>
                            <?php elseif ($type === 'status'): ?>
                                <span class="status-dot status-dot--<?= (int) $value === 1 ? 'live' : 'draft' ?>">
                                    <?= (int) $value === 1 ? 'Published' : 'Draft' ?>
                                </span>
                            <?php elseif ($type === 'toggle'): ?>
                                <label class="switch">
                                    <input type="checkbox" <?= (int) $value === 1 ? 'checked' : '' ?>
                                           data-toggle-url="<?= e(url('/admin/' . $key . '/' . (int) $row['id'] . '/toggle')) ?>"
                                           data-toggle-column="<?= e($col['key']) ?>"
                                           aria-label="Toggle <?= e($col['label']) ?>">
                                    <span class="switch__track" aria-hidden="true"></span>
                                </label>
                            <?php elseif ($type === 'mono'): ?>
                                <span class="mono-sm"><?= e((string) $value) ?></span>
                            <?php elseif ($type === 'badge'): ?>
                                <?php if ((string) $value !== ''): ?><span class="badge"><?= e((string) $value) ?></span><?php endif; ?>
                            <?php elseif ($type === 'money'): ?>
                                <?= e(money($value)) ?>
                            <?php elseif ($type === 'rating'): ?>
                                <span class="rating" aria-label="<?= (int) $value ?> out of 5">
                                    <?php for ($s = 1; $s <= 5; $s++): ?><?= icon('star', 'icon' . ($s <= (int) $value ? '' : ' icon--off')) ?><?php endfor; ?>
                                </span>
                            <?php else: ?>
                                <?= e(str_limit((string) $value, (int) ($col['truncate'] ?? 60))) ?>
                            <?php endif; ?>
                        </td>
                        <?php endforeach; ?>

                        <td class="actions">
                            <div class="row-actions">
                                <?php if (!empty($resource['public_url']) && (int) ($row['is_published'] ?? 1) === 1): ?>
                                <a class="icon-btn" title="View on the site" aria-label="View on the site" target="_blank" rel="noopener"
                                   href="<?= e(url(str_replace('{slug}', (string) ($row['slug'] ?? ''), $resource['public_url']))) ?>">
                                    <?= icon('external') ?>
                                </a>
                                <?php endif; ?>
                                <a class="icon-btn" title="Edit" aria-label="Edit" href="<?= e(url('/admin/' . $key . '/' . (int) $row['id'] . '/edit')) ?>">
                                    <?= icon('edit') ?>
                                </a>
                                <form method="post" style="display:inline"
                                      action="<?= e(url('/admin/' . $key . '/' . (int) $row['id'] . '/delete')) ?>"
                                      data-confirm="Delete this <?= e(strtolower($resource['singular'])) ?>? This cannot be undone.">
                                    <?= csrf_field() ?>
                                    <button class="icon-btn icon-btn--danger" type="submit" title="Delete" aria-label="Delete"><?= icon('trash') ?></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?= $view->partial('partials/pagination', ['paginator' => $paginator, 'baseUrl' => '/admin/' . $key, 'query' => $query]) ?>
    <?php endif; ?>
</div>
