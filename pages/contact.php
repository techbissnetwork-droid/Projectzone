<?php
/** @var array $countries @var array $faqs @var \Techbiss\Repo\SettingsRepo $settings */
$whatsapp = preg_replace('/[^0-9]/', '', $settings->get('whatsapp'));
?>
<?= $view->partial('partials/page-head', [
    'eyebrow' => 'Contact',
    'heading' => 'Tell us where your business is today.',
    'lead'    => 'No sales script. Tell us what you run and what is missing.',
]) ?>

<section class="section section--flush-top">
    <div class="container">
        <div class="contact-grid">
            <aside class="stack stack-5" data-reveal="left">
                <div class="card card--pad-lg">
                    <h2 class="card__title">Direct contact</h2>
                    <div class="mt-3">
                        <?php if ($settings->get('contact_email') !== ''): ?>
                        <div class="contact-item">
                            <span class="icon-plate icon-plate--sm"><?= icon('mail') ?></span>
                            <div>
                                <div class="contact-item__label">General enquiries</div>
                                <div class="contact-item__value"><a href="mailto:<?= e($settings->get('contact_email')) ?>"><?= e($settings->get('contact_email')) ?></a></div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if ($settings->get('sales_email') !== '' && $settings->get('sales_email') !== $settings->get('contact_email')): ?>
                        <div class="contact-item">
                            <span class="icon-plate icon-plate--sm"><?= icon('send') ?></span>
                            <div>
                                <div class="contact-item__label">New projects</div>
                                <div class="contact-item__value"><a href="mailto:<?= e($settings->get('sales_email')) ?>"><?= e($settings->get('sales_email')) ?></a></div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if ($settings->get('support_email') !== '' && $settings->get('support_email') !== $settings->get('contact_email')): ?>
                        <div class="contact-item">
                            <span class="icon-plate icon-plate--sm"><?= icon('shield') ?></span>
                            <div>
                                <div class="contact-item__label">Existing clients</div>
                                <div class="contact-item__value"><a href="mailto:<?= e($settings->get('support_email')) ?>"><?= e($settings->get('support_email')) ?></a></div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if ($settings->get('contact_phone') !== ''): ?>
                        <div class="contact-item">
                            <span class="icon-plate icon-plate--sm"><?= icon('phone') ?></span>
                            <div>
                                <div class="contact-item__label">Phone</div>
                                <div class="contact-item__value"><a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $settings->get('contact_phone'))) ?>"><?= e($settings->get('contact_phone')) ?></a></div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if ($whatsapp !== ''): ?>
                        <div class="contact-item">
                            <span class="icon-plate icon-plate--sm"><?= icon('whatsapp') ?></span>
                            <div>
                                <div class="contact-item__label">WhatsApp</div>
                                <div class="contact-item__value"><a href="https://wa.me/<?= e($whatsapp) ?>" target="_blank" rel="noopener noreferrer">Message us</a></div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if ($settings->get('address') !== ''): ?>
                        <div class="contact-item">
                            <span class="icon-plate icon-plate--sm"><?= icon('pin') ?></span>
                            <div>
                                <div class="contact-item__label">Address</div>
                                <div class="contact-item__value"><?= nl2br(e($settings->get('address'))) ?></div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if ($settings->get('business_hours') !== ''): ?>
                        <div class="contact-item">
                            <span class="icon-plate icon-plate--sm"><?= icon('clock') ?></span>
                            <div>
                                <div class="contact-item__label">Business hours</div>
                                <div class="contact-item__value"><?= e($settings->get('business_hours')) ?></div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card">
                    <h3 class="card__title" style="font-size:var(--fs-sm)">Would a quote be more useful?</h3>
                    <p class="card__text">If you already know roughly what you need, the quote form captures the detail in one go.</p>
                    <div class="card__footer stack stack-2">
                        <a class="btn btn--ghost btn--block btn--sm" href="<?= e(url('/request')) ?>">Tell us what you need</a>
                    </div>
                </div>
            </aside>

            <div class="card card--pad-lg" data-reveal="right">
                <h2 class="card__title">Send us a message</h2>
                <p class="card__text mb-5">We reply to every message, usually within one business day.</p>

                <form method="post" action="<?= e(url('/contact')) ?>" data-form novalidate>
                    <?= csrf_field() ?>
                    <div class="hp-field" aria-hidden="true">
                        <label for="c-hp">Leave this empty</label>
                        <input id="c-hp" type="text" name="website_url" tabindex="-1" autocomplete="off">
                    </div>

                    <div class="stack stack-4">
                        <div class="field--row">
                            <div class="field">
                                <label class="label" for="c-name">Your name <span class="req" aria-hidden="true">*</span></label>
                                <input class="input<?= error_for('name') ? ' is-invalid' : '' ?>" id="c-name" type="text" name="name"
                                       value="<?= e(old('name')) ?>" required maxlength="120" autocomplete="name"
                                       <?= $view->partial('partials/field-invalid', ['key' => 'name']) ?>>
                                <?= $view->partial('partials/field-error', ['key' => 'name']) ?>
                            </div>
                            <div class="field">
                                <label class="label" for="c-company">Company</label>
                                <input class="input" id="c-company" type="text" name="company"
                                       value="<?= e(old('company')) ?>" maxlength="190" autocomplete="organization">
                            </div>
                        </div>

                        <div class="field--row">
                            <div class="field">
                                <label class="label" for="c-email">Email <span class="req" aria-hidden="true">*</span></label>
                                <input class="input<?= error_for('email') ? ' is-invalid' : '' ?>" id="c-email" type="email" name="email"
                                       value="<?= e(old('email')) ?>" required maxlength="190" autocomplete="email"
                                       <?= $view->partial('partials/field-invalid', ['key' => 'email']) ?>>
                                <?= $view->partial('partials/field-error', ['key' => 'email']) ?>
                            </div>
                            <div class="field">
                                <label class="label" for="c-phone">Phone</label>
                                <input class="input" id="c-phone" type="tel" name="phone"
                                       value="<?= e(old('phone')) ?>" maxlength="32" autocomplete="tel">
                            </div>
                        </div>

                        <div class="field--row">
                            <div class="field">
                                <label class="label" for="c-country">Country</label>
                                <select class="select" id="c-country" name="country">
                                    <option value="">Select a country</option>
                                    <?php foreach ($countries as $country): ?>
                                    <option value="<?= e($country) ?>" <?= old('country') === $country ? 'selected' : '' ?>><?= e($country) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="field">
                                <label class="label" for="c-subject">Subject</label>
                                <input class="input" id="c-subject" type="text" name="subject"
                                       value="<?= e(old('subject')) ?>" maxlength="190" placeholder="What is this about?">
                            </div>
                        </div>

                        <div class="field">
                            <label class="label" for="c-message">Message <span class="req" aria-hidden="true">*</span></label>
                            <textarea class="textarea<?= error_for('message') ? ' is-invalid' : '' ?>" id="c-message" name="message"
                                      required maxlength="5000" placeholder="Tell us about your business and what you are trying to solve."
                                      <?= $view->partial('partials/field-invalid', ['key' => 'message']) ?>><?= e(old('message')) ?></textarea>
                            <?= $view->partial('partials/field-error', ['key' => 'message']) ?>
                        </div>

                        <div class="form-actions">
                            <button class="btn btn--primary btn--lg btn--arrow" type="submit">Send message<?= icon('arrow-right') ?></button>
                            <span class="hint">We never share your details with anyone.</span>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</section>

<?php if ($settings->get('map_embed') !== ''): ?>
<section class="section section--tight section--flush-top">
    <div class="container">
        <div class="split__media" style="aspect-ratio:21/9" data-reveal>
            <iframe src="<?= e($settings->get('map_embed')) ?>" title="Our location on a map"
                    style="width:100%;height:100%;border:0" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if ($faqs): ?>
<section class="section">
    <div class="container container--narrow">
        <div class="section-head section-head--center" data-reveal>
            <p class="eyebrow eyebrow--plain">Before you write</p>
            <h2 class="mt-4">These might already answer it</h2>
        </div>
        <?= $view->partial('partials/faq-accordion', ['faqs' => $faqs, 'groupId' => 'contact']) ?>
    </div>
</section>
<?php endif; ?>
