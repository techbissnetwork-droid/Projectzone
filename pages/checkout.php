<?php
/** @var array $package @var array $addons @var array $countries @var array $paymentMethods */
$p        = $package['pricing'];
$showSave = setting_bool('show_prepaid_savings', true);
$currency = setting('currency_symbol', '$');
$oldAddons  = array_map('intval', (array) old('addons', []));
$showPrice  = setting_bool('public_pricing', false);
$features   = $package['features'] ?? [];
$oldFeats   = old('features', null);
// Everything the package includes starts ticked; unticking is how someone says
// "I do not need that", which is exactly what makes the request worth pricing.
$featChosen = static function (string $title) use ($oldFeats): bool {
    return $oldFeats === null ? true : in_array($title, (array) $oldFeats, true);
};
?>
<?= $view->partial('partials/page-head', [
    'eyebrow' => 'Package request',
    'heading' => 'Build your ' . $package['name'] . ' request',
    'lead'    => 'Tick what you want, drop what you do not, add anything missing. We reply with a price.',
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

                        <?php if ($features): ?>
                        <fieldset style="border:0;padding:0;margin:0">
                            <legend class="eyebrow mb-2">What do you want included?</legend>
                            <p class="hint mb-4">
                                Everything in the package is ticked. Untick anything you do not need — it changes what we quote.
                            </p>
                            <div class="option-grid">
                                <?php foreach ($features as $f): ?>
                                <?php if ((int) $f['is_included'] === 0) { continue; } ?>
                                <label class="option-card">
                                    <input type="checkbox" name="features[]" value="<?= e($f['title']) ?>"
                                           <?= $featChosen((string) $f['title']) ? 'checked' : '' ?>>
                                    <span class="check__box" aria-hidden="true"></span>
                                    <span class="flex-1">
                                        <span class="option-card__title"><?= e($f['title']) ?></span>
                                        <?php if (!empty($f['description'])): ?>
                                        <span class="option-card__text"><?= e($f['description']) ?></span>
                                        <?php endif; ?>
                                    </span>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </fieldset>
                        <?php endif; ?>

                        <?php if ($addons): ?>
                        <fieldset style="border:0;padding:0;margin:0">
                            <legend class="eyebrow mb-2">Anything else?</legend>
                            <p class="hint mb-4">Tick any of these to have them included in the quote.</p>
                            <div class="option-grid">
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
                                    <?php if ($showPrice): ?><span class="badge nowrap"><?= e(money($addon['price'])) ?></span><?php endif; ?>
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
                                    <label class="label" for="k-req">Anything not on the list?</label>
                                    <textarea class="textarea" id="k-req" name="requirements" rows="5" maxlength="3000"
                                              placeholder="Features you want that are not ticked above, how the app or site should work, pages you need, systems to connect to, deadlines to hit."><?= e(old('requirements')) ?></textarea>
                                    <span class="hint">Write it in your own words. This is what we price against.</span>
                                </div>
                            </div>
                        </fieldset>

                        <fieldset style="border:0;padding:0;margin:0">
                            <legend class="eyebrow mb-4">How should we reply?</legend>
                            <div class="stack stack-2">
                                <?php foreach (\Techbiss\Repo\ProjectOrderRepo::contactLabels() as $key => $label): ?>
                                <label class="option-card">
                                    <input type="radio" name="preferred_contact" value="<?= e($key) ?>"
                                           <?= old('preferred_contact', 'whatsapp') === $key ? 'checked' : '' ?>>
                                    <span class="check__box" aria-hidden="true" style="border-radius:50%"></span>
                                    <span class="flex-1"><span class="option-card__title"><?= e($label) ?></span></span>
                                </label>
                                <?php endforeach; ?>
                            </div>
                            <p class="hint mt-4">
                                We send back what it costs and how long it takes. Nothing is charged here, and no payment
                                details are asked for.
                            </p>
                        </fieldset>

                        <div class="form-actions">
                            <button class="btn btn--primary btn--lg btn--arrow" type="submit">Send my request<?= icon('arrow-right') ?></button>
                            <span class="hint">Nothing is charged, and no payment details are taken.</span>
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

                    <?php if ($showPrice): ?>
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
                    <?php else: ?>
                    <p class="mt-2" style="font-size:var(--fs-sm)">
                        There is no price on this page on purpose. What you tick below changes the work, and the work is
                        what sets the figure — so we quote against your actual list rather than a number you would have
                        to negotiate down.
                    </p>
                    <p class="hint mt-4">
                        <?= icon('shield') ?> Nothing is charged here and no payment details are asked for. You get a
                        written price and a schedule, and nothing starts until you say so.
                    </p>
                    <?php endif; ?>
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
