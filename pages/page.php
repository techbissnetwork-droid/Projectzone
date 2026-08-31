<?php
/** Generic CMS page. @var array $page @var array $stats @var array $steps @var array $services @var array $testimonials */
$hero = media_url($page['hero_image'] ?? '');
?>
<?= $view->partial('partials/page-head', [
    'eyebrow' => (string) $page['eyebrow'],
    'heading' => (string) $page['title'],
    'lead'    => (string) $page['subtitle'],
]) ?>

<?php if ($hero !== ''): ?>
<section class="section--flush-top" style="padding-bottom:var(--s-6)">
    <div class="container">
        <div class="split__media" style="aspect-ratio:21/9" data-reveal="scale">
            <img src="<?= e($hero) ?>" alt="<?= e($page['title']) ?>" loading="lazy">
        </div>
    </div>
</section>
<?php endif; ?>

<?php if (!empty($page['content'])): ?>
<section class="section section--flush-top">
    <div class="container container--narrow">
        <div class="prose" style="max-width:none" data-reveal><?= $page['content'] ?></div>
    </div>
</section>
<?php endif; ?>

<?php if (!empty($stats)): ?>
<div class="stats-band" data-reveal>
    <?php foreach ($stats as $stat): ?>
    <div class="stat">
        <div class="stat__value"><?= e($stat['prefix']) ?><?= e($stat['value']) ?><?= e($stat['suffix']) ?></div>
        <div class="stat__label"><?= e($stat['label']) ?></div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if (!empty($services)): ?>
<section class="section">
    <div class="container">
        <div class="section-head" data-reveal>
            <p class="eyebrow">What we do</p>
            <h2 class="mt-4">Everything under one partner.</h2>
        </div>
        <div class="grid grid-3" data-reveal-stagger>
            <?php foreach ($services as $i => $service): ?>
                <?= $view->partial('partials/service-card', ['service' => $service, 'i' => $i]) ?>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if (!empty($steps)): ?>
<section class="section">
    <div class="container">
        <div class="section-head" data-reveal>
            <p class="eyebrow">How we work</p>
            <h2 class="mt-4">A process you can plan around.</h2>
        </div>
        <div class="process">
            <?php foreach ($steps as $step): ?>
            <div class="process__step" data-reveal>
                <div class="process__num"><?= e($step['step_number']) ?></div>
                <div class="process__body">
                    <h3 class="process__title" style="font-size:var(--fs-h4)"><?= e($step['title']) ?></h3>
                    <p class="process__text"><?= e($step['description']) ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if (!empty($testimonials)): ?>
<section class="section">
    <div class="container">
        <div class="grid grid-3" data-reveal-stagger>
            <?php foreach ($testimonials as $i => $t): ?>
            <article class="card quote-card" style="--i:<?= $i ?>" data-reveal>
                <span class="quote-card__mark"><?= icon('quote') ?></span>
                <p class="quote-card__text">“<?= e($t['quote']) ?>”</p>
                <div class="quote-card__author">
                    <span class="avatar" aria-hidden="true"><?= e(initials((string) $t['client_name'])) ?></span>
                    <div>
                        <div class="quote-card__name"><?= e($t['client_name']) ?></div>
                        <div class="quote-card__role"><?= e($t['company']) ?></div>
                    </div>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?= $view->partial('partials/cta-band') ?>
