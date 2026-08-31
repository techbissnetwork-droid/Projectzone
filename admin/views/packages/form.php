<?php
/** @var array $row @var bool $isNew @var array $features @var array $allAddons @var array $selectedAddons */
use Techbiss\Core\Icons;

$id     = (int) ($row['id'] ?? 0);
$action = $isNew ? url('/admin/packages') : url('/admin/packages/' . $id);
$val    = static fn (string $k, $d = '') => old($k, $row[$k] ?? $d);
$accents = ['cyan' => '#34d3e0', 'violet' => '#a78bfa', 'emerald' => '#34d399', 'amber' => '#fbbf24', 'rose' => '#fb7185', 'blue' => '#4f8cff'];
$selAddons = array_map('intval', (array) old('addons', $selectedAddons));
$formFeatures = old('features', null);
?>
<div class="page-header">
    <div>
        <h1><?= e($title) ?></h1>
        <p>Prices entered here are exactly what visitors see. Nothing is marked up, rounded or discounted anywhere else.</p>
    </div>
    <div class="page-header__actions">
        <a class="btn btn--ghost btn--sm" href="<?= e(url('/admin/packages')) ?>"><?= icon('arrow-left') ?>Back</a>
        <?php if (!$isNew && !empty($row['slug'])): ?>
        <a class="btn btn--quiet btn--sm" target="_blank" rel="noopener" href="<?= e(url('/packages/' . $row['slug'])) ?>"><?= icon('external') ?>View</a>
        <?php endif; ?>
    </div>
</div>

