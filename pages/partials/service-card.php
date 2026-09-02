<?php /** @var array $service @var int $i */ ?>
<article class="card card--interactive card--spotlight service-card" data-accent="<?= e($service['accent'] ?: 'cyan') ?>"
         data-reveal data-tilt style="--i:<?= (int) ($i ?? 0) ?>">
    <span class="icon-plate"><?= icon((string) ($service['icon'] ?: 'spark')) ?></span>
    <h3 class="service-card__name">
        <a href="<?= e(url('/services/' . $service['slug'])) ?>" style="position:static">
            <?= e($service['name']) ?>
        </a>
    </h3>
    <?php if (!empty($service['tagline'])): ?>
    <p class="service-card__tagline"><?= e($service['tagline']) ?></p>
    <?php endif; ?>
    <p class="service-card__text"><?= e($service['short_description']) ?></p>
    <div class="card__footer service-card__link">
        <a class="link" href="<?= e(url('/services/' . $service['slug'])) ?>">
            Explore <?= e($service['name']) ?><?= icon('arrow-right') ?>
        </a>
    </div>
</article>
