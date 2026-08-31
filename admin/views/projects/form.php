<?php
/** @var array $row @var bool $isNew @var array $categories @var array $industries
 *  @var array $technologies @var array $selectedTech @var array $features @var array $images */
$id      = (int) ($row['id'] ?? 0);
$action  = $isNew ? url('/admin/projects') : url('/admin/projects/' . $id);
$val     = static fn (string $k, $d = '') => old($k, $row[$k] ?? $d);
$accents = ['cyan' => '#34d3e0', 'violet' => '#a78bfa', 'emerald' => '#34d399', 'amber' => '#fbbf24', 'rose' => '#fb7185', 'blue' => '#4f8cff'];
$selTech = array_map('intval', (array) old('technologies', $selectedTech));
$formFeatures = old('features', null);
?>
<div class="page-header">
    <div>
        <h1><?= e($title) ?></h1>
        <p>Empty fields are hidden on the public page. There is no price field — you agree that on WhatsApp or email.</p>
    </div>
    <div class="page-header__actions">
        <a class="btn btn--ghost btn--sm" href="<?= e(url('/admin/projects')) ?>"><?= icon('arrow-left') ?>Back</a>
        <?php if (!$isNew && !empty($row['slug'])): ?>
        <a class="btn btn--quiet btn--sm" target="_blank" rel="noopener" href="<?= e(url('/premade-projects/' . $row['slug'])) ?>"><?= icon('external') ?>View</a>
        <?php endif; ?>
    </div>
</div>

