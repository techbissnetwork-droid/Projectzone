<?php /** @var array $rows @var array $categories */ ?>
<div class="page-header">
    <div>
        <h1>Blog posts</h1>
        <p><?= count($rows) ?> post<?= count($rows) === 1 ? '' : 's' ?>. Scheduled posts go live automatically at their publish time.</p>
    </div>
    <div class="page-header__actions">
        <a class="btn btn--ghost btn--sm" href="<?= e(url('/admin/blog_categories')) ?>">Categories</a>
        <a class="btn btn--primary btn--sm" href="<?= e(url('/admin/blog/create')) ?>"><?= icon('plus') ?>New post</a>
    </div>
</div>

<form class="toolbar" method="get" action="<?= e(url('/admin/blog')) ?>">
    <div class="input-group toolbar__search">
        <?= icon('search', 'icon icon--lead') ?>
        <label class="sr-only" for="b-q">Search posts</label>
        <input class="input" id="b-q" type="search" name="q" value="<?= e($search) ?>" placeholder="Search by title" data-search-submit>
    </div>
    <label class="sr-only" for="b-status">Status</label>
    <select class="select" id="b-status" name="status" data-autosubmit style="max-width:150px">
        <option value="">All statuses</option>
        <option value="published" <?= $status === 'published' ? 'selected' : '' ?>>Published</option>
        <option value="scheduled" <?= $status === 'scheduled' ? 'selected' : '' ?>>Scheduled</option>
        <option value="draft" <?= $status === 'draft' ? 'selected' : '' ?>>Draft</option>
    </select>
    <label class="sr-only" for="b-cat">Category</label>
    <select class="select" id="b-cat" name="category" data-autosubmit style="max-width:180px">
        <option value="">All categories</option>
        <?php foreach ($categories as $cat): ?>
        <option value="<?= (int) $cat['id'] ?>" <?= $categoryId === (int) $cat['id'] ? 'selected' : '' ?>><?= e($cat['name']) ?></option>
        <?php endforeach; ?>
    </select>
    <?php if ($search !== '' || $status !== '' || $categoryId > 0): ?>
    <a class="btn btn--quiet btn--sm" href="<?= e(url('/admin/blog')) ?>">Clear</a>
    <?php endif; ?>
</form>

<div class="panel">
    <?php if (!$rows): ?>
        <div class="panel__body">
            <div class="empty-state" style="border:0;background:none">
                <span class="empty-state__icon"><?= icon('edit') ?></span>
                <h3>No posts <?= ($search !== '' || $status !== '' || $categoryId > 0) ? 'match those filters' : 'yet' ?></h3>
                <p>Writing about the problems your customers actually have is the cheapest search visibility there is.</p>
                <a class="btn btn--primary mt-4" href="<?= e(url('/admin/blog/create')) ?>">Write the first post</a>
            </div>
        </div>
    <?php else: ?>
    <div class="table-wrap" style="border:0;border-radius:0;background:none">
        <table class="data-table" style="min-width:800px">
            <thead><tr><th>Post</th><th>Category</th><th>Author</th><th>Status</th><th class="num">Views</th><th class="num">Published</th><th class="actions">Actions</th></tr></thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                <tr>
                    <td>
                        <div class="row row--tight row--nowrap">
                            <?php $img = media_url($row['featured_image']); ?>
                            <span class="media-field__preview" style="width:46px;height:34px">
                                <?php if ($img !== ''): ?><img src="<?= e($img) ?>" alt="" loading="lazy"><?php else: ?><?= icon('file') ?><?php endif; ?>
                            </span>
                            <a href="<?= e(url('/admin/blog/' . (int) $row['id'] . '/edit')) ?>">
                                <span class="cell-title"><?= e(str_limit($row['title'], 52)) ?></span>
                                <span class="cell-sub"><?= (int) $row['reading_minutes'] ?> min read</span>
                            </a>
                        </div>
                    </td>
                    <td><?php if ($row['category_name']): ?><span class="badge"><?= e($row['category_name']) ?></span><?php endif; ?></td>
                    <td><span class="hint"><?= e($row['author_display'] ?: $row['author_name'] ?: '—') ?></span></td>
                    <td>
                        <span class="status-dot status-dot--<?= match ($row['status']) { 'published' => 'live', 'scheduled' => 'warn', default => 'draft' } ?>">
                            <?= e(ucfirst((string) $row['status'])) ?>
                        </span>
                    </td>
                    <td class="num"><span class="hint"><?= (int) $row['view_count'] ?></span></td>
                    <td class="num"><span class="hint"><?= $row['published_at'] ? e(format_date($row['published_at'])) : '—' ?></span></td>
                    <td class="actions">
                        <div class="row-actions">
                            <?php if ($row['status'] === 'published'): ?>
                            <a class="icon-btn" target="_blank" rel="noopener" title="View"
                               href="<?= e(url('/blog/' . $row['slug'])) ?>"><?= icon('external') ?></a>
                            <?php endif; ?>
                            <a class="icon-btn" title="Edit" href="<?= e(url('/admin/blog/' . (int) $row['id'] . '/edit')) ?>"><?= icon('edit') ?></a>
                            <form method="post" style="display:inline" action="<?= e(url('/admin/blog/' . (int) $row['id'] . '/toggle')) ?>">
                                <?= csrf_field() ?>
                                <button class="icon-btn" type="submit" title="<?= $row['status'] === 'published' ? 'Unpublish' : 'Publish now' ?>">
                                    <?= icon($row['status'] === 'published' ? 'eye-off' : 'eye') ?>
                                </button>
                            </form>
                            <form method="post" style="display:inline" action="<?= e(url('/admin/blog/' . (int) $row['id'] . '/delete')) ?>"
                                  data-confirm="Delete this post permanently?">
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
