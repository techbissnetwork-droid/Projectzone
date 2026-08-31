<?php
/**
 * A single premade project.
 *
 * Deliberately price-free. Buying is a conversation, so the page ends with the
 * channels the business actually answers on rather than a checkout.
 *
 * @var array $project @var array $related @var array $countries @var bool $sent
 * @var \Techbiss\Repo\SettingsRepo $settings
 */
$hero     = media_url($project['hero_image'] ?: $project['thumbnail']);
$images   = $project['images'] ?? [];
$features = $project['features'] ?? [];
$techs    = $project['technologies'] ?? [];

$demo      = trim((string) $project['demo_url']);
$apk       = trim((string) $project['apk_path']) !== '' || trim((string) $project['apk_external_url']) !== '';
$apkSize   = (int) $project['apk_size_bytes'];
$android   = trim((string) $project['android_url']);
$ios       = trim((string) $project['ios_url']);
$demoAdmin = trim((string) $project['demo_admin_url']);
$demoUser  = trim((string) $project['demo_username']);
$demoPass  = trim((string) $project['demo_password']);

// Each channel appears only when it is configured, so the page never offers a
// way to reach us that does not work.
$whatsapp  = preg_replace('/[^0-9]/', '', $settings->get('whatsapp'));
$waNumber  = ($whatsapp !== '' && strlen($whatsapp) >= 8) ? $whatsapp : '';
$salesMail = $settings->get('sales_email') ?: $settings->get('contact_email');
$askText   = 'Hi, I am interested in the "' . $project['name'] . '" project on your website.';
$waLink    = $waNumber !== '' ? 'https://wa.me/' . $waNumber . '?text=' . rawurlencode($askText) : '';
$mailLink  = $salesMail !== ''
    ? 'mailto:' . $salesMail . '?subject=' . rawurlencode($project['name'] . ' — enquiry') . '&body=' . rawurlencode($askText)
    : '';

$meta = array_filter([
    'Pages'      => (int) $project['page_count'] > 0 ? (string) (int) $project['page_count'] : '',
    'Setup time' => (int) $project['delivery_days'] > 0 ? (int) $project['delivery_days'] . ' days' : '',
    'Revisions'  => (string) $project['revisions'],
    'Support'    => (int) $project['support_months'] > 0 ? (int) $project['support_months'] . ' months' : '',
    'Licence'    => (string) $project['licence'],
], static fn ($v) => $v !== '');
?>
<section class="case-hero" data-accent="<?= e($project['accent'] ?: 'cyan') ?>">
    <div class="page-head__bg" aria-hidden="true"><span class="glow"></span><span class="grid-pattern"></span></div>
    <div class="container">
        <nav class="breadcrumbs" aria-label="Breadcrumb">
            <a href="<?= e(url('/')) ?>">Home</a><?= icon('chevron-right') ?>
            <a href="<?= e(url('/premade-projects')) ?>">Ready Projects</a><?= icon('chevron-right') ?>
            <span aria-current="page"><?= e($project['name']) ?></span>
        </nav>

        <div class="row row--tight mt-5">
            <?php if (!empty($project['badge'])): ?>
            <span class="badge badge--accent"><?= e($project['badge']) ?></span>
            <?php endif; ?>
            <?php if (!empty($project['category_name'])): ?>
            <span class="badge"><?= e($project['category_name']) ?></span>
            <?php endif; ?>
            <?php if (!empty($project['industry_name'])): ?>
            <span class="badge"><?= e($project['industry_name']) ?></span>
            <?php endif; ?>
        </div>

        <h1 class="mt-4"><?= e($project['name']) ?></h1>
        <?php if (!empty($project['tagline'])): ?>
        <p class="lead mt-4" style="max-width:52ch"><?= e($project['tagline']) ?></p>
        <?php endif; ?>

        <div class="row mt-6">
            <?php if ($demo !== ''): ?>
            <a class="btn btn--primary" href="<?= e($demo) ?>" target="_blank" rel="noopener noreferrer nofollow">
                <?= icon('external') ?>See it live
            </a>
            <?php endif; ?>
            <?php if ($apk): ?>
            <a class="btn btn--ghost" href="<?= e(url('/premade-projects/' . $project['slug'] . '/apk')) ?>">
                <?= icon('download') ?>Download the test APK
            </a>
            <?php endif; ?>
        </div>

        <?php if ($hero !== ''): ?>
        <div class="case-hero__image" data-reveal="scale">
            <img src="<?= e($hero) ?>" alt="<?= e($project['name']) ?>" width="1200" height="720" fetchpriority="high">
        </div>
        <?php endif; ?>
    </div>
