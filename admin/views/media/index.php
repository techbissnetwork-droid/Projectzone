<?php
/** @var array $rows @var array $folders @var \Techbiss\Core\Paginator $paginator */
$query = array_filter(['q' => $search, 'folder' => $folder, 'type' => $type]);
$human = static function (int $b): string {
    $u = ['B', 'KB', 'MB', 'GB']; $i = 0; $n = (float) $b;
    while ($n >= 1024 && $i < 3) { $n /= 1024; $i++; }
    return round($n, $i === 0 ? 0 : 1) . ' ' . $u[$i];
};
?>
<div class="page-header">
    <div>
        <h1>Media library</h1>
        <p><?= (int) $totalFiles ?> file<?= (int) $totalFiles === 1 ? '' : 's' ?> · <?= e($human((int) $totalBytes)) ?> stored ·
           max <?= e($human((int) $maxBytes)) ?> per upload</p>
    </div>
</div>

<form class="panel mb-4" method="post" action="<?= e(url('/admin/media/upload')) ?>" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <div class="panel__body">
        <label class="dropzone" data-dropzone style="display:block;cursor:pointer">
            <div class="dropzone__icon"><?= icon('upload') ?></div>
            <strong style="font-size:var(--fs-sm)">Drop files here, or click to choose</strong>
            <p class="hint mt-2">
                Accepted: <?= e(implode(', ', $allowed)) ?>. Files are validated by their real content, not their extension.
            </p>
            <input type="file" name="files[]" multiple hidden accept="image/*,application/pdf">
        </label>
        <div class="row row--tight mt-4">
            <label class="sr-only" for="up-folder">Folder</label>
            <select class="select" id="up-folder" name="folder" style="max-width:180px">
                <?php foreach ($folders as $f): ?>
                <option value="<?= e($f) ?>" <?= $folder === $f ? 'selected' : '' ?>><?= e(ucfirst($f)) ?></option>
                <?php endforeach; ?>
            </select>
            <label class="sr-only" for="up-alt">Alt text</label>
            <input class="input" id="up-alt" type="text" name="alt_text" placeholder="Alt text (optional, applies to all uploads)" maxlength="255" style="max-width:340px">
        </div>
    </div>
</form>

<form class="toolbar" method="get" action="<?= e(url('/admin/media')) ?>">
    <div class="input-group toolbar__search">
        <?= icon('search', 'icon icon--lead') ?>
        <label class="sr-only" for="md-q">Search media</label>
        <input class="input" id="md-q" type="search" name="q" value="<?= e($search) ?>" placeholder="Filename or alt text" data-search-submit>
    </div>
    <label class="sr-only" for="md-folder">Folder</label>
    <select class="select" id="md-folder" name="folder" data-autosubmit style="max-width:170px">
        <option value="">All folders</option>
        <?php foreach ($folders as $f): ?>
        <option value="<?= e($f) ?>" <?= $folder === $f ? 'selected' : '' ?>><?= e(ucfirst($f)) ?></option>
        <?php endforeach; ?>
    </select>
    <label class="sr-only" for="md-type">Type</label>
    <select class="select" id="md-type" name="type" data-autosubmit style="max-width:150px">
        <option value="">All types</option>
        <option value="image" <?= $type === 'image' ? 'selected' : '' ?>>Images</option>
        <option value="document" <?= $type === 'document' ? 'selected' : '' ?>>Documents</option>
    </select>
    <?php if ($query): ?><a class="btn btn--quiet btn--sm" href="<?= e(url('/admin/media')) ?>">Clear</a><?php endif; ?>
</form>

<div class="panel">
    <h2 class="sr-only">Files</h2>
    <?php if (!$rows): ?>
        <div class="panel__body">
            <div class="empty-state" style="border:0;background:none">
                <span class="empty-state__icon"><?= icon('image') ?></span>
                <h3>No files <?= $query ? 'match those filters' : 'yet' ?></h3>
                <p>Upload images here once and reuse them anywhere on the site.</p>
            </div>
        </div>
    <?php else: ?>
    <div class="panel__body">
        <div class="media-grid">
            <?php foreach ($rows as $row):
                $isImage = str_starts_with((string) $row['mime_type'], 'image/');
                $thumb   = media_url($row['thumb_path'] !== '' ? $row['thumb_path'] : $row['path']);
            ?>
            <div class="media-item">
                <div class="media-item__thumb">
                    <?php if ($isImage): ?>
                        <img src="<?= e($thumb) ?>" alt="<?= e($row['alt_text']) ?>" loading="lazy">
                    <?php else: ?>
                        <?= icon('file') ?>
                    <?php endif; ?>
                </div>
                <div class="media-item__body">
                    <div class="media-item__name" title="<?= e($row['original_name']) ?>"><?= e($row['original_name']) ?></div>
                    <div class="media-item__meta">
                        <?= e($human((int) $row['size_bytes'])) ?>
                        <?php if ($row['width']): ?> · <?= (int) $row['width'] ?>×<?= (int) $row['height'] ?><?php endif; ?>
                    </div>
                    <div class="row row--tight mt-2">
                        <button class="icon-btn" type="button" data-copy="<?= e($row['path']) ?>" title="Copy path" aria-label="Copy path"><?= icon('copy') ?></button>
                        <a class="icon-btn" href="<?= e(media_url($row['path'])) ?>" target="_blank" rel="noopener" title="Open" aria-label="Open"><?= icon('external') ?></a>
                        <form method="post" style="display:inline" action="<?= e(url('/admin/media/' . (int) $row['id'] . '/delete')) ?>"
                              data-confirm="Delete this file permanently? Anything using it will lose the image.">
                            <?= csrf_field() ?>
                            <button class="icon-btn icon-btn--danger" type="submit" title="Delete" aria-label="Delete"><?= icon('trash') ?></button>
                        </form>
                    </div>
                    <form method="post" action="<?= e(url('/admin/media/' . (int) $row['id'])) ?>" class="mt-2">
                        <?= csrf_field() ?>
                        <label class="sr-only" for="alt-<?= (int) $row['id'] ?>">Alt text</label>
                        <input class="input" id="alt-<?= (int) $row['id'] ?>" type="text" name="alt_text"
                               value="<?= e($row['alt_text']) ?>" placeholder="Alt text" maxlength="255"
                               style="min-height:32px;font-size:.72rem">
                        <input type="hidden" name="folder" value="<?= e($row['folder']) ?>">
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?= $view->partial('partials/pagination', ['paginator' => $paginator, 'baseUrl' => '/admin/media', 'query' => $query]) ?>
    <?php endif; ?>
</div>

<p class="help-text mt-4">
    <?= icon('shield') ?>
    Uploads are stored outside the application code, served with execution disabled, and validated against their real MIME type.
    Press Enter in an alt-text box to save it.
</p>
