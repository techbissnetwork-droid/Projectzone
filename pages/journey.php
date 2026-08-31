<?php
/** Start Your Digital Journey — six-step form.
 *  @var array $services @var array $industries @var array $countries
 *  @var array $budgets @var array $timelines @var array $stages */
$oldServices = (array) old('services_needed', []);
$stepLabels  = ['Business', 'Needs', 'Budget', 'Timeline', 'Details', 'Contact'];
?>
<?= $view->partial('partials/page-head', [
    'eyebrow' => 'Start your digital journey',
    'heading' => "Let's build your digital business.",
    'lead'    => 'Six short steps. Nothing is submitted until the final one, and there is no payment at any point.',
    'center'  => true,
]) ?>

<section class="section section--flush-top">
    <div class="container container--narrow">
        <form method="post" action="<?= e(url('/start')) ?>" data-form novalidate>
            <?= csrf_field() ?>
            <div class="hp-field" aria-hidden="true">
                <label for="j-hp">Leave this empty</label>
                <input id="j-hp" type="text" name="website_url" tabindex="-1" autocomplete="off">
            </div>

            <div class="wizard" data-wizard>
                <div class="wizard__progress">
                    <div class="wizard__bar"><div class="wizard__bar-fill" style="--progress:16.6%"></div></div>
                    <div class="wizard__steps">
                        <?php foreach ($stepLabels as $i => $label): ?>
                        <button class="wizard__pip" type="button" data-state="<?= $i === 0 ? 'current' : 'todo' ?>">
                            <span class="wizard__pip-num"><?= $i + 1 ?></span><?= e($label) ?>
                        </button>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="card card--pad-lg">

                    <!-- Step 1 — Business -->
                    <section class="wizard__panel is-active" aria-label="Step 1 of 6: your business">
                        <div class="wizard__panel-head">
                            <h2>Tell us about your business</h2>
                            <p>Start with the basics. Everything else follows from this.</p>
                        </div>
                        <div class="stack stack-4">
                            <div class="field">
                                <label class="label" for="j-business">Business name <span class="req" aria-hidden="true">*</span></label>
                                <input class="input<?= error_for('business_name') ? ' is-invalid' : '' ?>" id="j-business" type="text"
                                       name="business_name" value="<?= e(old('business_name')) ?>" required maxlength="190" autocomplete="organization">
                                <?php if (error_for('business_name')): ?><span class="field-error"><?= icon('alert') ?><?= e(error_for('business_name')) ?></span><?php endif; ?>
                            </div>

                            <?php if ($industries): ?>
                            <div class="field">
                                <label class="label" for="j-industry">Industry</label>
                                <select class="select" id="j-industry" name="industry_id">
                                    <option value="">Select the closest match</option>
                                    <?php foreach ($industries as $ind): ?>
                                    <option value="<?= (int) $ind['id'] ?>" <?= (int) old('industry_id') === (int) $ind['id'] ? 'selected' : '' ?>><?= e($ind['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <?php endif; ?>

                            <div class="field">
                                <span class="label">Where is the business today?</span>
                                <div class="stack stack-2 mt-2">
                                    <?php foreach ($stages as $stage): ?>
                                    <label class="option-card">
                                        <input type="radio" name="business_stage" value="<?= e($stage) ?>" <?= old('business_stage') === $stage ? 'checked' : '' ?>>
                                        <span class="check__box check__box--radio" aria-hidden="true" style="border-radius:50%"></span>
                                        <span class="option-card__title"><?= e($stage) ?></span>
                                    </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <div class="field">
                                <label class="label" for="j-website">Existing website</label>
                                <input class="input" id="j-website" type="text" name="website" value="<?= e(old('website')) ?>"
                                       maxlength="255" placeholder="yourbusiness.com">
                                <span class="hint">Leave blank if there is not one yet — that is the most common answer.</span>
                            </div>
                        </div>
                        <div class="wizard__nav">
                            <button class="btn btn--primary ml-auto btn--arrow" type="button" data-wizard-next>Continue<?= icon('arrow-right') ?></button>
                        </div>
                    </section>

                    <!-- Step 2 — Needs -->
                    <section class="wizard__panel" aria-label="Step 2 of 6: what you need">
                        <div class="wizard__panel-head">
                            <h2>What do you need?</h2>
                            <p>Choose everything that applies. You can change your mind later — this is not a commitment.</p>
                        </div>
                        <div class="grid grid-2" data-require-one>
                            <label class="option-card">
                                <input type="checkbox" name="services_needed[]" value="complete-digital-setup"
                                       <?= in_array('complete-digital-setup', $oldServices, true) ? 'checked' : '' ?>>
                                <span class="check__box" aria-hidden="true"></span>
                                <span>
                                    <span class="option-card__title">Complete Digital Setup</span>
                                    <span class="option-card__text">Everything, handled end to end.</span>
                                </span>
                            </label>
                            <?php foreach ($services as $service): ?>
                            <label class="option-card">
                                <input type="checkbox" name="services_needed[]" value="<?= e($service['slug']) ?>"
                                       <?= in_array($service['slug'], $oldServices, true) ? 'checked' : '' ?>>
                                <span class="check__box" aria-hidden="true"></span>
                                <span>
                                    <span class="option-card__title"><?= e($service['name']) ?></span>
                                    <span class="option-card__text"><?= e(str_limit($service['tagline'] ?: $service['short_description'], 58)) ?></span>
                                </span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                        <div class="wizard__nav">
                            <button class="btn btn--ghost" type="button" data-wizard-prev><?= icon('arrow-left') ?>Back</button>
                            <button class="btn btn--primary ml-auto btn--arrow" type="button" data-wizard-next>Continue<?= icon('arrow-right') ?></button>
                        </div>
                    </section>

                    <!-- Step 3 — Budget -->
                    <section class="wizard__panel" aria-label="Step 3 of 6: budget">
                        <div class="wizard__panel-head">
                            <h2>What budget are you working with?</h2>
                            <p>An honest range means we propose something realistic instead of wasting your time.</p>
                        </div>
                        <div class="stack stack-2">
                            <?php foreach ($budgets as $budget): ?>
                            <label class="option-card">
                                <input type="radio" name="budget_range" value="<?= e($budget) ?>" <?= old('budget_range') === $budget ? 'checked' : '' ?>>
                                <span class="check__box" aria-hidden="true" style="border-radius:50%"></span>
                                <span class="option-card__title"><?= e($budget) ?></span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                        <div class="notice mt-5"><?= icon('info') ?><span>Budget is never used to inflate a quote. It tells us which approach is worth proposing.</span></div>
                        <div class="wizard__nav">
                            <button class="btn btn--ghost" type="button" data-wizard-prev><?= icon('arrow-left') ?>Back</button>
                            <button class="btn btn--primary ml-auto btn--arrow" type="button" data-wizard-next>Continue<?= icon('arrow-right') ?></button>
                        </div>
                    </section>

                    <!-- Step 4 — Timeline -->
                    <section class="wizard__panel" aria-label="Step 4 of 6: timeline">
                        <div class="wizard__panel-head">
                            <h2>When do you want this live?</h2>
                            <p>We will tell you honestly whether that is achievable.</p>
                        </div>
                        <div class="stack stack-2">
                            <?php foreach ($timelines as $timeline): ?>
                            <label class="option-card">
                                <input type="radio" name="timeline" value="<?= e($timeline) ?>" <?= old('timeline') === $timeline ? 'checked' : '' ?>>
                                <span class="check__box" aria-hidden="true" style="border-radius:50%"></span>
                                <span class="option-card__title"><?= e($timeline) ?></span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                        <div class="wizard__nav">
                            <button class="btn btn--ghost" type="button" data-wizard-prev><?= icon('arrow-left') ?>Back</button>
                            <button class="btn btn--primary ml-auto btn--arrow" type="button" data-wizard-next>Continue<?= icon('arrow-right') ?></button>
                        </div>
                    </section>

                    <!-- Step 5 — Details -->
                    <section class="wizard__panel" aria-label="Step 5 of 6: project details">
                        <div class="wizard__panel-head">
                            <h2>Anything else we should know?</h2>
                            <p>The more context, the more useful our first response will be.</p>
                        </div>
                        <div class="field">
                            <label class="label" for="j-details">Project details</label>
                            <textarea class="textarea textarea--tall" id="j-details" name="project_details" maxlength="5000"
                                      placeholder="What does the business do? Who are your customers? What is not working right now? Is there anything you have already tried?"><?= e(old('project_details')) ?></textarea>
                            <span class="hint">Optional, but it genuinely changes the quality of our reply.</span>
                        </div>
                        <div class="wizard__nav">
                            <button class="btn btn--ghost" type="button" data-wizard-prev><?= icon('arrow-left') ?>Back</button>
                            <button class="btn btn--primary ml-auto btn--arrow" type="button" data-wizard-next>Continue<?= icon('arrow-right') ?></button>
                        </div>
                    </section>

                    <!-- Step 6 — Contact -->
                    <section class="wizard__panel" aria-label="Step 6 of 6: contact details">
                        <div class="wizard__panel-head">
                            <h2>How do we reach you?</h2>
                            <p>Last step. We reply personally — no automated drip sequence.</p>
                        </div>
                        <div class="stack stack-4">
                            <div class="field--row">
                                <div class="field">
                                    <label class="label" for="j-name">Your name <span class="req" aria-hidden="true">*</span></label>
                                    <input class="input<?= error_for('name') ? ' is-invalid' : '' ?>" id="j-name" type="text" name="name"
                                           value="<?= e(old('name')) ?>" required maxlength="120" autocomplete="name">
                                    <?php if (error_for('name')): ?><span class="field-error"><?= icon('alert') ?><?= e(error_for('name')) ?></span><?php endif; ?>
                                </div>
                                <div class="field">
                                    <label class="label" for="j-email">Email <span class="req" aria-hidden="true">*</span></label>
                                    <input class="input<?= error_for('email') ? ' is-invalid' : '' ?>" id="j-email" type="email" name="email"
                                           value="<?= e(old('email')) ?>" required maxlength="190" autocomplete="email">
                                    <?php if (error_for('email')): ?><span class="field-error"><?= icon('alert') ?><?= e(error_for('email')) ?></span><?php endif; ?>
                                </div>
                            </div>
                            <div class="field--row">
                                <div class="field">
                                    <label class="label" for="j-phone">Phone</label>
                                    <input class="input" id="j-phone" type="tel" name="phone" value="<?= e(old('phone')) ?>" maxlength="32" autocomplete="tel">
                                </div>
                                <div class="field">
                                    <label class="label" for="j-country">Country</label>
                                    <select class="select" id="j-country" name="country">
                                        <option value="">Select a country</option>
                                        <?php foreach ($countries as $country): ?>
                                        <option value="<?= e($country) ?>" <?= old('country') === $country ? 'selected' : '' ?>><?= e($country) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div>
                                <p class="eyebrow mb-3">Your answers</p>
                                <div class="wizard__summary" data-wizard-summary></div>
                            </div>
                        </div>
                        <div class="wizard__nav">
                            <button class="btn btn--ghost" type="button" data-wizard-prev><?= icon('arrow-left') ?>Back</button>
                            <button class="btn btn--primary btn--lg ml-auto btn--arrow" type="submit">
                                Let's build your digital business<?= icon('arrow-right') ?>
                            </button>
                        </div>
                    </section>
                </div>

                <p class="hint text-center">
                    <?= icon('lock') ?> Your details are stored securely and used only to respond to this enquiry.
                    No payment is taken and nothing is shared with third parties.
                </p>
            </div>
        </form>
    </div>
</section>
