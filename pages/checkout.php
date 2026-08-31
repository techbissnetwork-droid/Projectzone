<?php
/** @var array $package @var array $addons @var array $countries @var array $paymentMethods */
$p        = $package['pricing'];
$showSave = setting_bool('show_prepaid_savings', true);
$currency = setting('currency_symbol', '$');
$oldAddons = array_map('intval', (array) old('addons', []));
?>
<?= $view->partial('partials/page-head', [
    'eyebrow' => 'Package request',
    'heading' => 'Request the ' . $package['name'] . ' package',
    'lead'    => 'Confirm your details and requirements. No payment is taken on this page.',
]) ?>

<section class="section section--flush-top">
    <div class="container">
        <div class="checkout" data-checkout data-currency="<?= e($currency) ?>"
             data-base="<?= e(number_format($p['payable'], 2, '.', '')) ?>"
             data-regular="<?= e(number_format($p['regular'], 2, '.', '')) ?>">

            <div class="card card--pad-lg" data-reveal>
                <form method="post" action="<?= e(url('/checkout/' . $package['slug'])) ?>" data-form novalidate>
                    <?= csrf_field() ?>
                    <div class="hp-field" aria-hidden="true">
                        <label for="k-hp">Leave this empty</label>
                        <input id="k-hp" type="text" name="website_url" tabindex="-1" autocomplete="off">
                    </div>

                    <div class="stack stack-6">
                        <fieldset style="border:0;padding:0;margin:0">
                            <legend class="eyebrow mb-4">Your details</legend>
                            <div class="stack stack-4">
                                <div class="field--row">
                                    <div class="field">
                                        <label class="label" for="k-name">Full name <span class="req" aria-hidden="true">*</span></label>
                                        <input class="input<?= error_for('name') ? ' is-invalid' : '' ?>" id="k-name" type="text" name="name"
                                               value="<?= e(old('name')) ?>" required maxlength="120" autocomplete="name">
                                        <?php if (error_for('name')): ?><span class="field-error"><?= icon('alert') ?><?= e(error_for('name')) ?></span><?php endif; ?>
                                    </div>
                                    <div class="field">
                                        <label class="label" for="k-business">Business name <span class="req" aria-hidden="true">*</span></label>
                                        <input class="input<?= error_for('business_name') ? ' is-invalid' : '' ?>" id="k-business" type="text"
                                               name="business_name" value="<?= e(old('business_name')) ?>" required maxlength="190" autocomplete="organization">
                                        <?php if (error_for('business_name')): ?><span class="field-error"><?= icon('alert') ?><?= e(error_for('business_name')) ?></span><?php endif; ?>
                                    </div>
                                </div>
                                <div class="field--row">
                                    <div class="field">
                                        <label class="label" for="k-email">Email <span class="req" aria-hidden="true">*</span></label>
                                        <input class="input<?= error_for('email') ? ' is-invalid' : '' ?>" id="k-email" type="email" name="email"
                                               value="<?= e(old('email')) ?>" required maxlength="190" autocomplete="email">
                                        <?php if (error_for('email')): ?><span class="field-error"><?= icon('alert') ?><?= e(error_for('email')) ?></span><?php endif; ?>
                                    </div>
                                    <div class="field">
                                        <label class="label" for="k-phone">Phone <span class="req" aria-hidden="true">*</span></label>
                                        <input class="input<?= error_for('phone') ? ' is-invalid' : '' ?>" id="k-phone" type="tel" name="phone"
                                               value="<?= e(old('phone')) ?>" required maxlength="32" autocomplete="tel">
                                        <?php if (error_for('phone')): ?><span class="field-error"><?= icon('alert') ?><?= e(error_for('phone')) ?></span><?php endif; ?>
                                    </div>
                                </div>
                                <div class="field">
                                    <label class="label" for="k-country">Country</label>
                                    <select class="select" id="k-country" name="country">
                                        <option value="">Select a country</option>
                                        <?php foreach ($countries as $country): ?>
                                        <option value="<?= e($country) ?>" <?= old('country') === $country ? 'selected' : '' ?>><?= e($country) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </fieldset>

                        <?php if ($addons): ?>
                        <fieldset style="border:0;padding:0;margin:0">
                            <legend class="eyebrow mb-2">Optional add-ons</legend>
                            <p class="hint mb-4">Prices update live. Nothing is charged until you approve an invoice.</p>
                            <div class="stack stack-2">
                                <?php foreach ($addons as $addon): ?>
                                <label class="option-card">
                                    <input type="checkbox" name="addons[]" value="<?= (int) $addon['id'] ?>"
                                           data-price="<?= e(number_format((float) $addon['price'], 2, '.', '')) ?>"
                                           <?= in_array((int) $addon['id'], $oldAddons, true) ? 'checked' : '' ?>>
                                    <span class="check__box" aria-hidden="true"></span>
                                    <span class="flex-1">
                                        <span class="option-card__title"><?= e($addon['name']) ?></span>
                                        <span class="option-card__text"><?= e($addon['description']) ?></span>
                                    </span>
                                    <span class="badge nowrap"><?= e(money($addon['price'])) ?></span>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </fieldset>
                        <?php endif; ?>

                        <fieldset style="border:0;padding:0;margin:0">
                            <legend class="eyebrow mb-4">About the project</legend>
                            <div class="stack stack-4">
                                <div class="field">
                                    <label class="label" for="k-details">Tell us about the business</label>
                                    <textarea class="textarea" id="k-details" name="business_details" maxlength="3000"
                                              placeholder="What does the business do, who are your customers, and where are you based?"><?= e(old('business_details')) ?></textarea>
                                </div>
                                <div class="field">
                                    <label class="label" for="k-req">Specific requirements</label>
                                    <textarea class="textarea" id="k-req" name="requirements" maxlength="3000"
                                              placeholder="Anything the package must cover: pages you need, systems to integrate, deadlines to hit."><?= e(old('requirements')) ?></textarea>
                                </div>
                            </div>
                        </fieldset>

                        <fieldset style="border:0;padding:0;margin:0">
                            <legend class="eyebrow mb-4">How would you like to pay?</legend>
                            <?php if (!$paymentMethods): ?>
                                <div class="notice notice--warning">
                                    <?= icon('alert') ?>
                                    <span>No payment methods are configured yet. Send your request and we will contact you to arrange payment directly.</span>
                                </div>
                            <?php else: ?>
                                <div class="stack stack-2">
                                    <?php foreach ($paymentMethods as $i => $method): ?>
                                    <label class="option-card">
                                        <input type="radio" name="payment_method" value="<?= e($method['key']) ?>"
                                               <?= (old('payment_method') === $method['key'] || (old('payment_method') === '' && $i === 0)) ? 'checked' : '' ?> required>
                                        <span class="check__box" aria-hidden="true" style="border-radius:50%"></span>
                                        <span class="icon-plate icon-plate--sm"><?= icon($method['icon']) ?></span>
                                        <span class="flex-1">
                                            <span class="option-card__title"><?= e($method['label']) ?></span>
                                            <span class="option-card__text"><?= e($method['description']) ?></span>
                                        </span>
                                    </label>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <?php if (setting('payment_note') !== ''): ?>
                            <div class="notice notice--accent mt-4"><?= icon('info') ?><span><?= e(setting('payment_note')) ?></span></div>
                            <?php endif; ?>
                        </fieldset>

                        <div class="form-actions">
                            <button class="btn btn--primary btn--lg btn--arrow" type="submit">Submit request<?= icon('arrow-right') ?></button>
                            <span class="hint">This sends a request. Nothing is charged.</span>
                        </div>
                    </div>
                </form>
            </div>

            <aside class="checkout__aside">
                <div class="card card--pad-lg" data-accent="<?= e($package['accent'] ?: 'cyan') ?>" data-reveal="right">
                    <div class="row row--tight mb-4">
                        <span class="icon-plate icon-plate--sm"><?= icon((string) $package['icon']) ?></span>
                        <div>
                            <div style="font-size:var(--fs-sm);font-weight:600;color:var(--text)"><?= e($package['name']) ?> package</div>
                            <div class="hint"><?= (int) $package['duration_months'] ?> months included</div>
                        </div>
                    </div>

                    <div class="summary-line">
                        <span>Regular price</span>
                        <span class="num<?= ($p['has_discount'] && $showSave) ? ' strike text-muted' : '' ?>"><?= e(money($p['regular'])) ?></span>
                    </div>

                    <?php if ($p['has_discount'] && $showSave): ?>
                    <div class="summary-line">
                        <span>Prepaid price</span>
                        <span class="num"><?= e(money($p['payable'])) ?></span>
                    </div>
                    <div class="summary-line summary-line--save">
                        <span><?= icon('check-circle') ?> Prepaid saving (<?= (int) $p['percent'] ?>%)</span>
                        <span class="num" data-total-saving>−<?= e(money($p['saving'])) ?></span>
                    </div>
                    <?php endif; ?>

                    <div class="summary-line">
                        <span>Add-ons</span>
                        <span class="num" data-total-addons><?= e(money(0)) ?></span>
                    </div>

                    <div class="summary-line summary-line--total">
                        <span>Total</span>
                        <span class="num" data-total-final><?= e(money($p['payable'])) ?></span>
                    </div>

                    <p class="hint mt-4">
                        <?= icon('shield') ?> Indicative total. We confirm the final figure in writing before invoicing,
                        and it will not exceed this without your written approval.
                    </p>
                </div>

                <div class="card mt-4" data-reveal="right">
                    <h3 class="card__title" style="font-size:var(--fs-sm)">Included in <?= e($package['name']) ?></h3>
                    <div class="mt-2">
                        <?php foreach (array_slice($package['features'] ?? [], 0, 8) as $f): ?>
                        <?php if ((int) $f['is_included'] !== 1) { continue; } ?>
                        <div class="feature-row" style="padding:.5rem 0">
                            <span class="feature-row__check"><?= icon('check') ?></span>
                            <div class="feature-row__title"><?= e($f['title']) ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="card__footer">
                        <a class="link" href="<?= e(url('/packages/' . $package['slug'])) ?>">Full package details<?= icon('arrow-right') ?></a>
                    </div>
                </div>
            </aside>
        </div>
    </div>
</section>
