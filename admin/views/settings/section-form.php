<?php
/** @var array $row @var array $items */
$val = static fn (string $k, $d = '') => old($k, $row[$k] ?? $d);
$formItems = old('items', null);
$rows = $formItems !== null ? $formItems : $items;
?>
<div class="page-header">
    <div>
        <h1><?= e(ucfirst(str_replace('_', ' ', (string) $row['section_key']))) ?> section</h1>
        <p>Homepage · <span class="mono-sm"><?= e($row['section_key']) ?></span></p>
    </div>
    <div class="page-header__actions">
        <a class="btn btn--ghost btn--sm" href="<?= e(url('/admin/homepage')) ?>"><?= icon('arrow-left') ?>Back</a>
        <a class="btn btn--quiet btn--sm" target="_blank" rel="noopener" href="<?= e(url('/')) ?>"><?= icon('external') ?>View</a>
    </div>
</div>

<form method="post" action="<?= e(url('/admin/homepage/' . (int) $row['id'])) ?>" data-dirty-guard>
    <?= csrf_field() ?>
    <div class="form-cols">
        <div>
            <div class="panel">
                <div class="panel__head"><span class="panel__title">Copy</span></div>
                <div class="panel__body">
                    <div class="form-section">
                        <div class="field">
                            <label class="label" for="sec-eyebrow">Eyebrow</label>
                            <input class="input" id="sec-eyebrow" type="text" name="eyebrow" value="<?= e($val('eyebrow')) ?>" maxlength="120" data-counter>
                            <span class="hint">The small uppercase label above the heading.</span>
                        </div>
                        <div class="field">
                            <label class="label" for="sec-heading">Heading</label>
                            <input class="input" id="sec-heading" type="text" name="heading" value="<?= e($val('heading')) ?>" maxlength="255" data-counter>
                        </div>
                        <div class="field">
                            <label class="label" for="sec-sub">Supporting text</label>
                            <textarea class="textarea" id="sec-sub" name="subheading" maxlength="500" data-counter><?= e($val('subheading')) ?></textarea>
                        </div>
                        <div class="field--row">
                            <div class="field">
                                <label class="label" for="sec-cta">Button label</label>
                                <input class="input" id="sec-cta" type="text" name="cta_label" value="<?= e($val('cta_label')) ?>" maxlength="80">
                                <span class="hint">Leave blank to hide the button.</span>
                            </div>
                            <div class="field">
                                <label class="label" for="sec-url">Button link</label>
                                <input class="input" id="sec-url" type="text" name="cta_url" value="<?= e($val('cta_url')) ?>" maxlength="500" placeholder="/start">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="panel" data-repeater>
                <div class="panel__head">
                    <div>
                        <span class="panel__title">Items</span>
                        <div class="panel__sub">The repeated cards, bullets or chain steps inside this section.</div>
                    </div>
                    <button class="btn btn--ghost btn--sm" type="button" data-repeater-add><?= icon('plus') ?>Add item</button>
                </div>
                <div class="panel__body">
                    <div class="repeater" data-repeater-list data-sortable data-sortable-item="[data-repeater-row]">
                        <?php foreach ($rows as $i => $item): ?>
                        <div class="repeater__row" data-repeater-row data-id="<?= $i ?>">
                            <span class="repeater__handle" data-drag-handle><?= icon('drag') ?></span>
                            <div class="repeater__fields">
                                <input class="input" type="text" data-index-token name="items[<?= $i ?>][title]"
                                       value="<?= e($item['title'] ?? '') ?>" placeholder="Title" maxlength="190">
                                <input class="input" type="text" data-index-token name="items[<?= $i ?>][description]"
                                       value="<?= e($item['description'] ?? '') ?>" placeholder="Description" maxlength="500">
                                <div class="field--row" style="gap:.5rem">
                                    <input class="input" type="text" data-index-token name="items[<?= $i ?>][icon]"
                                           value="<?= e($item['icon'] ?? '') ?>" placeholder="Icon name" maxlength="60">
                                    <input class="input" type="text" data-index-token name="items[<?= $i ?>][value]"
                                           value="<?= e($item['value'] ?? '') ?>" placeholder="Number / badge" maxlength="60">
                                </div>
                            </div>
                            <button class="icon-btn icon-btn--danger" type="button" data-repeater-remove aria-label="Remove"><?= icon('trash') ?></button>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <template data-repeater-template>
                        <div class="repeater__row" data-repeater-row>
                            <span class="repeater__handle" data-drag-handle><?= icon('drag') ?></span>
                            <div class="repeater__fields">
                                <input class="input" type="text" data-index-token name="items[__INDEX__][title]" placeholder="Title" maxlength="190">
                                <input class="input" type="text" data-index-token name="items[__INDEX__][description]" placeholder="Description" maxlength="500">
                                <div class="field--row" style="gap:.5rem">
                                    <input class="input" type="text" data-index-token name="items[__INDEX__][icon]" placeholder="Icon name" maxlength="60">
                                    <input class="input" type="text" data-index-token name="items[__INDEX__][value]" placeholder="Number / badge" maxlength="60">
                                </div>
                            </div>
                            <button class="icon-btn icon-btn--danger" type="button" data-repeater-remove aria-label="Remove"><?= icon('trash') ?></button>
                        </div>
                    </template>

                    <p class="help-text mt-4">
                        Icon names come from the built-in set — for example <code>globe</code>, <code>server</code>,
                        <code>window</code>, <code>mail</code>, <code>layers</code>, <code>search</code>, <code>trend</code>.
                        An unknown name falls back to a neutral icon.
                    </p>
                </div>
            </div>
        </div>

        <aside class="form-aside">
            <div class="panel">
                <div class="panel__head"><span class="panel__title">Visibility</span></div>
                <div class="panel__body">
                    <label class="switch">
                        <input type="checkbox" name="is_published" value="1" <?= (int) $val('is_published', 1) === 1 ? 'checked' : '' ?>>
                        <span class="switch__track" aria-hidden="true"></span><span>Show this section</span>
                    </label>
                    <div class="field mt-4">
                        <span class="label">Background image</span>
                        <div class="media-field" data-media-field>
                            <div class="media-field__preview">
                                <?php $img = media_url($val('image')); ?>
                                <?php if ($img !== ''): ?><img src="<?= e($img) ?>" alt=""><?php else: ?><?= icon('image') ?><?php endif; ?>
                            </div>
                            <div class="media-field__body">
                                <span class="media-field__path"><?= $val('image') !== '' ? e($val('image')) : 'No image selected' ?></span>
                                <input type="hidden" name="image" value="<?= e($val('image')) ?>">
                                <div class="row row--tight">
                                    <button class="btn btn--ghost btn--sm" type="button" data-media-choose>Choose</button>
                                    <button class="btn btn--quiet btn--sm" type="button" data-media-clear>Clear</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </aside>
    </div>

    <div class="sticky-actions">
        <button class="btn btn--primary" type="submit"><?= icon('check') ?>Save section</button>
        <a class="btn btn--quiet" href="<?= e(url('/admin/homepage')) ?>">Cancel</a>
    </div>
</form>
