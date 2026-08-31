<?php /** @var array $rows @var array $categories @var string $search @var string $status @var int $categoryId */ ?>
<div class="page-header">
    <div>
        <h1>Premade projects</h1>
        <p><?= count($rows) ?> project<?= count($rows) === 1 ? '' : 's' ?> · drag the handle to change the order they appear on the site.</p>
    </div>
    <div class="page-header__actions">
        <a class="btn btn--primary btn--sm" href="<?= e(url('/admin/projects/create')) ?>"><?= icon('plus') ?>New project</a>
    </div>
</div>

<form class="toolbar" method="get" action="<?= e(url('/admin/projects')) ?>">
    <div class="input-group toolbar__search">
        <?= icon('search', 'icon icon--lead') ?>
        <label class="sr-only" for="pp-q">Search projects</label>
        <input class="input" id="pp-q" type="search" name="q" value="<?= e($search) ?>" placeholder="Search by name or tagline" data-search-submit>
    </div>
    <label class="sr-only" for="pp-status">Status</label>
    <select class="select" id="pp-status" name="status" data-autosubmit style="max-width:150px">
        <option value="">All statuses</option>
        <option value="published" <?= $status === 'published' ? 'selected' : '' ?>>Published</option>
        <option value="draft" <?= $status === 'draft' ? 'selected' : '' ?>>Draft</option>
        <option value="featured" <?= $status === 'featured' ? 'selected' : '' ?>>Featured</option>
    </select>
    <label class="sr-only" for="pp-cat">Category</label>
    <select class="select" id="pp-cat" name="category" data-autosubmit style="max-width:180px">
        <option value="">All categories</option>
        <?php foreach ($categories as $cat): ?>
        <option value="<?= (int) $cat['id'] ?>" <?= $categoryId === (int) $cat['id'] ? 'selected' : '' ?>><?= e($cat['name']) ?></option>
        <?php endforeach; ?>
    </select>
    <?php if ($search !== '' || $status !== '' || $categoryId > 0): ?>
    <a class="btn btn--quiet btn--sm" href="<?= e(url('/admin/projects')) ?>">Clear</a>
    <?php endif; ?>
</form>

<div class="panel">
    <?php if (!$rows): ?>
        <div class="panel__body">
            <div class="empty-state" style="border:0;background:none">
                <span class="empty-state__icon"><?= icon('rocket') ?></span>
                <h3>No premade projects <?= ($search !== '' || $status !== '' || $categoryId > 0) ? 'match those filters' : 'yet' ?></h3>
                <p>
                    List a build that is finished and can be handed over quickly. Add a working demo link so buyers can
                    see it before they ask. Price is never shown — you agree that in conversation.
                </p>
                <a class="btn btn--primary mt-4" href="<?= e(url('/admin/projects/create')) ?>"><?= icon('plus') ?>Add the first project</a>
            </div>
        </div>
    <?php else: ?>
    <div class="table-wrap" style="border:0;border-radius:0;background:none">
        <table class="data-table" style="min-width:820px">
            <thead>
                <tr>
                    <th aria-label="Reorder"></th>
                    <th>Project</th>
                    <th>Category</th>
                    <th>Demo</th>
                    <th>Images</th>
                    <th>Enquiries</th>
                    <th>Featured</th>
                    <th>Status</th>
                    <th class="actions">Actions</th>
                </tr>
            </thead>
            <tbody data-sortable="<?= e(url('/admin/projects/reorder')) ?>" data-sortable-item="tr[data-id]">
                <?php foreach ($rows as $row): ?>
                <tr data-id="<?= (int) $row['id'] ?>">
                    <td class="drag-handle" data-drag-handle title="Drag to reorder"><?= icon('drag') ?></td>
                    <td>
                        <div class="row row--tight row--nowrap">
                            <?php $thumb = media_url($row['thumbnail']); ?>
                            <span class="media-field__preview" style="width:46px;height:34px">
                                <?php if ($thumb !== ''): ?><img src="<?= e($thumb) ?>" alt="" loading="lazy">
                                <?php else: ?><?= icon('image') ?><?php endif; ?>
                            </span>
                            <a href="<?= e(url('/admin/projects/' . (int) $row['id'] . '/edit')) ?>">
                                <span class="cell-title"><?= e(str_limit($row['name'], 44)) ?></span>
                                <span class="cell-sub"><?= e(str_limit($row['tagline'] ?: 'No tagline set', 52)) ?></span>
                            </a>
                        </div>
                    </td>
                    <td><?php if ($row['category_name']): ?><span class="badge"><?= e($row['category_name']) ?></span><?php endif; ?></td>
                    <td>
                        <?php if ((string) $row['demo_url'] !== ''): ?>
                            <a class="icon-btn" title="Open the demo" aria-label="Open the demo" target="_blank" rel="noopener noreferrer"
                               href="<?= e($row['demo_url']) ?>"><?= icon('external') ?></a>
                        <?php else: ?>
                            <span class="hint">None</span>
                        <?php endif; ?>
                    </td>
                    <td><span class="hint tabular"><?= (int) $row['image_count'] ?></span></td>
                    <td><span class="hint tabular"><?= (int) $row['order_count'] ?></span></td>
                    <td>
                        <label class="switch">
                            <input type="checkbox" <?= (int) $row['is_featured'] === 1 ? 'checked' : '' ?>
                                   data-toggle-url="<?= e(url('/admin/projects/' . (int) $row['id'] . '/toggle')) ?>"
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
                            <a class="icon-btn" title="View on the site" aria-label="View on the site" target="_blank" rel="noopener"
                               href="<?= e(url('/premade-projects/' . $row['slug'])) ?>"><?= icon('eye') ?></a>
                            <?php endif; ?>
                            <a class="icon-btn" title="Edit" aria-label="Edit" href="<?= e(url('/admin/projects/' . (int) $row['id'] . '/edit')) ?>"><?= icon('edit') ?></a>
                            <form method="post" style="display:inline" action="<?= e(url('/admin/projects/' . (int) $row['id'] . '/duplicate')) ?>">
                                <?= csrf_field() ?>
                                <button class="icon-btn" type="submit" title="Duplicate as a draft" aria-label="Duplicate as a draft"><?= icon('copy') ?></button>
                            </form>
                            <form method="post" style="display:inline" action="<?= e(url('/admin/projects/' . (int) $row['id'] . '/delete')) ?>"
                                  data-confirm="Delete this project, its images and its features? Enquiries already received are kept. This cannot be undone.">
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
