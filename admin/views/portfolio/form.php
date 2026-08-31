<?php
/** @var array $row @var bool $isNew @var array $categories @var array $industries
 *  @var array $services @var array $technologies @var array $selectedTech @var array $selectedSvc @var array $images */
$id      = (int) ($row['id'] ?? 0);
$action  = $isNew ? url('/admin/portfolio') : url('/admin/portfolio/' . $id);
$val     = static fn (string $k, $d = '') => old($k, $row[$k] ?? $d);
$accents = ['cyan' => '#34d3e0', 'violet' => '#a78bfa', 'emerald' => '#34d399', 'amber' => '#fbbf24', 'rose' => '#fb7185', 'blue' => '#4f8cff'];
$selTech = array_map('intval', (array) old('technologies', $selectedTech));
$selSvc  = array_map('intval', (array) old('services', $selectedSvc));
?>
<div class="page-header">
    <div>
        <h1><?= e($title) ?></h1>
        <p>Empty fields are hidden automatically on the public case study — fill in only what is genuinely true of this project.</p>
    </div>
    <div class="page-header__actions">
        <a class="btn btn--ghost btn--sm" href="<?= e(url('/admin/portfolio')) ?>"><?= icon('arrow-left') ?>Back</a>
        <?php if (!$isNew && !empty($row['slug'])): ?>
        <a class="btn btn--quiet btn--sm" target="_blank" rel="noopener" href="<?= e(url('/portfolio/' . $row['slug'])) ?>"><?= icon('external') ?>View</a>
        <?php endif; ?>
    </div>
</div>

