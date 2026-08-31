<?php /** @var array $rows */ ?>
<div class="page-header">
    <div>
        <h1>Homepage content</h1>
        <p>Every band on the homepage — headline, the starting-point pitch, services, work, industries, the closing call to action — is edited here.</p>
    </div>
    <div class="page-header__actions">
        <a class="btn btn--ghost btn--sm" target="_blank" rel="noopener" href="<?= e(url('/')) ?>"><?= icon('external') ?>View homepage</a>
    </div>
</div>

<div class="panel">
    <div class="table-wrap" style="border:0;border-radius:0;background:none">
        <table class="data-table" style="min-width:700px">
            <thead><tr><th aria-label="Reorder"></th><th>Section</th><th>Heading</th><th class="num">Items</th><th>Visible</th><th class="actions">Actions</th></tr></thead>
            <tbody data-sortable="<?= e(url('/admin/homepage/reorder')) ?>" data-sortable-item="tr[data-id]">
                <?php foreach ($rows as $row): ?>
                <tr data-id="<?= (int) $row['id'] ?>">
                    <td class="drag-handle" data-drag-handle><?= icon('drag') ?></td>
                    <td>
                        <a href="<?= e(url('/admin/homepage/' . (int) $row['id'] . '/edit')) ?>">
                            <span class="cell-title"><?= e(ucfirst(str_replace('_', ' ', (string) $row['section_key']))) ?></span>
                            <span class="cell-sub mono-sm"><?= e($row['section_key']) ?></span>
                        </a>
                    </td>
                    <td><span class="hint"><?= e(str_limit($row['heading'], 62)) ?></span></td>
                    <td class="num"><span class="hint"><?= (int) $row['item_count'] ?></span></td>
                    <td>
                        <label class="switch">
                            <input type="checkbox" <?= (int) $row['is_published'] === 1 ? 'checked' : '' ?>
                                   data-toggle-url="<?= e(url('/admin/homepage/' . (int) $row['id'] . '/toggle')) ?>"
                                   aria-label="Toggle visible">
                            <span class="switch__track" aria-hidden="true"></span>
                        </label>
                    </td>
                    <td class="actions">
                        <a class="icon-btn" title="Edit" aria-label="Edit" href="<?= e(url('/admin/homepage/' . (int) $row['id'] . '/edit')) ?>"><?= icon('edit') ?></a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<p class="help-text mt-4">
    <?= icon('info') ?>
    Hiding a section removes it from the homepage entirely. Sections that pull from other tables — services,
    portfolio, industries — use their own content but take their heading and intro copy from here.
</p>