</section>

<?php if ($meta || $techs): ?>
<section class="section section--tight section--flush-top">
    <div class="container">
        <div class="case-meta" data-reveal>
            <?php foreach ($meta as $label => $value): ?>
            <div class="case-meta__cell">
                <div class="case-meta__label"><?= e($label) ?></div>
                <div class="case-meta__value"><?= e($value) ?></div>
            </div>
            <?php endforeach; ?>
            <?php if ($techs): ?>
            <div class="case-meta__cell">
                <div class="case-meta__label">Built with</div>
                <div class="chip-row mt-2">
                    <?php foreach ($techs as $tech): ?>
                    <span class="tech-chip"><?= e($tech) ?></span>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<section class="section section--flush-top">
    <div class="container">
        <div class="split" style="align-items:start">
            <div>
                <?php if (trim(strip_tags((string) $project['description'])) !== ''): ?>
                <div class="case-block" data-reveal>
                    <h2 class="eyebrow case-block__label" style="line-height:inherit">About it</h2>
                    <div class="prose"><?= $project['description'] ?></div>
                </div>
                <?php endif; ?>

                <?php if ($features): ?>
                <div class="case-block" data-reveal>
                    <h2 class="eyebrow case-block__label" style="line-height:inherit">What you get</h2>
                    <div class="mt-4">
                        <?php foreach ($features as $f): ?>
                        <div class="feature-row<?= (int) $f['is_included'] === 0 ? ' feature-row--excluded' : '' ?>">
                            <span class="feature-row__check"><?= icon((int) $f['is_included'] === 1 ? 'check' : 'x') ?></span>
                            <div>
                                <div class="feature-row__title"><?= e($f['title']) ?></div>
                                <?php if (!empty($f['description'])): ?>
                                <div class="feature-row__text"><?= e($f['description']) ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (trim(strip_tags((string) $project['whats_included'])) !== ''): ?>
                <div class="case-block" data-reveal>
                    <h2 class="eyebrow case-block__label" style="line-height:inherit">Also included</h2>
                    <div class="prose"><?= $project['whats_included'] ?></div>
                </div>
                <?php endif; ?>

                <?php if (!empty($project['customisation_note'])): ?>
                <div class="notice mt-4" data-reveal>
                    <?= icon('info') ?><span><?= e($project['customisation_note']) ?></span>
                </div>
                <?php endif; ?>
            </div>

            <aside class="stack stack-4" style="position:sticky;top:6.5rem">
                <?php if ($demo !== ''): ?>
                <div class="card" data-reveal="right">
                    <h3 class="card__title" style="font-size:var(--fs-sm)">Try it first</h3>
                    <div class="card__footer stack stack-2">
                        <a class="btn btn--primary btn--block" href="<?= e($demo) ?>" target="_blank" rel="noopener noreferrer nofollow">
                            <?= icon('external') ?>Open the demo
                        </a>
                        <?php if ($demoAdmin !== ''): ?>
                        <a class="btn btn--ghost btn--block" href="<?= e($demoAdmin) ?>" target="_blank" rel="noopener noreferrer nofollow">
                            Admin demo
                        </a>
                        <?php endif; ?>
                    </div>

                    <?php if ($demoUser !== '' || $demoPass !== ''): ?>
                    <dl class="demo-creds mt-4">
                        <?php if ($demoUser !== ''): ?>
                        <div><dt>User</dt><dd><?= e($demoUser) ?></dd></div>
                        <?php endif; ?>
                        <?php if ($demoPass !== ''): ?>
                        <div><dt>Pass</dt><dd><?= e($demoPass) ?></dd></div>
                        <?php endif; ?>
                    </dl>
                    <?php endif; ?>

                    <?php if (!empty($project['demo_note'])): ?>
                    <p class="hint mt-3"><?= e($project['demo_note']) ?></p>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <?php if ($apk || $android !== '' || $ios !== ''): ?>
                <div class="card" data-reveal="right">
                    <h3 class="card__title" style="font-size:var(--fs-sm)">Try the app</h3>
                    <div class="card__footer stack stack-2">
                        <?php if ($apk): ?>
                        <a class="btn btn--primary btn--block" href="<?= e(url('/premade-projects/' . $project['slug'] . '/apk')) ?>">
                            <?= icon('download') ?>Download APK<?php if ($apkSize > 0): ?>
                            <span class="hint" style="margin-left:.35rem"><?= e(human_bytes($apkSize)) ?></span>
                            <?php endif; ?>
                        </a>
                        <?php endif; ?>
                        <?php if ($android !== ''): ?>
                        <a class="btn btn--ghost btn--block" href="<?= e($android) ?>" target="_blank" rel="noopener noreferrer nofollow">
                            <?= icon('device') ?>Google Play
                        </a>
                        <?php endif; ?>
                        <?php if ($ios !== ''): ?>
                        <a class="btn btn--ghost btn--block" href="<?= e($ios) ?>" target="_blank" rel="noopener noreferrer nofollow">
                            <?= icon('device') ?>App Store
                        </a>
                        <?php endif; ?>
                    </div>
                    <?php if ((string) $project['apk_version'] !== '' || (string) $project['apk_note'] !== ''): ?>
                    <p class="hint mt-3">
                        <?php if ((string) $project['apk_version'] !== ''): ?>Version <?= e($project['apk_version']) ?>.<?php endif; ?>
                        <?= e($project['apk_note']) ?>
                    </p>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <div class="card" data-reveal="right">
                    <h3 class="card__title" style="font-size:var(--fs-sm)">Price</h3>
                    <p class="card__text mt-2">
                        Quoted per project — your content, your domain, any changes. Message us and we come back with a
                        figure, usually within one business day.
                    </p>
                    <div class="card__footer stack stack-2">
                        <?php if ($waLink !== ''): ?>
                        <a class="btn btn--primary btn--block" href="<?= e($waLink) ?>" target="_blank" rel="noopener noreferrer">
                            <?= icon('whatsapp') ?>Ask on WhatsApp
                        </a>
                        <?php endif; ?>
                        <?php if ($mailLink !== ''): ?>
                        <a class="btn btn--ghost btn--block" href="<?= e($mailLink) ?>"><?= icon('mail') ?>Email us</a>
                        <?php endif; ?>
                        <a class="btn btn--quiet btn--block" href="#ask">Use the form</a>
                    </div>
                </div>
            </aside>
        </div>
    </div>
