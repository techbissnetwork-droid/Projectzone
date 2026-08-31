<?php /** @var array $industries @var array $services */ ?>
<?= $view->partial('partials/page-head', [
    'eyebrow' => 'Industries',
    'heading' => 'Built around how your sector actually works.',
    'lead'    => 'A restaurant and a law firm need different things. We start from your sector, not a template.',
]) ?>

<section class="section section--flush-top">
    <div class="container">
        <?php if (!$industries): ?>
            <div class="empty-state">
                <span class="empty-state__icon"><?= icon('building') ?></span>
                <h3>Industry pages are being prepared</h3>
                <p>Tell us what your business does and we will explain how we would approach it.</p>
                <a class="btn btn--primary mt-4" href="<?= e(url('/contact')) ?>">Talk to us</a>
            </div>
        <?php else: ?>
        <div class="slider" data-slider>
            <div class="slider__track slider__track--cards" data-reveal-stagger>
            <?php foreach ($industries as $i => $industry): ?>
            <article class="card card--interactive card--spotlight" style="--i:<?= $i ?>" data-reveal>
                <span class="icon-plate"><?= icon((string) ($industry['icon'] ?: 'building')) ?></span>
                <h2 class="card__title mt-4" style="font-size:var(--fs-h4)">
                    <a href="<?= e(url('/industries/' . $industry['slug'])) ?>" style="position:static"><?= e($industry['name']) ?></a>
                </h2>
                <?php if (!empty($industry['tagline'])): ?>
                <p style="font-size:var(--fs-sm);color:var(--accent);font-weight:500;margin-bottom:.5rem"><?= e($industry['tagline']) ?></p>
                <?php endif; ?>
                <p class="card__text"><?= e($industry['short_description']) ?></p>
                <div class="card__footer">
                    <a class="link" href="<?= e(url('/industries/' . $industry['slug'])) ?>">
                        <?= e($industry['name']) ?> solutions<?= icon('arrow-right') ?>
                    </a>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
        </div>
        <?php endif; ?>
    </div>
</section>

<?php if ($services): ?>
<section class="section">
    <div class="container">
        <div class="section-head" data-reveal>
            <p class="eyebrow">Common ground</p>
            <h2 class="mt-4">What every sector needs regardless.</h2>
            <p class="lead">The foundation is the same everywhere. What changes is what gets built on top of it.</p>
        </div>
        <div class="slider" data-slider>
            <div class="slider__track slider__track--cards" data-reveal-stagger>
            <?php foreach ($services as $i => $service): ?>
                <?= $view->partial('partials/service-card', ['service' => $service, 'i' => $i]) ?>
            <?php endforeach; ?>
        </div>
        </div>
    </div>
</section>
<?php endif; ?>

<?= $view->partial('partials/cta-band', [
    'eyebrow' => 'Your industry',
    'heading' => 'Not listed? That is not a problem.',
    'lead'    => 'These are the sectors we see most, not the only ones. Tell us what you do.',
]) ?>