<form method="post" action="<?= e($action) ?>" enctype="multipart/form-data" data-dirty-guard>
    <?= csrf_field() ?>
    <div class="form-cols">
        <div>
            <div class="panel">
                <div class="panel__head"><span class="panel__title">Project</span></div>
                <div class="panel__body">
                    <div class="form-section">
                        <div class="field">
                            <label class="label" for="pr-name">Name <span class="req">*</span></label>
                            <input class="input<?= error_for('name') ? ' is-invalid' : '' ?>" id="pr-name" type="text"
                                   name="name" value="<?= e($val('name')) ?>" required maxlength="190" data-counter
                                   <?= $view->partial('partials/field-invalid', ['key' => 'name']) ?>>
                            <?= $view->partial('partials/field-error', ['key' => 'name']) ?>
                        </div>

                        <div class="field">
                            <label class="label" for="pr-slug">URL slug</label>
                            <div class="input-group">
                                <input class="input" id="pr-slug" type="text" name="slug" value="<?= e($val('slug')) ?>"
                                       maxlength="190" data-slug-from="name" spellcheck="false">
                                <button class="btn btn--quiet btn--sm" type="button" data-slug-regenerate
                                        aria-label="Regenerate slug from the title"><?= icon('refresh') ?></button>
                            </div>
                            <span class="hint">/premade-projects/<?= e($val('slug') ?: 'project-name') ?></span>
                        </div>

                        <div class="field">
                            <label class="label" for="pr-tagline">Tagline</label>
                            <input class="input" id="pr-tagline" type="text" name="tagline" value="<?= e($val('tagline')) ?>"
                                   maxlength="255" placeholder="One line. What it is, in plain words." data-counter>
                        </div>

                        <div class="field">
                            <label class="label" for="pr-short">Short description</label>
                            <textarea class="textarea" id="pr-short" name="short_description" maxlength="500" data-counter><?= e($val('short_description')) ?></textarea>
                            <span class="hint">Two sentences at most. Shown on the cards and used as the fallback meta description.</span>
                        </div>

                        <div class="field">
                            <label class="label" for="pr-desc">Details</label>
                            <textarea class="textarea" id="pr-desc" name="description" maxlength="30000"><?= e($val('description')) ?></textarea>
                            <span class="hint">Who it suits and what it does. Accepts HTML; left blank, the section is hidden.</span>
                        </div>

                        <div class="field">
                            <label class="label" for="pr-included">What you get</label>
                            <textarea class="textarea" id="pr-included" name="whats_included" maxlength="30000"><?= e($val('whats_included')) ?></textarea>
                            <span class="hint">Anything the tick list below does not cover.</span>
                        </div>

                        <div class="field">
                            <label class="label" for="pr-custom">Customisation note</label>
                            <textarea class="textarea" id="pr-custom" name="customisation_note" maxlength="500" data-counter><?= e($val('customisation_note')) ?></textarea>
                            <span class="hint">What can be changed for a buyer — colours, content, extra pages.</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Demo -->
            <div class="panel">
                <div class="panel__head">
                    <div>
                        <span class="panel__title">Live demo</span>
                        <div class="panel__sub">Without a demo URL, no demo button appears. Nothing here is invented for you.</div>
                    </div>
                </div>
                <div class="panel__body">
                    <div class="form-section">
                        <div class="field">
                            <label class="label" for="pr-demo">Demo URL</label>
                            <input class="input" id="pr-demo" type="text" name="demo_url" value="<?= e($val('demo_url')) ?>"
                                   maxlength="500" placeholder="demo.example.com">
                        </div>
                        <div class="field--row">
                            <div class="field">
                                <label class="label" for="pr-demo-admin">Demo admin URL</label>
                                <input class="input" id="pr-demo-admin" type="text" name="demo_admin_url" value="<?= e($val('demo_admin_url')) ?>"
                                       maxlength="500" placeholder="demo.example.com/admin">
                            </div>
                            <div class="field">
                                <label class="label" for="pr-demo-note">Demo note</label>
                                <input class="input" id="pr-demo-note" type="text" name="demo_note" value="<?= e($val('demo_note')) ?>"
                                       maxlength="255" placeholder="e.g. Data resets every night">
                            </div>
                        </div>
                        <div class="field--row">
                            <div class="field">
                                <label class="label" for="pr-demo-user">Demo username</label>
                                <input class="input" id="pr-demo-user" type="text" name="demo_username" value="<?= e($val('demo_username')) ?>"
                                       maxlength="120" autocomplete="off">
                            </div>
                            <div class="field">
                                <label class="label" for="pr-demo-pass">Demo password</label>
                                <input class="input" id="pr-demo-pass" type="text" name="demo_password" value="<?= e($val('demo_password')) ?>"
                                       maxlength="120" autocomplete="off">
                            </div>
                        </div>
                        <div class="alert alert--warn">
                            <strong>These credentials are printed on the public page.</strong>
                            Only ever use a throwaway account on the demo site. Never a real login, and never a password
                            used anywhere else.
                        </div>
                    </div>
                </div>
            </div>

            <!-- Mobile app -->
            <div class="panel">
                <div class="panel__head">
                    <div>
                        <span class="panel__title">Mobile app</span>
                        <div class="panel__sub">Only for builds that ship an app. Leave it all blank otherwise and nothing appears.</div>
                    </div>
                </div>
                <div class="panel__body">
                    <div class="form-section">
                        <?php $apk = (string) $val('apk_path'); ?>
                        <div class="field">
                            <label class="label" for="f-apk">Test APK</label>
                            <?php if ($apk !== ''): ?>
                            <div class="row row--tight">
                                <span class="badge"><?= e(basename($apk)) ?></span>
                                <span class="hint tabular"><?= e(human_bytes((int) $val('apk_size_bytes', 0))) ?></span>
                                <label class="check">
                                    <input type="checkbox" name="apk_remove" value="1">
                                    <span class="check__box" aria-hidden="true"></span><span>Remove on save</span>
                                </label>
                            </div>
                            <span class="hint">Uploading a new file replaces this one.</span>
                            <?php endif; ?>
                            <input class="input mt-2" id="f-apk" type="file" name="apk" accept=".apk,application/vnd.android.package-archive">
                            <span class="hint">
                                Checked on upload for an Android manifest, so an archive renamed <code>.apk</code> is refused.
                                It is served as a download, never run on the server.
                            </span>
                        </div>

                        <div class="field--row">
                            <div class="field">
                                <label class="label" for="pr-apk-url">Or link to an APK hosted elsewhere</label>
                                <input class="input" id="pr-apk-url" type="text" name="apk_external_url"
                                       value="<?= e($val('apk_external_url')) ?>" maxlength="500" placeholder="builds.example.com/app.apk">
                                <span class="hint">Used only when no file is uploaded above.</span>
                            </div>
                            <div class="field">
                                <label class="label" for="pr-apk-ver">Version</label>
                                <input class="input" id="pr-apk-ver" type="text" name="apk_version"
                                       value="<?= e($val('apk_version')) ?>" maxlength="40" placeholder="e.g. 1.4.0">
                            </div>
                        </div>

                        <div class="field">
                            <label class="label" for="pr-apk-note">Note shown with the download</label>
                            <input class="input" id="pr-apk-note" type="text" name="apk_note"
                                   value="<?= e($val('apk_note')) ?>" maxlength="255"
                                   placeholder="e.g. Android 8 or newer. Allow install from unknown sources.">
                        </div>

                        <div class="field--row">
                            <div class="field">
                                <label class="label" for="pr-android">Google Play URL</label>
                                <input class="input" id="pr-android" type="text" name="android_url"
                                       value="<?= e($val('android_url')) ?>" maxlength="500">
                            </div>
                            <div class="field">
                                <label class="label" for="pr-ios">App Store URL</label>
                                <input class="input" id="pr-ios" type="text" name="ios_url"
                                       value="<?= e($val('ios_url')) ?>" maxlength="500">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- What you get -->
            <div class="panel" data-repeater>
                <div class="panel__head">
                    <div>
                        <span class="panel__title">Included</span>
                        <div class="panel__sub">Short lines. Unticking “Included” shows the line struck through.</div>
                    </div>
                    <button class="btn btn--ghost btn--sm" type="button" data-repeater-add><?= icon('plus') ?>Add line</button>
                </div>
                <div class="panel__body">
                    <div class="repeater" data-repeater-list data-sortable data-sortable-item="[data-repeater-row]">
                        <?php
                        $featureRows = $formFeatures !== null ? $formFeatures : $features;
                        foreach ($featureRows as $i => $f): ?>
                        <div class="repeater__row" data-repeater-row data-id="<?= $i ?>">
                            <span class="repeater__handle" data-drag-handle><?= icon('drag') ?></span>
                            <div class="repeater__fields">
                                <input class="input" type="text" data-index-token name="features[<?= $i ?>][title]"
                                       value="<?= e($f['title'] ?? '') ?>" placeholder="e.g. Contact form with email alerts" maxlength="190">
                                <input class="input" type="text" data-index-token name="features[<?= $i ?>][description]"
                                       value="<?= e($f['description'] ?? '') ?>" placeholder="Short description (optional)" maxlength="500">
                                <label class="check">
                                    <input type="checkbox" data-index-token name="features[<?= $i ?>][is_included]" value="1"
                                           <?= (int) ($f['is_included'] ?? 1) === 1 ? 'checked' : '' ?>>
                                    <span class="check__box" aria-hidden="true"></span><span>Included</span>
                                </label>
                            </div>
                            <button class="icon-btn icon-btn--danger" type="button" data-repeater-remove aria-label="Remove"><?= icon('trash') ?></button>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <template data-repeater-template>
                        <div class="repeater__row" data-repeater-row>
                            <span class="repeater__handle" data-drag-handle><?= icon('drag') ?></span>
                            <div class="repeater__fields">
                                <input class="input" type="text" data-index-token name="features[__INDEX__][title]" placeholder="e.g. Contact form with email alerts" maxlength="190">
                                <input class="input" type="text" data-index-token name="features[__INDEX__][description]" placeholder="Short description (optional)" maxlength="500">
                                <label class="check">
                                    <input type="checkbox" data-index-token name="features[__INDEX__][is_included]" value="1" checked>
                                    <span class="check__box" aria-hidden="true"></span><span>Included</span>
                                </label>
                            </div>
                            <button class="icon-btn icon-btn--danger" type="button" data-repeater-remove aria-label="Remove"><?= icon('trash') ?></button>
                        </div>
                    </template>
                </div>
            </div>

            <!-- Gallery -->
            <div class="panel" data-gallery>
                <div class="panel__head">
                    <div>
                        <span class="panel__title">Screenshots</span>
                        <div class="panel__sub"><?= count($images) ?> image<?= count($images) === 1 ? '' : 's' ?> · shown in the gallery on the project page</div>
                    </div>
                </div>
                <div class="panel__body">
                    <div class="gallery-manager" data-gallery-list>
                        <?php foreach ($images as $img): ?>
                        <div class="gallery-manager__item">
                            <img src="<?= e(media_url($img['path'])) ?>" alt="<?= e($img['alt_text']) ?>" loading="lazy">
                            <button type="button" class="gallery-manager__remove" aria-label="Remove image"
                                    data-delete-url="<?= e(url('/admin/projects/' . $id . '/images/' . (int) $img['id'] . '/delete')) ?>">
                                <?= icon('x') ?>
                            </button>
                        </div>
                        <?php endforeach; ?>
                        <button type="button" class="gallery-manager__add" data-gallery-add>
                            <?= icon('plus') ?><span>Add images</span>
                        </button>
                    </div>
                    <?php if ($isNew): ?>
                    <p class="hint mt-4">Images can be chosen now — they are attached once the project is saved.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- SEO -->
            <div class="panel">
                <div class="panel__head">
                    <div>
                        <span class="panel__title">Search &amp; social</span>
                        <div class="panel__sub">Leave blank to use the name and short description.</div>
                    </div>
                </div>
                <div class="panel__body">
                    <div class="form-section">
                        <div class="field">
                            <label class="label" for="pr-seo-title">SEO title</label>
                            <input class="input" id="pr-seo-title" type="text" name="seo_title" value="<?= e($val('seo_title')) ?>" maxlength="190" data-counter>
                        </div>
                        <div class="field">
                            <label class="label" for="pr-seo-desc">Meta description</label>
                            <textarea class="textarea" id="pr-seo-desc" name="seo_description" maxlength="320" data-counter><?= e($val('seo_description')) ?></textarea>
                        </div>
                        <div class="field">
                            <label class="label">Social share image</label>
                            <div class="media-field" data-media-field>
                                <div class="media-field__preview">
                                    <?php $og = media_url($val('og_image')); ?>
                                    <?php if ($og !== ''): ?><img src="<?= e($og) ?>" alt=""><?php else: ?><?= icon('image') ?><?php endif; ?>
                                </div>
                                <div class="media-field__body">
                                    <span class="media-field__path"><?= $val('og_image') !== '' ? e($val('og_image')) : 'No image selected' ?></span>
                                    <input type="hidden" name="og_image" value="<?= e($val('og_image')) ?>">
                                    <div class="row row--tight">
                                        <button class="btn btn--ghost btn--sm" type="button" data-media-choose>Choose</button>
                                        <button class="btn btn--quiet btn--sm" type="button" data-media-clear>Clear</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <aside class="form-aside">
            <div class="panel">
                <div class="panel__head"><span class="panel__title">Publishing</span></div>
                <div class="panel__body">
                    <div class="form-section">
                        <label class="switch">
                            <input type="checkbox" name="is_published" value="1" <?= (int) $val('is_published', 1) === 1 ? 'checked' : '' ?>>
                            <span class="switch__track" aria-hidden="true"></span><span>Published</span>
                        </label>
                        <label class="switch">
                            <input type="checkbox" name="is_featured" value="1" <?= (int) $val('is_featured', 0) === 1 ? 'checked' : '' ?>>
                            <span class="switch__track" aria-hidden="true"></span><span>Feature at the top</span>
                        </label>
                        <div class="field">
                            <label class="label" for="pr-badge">Badge</label>
                            <input class="input" id="pr-badge" type="text" name="badge" value="<?= e($val('badge')) ?>"
                                   maxlength="40" placeholder="e.g. Popular">
                        </div>
                        <div class="field">
                            <label class="label" for="pr-cta">Button label</label>
                            <input class="input" id="pr-cta" type="text" name="cta_label" value="<?= e($val('cta_label', 'Enquire about this')) ?>" maxlength="60">
                        </div>
                    </div>
                </div>
            </div>

            <div class="panel">
                <div class="panel__head">
                    <div>
                        <span class="panel__title">Terms</span>
                        <div class="panel__sub">Anything left at zero or blank is hidden.</div>
                    </div>
                </div>
                <div class="panel__body">
                    <div class="form-section">
                        <div class="field--row">
                            <div class="field">
                                <label class="label" for="pr-days">Setup time (days)</label>
                                <input class="input" id="pr-days" type="number" min="0" max="365" name="delivery_days" value="<?= e((string) $val('delivery_days', 0)) ?>">
                            </div>
                            <div class="field">
                                <label class="label" for="pr-pages">Pages</label>
                                <input class="input" id="pr-pages" type="number" min="0" max="999" name="page_count" value="<?= e((string) $val('page_count', 0)) ?>">
                            </div>
                        </div>
                        <div class="field--row">
                            <div class="field">
                                <label class="label" for="pr-support">Support (months)</label>
                                <input class="input" id="pr-support" type="number" min="0" max="120" name="support_months" value="<?= e((string) $val('support_months', 0)) ?>">
                            </div>
                            <div class="field">
                                <label class="label" for="pr-revisions">Revisions</label>
                                <input class="input" id="pr-revisions" type="text" name="revisions" value="<?= e($val('revisions')) ?>"
                                       maxlength="60" placeholder="e.g. 2 rounds">
                            </div>
                        </div>
                        <div class="field">
                            <label class="label" for="pr-licence">Licence</label>
                            <input class="input" id="pr-licence" type="text" name="licence" value="<?= e($val('licence')) ?>"
                                   maxlength="80" placeholder="e.g. One business, one domain">
                        </div>
                    </div>
                </div>
            </div>

            <div class="panel">
                <div class="panel__head"><span class="panel__title">Images</span></div>
                <div class="panel__body">
                    <div class="form-section">
                        <?php foreach ([['thumbnail', 'Card thumbnail'], ['hero_image', 'Hero image']] as [$k, $lbl]): ?>
                        <div class="field">
                            <span class="label"><?= e($lbl) ?></span>
                            <div class="media-field" data-media-field>
                                <div class="media-field__preview">
                                    <?php $img = media_url($val($k)); ?>
                                    <?php if ($img !== ''): ?><img src="<?= e($img) ?>" alt=""><?php else: ?><?= icon('image') ?><?php endif; ?>
                                </div>
                                <div class="media-field__body">
                                    <span class="media-field__path"><?= $val($k) !== '' ? e($val($k)) : 'No image selected' ?></span>
                                    <input type="hidden" name="<?= e($k) ?>" value="<?= e($val($k)) ?>">
                                    <div class="row row--tight">
                                        <button class="btn btn--ghost btn--sm" type="button" data-media-choose>Choose</button>
                                        <button class="btn btn--quiet btn--sm" type="button" data-media-clear>Clear</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="panel">
                <div class="panel__head"><span class="panel__title">Classification</span></div>
                <div class="panel__body">
                    <div class="form-section">
                        <div class="field">
                            <label class="label" for="pr-cat">Category</label>
                            <select class="select" id="pr-cat" name="category_id">
                                <option value="">None</option>
                                <?php foreach ($categories as $cat): ?>
                                <option value="<?= (int) $cat['id'] ?>" <?= (int) $val('category_id') === (int) $cat['id'] ? 'selected' : '' ?>><?= e($cat['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="field">
                            <label class="label" for="pr-ind">Industry</label>
                            <select class="select" id="pr-ind" name="industry_id">
                                <option value="">None</option>
                                <?php foreach ($industries as $ind): ?>
                                <option value="<?= (int) $ind['id'] ?>" <?= (int) $val('industry_id') === (int) $ind['id'] ? 'selected' : '' ?>><?= e($ind['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="field">
                            <span class="label">Accent colour</span>
                            <input type="hidden" name="accent" value="<?= e($val('accent', 'cyan')) ?>">
                            <div class="accent-picker" data-accent-picker="accent">
                                <?php foreach ($accents as $name => $hex): ?>
                                <button type="button" class="accent-swatch<?= $val('accent', 'cyan') === $name ? ' is-selected' : '' ?>"
                                        data-accent="<?= e($name) ?>" style="background:<?= e($hex) ?>" aria-label="<?= e($name) ?>"></button>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($technologies): ?>
            <div class="panel">
                <div class="panel__head">
                    <span class="panel__title">Built with</span>
                    <a class="btn btn--quiet btn--sm" href="<?= e(url('/admin/portfolio_technologies')) ?>">Manage</a>
                </div>
                <div class="panel__body" style="max-height:280px;overflow-y:auto">
                    <div class="stack stack-2">
                        <?php foreach ($technologies as $tech): ?>
                        <label class="check">
                            <input type="checkbox" name="technologies[]" value="<?= (int) $tech['id'] ?>"
                                   <?= in_array((int) $tech['id'], $selTech, true) ? 'checked' : '' ?>>
                            <span class="check__box" aria-hidden="true"></span>
                            <span><?= e($tech['name']) ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </aside>
    </div>

    <div class="sticky-actions">
        <button class="btn btn--primary" type="submit"><?= icon('check') ?><?= $isNew ? 'Create project' : 'Save changes' ?></button>
        <a class="btn btn--quiet" href="<?= e(url('/admin/projects')) ?>">Cancel</a>
    </div>
</form>