</section>

<?php if ($images): ?>
<section class="section section--flush-top">
    <div class="container">
        <div class="section-head">
            <span class="eyebrow">Screens</span>
            <h2>A look inside.</h2>
        </div>
        <?php
        // Alt text has to tell the images apart: the stored text is often the same
        // line for every shot, so anything not unique to this image gets its
        // position instead.
        $altCounts = array_count_values(array_map(static fn ($im) => trim((string) $im['alt_text']), $images));
        ?>
        <div class="gallery" data-reveal-stagger>
            <?php foreach ($images as $i => $img):
                $src = media_url($img['path']);
                $alt = trim((string) $img['alt_text']);
                if ($alt === '' || ($altCounts[$alt] ?? 0) !== 1) {
                    $alt = ($alt !== '' ? $alt : $project['name'])
                        . ', image ' . ($i + 1) . ' of ' . count($images);
                } ?>
            <figure class="gallery__item" data-lightbox="<?= e($src) ?>"
                    data-lightbox-alt="<?= e($alt) ?>"
                    aria-label="Open image <?= $i + 1 ?> of <?= count($images) ?>" data-reveal style="--i:<?= $i ?>">
                <img src="<?= e($src) ?>" alt="<?= e($alt) ?>" loading="lazy" decoding="async">
                <?php if (!empty($img['caption'])): ?>
                <figcaption class="gallery__caption"><?= e($img['caption']) ?></figcaption>
                <?php endif; ?>
            </figure>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- Enquiry -->
