<?php
/** @var array $industry @var array $services @var array $projects @var array $others */
$challenges = lines_to_list($industry['challenges']);
$solutions  = lines_to_list($industry['solutions']);
$image      = media_url($industry['image']);
?>
<?= $view->partial('partials/page-head', [
    'eyebrow' => 'Industries',
    'heading' => 'Digital solutions for ' . strtolower((string) $industry['name']),
    'lead'    => (string) $industry['short_description'],
    'actions' => '<a class="btn btn--primary btn--arrow" href="' . e(url('/request')) . '">Tell Us What You Need' . icon('arrow-right') . '</a>'
               . '<a class="btn btn--ghost" href="' . e(url('/request')) . '">Tell Us What You Need</a>',
]) ?>

<section class="section section--flush-top">
    <div class="container">
        <div class="split">
            <div data-reveal="left">
                <?php if (!empty($industry['description'])): ?>
                <div class="prose"><?= $industry['description'] ?></div>
                <?php endif; ?>

                <?php if ($challenges): ?>
                <h2 class="mt-8" style="font-size:var(--fs-h3)">What usually gets in the way</h2>
                <div class="stack stack-3 mt-5">
                    <?php foreach ($challenges as $c): ?>
                    <div class="row row--nowrap row--tight" style="align-items:flex-start">
                        <span class="problem-item__icon"><?= icon('x') ?></span>
                        <span style="font-size:var(--fs-sm);color:var(--text-soft)"><?= e($c) ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <?php if ($solutions): ?>
                <h2 class="mt-8" style="font-size:var(--fs-h3)">How we approach it</h2>
                <div class="stack stack-3 mt-5">
                    <?php foreach ($solutions as $s): ?>
                    <div class="row row--nowrap row--tight" style="align-items:flex-start">
                        <span class="feature-row__check"><?= icon('check') ?></span>
                        <span style="font-size:var(--fs-sm);color:var(--text-soft)"><?= e($s) ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <div class="stack stack-4" data-reveal="right">
                <?php if ($image !== ''): ?>
                <div class="split__media"><img src="<?= e($image) ?>" alt="<?= e($industry['name']) ?>" loading="lazy"></div>
                <?php endif; ?>

                <?php if ($services): ?>
                <div class="card card--pad-lg">
                    <h3 class="card__title" style="font-size:var(--fs-sm)">Services we usually start with</h3>
                    <div class="stack stack-3 mt-4">
                        <?php foreach ($services as $svc): ?>
                        <a class="row row--tight" href="<?= e(url('/services/' . $svc['slug'])) ?>">
                            <span class="icon-plate icon-plate--sm" data-accent="<?= e($svc['accent'] ?: 'cyan') ?>"><?= icon((string) $svc['icon']) ?></span>
                            <span>
                                <span style="display:block;font-size:var(--fs-sm);font-weight:550;color:var(--text)"><?= e($svc['name']) ?></span>
                                <span style="display:block;font-size:var(--fs-xs);color:var(--text-muted)"><?= e(str_limit($svc['tagline'] ?: $svc['short_description'], 58)) ?></span>
                            </span>
                        </a>
                        <?php endforeach; ?>
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
            <h2><?= e($industry['name']) ?> work</h2>
            <a class="link hide-sm" href="<?= e(url('/portfolio?industry=' . $industry['slug'])) ?>">All <?= e(strtolower((string) $industry['name'])) ?> projects<?= icon('arrow-right') ?></a>
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

<?php if ($others): ?>
<section class="section section--tight">
    <div class="container">
        <h2 class="mb-5" style="font-size:var(--fs-h3)" data-reveal>Other industries</h2>
        <div class="grid grid-4" data-reveal-stagger>
            <?php foreach ($others as $i => $other): ?>
            <div class="industry-card" style="--i:<?= $i ?>">
                <span class="icon-plate icon-plate--sm"><?= icon((string) ($other['icon'] ?: 'building')) ?></span>
                <div class="industry-card__name">
                    <a href="<?= e(url('/industries/' . $other['slug'])) ?>"><?= e($other['name']) ?></a>
                </div>
                <span class="industry-card__arrow"><?= icon('arrow-right') ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?= $view->partial('partials/cta-band', [
    'eyebrow' => $industry['name'],
    'heading' => 'Take your ' . strtolower((string) $industry['name']) . ' business digital.',
]) ?>
