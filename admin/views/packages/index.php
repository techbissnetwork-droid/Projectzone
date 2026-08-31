<?php /** @var array $rows */ ?>
<div class="page-header">
    <div>
        <h1>Packages</h1>
        <p>All pricing on the public site is read from these records. A saving is only ever shown when a prepaid price is genuinely lower than the regular price.</p>
    </div>
    <div class="page-header__actions">
        <a class="btn btn--ghost btn--sm" href="<?= e(url('/admin/package_addons')) ?>"><?= icon('plus') ?>Add-ons</a>
        <a class="btn btn--primary btn--sm" href="<?= e(url('/admin/packages/create')) ?>"><?= icon('plus') ?>New package</a>
    </div>
</div>

<div class="panel">
    <?php if (!$rows): ?>
        <div class="panel__body">
            <div class="empty-state" style="border:0;background:none">
                <span class="empty-state__icon"><?= icon('package') ?></span>
                <h3>No packages yet</h3>
                <p>Packages are how most visitors will buy. Create one and it appears on /packages straight away.</p>
                <a class="btn btn--primary mt-4" href="<?= e(url('/admin/packages/create')) ?>">Create the first package</a>
            </div>
        </div>
    <?php else: ?>
    <div class="table-wrap" style="border:0;border-radius:0;background:none">
        <table class="data-table" style="min-width:820px">
            <thead>
                <tr>
                    <th aria-label="Reorder"></th>
                    <th>Package</th>
                    <th class="num">Regular</th>
                    <th class="num">Prepaid</th>
                    <th class="num">Saving</th>
                    <th>Features</th>
                    <th>Featured</th>
                    <th>Status</th>
                    <th class="actions">Actions</th>
                </tr>
            </thead>
            <tbody data-sortable="<?= e(url('/admin/packages/reorder')) ?>" data-sortable-item="tr[data-id]">
                <?php foreach ($rows as $row): $p = $row['pricing']; ?>
                <tr data-id="<?= (int) $row['id'] ?>">
                    <td class="drag-handle" data-drag-handle><?= icon('drag') ?></td>
                    <td>
                        <a href="<?= e(url('/admin/packages/' . (int) $row['id'] . '/edit')) ?>">
                            <span class="cell-title"><?= e($row['name']) ?>
                                <?php if (!empty($row['badge'])): ?><span class="badge badge--accent"><?= e($row['badge']) ?></span><?php endif; ?>
                            </span>
                            <span class="cell-sub"><?= e(str_limit($row['tagline'], 56)) ?></span>
                        </a>
                    </td>
                    <td class="num"><?= $p['is_custom'] ? '<span class="hint">—</span>' : e(money($p['regular'])) ?></td>
                    <td class="num">
                        <?php if ($p['is_custom']): ?>
                            <span class="badge badge--info">Custom quote</span>
                        <?php elseif ($p['prepaid'] === null): ?>
                            <span class="hint">Not set</span>
                        <?php else: ?>
                            <strong><?= e(money($p['prepaid'])) ?></strong>
                        <?php endif; ?>
                    </td>
                    <td class="num">
                        <?php if ($p['has_discount']): ?>
                            <span class="status-dot status-dot--live"><?= e(money($p['saving'])) ?> (<?= (int) $p['percent'] ?>%)</span>
                        <?php else: ?>
                            <span class="hint">—</span>
                        <?php endif; ?>
                    </td>
                    <td><span class="hint tabular"><?= (int) $row['feature_count'] ?></span></td>
                    <td>
                        <label class="switch">
                            <input type="checkbox" <?= (int) $row['is_featured'] === 1 ? 'checked' : '' ?>
                                   data-toggle-url="<?= e(url('/admin/packages/' . (int) $row['id'] . '/toggle')) ?>"
                                   data-toggle-column="is_featured" aria-label="Toggle featured">
                            <span class="switch__track" aria-hidden="true"></span>
                        </label>
                    </td>
                    <td>
                        <span class="status-dot status-dot--<?= (int) $row['is_published'] === 1 ? 'live' : 'draft' ?>">
                            <?= (int) $row['is_published'] === 1 ? 'Published' : 'Draft' ?>
                        </span>
                    </td>
                    <td class="actions">
                        <div class="row-actions">
                            <?php if ((int) $row['is_published'] === 1): ?>
                            <a class="icon-btn" target="_blank" rel="noopener" title="View"
                               href="<?= e(url('/packages/' . $row['slug'])) ?>"><?= icon('external') ?></a>
                            <?php endif; ?>
                            <a class="icon-btn" title="Edit" href="<?= e(url('/admin/packages/' . (int) $row['id'] . '/edit')) ?>"><?= icon('edit') ?></a>
                            <form method="post" style="display:inline" action="<?= e(url('/admin/packages/' . (int) $row['id'] . '/delete')) ?>"
                                  data-confirm="Delete this package? Packages with purchase history cannot be deleted.">
                                <?= csrf_field() ?>
                                <button class="icon-btn icon-btn--danger" type="submit" title="Delete"><?= icon('trash') ?></button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
