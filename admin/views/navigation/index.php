<?php /** @var array $rows @var string $menu @var array $menus */ ?>
<div class="page-header">
    <div>
        <h1>Navigation</h1>
        <p>Menus are read live by the site. Reordering here changes the order visitors see immediately.</p>
    </div>
    <div class="page-header__actions">
        <a class="btn btn--primary btn--sm" href="<?= e(url('/admin/navigation/create?menu=' . urlencode($menu))) ?>">
            <?= icon('plus') ?>New item
        </a>
    </div>
</div>

<div class="tabs">
    <?php foreach ($menus as $m): ?>
    <a class="tab<?= $menu === $m ? ' is-active' : '' ?>" href="<?= e(url('/admin/navigation?menu=' . urlencode($m))) ?>">
        <?= e(ucfirst($m)) ?> menu
    </a>
    <?php endforeach; ?>
</div>

<div class="panel">
    <?php if (!$rows): ?>
        <div class="panel__body">
            <div class="empty-state" style="border:0;background:none">
                <span class="empty-state__icon"><?= icon('link') ?></span>
                <h3>No items in the <?= e($menu) ?> menu</h3>
                <p>Add the first link and it appears in the site navigation straight away.</p>
                <a class="btn btn--primary mt-4" href="<?= e(url('/admin/navigation/create?menu=' . urlencode($menu))) ?>">Add an item</a>
            </div>
        </div>
    <?php else: ?>
    <div class="table-wrap" style="border:0;border-radius:0;background:none">
        <table class="data-table" style="min-width:720px">
            <thead><tr><th aria-label="Reorder"></th><th>Label</th><th>Destination</th><th>Type</th><th>Active</th><th class="actions">Actions</th></tr></thead>
            <tbody data-sortable="<?= e(url('/admin/navigation/reorder')) ?>" data-sortable-item="tr[data-id]">
                <?php foreach ($rows as $row): $isChild = !empty($row['parent_id']); ?>
                <tr data-id="<?= (int) $row['id'] ?>">
                    <td class="drag-handle" data-drag-handle><?= icon('drag') ?></td>
                    <td style="<?= $isChild ? 'padding-left:2.4rem' : '' ?>">
                        <a href="<?= e(url('/admin/navigation/' . (int) $row['id'] . '/edit')) ?>">
                            <span class="cell-title">
                                <?php if ($isChild): ?><span class="hint" style="margin-right:.35rem">↳</span><?php endif; ?>
                                <?= e($row['label']) ?>
                                <?php if ((int) $row['is_button'] === 1): ?><span class="badge badge--accent">Button</span><?php endif; ?>
                            </span>
                            <?php if (!empty($row['description'])): ?>
                            <span class="cell-sub"><?= e(str_limit($row['description'], 56)) ?></span>
                            <?php endif; ?>
                        </a>
                    </td>
                    <td><span class="mono-sm"><?= e($row['url'] ?: '— (dropdown only)') ?></span></td>
                    <td><span class="badge"><?= e($row['link_type']) ?></span></td>
                    <td>
                        <label class="switch">
                            <input type="checkbox" <?= (int) $row['is_active'] === 1 ? 'checked' : '' ?>
                                   data-toggle-url="<?= e(url('/admin/navigation/' . (int) $row['id'] . '/toggle')) ?>"
                                   aria-label="Toggle visible">
                            <span class="switch__track" aria-hidden="true"></span>
                        </label>
                    </td>
                    <td class="actions">
                        <div class="row-actions">
                            <a class="icon-btn" title="Edit" aria-label="Edit" href="<?= e(url('/admin/navigation/' . (int) $row['id'] . '/edit')) ?>"><?= icon('edit') ?></a>
                            <form method="post" style="display:inline" action="<?= e(url('/admin/navigation/' . (int) $row['id'] . '/delete')) ?>"
                                  data-confirm="Delete this menu item? Any dropdown items under it are deleted too.">
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
    <?php endif; ?>
</div>
