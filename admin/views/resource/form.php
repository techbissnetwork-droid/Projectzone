<?php
/** Generic resource form. @var array $resource @var array $row @var bool $isNew @var array $extras */
$key      = $resourceKey;
$action   = $isNew ? url('/admin/' . $key) : url('/admin/' . $key . '/' . (int) $row['id']);
$main     = array_values(array_filter($resource['fields'], static fn ($f) => ($f['group'] ?? 'main') === 'main'));
$seo      = array_values(array_filter($resource['fields'], static fn ($f) => ($f['group'] ?? '') === 'seo'));
$sidebarTypes = ['bool', 'icon', 'accent', 'media', 'lookup', 'select'];
$sideKeys = ['is_published', 'is_featured', 'noindex', 'icon', 'accent', 'image', 'hero_image', 'template', 'portfolio_id', 'rating', 'billing_period', 'currency'];
$body     = array_values(array_filter($main, static fn ($f) => !in_array($f['key'], $sideKeys, true)));
$aside    = array_values(array_filter($main, static fn ($f) => in_array($f['key'], $sideKeys, true)));
?>
<div class="page-header">
    <div>
        <h1><?= e($title) ?></h1>
        <p><?= e($resource['plural']) ?> · changes appear on the public site as soon as they are saved.</p>
    </div>
    <div class="page-header__actions">
        <a class="btn btn--ghost btn--sm" href="<?= e(url('/admin/' . $key)) ?>"><?= icon('arrow-left') ?>Back to list</a>
        <?php if (!$isNew && !empty($resource['public_url']) && !empty($row['slug'])): ?>
        <a class="btn btn--quiet btn--sm" target="_blank" rel="noopener"
           href="<?= e(url(str_replace('{slug}', (string) $row['slug'], $resource['public_url']))) ?>">
            <?= icon('external') ?>View
        </a>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($resource['notice'])): ?>
<div class="notice notice--accent mb-4"><?= icon('info') ?><span><?= e($resource['notice']) ?></span></div>
<?php endif; ?>

<form method="post" action="<?= e($action) ?>" data-dirty-guard>
    <?= csrf_field() ?>

    <div class="form-cols">
        <div>
            <div class="panel">
                <div class="panel__head"><span class="panel__title">Content</span></div>
                <div class="panel__body">
                    <div class="form-section">
                        <?php foreach ($body as $field): ?>
                            <?= $view->partial('partials/field', [
                                'field'  => $field,
                                'value'  => $row[$field['key']] ?? ($field['default'] ?? ''),
                                'extras' => $extras,
                            ]) ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <?php if (!empty($resource['repeater'])): ?>
            <div class="panel" data-repeater>
                <div class="panel__head">
                    <div>
                        <span class="panel__title"><?= e($resource['repeater']['title']) ?></span>
                        <div class="panel__sub">Drag to reorder. Empty rows are ignored on save.</div>
                    </div>
                    <button class="btn btn--ghost btn--sm" type="button" data-repeater-add><?= icon('plus') ?>Add row</button>
                </div>
                <div class="panel__body">
                    <div class="repeater" data-repeater-list data-sortable data-sortable-item="[data-repeater-row]">
                        <?php foreach (($extras['repeater_rows'] ?? []) as $i => $r): ?>
                        <div class="repeater__row" data-repeater-row data-id="<?= (int) $r['id'] ?>">
                            <span class="repeater__handle" data-drag-handle><?= icon('drag') ?></span>
                            <div class="repeater__fields">
                                <?php foreach ($resource['repeater']['fields'] as $rf): ?>
                                <input class="input" type="text" data-index-token
                                       name="repeater[<?= $i ?>][<?= e($rf['key']) ?>]"
                                       value="<?= e($r[$rf['key']] ?? '') ?>"
                                       placeholder="<?= e($rf['label']) ?>" maxlength="500">
                                <?php endforeach; ?>
                            </div>
                            <button class="icon-btn icon-btn--danger" type="button" data-repeater-remove aria-label="Remove row">
                                <?= icon('trash') ?>
                            </button>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <template data-repeater-template>
                        <div class="repeater__row" data-repeater-row>
                            <span class="repeater__handle" data-drag-handle><?= icon('drag') ?></span>
                            <div class="repeater__fields">
                                <?php foreach ($resource['repeater']['fields'] as $rf): ?>
                                <input class="input" type="text" data-index-token
                                       name="repeater[__INDEX__][<?= e($rf['key']) ?>]"
                                       placeholder="<?= e($rf['label']) ?>" maxlength="500">
                                <?php endforeach; ?>
                            </div>
                            <button class="icon-btn icon-btn--danger" type="button" data-repeater-remove aria-label="Remove row">
                                <?= icon('trash') ?>
                            </button>
                        </div>
                    </template>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($seo): ?>
            <div class="panel">
                <div class="panel__head">
                    <div>
                        <span class="panel__title">Search &amp; social</span>
                        <div class="panel__sub">Leave blank to fall back to the title and description above.</div>
                    </div>
                </div>
                <div class="panel__body">
                    <div class="form-section">
                        <?php foreach ($seo as $field): ?>
                            <?= $view->partial('partials/field', [
                                'field'  => $field,
                                'value'  => $row[$field['key']] ?? ($field['default'] ?? ''),
                                'extras' => $extras,
                            ]) ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <aside class="form-aside">
            <?php if ($aside): ?>
            <div class="panel">
                <div class="panel__head"><span class="panel__title">Options</span></div>
                <div class="panel__body">
                    <div class="form-section">
                        <?php foreach ($aside as $field): ?>
                            <?= $view->partial('partials/field', [
                                'field'  => $field,
                                'value'  => $row[$field['key']] ?? ($field['default'] ?? ''),
                                'extras' => $extras,
                            ]) ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php if (!$isNew): ?>
            <div class="panel">
                <div class="panel__body">
                    <div class="kv-list">
                        <?php if (!empty($row['created_at'])): ?>
                        <div class="kv"><span class="kv__label">Created</span><span class="kv__value"><?= e(format_date($row['created_at'], 'j M Y, H:i')) ?></span></div>
                        <?php endif; ?>
                        <?php if (!empty($row['updated_at'])): ?>
                        <div class="kv"><span class="kv__label">Last updated</span><span class="kv__value"><?= e(time_ago($row['updated_at'])) ?></span></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </aside>
    </div>

    <div class="sticky-actions">
        <button class="btn btn--primary" type="submit"><?= icon('check') ?><?= $isNew ? 'Create' : 'Save changes' ?></button>
        <a class="btn btn--quiet" href="<?= e(url('/admin/' . $key)) ?>">Cancel</a>
        <span class="hint ml-auto hide-sm">Changes go live on the public site immediately.</span>
    </div>
</form>
