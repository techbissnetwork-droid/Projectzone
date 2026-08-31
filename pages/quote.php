<?php
/** @var array $services @var array $packages @var array $industries @var array $countries
 *  @var array $budgets @var array $timelines @var string $preselect */
$oldServices = (array) old('services_needed', []);
$preselectId = 0;
foreach ($packages as $p) { if ($p['slug'] === $preselect) { $preselectId = (int) $p['id']; } }
?>
<?= $view->partial('partials/page-head', [
    'eyebrow' => 'Request a quote',
    'heading' => 'Tell us what you need. We will price it properly.',
    'lead'    => 'One form, no obligation. You get a written scope, a schedule and a price — not a range that changes later.',
]) ?>

<section class="section section--flush-top">
    <div class="container">
        <div class="checkout">
            <div class="card card--pad-lg" data-reveal>
                <form method="post" action="<?= e(url('/quote')) ?>" data-form novalidate>
                    <?= csrf_field() ?>
                    <div class="hp-field" aria-hidden="true">
                        <label for="q-hp">Leave this empty</label>
                        <input id="q-hp" type="text" name="website_url" tabindex="-1" autocomplete="off">
                    </div>

                    <div class="stack stack-6">
                        <fieldset style="border:0;padding:0;margin:0">
                            <legend class="eyebrow mb-4">About you</legend>
                            <div class="stack stack-4">
                                <div class="field--row">
                                    <div class="field">
                                        <label class="label" for="q-name">Your name <span class="req" aria-hidden="true">*</span></label>
                                        <input class="input<?= error_for('name') ? ' is-invalid' : '' ?>" id="q-name" type="text" name="name"
                                               value="<?= e(old('name')) ?>" required maxlength="120" autocomplete="name">
                                        <?php if (error_for('name')): ?><span class="field-error"><?= icon('alert') ?><?= e(error_for('name')) ?></span><?php endif; ?>
                                    </div>
                                    <div class="field">
                                        <label class="label" for="q-business">Business name</label>
                                        <input class="input" id="q-business" type="text" name="business_name"
                                               value="<?= e(old('business_name')) ?>" maxlength="190" autocomplete="organization">
                                    </div>
                                </div>
                                <div class="field--row">
                                    <div class="field">
                                        <label class="label" for="q-email">Email <span class="req" aria-hidden="true">*</span></label>
                                        <input class="input<?= error_for('email') ? ' is-invalid' : '' ?>" id="q-email" type="email" name="email"
                                               value="<?= e(old('email')) ?>" required maxlength="190" autocomplete="email">
                                        <?php if (error_for('email')): ?><span class="field-error"><?= icon('alert') ?><?= e(error_for('email')) ?></span><?php endif; ?>
                                    </div>
                                    <div class="field">
                                        <label class="label" for="q-phone">Phone</label>
                                        <input class="input" id="q-phone" type="tel" name="phone" value="<?= e(old('phone')) ?>" maxlength="32" autocomplete="tel">
                                    </div>
                                </div>
                                <div class="field--row">
                                    <div class="field">
                                        <label class="label" for="q-country">Country</label>
                                        <select class="select" id="q-country" name="country">
                                            <option value="">Select a country</option>
                                            <?php foreach ($countries as $country): ?>
                                            <option value="<?= e($country) ?>" <?= old('country') === $country ? 'selected' : '' ?>><?= e($country) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="field">
                                        <label class="label" for="q-website">Existing website</label>
                                        <input class="input" id="q-website" type="text" name="website"
                                               value="<?= e(old('website')) ?>" maxlength="255" placeholder="yourbusiness.com">
                                        <span class="hint">Leave blank if you do not have one yet.</span>
                                    </div>
                                </div>
                                <?php if ($industries): ?>
                                <div class="field">
                                    <label class="label" for="q-industry">Industry</label>
                                    <select class="select" id="q-industry" name="industry_id">
                                        <option value="">Select an industry</option>
                                        <?php foreach ($industries as $ind): ?>
                                        <option value="<?= (int) $ind['id'] ?>" <?= (int) old('industry_id') === (int) $ind['id'] ? 'selected' : '' ?>><?= e($ind['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <?php endif; ?>
                            </div>
                        </fieldset>

                        <?php if ($services): ?>
                        <fieldset style="border:0;padding:0;margin:0">
                            <legend class="eyebrow mb-4">What do you need?</legend>
                            <div class="grid grid-2">
                                <label class="option-card">
                                    <input type="checkbox" name="services_needed[]" value="complete-digital-setup"
                                           <?= in_array('complete-digital-setup', $oldServices, true) ? 'checked' : '' ?>>
                                    <span class="check__box" aria-hidden="true"></span>
                                    <span>
                                        <span class="option-card__title">Complete digital setup</span>
                                        <span class="option-card__text">Everything — domain through to launch and support.</span>
                                    </span>
                                </label>
                                <?php foreach ($services as $service): ?>
                                <label class="option-card">
                                    <input type="checkbox" name="services_needed[]" value="<?= e($service['slug']) ?>"
                                           <?= in_array($service['slug'], $oldServices, true) ? 'checked' : '' ?>>
                                    <span class="check__box" aria-hidden="true"></span>
                                    <span>
                                        <span class="option-card__title"><?= e($service['name']) ?></span>
                                        <span class="option-card__text"><?= e(str_limit($service['tagline'] ?: $service['short_description'], 62)) ?></span>
                                    </span>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </fieldset>
                        <?php endif; ?>

                        <fieldset style="border:0;padding:0;margin:0">
                            <legend class="eyebrow mb-4">Scope</legend>
                            <div class="stack stack-4">
                                <?php if ($packages): ?>
                                <div class="field">
                                    <label class="label" for="q-package">Interested in a package?</label>
                                    <select class="select" id="q-package" name="package_id">
                                        <option value="">No specific package</option>
                                        <?php foreach ($packages as $package): ?>
                                        <option value="<?= (int) $package['id'] ?>"
                                                <?= ((int) old('package_id') === (int) $package['id'] || $preselectId === (int) $package['id']) ? 'selected' : '' ?>>
                                            <?= e($package['name']) ?><?= $package['pricing']['is_custom'] ? ' — custom quote' : ' — ' . money($package['pricing']['payable']) ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <?php endif; ?>

                                <div class="field--row">
                                    <div class="field">
                                        <label class="label" for="q-budget">Budget</label>
                                        <select class="select" id="q-budget" name="budget_range">
                                            <option value="">Prefer not to say</option>
                                            <?php foreach ($budgets as $budget): ?>
                                            <option value="<?= e($budget) ?>" <?= old('budget_range') === $budget ? 'selected' : '' ?>><?= e($budget) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <span class="hint">A range helps us propose something realistic.</span>
                                    </div>
                                    <div class="field">
                                        <label class="label" for="q-timeline">Timeline</label>
                                        <select class="select" id="q-timeline" name="timeline">
                                            <option value="">Not sure</option>
                                            <?php foreach ($timelines as $timeline): ?>
                                            <option value="<?= e($timeline) ?>" <?= old('timeline') === $timeline ? 'selected' : '' ?>><?= e($timeline) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>

                                <div class="field">
                                    <label class="label" for="q-details">Project details <span class="req" aria-hidden="true">*</span></label>
                                    <textarea class="textarea textarea--tall<?= error_for('project_details') ? ' is-invalid' : '' ?>" id="q-details"
                                              name="project_details" required maxlength="5000"
                                              placeholder="What does the business do? Who are your customers? What is not working today, and what would success look like?"><?= e(old('project_details')) ?></textarea>
                                    <?php if (error_for('project_details')): ?><span class="field-error"><?= icon('alert') ?><?= e(error_for('project_details')) ?></span><?php endif; ?>
                                </div>
                            </div>
                        </fieldset>

                        <div class="form-actions">
                            <button class="btn btn--primary btn--lg btn--arrow" type="submit">Send my request<?= icon('arrow-right') ?></button>
                            <span class="hint">No obligation. No payment. No automated follow-up sequence.</span>
                        </div>
                    </div>
                </form>
            </div>

            <aside class="checkout__aside stack stack-4">
                <div class="card" data-reveal="right">
                    <h2 class="card__title" style="font-size:var(--fs-sm)">What happens next</h2>
                    <div class="stack stack-4 mt-4">
                        <?php foreach ([
                            ['01', 'We read it properly', 'A person reads your request — not a bot, not a scoring system.'],
                            ['02', 'We ask what is missing', 'If something is unclear, we ask before quoting rather than guessing.'],
                            ['03', 'You get a written quote', 'Scope, schedule and price in writing, usually within two business days.'],
                        ] as $step): ?>
                        <div class="row row--nowrap" style="align-items:flex-start;gap:.85rem">
                            <span class="process__num" style="font-size:1rem;min-width:1.6rem"><?= e($step[0]) ?></span>
                            <div>
                                <div style="font-size:var(--fs-sm);font-weight:550;color:var(--text)"><?= e($step[1]) ?></div>
                                <p class="card__text mt-2" style="font-size:var(--fs-xs)"><?= e($step[2]) ?></p>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="notice notice--accent" data-reveal="right">
                    <?= icon('shield') ?>
                    <span>Your details are stored securely and used only to respond to this request.</span>
                </div>
            </aside>
        </div>
    </div>
</section>
