<?php
/** @var array $service @var array $features @var array $related @var array $projects @var array $steps */
$deliverables = lines_to_list($service['deliverables']);
$image        = media_url($service['image']);

// A quick way to start the conversation without going through /request first —
// each channel appears only when it is actually configured.
$askText   = 'Hi, I am interested in your "' . $service['name'] . '" service.';
$waLink    = whatsapp_link($askText);
$mailLink  = email_link($service['name'] . ' — enquiry', $askText);
$phoneLink = phone_link();
?>
<?php
/* No CTA here: the price card a little further down the page ends in the
   exact same "Ask about this" — right next to it on a wide screen, since
   this page-head sits above a two-column split. One button, next to the
   price it's asking about, beats the same one twice. */
?>
<?= $view->partial('partials/page-head', [
    'eyebrow' => $service['tagline'] ?: 'Service',
    'heading' => (string) $service['name'],
    'lead'    => (string) $service['short_description'],
]) ?>

<section class="section section--flush-top">
    <div class="container">
        <div class="split" data-accent="<?= e($service['accent'] ?: 'cyan') ?>">
            <div data-reveal="left">
                <?php if (!empty($service['description'])): ?>
                <div class="prose"><?= $service['description'] ?></div>
                <?php endif; ?>

                <?php if ($deliverables): ?>
                <h2 class="mt-8" style="font-size:var(--fs-h3)">What you get</h2>
                <div class="grid grid-2 mt-5">
                    <?php foreach ($deliverables as $d): ?>
                    <div class="row row--nowrap row--tight" style="align-items:flex-start">
                        <span class="feature-row__check"><?= icon('check') ?></span>
                        <span style="font-size:var(--fs-sm);color:var(--text-soft)"><?= e($d) ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <?php if (!empty($service['process_note'])): ?>
                <div class="notice notice--accent mt-6"><?= icon('info') ?><span><?= e($service['process_note']) ?></span></div>
                <?php endif; ?>
            </div>

            <div class="stack stack-4" data-reveal="right">
                <?php if ($image !== ''): ?>
                <div class="split__media"><img src="<?= e($image) ?>" alt="<?= e($service['name']) ?>" loading="lazy"></div>
                <?php endif; ?>

                <?php if ($features): ?>
                <div class="card card--pad-lg">
                    <span class="icon-plate"><?= icon((string) $service['icon']) ?></span>
                    <h3 class="card__title mt-4">Why it matters</h3>
                    <div class="mt-3">
                        <?php foreach ($features as $f): ?>
                        <div class="feature-row">
                            <span class="feature-row__check"><?= icon('check') ?></span>
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

                <?php if (setting_bool('public_pricing', false) && $service['starting_price'] !== null && (float) $service['starting_price'] > 0): ?>
                <div class="card">
                    <div class="hint">Starting from</div>
                    <div class="price__amount mt-2"><?= e(money($service['starting_price'])) ?></div>
                    <?php if (!empty($service['price_note'])): ?>
                    <p class="price__note mt-2"><?= e($service['price_note']) ?></p>
                    <?php endif; ?>
                    <div class="card__footer stack stack-2">
                        <?php if ($waLink !== ''): ?>
                        <a class="btn btn--primary btn--block" href="<?= e($waLink) ?>" target="_blank" rel="noopener noreferrer">
                            <?= icon('whatsapp') ?>Ask on WhatsApp
                        </a>
                        <?php if ($phoneLink !== ''): ?>
                        <a class="btn btn--ghost btn--block" href="<?= e($phoneLink) ?>"><?= icon('phone') ?>Call us</a>
                        <?php endif; ?>
                        <a class="btn btn--ghost btn--block" href="<?= e(url('/request?service=' . $service['slug'])) ?>">Ask about this</a>
                        <?php elseif ($phoneLink !== ''): ?>
                        <a class="btn btn--primary btn--block" href="<?= e($phoneLink) ?>"><?= icon('phone') ?>Call us</a>
                        <a class="btn btn--ghost btn--block" href="<?= e(url('/request?service=' . $service['slug'])) ?>">Ask about this</a>
                        <?php else: ?>
                        <a class="btn btn--primary btn--block" href="<?= e(url('/request?service=' . $service['slug'])) ?>">Ask about this</a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php else: ?>
                <div class="card">
                    <h3 class="card__title">Price</h3>
                    <p class="card__text">Scoped to what you actually need, then agreed on WhatsApp or by email.</p>
                    <div class="card__footer stack stack-2">
                        <?php if ($waLink !== ''): ?>
                        <a class="btn btn--primary btn--block" href="<?= e($waLink) ?>" target="_blank" rel="noopener noreferrer">
                            <?= icon('whatsapp') ?>Ask on WhatsApp
                        </a>
                        <?php if ($phoneLink !== ''): ?>
                        <a class="btn btn--ghost btn--block" href="<?= e($phoneLink) ?>"><?= icon('phone') ?>Call us</a>
                        <?php endif; ?>
                        <a class="btn btn--ghost btn--block" href="<?= e(url('/request?service=' . $service['slug'])) ?>">Ask about this</a>
                        <?php elseif ($phoneLink !== ''): ?>
                        <a class="btn btn--primary btn--block" href="<?= e($phoneLink) ?>"><?= icon('phone') ?>Call us</a>
                        <a class="btn btn--ghost btn--block" href="<?= e(url('/request?service=' . $service['slug'])) ?>">Ask about this</a>
                        <?php else: ?>
                        <a class="btn btn--primary btn--block" href="<?= e(url('/request?service=' . $service['slug'])) ?>">Ask about this</a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<?php if ($projects): ?>
<section class="section">
    <div class="container">
        <div class="row row--between mb-6" data-reveal>
            <h2>Related work</h2>
            <a class="link hide-sm" href="<?= e(url('/portfolio')) ?>">All projects<?= icon('arrow-right') ?></a>
        </div>
        <div class="slider" data-slider>
            <div class="slider__track slider__track--work" data-reveal-stagger>
            <?php foreach ($projects as $i => $project): ?>
                <?= $view->partial('partials/work-card', ['project' => $project, 'i' => $i]) ?>
            <?php endforeach; ?>
        </div>
    </div>
    </div>
</section>
<?php endif; ?>

<?php if ($related): ?>
<section class="section">
    <div class="container">
        <div class="section-head" data-reveal>
            <p class="eyebrow">Works well with</p>
            <h2 class="mt-4">Services businesses usually pair with this.</h2>
        </div>
        <div class="grid grid-3" data-reveal-stagger>
            <?php foreach ($related as $i => $svc): ?>
                <?= $view->partial('partials/service-card', ['service' => $svc, 'i' => $i]) ?>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?= $view->partial('partials/cta-band', [
    'eyebrow' => 'Next step',
    // Lowercasing the name so it reads mid-sentence breaks an acronym like
    // "AI" or "SEO" into "ai"/"seo" — keep any all-caps word (2+ letters) as
    // the admin wrote it, lowercase the rest.
    'heading' => 'Ready to talk about ' . preg_replace_callback('/\S+/', static function (array $m): string {
        $letters = preg_replace('/[^A-Za-z]/', '', $m[0]);
        return ($letters !== '' && $letters === strtoupper($letters) && strlen($letters) > 1) ? $m[0] : mb_strtolower($m[0]);
    }, (string) $service['name']) . '?',
    'lead'    => 'Tell us about the business. You get a scope, a schedule and a price.',
]) ?>
