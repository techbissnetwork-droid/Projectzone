<?php
/** @var array $service @var array $features @var array $related @var array $projects @var array $packages @var array $steps */
$deliverables = lines_to_list($service['deliverables']);
$image        = media_url($service['image']);
?>
<?= $view->partial('partials/page-head', [
    'eyebrow' => $service['tagline'] ?: 'Service',
    'heading' => (string) $service['name'],
    'lead'    => (string) $service['short_description'],
    'actions' => '<a class="btn btn--primary btn--arrow" href="' . e(url('/quote')) . '">Request a quote' . icon('arrow-right') . '</a>'
               . '<a class="btn btn--ghost" href="' . e(url('/start')) . '">Start Your Digital Journey</a>',
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
                    <div class="card__footer">
                        <a class="btn btn--primary btn--block" href="<?= e(url('/quote')) ?>">Get an exact quote</a>
                    </div>
                </div>
                <?php else: ?>
                <div class="card">
                    <h3 class="card__title">Pricing</h3>
                    <p class="card__text">This service is scoped to your requirements. Packages that include it are published with full pricing.</p>
                    <div class="card__footer stack stack-2">
                        <a class="btn btn--primary btn--block" href="<?= e(url('/packages')) ?>">See packages</a>
                        <a class="btn btn--ghost btn--block" href="<?= e(url('/quote')) ?>">Request a custom quote</a>
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
    'heading' => 'Ready to talk about ' . strtolower((string) $service['name']) . '?',
    'lead'    => 'Tell us about the business. You get a scope, a schedule and a price.',
]) ?>