<form method="post" action="<?= e($action) ?>" data-dirty-guard>
    <?= csrf_field() ?>
    <div class="form-cols">
        <div>
            <div class="panel">
                <div class="panel__head"><span class="panel__title">Project</span></div>
                <div class="panel__body">
                    <div class="form-section">
                        <div class="field">
                            <label class="label" for="p-title">Title <span class="req">*</span></label>
                            <input class="input<?= error_for('title') ? ' is-invalid' : '' ?>" id="p-title" type="text"
                                   name="title" value="<?= e($val('title')) ?>" required maxlength="190" data-counter>
                            <?php if (error_for('title')): ?><span class="field-error"><?= icon('alert') ?><?= e(error_for('title')) ?></span><?php endif; ?>
                        </div>

                        <div class="field">
                            <label class="label" for="p-slug">URL slug</label>
                            <div class="input-group">
                                <input class="input" id="p-slug" type="text" name="slug" value="<?= e($val('slug')) ?>"
                                       maxlength="190" data-slug-from="title" spellcheck="false">
                                <button class="btn btn--quiet btn--sm" type="button" data-slug-regenerate><?= icon('refresh') ?></button>
                            </div>
                            <span class="hint">/portfolio/<?= e($val('slug') ?: 'project-name') ?></span>
                        </div>

                        <div class="field--row">
                            <div class="field">
                                <label class="label" for="p-client">Client</label>
                                <input class="input" id="p-client" type="text" name="client_name" value="<?= e($val('client_name')) ?>" maxlength="190">
                            </div>
                            <div class="field">
                                <label class="label" for="p-duration">Duration</label>
                                <input class="input" id="p-duration" type="text" name="duration" value="<?= e($val('duration')) ?>"
                                       maxlength="60" placeholder="e.g. 6 weeks">
                            </div>
                        </div>

                        <div class="field">
                            <label class="label" for="p-short">Short description</label>
                            <textarea class="textarea" id="p-short" name="short_description" maxlength="500" data-counter><?= e($val('short_description')) ?></textarea>
                            <span class="hint">Shown on portfolio cards and used as the fallback meta description.</span>
                        </div>
                    </div>

                    <div class="form-section">
                        <div class="form-section__title">Case study</div>
                        <?php foreach ([
                            ['overview',  'Overview',  'What the project was and what it set out to do.'],
                            ['challenge', 'Challenge', 'What was not working before. Be specific.'],
                            ['solution',  'Solution',  'What you built and why you built it that way.'],
                            ['results',   'Results',   'What actually changed. Only real, verifiable outcomes.'],
                        ] as [$key, $label, $hint]): ?>
                        <div class="field">
                            <label class="label" for="p-<?= e($key) ?>"><?= e($label) ?></label>
                            <textarea class="textarea" id="p-<?= e($key) ?>" name="<?= e($key) ?>" maxlength="30000"><?= e($val($key)) ?></textarea>
                            <span class="hint"><?= e($hint) ?> Accepts HTML; blank sections are hidden on the site.</span>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="form-section">
                        <div class="form-section__title">Links</div>
                        <div class="field--row">
                            <div class="field">
                                <label class="label" for="p-url">Live website</label>
                                <input class="input" id="p-url" type="text" name="project_url" value="<?= e($val('project_url')) ?>"
                                       maxlength="500" placeholder="example.com">
                            </div>
                            <div class="field">
                                <label class="label" for="p-date">Project date</label>
                                <input class="input" id="p-date" type="date" name="project_date"
                                       value="<?= e(substr((string) $val('project_date'), 0, 10)) ?>">
                            </div>
                        </div>
                        <div class="field--row">
                            <div class="field">
                                <label class="label" for="p-android">Google Play URL</label>
                                <input class="input" id="p-android" type="text" name="android_url" value="<?= e($val('android_url')) ?>" maxlength="500">
                            </div>
                            <div class="field">
                                <label class="label" for="p-ios">App Store URL</label>
                                <input class="input" id="p-ios" type="text" name="ios_url" value="<?= e($val('ios_url')) ?>" maxlength="500">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Gallery -->
            <div class="panel" data-gallery>
                <div class="panel__head">
                    <div>
                        <span class="panel__title">Gallery</span>
                        <div class="panel__sub"><?= count($images) ?> image<?= count($images) === 1 ? '' : 's' ?> · shown in the case study gallery</div>
                    </div>
                </div>
                <div class="panel__body">
                    <div class="gallery-manager" data-gallery-list>
                        <?php foreach ($images as $img): ?>
                        <div class="gallery-manager__item">
                            <img src="<?= e(media_url($img['path'])) ?>" alt="<?= e($img['alt_text']) ?>" loading="lazy">
                            <button type="button" class="gallery-manager__remove" aria-label="Remove image"
                                    data-delete-url="<?= e(url('/admin/portfolio/' . $id . '/images/' . (int) $img['id'] . '/delete')) ?>">
                                <?= icon('x') ?>
                            </button>
                        </div>
                        <?php endforeach; ?>
                        <button type="button" class="gallery-manager__add" data-gallery-add>
                            <?= icon('plus') ?><span>Add images</span>
                        </button>
                    </div>
                    <?php if ($isNew): ?>
                    <p class="hint mt-4">Images can be added now — they are attached once the project is saved.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- SEO -->
            <div class="panel">
                <div class="panel__head">
                    <div>
                        <span class="panel__title">Search &amp; social</span>
                        <div class="panel__sub">Leave blank to use the title and short description.</div>
                    </div>
                </div>
                <div class="panel__body">
                    <div class="form-section">
                        <div class="field">
                            <label class="label" for="p-seo-title">SEO title</label>
                            <input class="input" id="p-seo-title" type="text" name="seo_title" value="<?= e($val('seo_title')) ?>" maxlength="190" data-counter>
                        </div>
                        <div class="field">
                            <label class="label" for="p-seo-desc">Meta description</label>
                            <textarea class="textarea" id="p-seo-desc" name="seo_description" maxlength="320" data-counter><?= e($val('seo_description')) ?></textarea>
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
                            <span class="switch__track" aria-hidden="true"></span><span>Feature on the homepage</span>
                        </label>
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
                            <label class="label" for="p-cat">Category</label>
                            <select class="select" id="p-cat" name="category_id">
                                <option value="">None</option>
                                <?php foreach ($categories as $cat): ?>
                                <option value="<?= (int) $cat['id'] ?>" <?= (int) $val('category_id') === (int) $cat['id'] ? 'selected' : '' ?>><?= e($cat['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="field">
                            <label class="label" for="p-ind">Industry</label>
                            <select class="select" id="p-ind" name="industry_id">
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

            <?php if ($services): ?>
            <div class="panel">
                <div class="panel__head"><span class="panel__title">Services delivered</span></div>
                <div class="panel__body">
                    <div class="stack stack-2">
                        <?php foreach ($services as $svc): ?>
                        <label class="check">
                            <input type="checkbox" name="services[]" value="<?= (int) $svc['id'] ?>"
                                   <?= in_array((int) $svc['id'], $selSvc, true) ? 'checked' : '' ?>>
                            <span class="check__box" aria-hidden="true"></span>
                            <span><?= e($svc['name']) ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($technologies): ?>
            <div class="panel">
                <div class="panel__head">
                    <span class="panel__title">Technologies</span>
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
        <a class="btn btn--quiet" href="<?= e(url('/admin/portfolio')) ?>">Cancel</a>
    </div>
</form>