<form method="post" action="<?= e($action) ?>" data-dirty-guard>
    <?= csrf_field() ?>
    <div class="form-cols">
        <div>
            <div class="panel">
                <div class="panel__head"><span class="panel__title">Package details</span></div>
                <div class="panel__body">
                    <div class="form-section">
                        <div class="field--row">
                            <div class="field">
                                <label class="label" for="k-name">Name <span class="req">*</span></label>
                                <input class="input<?= error_for('name') ? ' is-invalid' : '' ?>" id="k-name" type="text"
                                       name="name" value="<?= e($val('name')) ?>" required maxlength="120"
                                       <?= $view->partial('partials/field-invalid', ['key' => 'name']) ?>>
                                <?= $view->partial('partials/field-error', ['key' => 'name', 'withIcon' => false]) ?>
                            </div>
                            <div class="field">
                                <label class="label" for="k-slug">URL slug</label>
                                <div class="input-group">
                                    <input class="input" id="k-slug" type="text" name="slug" value="<?= e($val('slug')) ?>"
                                           maxlength="190" data-slug-from="name" spellcheck="false">
                                    <button class="btn btn--quiet btn--sm" type="button" data-slug-regenerate
                                            aria-label="Regenerate slug from the title"><?= icon('refresh') ?></button>
                                </div>
                            </div>
                        </div>

                        <div class="field">
                            <label class="label" for="k-tagline">Tagline</label>
                            <input class="input" id="k-tagline" type="text" name="tagline" value="<?= e($val('tagline')) ?>" maxlength="255" data-counter>
                        </div>

                        <div class="field">
                            <label class="label" for="k-short">Short description</label>
                            <textarea class="textarea" id="k-short" name="short_description" maxlength="500" data-counter><?= e($val('short_description')) ?></textarea>
                        </div>

                        <div class="field">
                            <label class="label" for="k-desc">Full description</label>
                            <textarea class="textarea textarea--tall" id="k-desc" name="description" maxlength="30000"><?= e($val('description')) ?></textarea>
                            <span class="hint">Accepts HTML. Shown on the package detail page.</span>
                        </div>

                        <div class="field">
                            <label class="label" for="k-best">Best for</label>
                            <input class="input" id="k-best" type="text" name="best_for" value="<?= e($val('best_for')) ?>"
                                   maxlength="255" placeholder="Small and offline businesses beginning their digital journey">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pricing -->
            <div class="panel" data-pricing-preview data-currency="<?= e(setting('currency_symbol', '$')) ?>">
                <div class="panel__head">
                    <div>
                        <span class="panel__title">Pricing</span>
                        <div class="panel__sub">The prepaid price must be below the regular price, or left empty.</div>
                    </div>
                </div>
                <div class="panel__body">
                    <div class="form-section">
                        <label class="switch">
                            <input type="checkbox" name="is_custom_quote" value="1" <?= (int) $val('is_custom_quote', 0) === 1 ? 'checked' : '' ?>>
                            <span class="switch__track" aria-hidden="true"></span>
                            <span>Custom quote — do not publish a price</span>
                        </label>

                        <div class="field--row">
                            <div class="field">
                                <label class="label" for="k-regular">Regular price</label>
                                <input class="input<?= error_for('regular_price') ? ' is-invalid' : '' ?>" id="k-regular" type="number"
                                       name="regular_price" value="<?= e((string) $val('regular_price', '')) ?>" step="0.01" min="0" inputmode="decimal"
                                       <?= $view->partial('partials/field-invalid', ['key' => 'regular_price']) ?>>
                                <?= $view->partial('partials/field-error', ['key' => 'regular_price', 'withIcon' => false]) ?>
                            </div>
                            <div class="field">
                                <label class="label" for="k-prepaid">Prepaid price</label>
                                <input class="input<?= error_for('prepaid_price') ? ' is-invalid' : '' ?>" id="k-prepaid" type="number"
                                       name="prepaid_price" value="<?= e((string) $val('prepaid_price', '')) ?>" step="0.01" min="0" inputmode="decimal"
                                       <?= $view->partial('partials/field-invalid', ['key' => 'prepaid_price', 'describedBy' => 'hint-prepaid_price']) ?>>
                                <span class="hint" id="hint-prepaid_price">Leave empty for no prepaid discount.</span>
                                <?= $view->partial('partials/field-error', ['key' => 'prepaid_price', 'withIcon' => false]) ?>
                            </div>
                        </div>

                        <div class="notice" data-pricing-output></div>

                        <div class="field--row">
                            <div class="field">
                                <label class="label" for="k-currency">Currency code</label>
                                <input class="input" id="k-currency" type="text" name="currency" value="<?= e($val('currency', 'USD')) ?>" maxlength="6">
                            </div>
                            <div class="field">
                                <label class="label" for="k-billing">Billing period</label>
                                <select class="select" id="k-billing" name="billing_period">
                                    <?php foreach (['one-time' => 'One-time', 'monthly' => 'Monthly', 'yearly' => 'Yearly', 'project' => 'Per project'] as $v => $l): ?>
                                    <option value="<?= e($v) ?>" <?= $val('billing_period', 'one-time') === $v ? 'selected' : '' ?>><?= e($l) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="field">
                                <label class="label" for="k-duration">Duration (months)</label>
                                <input class="input" id="k-duration" type="number" name="duration_months"
                                       value="<?= e((string) $val('duration_months', 12)) ?>" min="1" max="240">
                                <span class="hint">Sets the expiry date when a purchase is marked paid.</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Features -->
            <div class="panel" data-repeater>
                <div class="panel__head">
                    <div>
                        <span class="panel__title">What's included</span>
                        <div class="panel__sub">Unticking “Included” shows the line struck through, which is how the comparison table marks exclusions.</div>
                    </div>
                    <button class="btn btn--ghost btn--sm" type="button" data-repeater-add><?= icon('plus') ?>Add feature</button>
                </div>
                <div class="panel__body">
                    <div class="repeater" data-repeater-list data-sortable data-sortable-item="[data-repeater-row]">
                        <?php
                        $rows = $formFeatures !== null ? $formFeatures : $features;
                        foreach ($rows as $i => $f): ?>
                        <div class="repeater__row" data-repeater-row data-id="<?= $i ?>">
                            <span class="repeater__handle" data-drag-handle><?= icon('drag') ?></span>
                            <div class="repeater__fields">
                                <input class="input" type="text" data-index-token name="features[<?= $i ?>][title]"
                                       value="<?= e($f['title'] ?? '') ?>" placeholder="Feature title" maxlength="190">
                                <input class="input" type="text" data-index-token name="features[<?= $i ?>][description]"
                                       value="<?= e($f['description'] ?? '') ?>" placeholder="Short description (optional)" maxlength="500">
                                <div class="row row--tight">
                                    <label class="check">
                                        <input type="checkbox" data-index-token name="features[<?= $i ?>][is_included]" value="1"
                                               <?= (int) ($f['is_included'] ?? 1) === 1 ? 'checked' : '' ?>>
                                        <span class="check__box" aria-hidden="true"></span><span>Included</span>
                                    </label>
                                    <label class="check">
                                        <input type="checkbox" data-index-token name="features[<?= $i ?>][is_highlight]" value="1"
                                               <?= (int) ($f['is_highlight'] ?? 0) === 1 ? 'checked' : '' ?>>
                                        <span class="check__box" aria-hidden="true"></span><span>Highlight</span>
                                    </label>
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
                                <input class="input" type="text" data-index-token name="features[__INDEX__][title]" placeholder="Feature title" maxlength="190">
                                <input class="input" type="text" data-index-token name="features[__INDEX__][description]" placeholder="Short description (optional)" maxlength="500">
                                <div class="row row--tight">
                                    <label class="check">
                                        <input type="checkbox" data-index-token name="features[__INDEX__][is_included]" value="1" checked>
                                        <span class="check__box" aria-hidden="true"></span><span>Included</span>
                                    </label>
                                    <label class="check">
                                        <input type="checkbox" data-index-token name="features[__INDEX__][is_highlight]" value="1">
                                        <span class="check__box" aria-hidden="true"></span><span>Highlight</span>
                                    </label>
                                </div>
                            </div>
                            <button class="icon-btn icon-btn--danger" type="button" data-repeater-remove aria-label="Remove"><?= icon('trash') ?></button>
                        </div>
                    </template>
                </div>
            </div>

            <!-- SEO -->
            <div class="panel">
                <div class="panel__head"><span class="panel__title">Search &amp; social</span></div>
                <div class="panel__body">
                    <div class="form-section">
                        <div class="field">
                            <label class="label" for="k-seo-title">SEO title</label>
                            <input class="input" id="k-seo-title" type="text" name="seo_title" value="<?= e($val('seo_title')) ?>" maxlength="190" data-counter>
                        </div>
                        <div class="field">
                            <label class="label" for="k-seo-desc">Meta description</label>
                            <textarea class="textarea" id="k-seo-desc" name="seo_description" maxlength="320" data-counter><?= e($val('seo_description')) ?></textarea>
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
                            <span class="switch__track" aria-hidden="true"></span><span>Highlight as recommended</span>
                        </label>
                        <div class="field">
                            <label class="label" for="k-badge">Badge</label>
                            <input class="input" id="k-badge" type="text" name="badge" value="<?= e($val('badge')) ?>"
                                   maxlength="40" placeholder="Most Popular">
                        </div>
                        <div class="field">
                            <label class="label" for="k-cta">Button label</label>
                            <input class="input" id="k-cta" type="text" name="cta_label" value="<?= e($val('cta_label', 'Get Started')) ?>" maxlength="60">
                        </div>
                    </div>
                </div>
            </div>

            <div class="panel">
                <div class="panel__head"><span class="panel__title">Appearance</span></div>
                <div class="panel__body">
                    <div class="form-section">
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
                        <div class="field">
                            <span class="label">Icon</span>
                            <input type="hidden" name="icon" value="<?= e($val('icon', 'layers')) ?>">
                            <div class="icon-picker" data-icon-picker="icon">
                                <?php foreach (Icons::names() as $iconName): ?>
                                <button type="button" class="icon-picker__item<?= $val('icon', 'layers') === $iconName ? ' is-selected' : '' ?>"
                                        data-icon="<?= e($iconName) ?>" aria-label="<?= e($iconName) ?>"><?= icon($iconName) ?></button>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($allAddons): ?>
            <div class="panel">
                <div class="panel__head">
                    <span class="panel__title">Available add-ons</span>
                    <a class="btn btn--quiet btn--sm" href="<?= e(url('/admin/package_addons')) ?>">Manage</a>
                </div>
                <div class="panel__body" style="max-height:300px;overflow-y:auto">
                    <div class="stack stack-2">
                        <?php foreach ($allAddons as $addon): ?>
                        <label class="check">
                            <input type="checkbox" name="addons[]" value="<?= (int) $addon['id'] ?>"
                                   <?= in_array((int) $addon['id'], $selAddons, true) ? 'checked' : '' ?>>
                            <span class="check__box" aria-hidden="true"></span>
                            <span><?= e($addon['name']) ?> <span class="hint">· <?= e(money($addon['price'])) ?></span></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </aside>
    </div>

    <div class="sticky-actions">
        <button class="btn btn--primary" type="submit"><?= icon('check') ?><?= $isNew ? 'Create package' : 'Save changes' ?></button>
        <a class="btn btn--quiet" href="<?= e(url('/admin/packages')) ?>">Cancel</a>
    </div>
</form>