<section class="section" id="ask">
    <div class="container container--narrow">
        <div class="section-head section-head--center">
            <span class="eyebrow">Ask about it</span>
            <h2>Tell us what you need.</h2>
            <p class="lead">We reply with a price and a date. Nothing is charged here.</p>
        </div>

        <?php if ($sent): ?>
        <div class="notice mt-6" role="status">
            <?= icon('check-circle') ?>
            <span><strong style="color:var(--text)">Got it.</strong> We have your enquiry about <?= e($project['name']) ?> and will reply shortly.</span>
        </div>
        <?php endif; ?>

        <form class="card card--pad-lg mt-6" method="post"
              action="<?= e(url('/premade-projects/' . $project['slug'] . '/enquire')) ?>" data-form novalidate>
            <?= csrf_field() ?>
            <div class="hp-field" aria-hidden="true">
                <label for="pe-hp">Leave this empty</label>
                <input id="pe-hp" type="text" name="website_url" tabindex="-1" autocomplete="off">
            </div>

            <div class="stack stack-4">
                <div class="field--row">
                    <div class="field">
                        <label class="label" for="pe-name">Your name <span class="req" aria-hidden="true">*</span></label>
                        <input class="input<?= error_for('name') ? ' is-invalid' : '' ?>" id="pe-name" type="text" name="name"
                               value="<?= e(old('name')) ?>" required maxlength="120" autocomplete="name"
                               <?= $view->partial('partials/field-invalid', ['key' => 'name']) ?>>
                        <?= $view->partial('partials/field-error', ['key' => 'name']) ?>
                    </div>
                    <div class="field">
                        <label class="label" for="pe-business">Business</label>
                        <input class="input" id="pe-business" type="text" name="business_name"
                               value="<?= e(old('business_name')) ?>" maxlength="190" autocomplete="organization">
                    </div>
                </div>

                <div class="field--row">
                    <div class="field">
                        <label class="label" for="pe-email">Email <span class="req" aria-hidden="true">*</span></label>
                        <input class="input<?= error_for('email') ? ' is-invalid' : '' ?>" id="pe-email" type="email" name="email"
                               value="<?= e(old('email')) ?>" required maxlength="190" autocomplete="email"
                               <?= $view->partial('partials/field-invalid', ['key' => 'email']) ?>>
                        <?= $view->partial('partials/field-error', ['key' => 'email']) ?>
                    </div>
                    <div class="field">
                        <label class="label" for="pe-phone">Phone / WhatsApp</label>
                        <input class="input<?= error_for('phone') ? ' is-invalid' : '' ?>" id="pe-phone" type="tel" name="phone"
                               value="<?= e(old('phone')) ?>" maxlength="32" autocomplete="tel"
                               <?= $view->partial('partials/field-invalid', ['key' => 'phone']) ?>>
                        <?= $view->partial('partials/field-error', ['key' => 'phone']) ?>
                    </div>
                </div>

                <div class="field--row">
                    <div class="field">
                        <label class="label" for="pe-contact">Reply on</label>
                        <select class="select" id="pe-contact" name="preferred_contact">
                            <?php foreach (\Techbiss\Repo\ProjectOrderRepo::contactLabels() as $key => $label): ?>
                            <option value="<?= e($key) ?>" <?= old('preferred_contact', 'whatsapp') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label class="label" for="pe-country">Country</label>
                        <select class="select" id="pe-country" name="country">
                            <option value="">Select a country</option>
                            <?php foreach ($countries as $country): ?>
                            <option value="<?= e($country) ?>" <?= old('country') === $country ? 'selected' : '' ?>><?= e($country) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="field">
                    <label class="label" for="pe-domain">Your domain, if you have one</label>
                    <input class="input" id="pe-domain" type="text" name="domain_name"
                           value="<?= e(old('domain_name')) ?>" maxlength="190" placeholder="yourbusiness.com">
                </div>

                <div class="field">
                    <label class="label" for="pe-req">Anything to change?</label>
                    <textarea class="textarea" id="pe-req" name="requirements" rows="4" maxlength="3000"
                              placeholder="Colours, pages, content — whatever should differ from the demo."><?= e(old('requirements')) ?></textarea>
                </div>

                <button class="btn btn--primary btn--lg btn--block" type="submit"><?= icon('send') ?>Send enquiry</button>
                <p class="hint">We only use these details to answer you.</p>
            </div>
        </form>
    </div>
</section>

<?php if ($related): ?>
<section class="section section--flush-top">
    <div class="container">
        <div class="section-head">
            <span class="eyebrow">Similar</span>
            <h2>Others like this.</h2>
        </div>
        <div class="slider" data-slider>
            <div class="slider__track slider__track--work" data-reveal-stagger>
            <?php foreach ($related as $i => $item): ?>
                <?= $view->partial('partials/project-card', ['project' => $item, 'i' => $i]) ?>
            <?php endforeach; ?>
        </div>
    </div>
    </div>
</section>
<?php endif; ?>
